<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CategoryApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository    $categoryRepo,
        private ProductRepository     $productRepo,
        private SluggerInterface      $slugger
        
    ) {}

    /**
     * PATCH /api/categories/{id}
     * Update a category's name, slug, and description
     */
    #[Route('/api/categories/{id}', name: 'api_category_update', methods: ['PATCH'])]
    public function updateCategory(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepo->find($id);
        if (!$category) {
            return $this->json([
                'status' => 'error',
                'message' => 'Category not found.'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $errors = [];

        // name is required and non-empty
        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                $errors[] = 'Name cannot be empty.';
            } else {
                $category->setName($name);
            }
        }

        // slug optional, but if provided must be non-empty
        if (array_key_exists('slug', $data)) {
            $slug = trim((string) $data['slug']);
            if ($slug === '') {
                $errors[] = 'Slug cannot be empty.';
            } else {
                $category->setSlug((string) $this->slugger->slug($slug));
            }
        }

        // description optional
        if (array_key_exists('description', $data)) {
            $category->setDescription($data['description']);
        }

        if (!empty($errors)) {
            return $this->json([
                'status' => 'error',
                'errors' => $errors
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $category->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'description' => $category->getDescription(),
            ],
        ], JsonResponse::HTTP_OK);
    }

    /**
     * GET /api/categories
     * Retrieve all categories
     */
    #[Route('/api/categories', name: 'api_category_list', methods: ['GET'])]
    public function listCategories(): JsonResponse
    {
        $categories = $this->categoryRepo->findAll();
        $data = array_map(fn(Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'slug' => $c->getSlug(),
            'description' => $c->getDescription(),
        ], $categories);

        return $this->json([
            'status' => 'success',
            'total' => count($data),
            'data' => $data,
        ], JsonResponse::HTTP_OK);
    }

    /**
     * GET /api/categories/{id}/products?limit=12&page=1
     * Retrieve products by category with pagination
     */
    #[Route('/api/categories/{id}/products', name: 'api_category_products', methods: ['GET'])]
    public function productsByCategory(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepo->find($id);
        if (!$category) {
            return $this->json([
                'status' => 'error',
                'message' => 'Category not found.'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $limit = max(1, (int)$request->query->get('limit', 10));
        $page  = max(1, (int)$request->query->get('page', 1));
        $offset = ($page - 1) * $limit;

        // // count total products in category
        // $totalProducts = $this->productRepo->countByCategoryId($id);

        // fetch paginated products
        $products = $this->productRepo->findByCategoryPaginated($id, $limit, $offset);

        $items = [];
        foreach ($products as $product) {
            $items[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'description' => $product->getDescription(),
                'sku' => $product->getSku(),
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
                'categories' => array_map(fn($c) => [
                    'id' => $c->getId(),
                    'name' => $c->getName(),
                ], $product->getCategories()->toArray()),
                'images' => array_map(fn($img) => $img->getUrl(), $product->getFilename()->toArray()),
            ];
        }

        return $this->json([
            'status' => 'success',
            // 'total_products' => $totalProducts,
            'page' => $page,
            'data' => $items,
        ], JsonResponse::HTTP_OK);
    }





    #[Route('/api/categories/{id}', name: 'api_category_delete', methods: ['DELETE'])]
    public function deleteCategory(int $id): JsonResponse
    {
        $category = $this->categoryRepo->find($id);

        if (!$category) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Category not found.'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->em->remove($category);
        $this->em->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Category deleted successfully.'
        ], JsonResponse::HTTP_OK);
    }
}
