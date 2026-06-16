<?php

namespace App\Domain\Entities;

class FreightTable
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $carrierId,
        public readonly string $originCity,
        public readonly string $validityStart,
        public readonly string $validityEnd,
        public readonly string $status,
        public readonly array $routes,
        public readonly array $weightRanges,
        public readonly array $fees,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {}

    public static function create(
        string $id,
        string $name,
        string $carrierId,
        string $originCity,
        string $validityStart,
        string $validityEnd,
    ): self {
        return new self(
            id: $id,
            name: $name,
            carrierId: $carrierId,
            originCity: $originCity,
            validityStart: $validityStart,
            validityEnd: $validityEnd,
            status: 'Rascunho',
            routes: [],
            weightRanges: [],
            fees: [],
        );
    }
}
