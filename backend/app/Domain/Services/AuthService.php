<?php

namespace App\Domain\Services;

interface AuthService
{
    public function login(string $email, string $password): ?array;
    public function logout(string $token): void;
    public function validateToken(string $token): ?array;
}
