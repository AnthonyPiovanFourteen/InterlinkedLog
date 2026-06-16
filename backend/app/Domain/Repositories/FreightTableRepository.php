<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\FreightTable;

interface FreightTableRepository
{
    public function findById(string $id): ?FreightTable;
    public function findByCarrier(string $carrierId): array;
    public function findActiveByCarrierAndRoute(string $carrierId, string $originCity, string $destCity, string $destState): ?FreightTable;
    public function findAll(array $filters = []): array;
    public function save(FreightTable $table): void;
    public function delete(string $id): void;
}
