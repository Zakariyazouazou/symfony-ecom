<?php
// src/Controller/SecurityController.php
namespace App\Controller;

use App\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityController
{
    public function __construct(
        private RefreshTokenManagerInterface $refreshManager,
        private EntityManagerInterface       $em
    ) {}

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(TokenStorageInterface $tokenStorage): JsonResponse
    {
        // 1) Identify the user
        $user     = $tokenStorage->getToken()->getUser();
        $username = $user->getUserIdentifier();

        // 2) Fetch all RefreshToken entities for that user
        $tokens = $this->em
            ->getRepository(RefreshToken::class)
            ->findBy(['username' => $username]);

        // 3) Delete each one via the bundle’s manager
        foreach ($tokens as $tokenEntity) {
            $this->refreshManager->delete($tokenEntity);
        }

        // 4) Clear the cookie
        $cookie = Cookie::create('refresh_token')
            ->withValue('')
            ->withExpires(new \DateTime('-1 day'))
            ->withPath('/api/token/refresh')
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withSameSite('strict');

        $response = new JsonResponse(
            ['message' => 'Logged out successfully'],
            JsonResponse::HTTP_OK
        );
        $response->headers->setCookie($cookie);

        return $response;
    }
}
