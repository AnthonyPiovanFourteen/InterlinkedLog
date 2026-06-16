<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Quotation;

interface QuotationRepository
{
    public function findById(string $id): ?Quotation;
    public function findByCompany(string $companyId, array $filters = []): array;
    public function save(Quotation $quotation): void;
}
