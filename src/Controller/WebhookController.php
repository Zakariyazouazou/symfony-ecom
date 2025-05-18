<?php
namespace App\Controller;

use App\Entity\Orders;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Webhook;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class WebhookController extends AbstractController
{
    private string $endpointSecret = 'whsec_84d7ba0142345db2083ffdc4df55f1deb5409616076d1e747083835d8274ed45';

    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/api/webhook', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $this->endpointSecret
            );  
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid payload or signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            /** @var Orders $order */
            $order = $this->em->getRepository(Orders::class)
                ->find($session->metadata->order_id);
            if ($order) {
                $order->setStatus('paid');
                $order->setStripePaymentId($session->id);
                $this->em->flush();
            }
        }

        return new Response('Webhook handled', 200);
    }
}
