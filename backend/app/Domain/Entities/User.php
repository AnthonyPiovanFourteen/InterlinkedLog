<?php

namespace App\Domain\Entities;

class User
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $role,
        public readonly string $status,
        public readonly string $companyId,
        public readonly ?string $lastAccessAt = null,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {}

    public static function create(
        string $id,
        string $name,
        string $email,
        string $passwordHash,
        string $role,
        string $companyId,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            passwordHash: $passwordHash,
            role: $role,
            status: 'Ativo',
            companyId: $companyId,
        );
    }
}
