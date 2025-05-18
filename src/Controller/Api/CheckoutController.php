<?php
namespace App\Controller\Api;

use App\Entity\Orders;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CheckoutController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/api/create-checkout-session', methods: ['POST'])]
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. Load the order by ID
        /** @var Orders|null $order */
        $order = $this->em->getRepository(Orders::class)
                      ->find($data['orderId'] ?? 0);

        // 2. If not found, return 404
        if (!$order) {
            return new JsonResponse([
                'error'   => 'Order not found',
                'message' => sprintf('No order with ID %s exists.', $data['orderId'] ?? '–')
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // 3. Mark order as pending
        $order->setStatus('pending');
        $this->em->flush();

        // 4. Always get items from OrderItems and product data
        $orderItems = $order->getOrderId();
        $lineItems = [];

        foreach ($orderItems as $orderItem) {
            $product = $orderItem->getProductId();
            if (!$product) {
                continue;
            }

            $unitPrice = $product->getPrice() ?? $orderItem->getUnitPrice();
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => (int)round($unitPrice * 100),
                    'product_data' => ['name' => $product->getName()],
                ],
                'quantity' => $orderItem->getQuantity(),
            ];
        }

        // 5. Initialize Stripe SDK
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        // 6. Create Stripe Checkout Session
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'metadata'             => ['order_id' => $order->getId()],
            'success_url'          => $data['successUrl'],
            'cancel_url'           => $data['cancelUrl'],
        ]);

        // 7. Save Stripe session ID back to the order
        $order->setStripePaymentId($session->id);
        $this->em->flush();

        // 8. Return the checkout link
        return new JsonResponse([
            'message'            => 'Success! Please click the link below to complete your payment.',
            'stripeCheckoutLink' => $session->url,
        ]);
    }
}
