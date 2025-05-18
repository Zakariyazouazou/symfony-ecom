<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

#[Route('/api', name: 'api_')]
class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function index(ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $em = $doctrine->getManager();
        $decoded = json_decode($request->getContent());

        $errors = [];

        $email = $decoded->email ?? null;
        $password = $decoded->password ?? null;
        $firstName = $decoded->first_name ?? null;
        $lastName = $decoded->last_name ?? null;

        $roles = $decoded->roles ?? [];



        if (!is_array($roles)) {
            $errors[] = 'Roles must be an array of strings.';
        } else {
            // Optional: validate allowed roles (you can customize this list)
            $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN'];
            foreach ($roles as $role) {
                if (!in_array($role, $allowedRoles)) {
                    $errors[] = "Invalid role: $role";
                }
            }

            // Ensure at least ROLE_USER is always added
            if (!in_array('ROLE_USER', $roles)) {
                $roles[] = 'ROLE_USER';
            }
        }

        // Email validation
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid or missing email.';
        }

        // Password validation
        if (!$password) {
            $errors[] = 'Password is required.';
        } elseif (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||         // at least one uppercase
            !preg_match('/\d/', $password)               // at least one number
        ) {
            $errors[] = 'Password must be at least 8 characters long and contain at least one uppercase letter and one number.';
        }

        // Name validation
        if (!$firstName || !preg_match('/^[a-zA-Z]+$/', $firstName)) {
            $errors[] = 'First name is required and must contain only letters.';
        }

        if (!$lastName || !preg_match('/^[a-zA-Z]+$/', $lastName)) {
            $errors[] = 'Last name is required and must contain only letters.';
        }

        // Return errors if any
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        // All good, create user
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($email); // still using email as username
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles($roles);
        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $now = new \DateTimeImmutable();
        $user->setCreatedAt($now);
        $user->setUpdatedAt($now);

        $em->persist($user);
        $em->flush();

        return $this->json(['message' => 'Registered Successfully']);
    }
}
