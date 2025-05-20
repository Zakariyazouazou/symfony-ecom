<?php

namespace App\Security\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use DateInterval;
use DateTimeImmutable;

class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private JWTManager $jwtManager;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private string $refreshTokenTTL;

    public function __construct(
        JWTManager $jwtManager,
        RefreshTokenManagerInterface $refreshTokenManager,
        string $refreshTokenTTL // e.g. 'P30D' (ISO 8601 interval) or '30 days'
    ) {
        $this->jwtManager          = $jwtManager;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->refreshTokenTTL     = $refreshTokenTTL;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {

         $response = new JsonResponse([
             'token' => "hello"
         ], Response::HTTP_OK);


         return $response;

        // 1) Create the JWT
        // $user = $token->getUser();
        // $jwt  = $this->jwtManager->create($user);

        // // 2) Compute expiration
        // // If using ISO 8601 interval (e.g. 'P30D'):
        // try {
        //     $interval = new DateInterval($this->refreshTokenTTL);
        //     $expiresAt = (new DateTimeImmutable())->add($interval);
        // } catch (\Exception $e) {
        //     // Fallback for human-readable strings (e.g. '30 days')
        //     $expiresAt = new DateTimeImmutable(sprintf('+%s', $this->refreshTokenTTL));
        // }

        // // 3) Create & persist the refresh token
        // $refreshToken = $this->refreshTokenManager->create();
        // $refreshToken
        //     ->setUsername($user->getUserIdentifier())
        //     ->setRefreshToken(bin2hex(random_bytes(64)))
        //     ->setValid($expiresAt);
        // $this->refreshTokenManager->save($refreshToken);

        // // 4) Build JSON payload
        // $data = [
        //     'token'         => $jwt,
        //     // 'refresh_token' => $refreshToken->getRefreshToken(),
        //     'roles'         => $user->getRoles(),
        //     // add other user getters here if you like:
        //     // 'firstName' => $user->getFirstName(),
        //     // 'lastName'  => $user->getLastName(),
        // ];

        // // 5) Create response and attach cookies
        // $response = new JsonResponse($data, Response::HTTP_OK);

        // // ACCESS TOKEN cookie
        // // $accessCookie = Cookie::create('ACCESS_TOKEN')
        // //     ->withValue($jwt)
        // //     ->withExpires($expiresAt)
        // //     ->withHttpOnly(true)
        // //     ->withSecure(true)
        // //     ->withPath('/')        // adjust as needed
        // //     ->withSameSite('lax'); // or 'strict'/'none'

        // // REFRESH TOKEN cookie
        // $refreshCookie = Cookie::create('refresh_token')
        //     ->withValue($refreshToken->getRefreshToken())
        //     ->withExpires($expiresAt)
        //     ->withHttpOnly(true)
        //     ->withSecure(true)
        //     ->withPath('/')
        //     ->withSameSite('none');

        // // $response->headers->setCookie($accessCookie);
        // $response->headers->setCookie($refreshCookie);

        // return $response;
    }
}
