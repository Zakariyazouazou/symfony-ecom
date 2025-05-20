<?php
// src/EventSubscriber/JwtSubscriber.php
namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;

class JwtSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationSuccessEvent::class => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data     = $event->getData();      // ['token' => '…']
        $response = $event->getResponse();

        // Create a custom cookie instead of the default
        $cookie = Cookie::create('ACCESS_TOKEN')
            // online token
            // ->withValue($data['token'])
            // ->withExpires(time() + (int) $event->getResponse()->headers->get('X-Token-TTL', 3600))
            // ->withHttpOnly(true)
            // ->withSecure(true)
            // ->withSameSite('strict')
            // ->withPath('/api');

            // offline token
            ->withValue($data['token'])
            ->withExpires(time() + (int) $event->getResponse()->headers->get('X-Token-TTL', 3600))
            ->withPath('/')                   // ← entire app
            ->withDomain('localhost')         // ← exact match
            ->withSecure(false)               // ← HTTP in dev
            ->withHttpOnly(true)
            ->withSameSite('none');           // ← cross-site allowed




        $response->headers->setCookie($cookie);

        // Optionally remove it from the JSON body:
        // unset($data['token']);
        // $event->setData($data);
    }
}
