<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Carrier;

interface CarrierRepository
{
    public function findById(string $id): ?Carrier;
    public function findAll(): array;
    public function findByOrigin(string $city, string $state): array;
    public function save(Carrier $carrier): void;
    public function delete(string $id): void;
}
