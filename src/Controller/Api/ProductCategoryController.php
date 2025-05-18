<?php
namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\Category;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductCategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductRepository      $productRepo,
        private CategoryRepository     $categoryRepo,
        private SluggerInterface       $slugger
    ){}

    /**
     * Update **only** the categories attached to a product.
     *
     * PATCH /api/products/category/{id}
     */
    #[Route('/api/products/category/{id}', name: 'api_product_update_categories', methods: ['PATCH'])]
    public function updateCategories(int $id, Request $request): JsonResponse
    {
        // 1) Load product
        $product = $this->productRepo->find($id);
        if (!$product) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Product not found.'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // 2) Decode & validate payload
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['categories']) || !is_array($data['categories']) || count($data['categories']) < 1) {
            return $this->json([
                'status'  => 'error',
                'message' => 'The "categories" field must be a non-empty array.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $errors  = [];
        $newCats = [];

        // 3) Process each category object
        foreach ($data['categories'] as $idx => $catData) {
            // — existing category by ID?
            if (!empty($catData['id'])) {
                $cat = $this->categoryRepo->find((int)$catData['id']);
                if (!$cat) {
                    $errors[] = "Category with id {$catData['id']} not found (index $idx).";
                    continue;
                }
                // update name/slug/description if provided
                if (isset($catData['name'])) {
                    $cat->setName($catData['name']);
                }
                if (isset($catData['slug'])) {
                    $cat->setSlug((string)$this->slugger->slug($catData['slug']));
                }
                if (array_key_exists('description', $catData)) {
                    $cat->setDescription($catData['description']);
                }
                $cat->setUpdatedAt(new \DateTimeImmutable());
                $this->em->persist($cat);
            }
            // — brand new category
            else {
                $name = trim($catData['name'] ?? '');
                if ($name === '') {
                    $errors[] = "New category at index $idx requires a non-empty name.";
                    continue;
                }
                $slug = isset($catData['slug'])
                      ? (string)$this->slugger->slug($catData['slug'])
                      : (string)$this->slugger->slug($name);

                $cat = new Category();
                $cat->setName($name)
                    ->setSlug($slug)
                    ->setDescription($catData['description'] ?? null)
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setUpdatedAt(new \DateTimeImmutable());

                $this->em->persist($cat);
            }

            $newCats[] = $cat;
        }

        // 4) If any lookup/validation errors, bail out
        if (!empty($errors)) {
            return $this->json([
                'status' => 'error',
                'errors' => $errors
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // 5) Sync the product ↔ category relation
        //   a) remove any old categories *not* in newCats
        foreach ($product->getCategories() as $oldCat) {
            if (!in_array($oldCat, $newCats, true)) {
                $product->removeCategory($oldCat);
            }
        }
        //   b) attach any new ones not already present
        foreach ($newCats as $cat) {
            if (!$product->getCategories()->contains($cat)) {
                $product->addCategory($cat);
            }
        }

        // 6) Flush and respond
        $this->em->flush();

        // prepare output list
        $outCats = array_map(fn(Category $c) => [
            'id'          => $c->getId(),
            'name'        => $c->getName(),
            'slug'        => $c->getSlug(),
            'description' => $c->getDescription(),
        ], $product->getCategories()->toArray());

        return $this->json([
            'status'     => 'success',
            'productId'  => $product->getId(),
            'categories' => $outCats
        ], JsonResponse::HTTP_OK);
    }
}
