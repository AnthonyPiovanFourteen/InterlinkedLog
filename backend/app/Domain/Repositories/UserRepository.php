<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\User;

interface UserRepository
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findByToken(string $token): ?User;
    public function save(User $user): void;
    public function delete(string $id): void;
    public function findByCompany(string $companyId): array;
    public function setToken(string $userId, ?string $token): void;
}
