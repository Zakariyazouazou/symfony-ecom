<?php
// src/EventListener/CorsRequestListener.php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsRequestListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // We need this to run with a very high priority
        // Must execute before authentication/firewall listeners
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Only handle OPTIONS requests (preflight)
        if ($request->getRealMethod() !== 'OPTIONS') {
            return;
        }
        
        // Handle CORS preflight request
        $response = new Response();
        
        // Set CORS headers
        $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:5173');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '3600');
        
        // Stop propagation to other listeners
        $event->setResponse($response);
    }
}