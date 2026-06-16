<?php

namespace App\Domain\Entities;

class Carrier
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $cnpj,
        public readonly string $originCity,
        public readonly string $originState,
        public readonly string $status,
        public readonly string $contactName = '',
        public readonly string $contactPhone = '',
        public readonly string $contactEmail = '',
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {}

    public static function create(
        string $id,
        string $name,
        string $cnpj,
        string $originCity,
        string $originState,
        string $contactName = '',
        string $contactPhone = '',
        string $contactEmail = '',
    ): self {
        return new self(
            id: $id,
            name: $name,
            cnpj: $cnpj,
            originCity: $originCity,
            originState: $originState,
            status: 'Ativa',
            contactName: $contactName,
            contactPhone: $contactPhone,
            contactEmail: $contactEmail,
        );
    }
}
