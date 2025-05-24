<?php
// src/Controller/Api/OrdersListController.php
namespace App\Controller\Api;

use App\Entity\OrderItems;
use App\Entity\Orders;
use App\Repository\OrdersRepository;
use App\Repository\ProductImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OrderAdminController extends AbstractController
{
    #[Route('/api/main_order_list', name: 'api_list_orders', methods: ['GET'])]
    public function listOrders(OrdersRepository $ordersRepo): JsonResponse
    {
        $orders = $ordersRepo->findAll();

        $data = array_map(function (Orders $order) {
            // calculate total quantity
            $totalQty = 0;
            foreach ($order->getOrderId() as $item) {
                $totalQty += $item->getQuantity();
            }

            return [
                'order_id'      => $order->getId(),
                'order_name'    => $order->getCustomerEmail(), // or getUserId()->getName() if exists
                'status'        => $order->getStatus(),
                'total_quantity' => $totalQty,
                'total_amount'  => $order->getTotalAmount(),
            ];
        }, $orders);

        return $this->json($data, 200);
    }


    #[Route('/api/orders/{orderId}/status', name: 'api_update_order_status', methods: ['PATCH'])]
    public function updateOrderStatus(
        int $orderId,
        Request $request,
        EntityManagerInterface $em,
        OrdersRepository $ordersRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            return $this->json(['error' => 'New status is required'], 400);
        }


        $allowedStatuses = ['wait to pay', 'paid', 'shipped', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return $this->json(['error' => 'Invalid status value'], 400);
        }

        /** @var Orders|null $order */
        $order = $ordersRepo->find($orderId);
        if (!$order) {
            return $this->json(['error' => "Order #$orderId not found"], 404);
        }

        $order->setStatus($newStatus);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json([
            'message'    => "Order status updated successfully",
            'order_id'   => $order->getId(),
            'new_status' => $order->getStatus(),
        ], 200);
    }



    #[Route('/api/orders/{orderId}/items_details', name: 'api_order_items_details', methods: ['GET'])]
    public function getOrderItemsWithImages(
        int $orderId,
        OrdersRepository $ordersRepo,
        ProductImageRepository $imageRepo
    ): JsonResponse {
        $order = $ordersRepo->find($orderId);
        if (!$order) {
            return $this->json(['error' => "Order #$orderId not found"], 404);
        }

        $items = $order->getOrderId();  // your collection of OrderItems

        $data = array_map(function (OrderItems $item) use ($imageRepo) {
            $product = $item->getProductId();

            // fetch exactly one ProductImage, sorted by position ASC
            $firstImage = $imageRepo->findOneBy(
                ['product_id' => $product],
                ['position'   => 'ASC'],
                1
            );

            return [
                'item_id'       => $item->getId(),
                'product_id'    => $product->getId(),
                'product_name'  => $product->getName(),
                // null if no image; otherwise URL (you could also use getFilename())
                'product_image' => $firstImage?->getUrl(),
                'unit_price'    => $item->getUnitPrice(),
                'quantity'      => $item->getQuantity(),
                'total_price'   => $item->getTotalPrice(),
            ];
        }, $items->toArray());

        return $this->json($data, 200);
    }
}
