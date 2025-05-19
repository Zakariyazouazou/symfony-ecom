<?php
namespace App\Security\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;

class CustomRefreshSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private JWTTokenManagerInterface $jwtManager;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private string $refreshTokenTTL;

    public function __construct(
        JWTTokenManagerInterface     $jwtManager,
        RefreshTokenManagerInterface $refreshTokenManager,
        string                       $refreshTokenTTL   // e.g. 'P30D' or '30 days'
    ) {
        $this->jwtManager          = $jwtManager;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->refreshTokenTTL     = $refreshTokenTTL;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        /** @var User $user */
        $user = $token->getUser();

        // 1) Create new JWT
        $jwt = $this->jwtManager->create($user);

        // 2) Compute refresh-token expiration
        try {
            $interval  = new DateInterval($this->refreshTokenTTL);
            $expiresAt = (new DateTimeImmutable())->add($interval);
        } catch (\Exception $e) {
            // fallback for human‑readable strings like "30 days"
            $expiresAt = new DateTimeImmutable(sprintf('+%s', $this->refreshTokenTTL));
        }

        // 3) Create & persist new refresh token
        $refreshToken = $this->refreshTokenManager->create();
        $refreshToken
            ->setUsername($user->getUserIdentifier())
            ->setRefreshToken(bin2hex(random_bytes(64)))
            ->setValid($expiresAt);
        $this->refreshTokenManager->save($refreshToken);

        // 4) Build payload (token + refresh + user info)
        $data = [
            'token'         => $jwt,
            // 'refresh_token' => $refreshToken->getRefreshToken(),
            'user' => [
                'id'       => $user->getId(),
                'username' => $user->getUsername(),
                'email'    => $user->getUserIdentifier(),
                'roles'    => $user->getRoles(),
            ],
        ];

        $response = new JsonResponse($data, Response::HTTP_OK);

        // 5) Set refresh_token cookie
        $cookie = Cookie::create('refresh_token')
            ->withValue($refreshToken->getRefreshToken())
            ->withExpires($expiresAt)
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withPath('/api/token/refresh')
            ->withSameSite('none');

        $response->headers->setCookie($cookie);

        return $response;
    }
}
