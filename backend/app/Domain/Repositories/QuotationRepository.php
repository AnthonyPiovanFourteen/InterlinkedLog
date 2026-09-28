<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Quotation;

interface QuotationRepository
{
    public function findById(string $id): ?Quotation;

    public function findByIdForUpdate(string $id): ?Quotation;

    public function findByCompany(string $companyId, array $filters = []): array;

    /**
     * Acrescenta UM resultado, sem tocar nos demais. O save() regrava o
     * conjunto inteiro, o que não serve para cotação que chega aos poucos.
     *
     * @param  array<string,mixed>  $result
     */
    public function appendResult(string $quotationId, array $result): void;

    public function save(Quotation $quotation): void;
}
