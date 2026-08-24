<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\FreightTable;

interface FreightTableRepository
{
    public function findById(string $companyId, string $id): ?FreightTable;
    public function findByCarrier(string $companyId, string $carrierId): array;
    public function findActiveByCarrierAndRoute(string $companyId, string $carrierId, string $originCity, string $destCity, string $destState): ?FreightTable;
    public function findAll(string $companyId, array $filters = []): array;
    public function save(FreightTable $table): void;
    public function delete(string $companyId, string $id): void;
}
