<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/user', name: 'api_user_')]
class UserController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route(path: '/testLogin', name: 'test_login', methods: ['GET'])]
    public function testLogin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Optional: Log or inspect $data if needed
        // $username = $data['username'] ?? null;
        // $password = $data['password'] ?? null;

        return new JsonResponse(['message' => 'Test login received'], Response::HTTP_OK);
    }


    #[Route(path: '/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(DATE_ATOM),
        ];

        return new JsonResponse($data);
    }
}
