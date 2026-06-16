<?php

namespace App\Application\UseCases\Auth;

use App\Domain\Services\AuthService;

class LoginUserUseCase
{
    public function __construct(private AuthService $authService) {}

    public function execute(string $email, string $password): ?array
    {
        return $this->authService->login($email, $password);
    }
}
