<?php

namespace App\Domain\Entities;

class Company
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $cnpj,
        public readonly string $plan,
        public readonly ?string $status = null,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {}

    public static function create(string $id, string $name, string $cnpj, string $plan = 'Starter', ?string $status = null): self
    {
        return new self(
            id: $id,
            name: $name,
            cnpj: $cnpj,
            plan: $plan,
            status: $status ?? 'Trial',
        );
    }
}
