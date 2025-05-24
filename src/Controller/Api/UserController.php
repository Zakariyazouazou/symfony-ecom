<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/user', name: 'api_user_')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository  // you can inject the repository directly if preferred
    ) {}

    #[Route(path: '/all', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $users = $this->em->getRepository(User::class)->findAll();

        $data = [];
        foreach ($users as $user) {
            $data[] = $this->transformUser($user);
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }


    #[Route(path: '/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(
                ['error' => 'User not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $this->transformUser($user);

        return new JsonResponse($data, Response::HTTP_OK);
    }



    private function transformUser(User $user): array
    {
        return [
            'id'         => $user->getId(),
            'email'      => $user->getEmail(),
            'username'   => $user->getUsername(),
            'firstName'  => $user->getFirstName(),
            'lastName'   => $user->getLastName(),
            'roles'      => $user->getRoles(),
            'createdAt'  => $user->getCreatedAt()->format(\DateTime::ATOM),
            'updatedAt'  => $user->getUpdatedAt()->format(\DateTime::ATOM),
        ];
    }
}
