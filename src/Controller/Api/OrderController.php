<?php
// src/Controller/Api/OrderController.php
namespace App\Controller\Api;

use App\Entity\Orders;
use App\Entity\OrderItems;
use App\Repository\OrderItemsRepository;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\OrdersRepository;
use App\Repository\ProductImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderController extends AbstractController
{
    #[Route('/api/orders/items/{itemId}', name: 'api_update_order_item', methods: ['PATCH'])]
    public function updateOrderItem(
        int $itemId,
        Request $request,
        EntityManagerInterface $em,
        OrderItemsRepository $itemRepo,
        OrdersRepository $ordersRepo,
        ProductRepository $productRepo
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true);
        $delta = isset($data['quantity']) ? (int)$data['quantity'] : null;

        if ($delta === null) {
            return $this->json(['error' => 'quantity is required'], 400);
        }

        // 1) Load the OrderItem (404 if not found)
        /** @var OrderItems|null $item */
        $item = $itemRepo->find($itemId);
        if (!$item) {
            return $this->json([
                'error' => "Order item #{$itemId} not found"
            ], 404);
        }

        $order   = $item->getOrderId();
        $product = $item->getProductId();
        $unit    = $product->getPrice();

        // 2) Compute new item quantity
        $currentQty = $item->getQuantity();
        $newQty     = $currentQty + $delta;

        // 3) If new quantity <= 0, remove the item entirely
        if ($newQty <= 0) {
            // refund entire currentQty to stock
            $product->setStock($product->getStock() + $currentQty);

            // subtract this item’s totalPrice from order
            $order->setTotalAmount($order->getTotalAmount() - $item->getTotalPrice());

            // remove the item
            $order->removeOrderId($item);
            $em->remove($item);
            $em->flush();

            // --- NEW: after removal, if order has no items, delete it too
            if ($order->getOrderId()->isEmpty()) {
                $em->remove($order);
                $em->flush();

                return $this->json([
                    'message' => 'Order and its last item have been deleted'
                ], 200);
            }

            // otherwise just update timestamps & return
            $order->setUpdatedAt(new \DateTimeImmutable());
            $product->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            return $this->json([
                'message'      => 'Item removed from order',
                'order_id'     => $order->getId(),
                'total_amount' => $order->getTotalAmount(),
            ], 200);
        }

        // 4) Otherwise, update the item quantity & order total
        if ($delta > 0 && $product->getStock() < $delta) {
            return $this->json(['error' => 'Insufficient stock to increase quantity'], 400);
        }

        // adjust stock: positive delta → subtract, negative delta → add back
        $product->setStock($product->getStock() - $delta);

        // compute price change
        $priceChange = $unit * $delta;
        $item->setQuantity($newQty);
        $item->setTotalPrice($unit * $newQty);

        // update order total
        $order->setTotalAmount($order->getTotalAmount() + $priceChange);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $product->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        // 5) Return updated order summary
        $totalQty = 0;
        foreach ($order->getOrderId() as $oi) {
            $totalQty += $oi->getQuantity();
        }

        return $this->json([
            'order_id'       => $order->getId(),
            'status'         => $order->getStatus(),
            'total_amount'   => $order->getTotalAmount(),
            'total_quantity' => $totalQty,
            'items'          => array_map(fn($i) => [
                'item_id'     => $i->getId(),
                'product_id'  => $i->getProductId()->getId(),
                'quantity'    => $i->getQuantity(),
                'unit_price'  => $i->getUnitPrice(),
                'total_price' => $i->getTotalPrice(),
            ], $order->getOrderId()->toArray()),
        ], 200);
    }



    #[Route('/api/orders', name: 'api_create_order', methods: ['POST'])]
    public function createOrder(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        ProductRepository $productRepo,
        OrdersRepository $ordersRepo
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        // 1) Basic validation
        foreach (['user_id', 'product_id', 'quantity'] as $field) {
            if (empty($payload[$field])) {
                return $this->json(['error' => "$field is required"], 400);
            }
        }

        // 2) Load User & Product
        $user    = $userRepo->find($payload['user_id']);
        $product = $productRepo->find($payload['product_id']);
        $qty     = (int) $payload['quantity'];


        $userEmail = $user->getEmail();

        if (!$user || !$product) {
            return $this->json(['error' => 'Invalid user or product'], 404);
        }

        // 3) Stock check
        if ($product->getStock() < $qty) {
            return $this->json(['error' => 'Insufficient stock'], 400);
        }

        // 4) Get or create “wait to pay” order
        $order = $ordersRepo->findOneBy([
            'user_id' => $user,
            'status'  => 'wait to pay',
        ]);

        if (!$order) {
            $order = new Orders();
            $order
                ->setUserId($user)
                ->setStatus('wait to pay')
                ->setCustomerEmail($userEmail)
                ->setTotalAmount(0)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($order);
        }

        // 5) Create order item
        $unitPrice  = $product->getPrice();
        $totalPrice = $unitPrice * $qty;

        $item = new OrderItems();
        $item
            ->setOrderId($order)
            ->setProductId($product)
            ->setQuantity($qty)
            ->setUnitPrice($unitPrice)
            ->setTotalPrice($totalPrice);
        $em->persist($item);

        // 6) Update order totals & stock
        $order->setTotalAmount($order->getTotalAmount() + $totalPrice);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $product->setStock($product->getStock() - $qty);

        // 7) Flush
        $em->flush();

        // Compute total quantity
        $totalQuantity = 0;
        foreach ($order->getOrderId() as $oi) {
            $totalQuantity += $oi->getQuantity();
        }

        return $this->json([
            'order_id'       => $order->getId(),
            'customer_email' => $order->getCustomerEmail(),
            'status'         => $order->getStatus(),
            'total_amount'   => $order->getTotalAmount(),
            'total_quantity' => $totalQuantity,
            'items'          => array_map(fn($i) => [
                'product_id'  => $i->getProductId()->getId(),
                'quantity'    => $i->getQuantity(),
                'unit_price'  => $i->getUnitPrice(),
                'total_price' => $i->getTotalPrice(),
            ], $order->getOrderId()->toArray()),
        ], 201);
    }


    #[Route('/api/orders/{orderId}', name: 'api_delete_order', methods: ['DELETE'])]
    public function deleteOrder(
        int $orderId,
        EntityManagerInterface $em,
        OrdersRepository $ordersRepo
    ): JsonResponse {
        // 1) Load the Order (404 if not found)
        /** @var Orders|null $order */
        $order = $ordersRepo->find($orderId);
        if (!$order) {
            return $this->json([
                'error' => "Order #{$orderId} not found"
            ], 404);
        }

        // 2) For each item: refund its quantity back to the product’s stock
        foreach ($order->getOrderId() as $item) {
            $product = $item->getProductId();
            $qty     = $item->getQuantity();

            // refund stock
            $product->setStock($product->getStock() + $qty);
            // mark product as updated
            $product->setUpdatedAt(new \DateTimeImmutable());

            // remove the item
            $em->remove($item);
        }

        // 3) Remove the order itself
        $em->remove($order);

        // 4) Persist all changes
        $em->flush();

        return $this->json([
            'message'  => "Order #{$orderId} and all its items have been deleted, and stock has been restored.",
        ], 200);
    }



    #[Route('/api/orders/user/{userId}', name: 'api_user_orders', methods: ['GET'])]
    public function getUserOrders(
        int $userId,
        OrdersRepository $ordersRepo,
        ProductImageRepository $imageRepo
    ): JsonResponse {
        $orders = $ordersRepo->findBy(['user_id' => $userId]);

        if (!$orders) {
            return $this->json([
                'error'  => 'No orders found for this user',
                'status' => 404
            ], 404);
        }

        $results = [];

        foreach ($orders as $order) {
            $itemsData = [];

            foreach ($order->getOrderId() as $item) {
                $product = $item->getProductId();

                // Fetch product images
                $images = $imageRepo->findBy(['product_id' => $product]);
                $imageData = array_map(fn($img) => [
                    'url'      => $img->getUrl(),
                    'alt_text' => $img->getAltText(),
                    'position' => $img->getPosition()
                ], $images);

                $itemsData[] = [
                    'item_id'      => $item->getId(),
                    'product_id'   => $product->getId(),
                    'product_name' => $product->getName(),
                    'unit_price'   => $item->getUnitPrice(),
                    'quantity'     => $item->getQuantity(),
                    'total_price'  => $item->getTotalPrice(),
                    'images'       => $imageData
                ];
            }

            $results[] = [
                'order_id'       => $order->getId(),
                'status'         => $order->getStatus(),
                'customer_email' => $order->getCustomerEmail(),
                'total_amount'   => $order->getTotalAmount(),
                'created_at'     => $order->getCreatedAt()?->format('Y-m-d H:i:s'),
                'items'          => $itemsData
            ];
        }

        return $this->json($results, 200);
    }



    #[Route('/api/orders/items/{itemId}', name: 'api_delete_order_item', methods: ['DELETE'])]
    public function deleteOrderItem(
        int $itemId,
        EntityManagerInterface $em,
        OrderItemsRepository $itemsRepo
    ): JsonResponse {
        // 1) Fetch the OrderItem
        $item = $itemsRepo->find($itemId);
        if (!$item) {
            return $this->json([
                'error'  => 'Order item not found',
                'status' => 404
            ], 404);
        }

        $order   = $item->getOrderId();
        $product = $item->getProductId();
        $qty     = $item->getQuantity();
        $unitPrice = $item->getUnitPrice();

        // 2) Return the reserved quantity to stock
        $product->setStock($product->getStock() + $qty);

        // 3) Remove the item
        $em->remove($item);
        $em->flush();

        // 4) If the order has no more items, delete it too
        $remainingItems = $order->getOrderId()->count();
        if ($remainingItems === 0) {
            $em->remove($order);
            $em->flush();

            return $this->json([
                'message' => 'Order item deleted. Parent order was empty and has also been deleted.'
            ], 200);
        }

        // 5) Otherwise, recompute order total and quantity
        $newTotal = 0;
        $newQtySum = 0;
        foreach ($order->getOrderId() as $oi) {
            $newTotal   += $oi->getTotalPrice();
            $newQtySum  += $oi->getQuantity();
        }
        $order->setTotalAmount($newTotal)
            ->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        // 6) Return the updated order in the same shape as create/update
        return $this->json([
            'order_id'       => $order->getId(),
            'status'         => $order->getStatus(),
            'customer_email' => $order->getCustomerEmail(),
            'total_amount'   => $order->getTotalAmount(),
            'total_quantity' => $newQtySum,
            'items'          => array_map(fn($i) => [
                'item_id'     => $i->getId(),
                'product_id'  => $i->getProductId()->getId(),
                'quantity'    => $i->getQuantity(),
                'unit_price'  => $i->getUnitPrice(),
                'total_price' => $i->getTotalPrice(),
            ], $order->getOrderId()->toArray()),
        ], 200);
    }
}
