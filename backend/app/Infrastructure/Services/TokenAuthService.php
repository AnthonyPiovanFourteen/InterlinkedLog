<?php

namespace App\Infrastructure\Services;

use App\Domain\Repositories\UserRepository;
use App\Domain\Services\AuthService;

class TokenAuthService implements AuthService
{
    public function __construct(private UserRepository $userRepository) {}

    public function login(string $email, string $password): ?array
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return null;
        }

        if ($user->status !== 'Ativo') {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->userRepository->setToken($user->id, $token);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function logout(string $token): void
    {
        $user = $this->userRepository->findByToken($token);
        if ($user) {
            $this->userRepository->setToken($user->id, null);
        }
    }

    public function validateToken(string $token): ?array
    {
        $user = $this->userRepository->findByToken($token);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company_id' => $user->companyId,
            'status' => $user->status,
        ];
    }
}
