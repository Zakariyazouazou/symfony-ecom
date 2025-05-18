<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository    $categoryRepo,
        private SluggerInterface      $slugger
    ) {}



    #[Route('/api/products/search', name: 'api_product_search', methods: ['GET'])]
    public function search(Request $request, ProductRepository $repo): JsonResponse
    {
        $query = $request->query->get('q', '');
        if (empty($query)) {
            return $this->json(['status' => 'error', 'message' => 'Search query is required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $products = $repo->createQueryBuilder('p')
            ->leftJoin('p.categories', 'c')
            ->leftJoin('p.filename', 'i')
            ->addSelect('c')
            ->addSelect('i')
            ->where('p.name LIKE :q OR p.slug LIKE :q OR p.sku LIKE :q')
            ->setParameter('q', "%{$query}%")
            ->getQuery()
            ->getResult();

        $results = array_map(function (Product $p) {
            return [
                'id'          => $p->getId(),
                'name'        => $p->getName(),
                'slug'        => $p->getSlug(),
                'description' => $p->getDescription(),
                'sku'         => $p->getSku(),
                'price'       => $p->getPrice(),
                'stock'       => $p->getStock(),
                'categories'  => array_map(fn(Category $c) => [
                    'id'   => $c->getId(),
                    'name' => $c->getName(),
                ], $p->getCategories()->toArray()),
                'images'      => array_map(fn(ProductImage $i) => $i->getUrl(), $p->getFilename()->toArray()),
            ];
        }, $products);

        return $this->json(['status' => 'success', 'results' => $results]);
    }



    // Create a product
    #[Route('/api/products', name: 'api_product_create_batch', methods: ['POST'])]
    public function createBatch(Request $request): JsonResponse
    {
        // Decode payload
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Expected an array of products.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // 1) Gather all category IDs & slugs in the batch
        $allIds   = [];
        $allSlugs = [];
        foreach ($payload as $item) {
            if (!empty($item['categories']) && is_array($item['categories'])) {
                foreach ($item['categories'] as $c) {
                    if (is_numeric($c)) {
                        $allIds[] = (int) $c;
                    } else {
                        $allSlugs[] = (string) $this->slugger->slug((string)$c);
                    }
                }
            }
        }
        $allIds   = array_unique($allIds);
        $allSlugs = array_unique($allSlugs);

        // 2) Bulk-fetch existing categories by ID & slug
        $foundById   = $allIds   ? $this->categoryRepo->findBy(['id'   => $allIds])   : [];
        $foundBySlug = $allSlugs ? $this->categoryRepo->findBy(['slug' => $allSlugs]) : [];

        // 3) Build lookup map in memory
        $existing = ['id' => [], 'slug' => []];
        foreach ($foundById   as $cat) {
            $existing['id'][$cat->getId()]     = $cat;
        }
        foreach ($foundBySlug as $cat) {
            $existing['slug'][$cat->getSlug()] = $cat;
        }

        $errors  = [];
        $created = [];

        // 4) Process each product in payload
        foreach ($payload as $index => $item) {
            // Validate required fields
            $missing = [];
            foreach (['name', 'slug', 'description', 'price', 'stock'] as $f) {
                if (!isset($item[$f]) || ($item[$f] === '' && $item[$f] !== '0')) {
                    $missing[] = $f;
                }
            }
            if (empty($item['categories']) || !is_array($item['categories'])) {
                $missing[] = 'categories (min 1)';
            }
            if (empty($item['images']) || !is_array($item['images'])) {
                $missing[] = 'images (min 1)';
            }
            if ($missing) {
                $errors[$index] = [
                    'status'  => 'error',
                    'message' => 'Missing or invalid fields: ' . implode(', ', $missing)
                ];
                continue;
            }

            // Build Product
            $product = new Product();
            $product->setName($item['name'])
                ->setSlug($item['slug'])
                ->setDescription($item['description'])
                ->setPrice((float)$item['price'])
                ->setStock((int)$item['stock'])
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());

            // SKU: provided or random
            $sku = $item['sku'] ?? null;
            if (empty($sku)) {
                $sku = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            }
            $product->setSku($sku);

            // Categories: reuse existing or create new
            foreach ($item['categories'] as $catInput) {
                $category = null;
                if (is_numeric($catInput) && isset($existing['id'][(int)$catInput])) {
                    $category = $existing['id'][(int)$catInput];
                } else {
                    $slug = (string)$this->slugger->slug((string)$catInput);
                    if (isset($existing['slug'][$slug])) {
                        $category = $existing['slug'][$slug];
                    }
                }

                if (!$category) {
                    // Truly new category
                    $name = is_string($catInput) ? $catInput : 'Category';
                    $slug = (string)$this->slugger->slug($name);

                    $category = (new Category())
                        ->setName($name)
                        ->setSlug($slug)
                        ->setCreatedAt(new \DateTimeImmutable())
                        ->setUpdatedAt(new \DateTimeImmutable());

                    $this->em->persist($category);
                    // Add to map immediately
                    $existing['slug'][$slug] = $category;
                }

                $product->addCategory($category);
            }

            // Images
            foreach ($item['images'] as $url) {
                $img = (new ProductImage())
                    ->setUrl($url)
                    ->setFilename(basename($url))
                    ->setCreatedAt(new \DateTimeImmutable());

                $product->addFilename($img);
                $this->em->persist($img);
            }

            $this->em->persist($product);
            $created[$index] = $product;
        }

        // If any validation errors, return them
        if ($errors) {
            return $this->json([
                'status'  => 'error',
                'details' => $errors,
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Persist all new entities at once
        $this->em->flush();

        // Build response
        $response = [];
        foreach ($created as $product) {
            $response[] = [
                'id'          => $product->getId(),
                'name'        => $product->getName(),
                'slug'        => $product->getSlug(),
                'description' => $product->getDescription(),
                'sku'         => $product->getSku(),
                'price'       => $product->getPrice(),
                'stock'       => $product->getStock(),
                'categories'  => array_map(fn(Category $c) => [
                    'id'   => $c->getId(),
                    'name' => $c->getName(),
                    'slug' => $c->getSlug(),
                ], $product->getCategories()->toArray()),
                'images'      => array_map(fn(ProductImage $i) => [
                    'id'  => $i->getId(),
                    'url' => $i->getUrl(),
                ], $product->getFilename()->toArray()),
            ];
        }

        return $this->json($response, JsonResponse::HTTP_CREATED);
    }


    // get list of the products
    #[Route('/api/products', name: 'api_product_list', methods: ['GET'])]
    public function list(Request $request, ProductRepository $repo): JsonResponse
    {
        $page  = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 10));
        $offset = ($page - 1) * $limit;

        $total = $repo->count([]);
        $products = $repo->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

        $data = [];
        foreach ($products as $p) {
            $data[] = [
                'id'          => $p->getId(),
                'name'        => $p->getName(),
                'slug'        => $p->getSlug(),
                'description' => $p->getDescription(),
                'sku'         => $p->getSku(),
                'price'       => $p->getPrice(),
                'stock'       => $p->getStock(),
                'categories'  => array_map(fn($c) => [
                    'id'   => $c->getId(),
                    'name' => $c->getName(),
                ], $p->getCategories()->toArray()),
                'images'      => array_map(fn($i) => $i->getUrl(), $p->getFilename()->toArray()),
            ];
        }

        return $this->json([
            'total_products' => $total,
            'page'           => $page,
            'data'           => $data,
        ]);
    }


    // get single product depend the id
    #[Route('/api/products/{id}', name: 'api_product_get', methods: ['GET'])]
    public function getOne(int $id, ProductRepository $repo): JsonResponse
    {
        $product = $repo->find($id);
        if (!$product) {
            return $this->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'sku' => $product->getSku(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'categories' => array_map(fn(Category $c) => [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'slug' => $c->getSlug(),
                'description' => $c->getDescription(),
                'createdAt' => $c->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt' => $c->getUpdatedAt()?->format(DATE_ATOM),
            ], $product->getCategories()->toArray()),
            'images' => array_map(fn(ProductImage $i) => [
                'id' => $i->getId(),
                'filename' => $i->getFilename(),
                'url' => $i->getUrl(),
                'altText' => $i->getAltText(),
                'position' => $i->getPosition(),
                'createdAt' => $i->getCreatedAt()?->format(DATE_ATOM),
            ], $product->getFilename()->toArray()),
        ];

        return $this->json($data);
    }


    // Delete single product depend the id
    #[Route('/api/products/{id}', name: 'api_product_delete', methods: ['DELETE'])]
    public function delete(int $id, ProductRepository $repo): JsonResponse
    {
        $product = $repo->find($id);
        if (!$product) {
            return $this->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // Remove associated images
        foreach ($product->getFilename() as $image) {
            $this->em->remove($image);
        }
        // Remove product itself
        $this->em->remove($product);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'Product deleted successfully.'
        ], JsonResponse::HTTP_OK);
    }





    #[Route('/api/products/{id}', name: 'api_product_update', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        ProductRepository $productRepo
    ): JsonResponse {
        $product = $productRepo->find($id);
        if (!$product) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Product not found.'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data   = json_decode($request->getContent(), true, JSON_THROW_ON_ERROR);
        $errors = [];

        // 1) Update basic scalar fields if provided
        foreach (['name', 'slug', 'description', 'price', 'stock', 'sku'] as $field) {
            if (array_key_exists($field, $data)) {
                $setter = 'set' . ucfirst($field);
                $product->$setter($data[$field]);
            }
        }

        // 2) Images update
        if (array_key_exists('images', $data)) {
            if (!is_array($data['images']) || count($data['images']) < 1) {
                $errors[] = 'Images field must be a non-empty array.';
            } else {
                $providedIds = [];

                foreach ($data['images'] as $imgData) {
                    // ——— Update existing image
                    if (!empty($imgData['id'])) {
                        $providedIds[] = $imgData['id'];
                        $img = $this->em
                            ->getRepository(ProductImage::class)
                            ->find((int)$imgData['id']);
                        if (!$img) {
                            $errors[] = "Image with id {$imgData['id']} not found.";
                            continue;
                        }
                        // patch any provided fields
                        foreach (['filename', 'url', 'altText', 'position'] as $f) {
                            if (array_key_exists($f, $imgData)) {
                                $setter = 'set' . ucfirst($f);
                                $img->$setter($imgData[$f]);
                            }
                        }
                    }
                    // ——— Create new image
                    else {
                        if (empty($imgData['url'])) {
                            $errors[] = 'New image requires a url.';
                            continue;
                        }
                        $newImg = new ProductImage();
                        $newImg->setUrl($imgData['url'])
                            ->setFilename(
                                $imgData['filename']
                                    ?? basename($imgData['url'])
                            )
                            ->setAltText($imgData['altText'] ?? null)
                            ->setPosition($imgData['position'] ?? null)
                            ->setCreatedAt(new \DateTimeImmutable());

                        $product->addFilename($newImg);
                        $this->em->persist($newImg);
                    }
                }

                // ——— Remove any old images not in providedIds
                foreach ($product->getFilename() as $oldImg) {
                    if (
                        $oldImg->getId() !== null
                        && !in_array($oldImg->getId(), $providedIds, true)
                    ) {
                        $this->em->remove($oldImg);
                    }
                }
            }
        }

        // 3) Return errors if any
        if (!empty($errors)) {
            return $this->json([
                'status' => 'error',
                'errors' => $errors
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // 4) Persist changes
        $product->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        // 5) Build response payload
        $images = array_map(fn(ProductImage $i) => [
            'id'       => $i->getId(),
            'filename' => $i->getFilename(),
            'url'      => $i->getUrl(),
            'altText'  => $i->getAltText(),
            'position' => $i->getPosition(),
        ], $product->getFilename()->toArray());

        return $this->json([
            'status'  => 'success',
            'product' => [
                'id'          => $product->getId(),
                'name'        => $product->getName(),
                'slug'        => $product->getSlug(),
                'description' => $product->getDescription(),
                'sku'         => $product->getSku(),
                'price'       => $product->getPrice(),
                'stock'       => $product->getStock(),
                'images'      => $images,
            ],
        ], JsonResponse::HTTP_OK);
    }
}
