<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\QuotationGatewayAttempt;

interface QuotationGatewayAttemptRepository
{
    /** @return array<int,QuotationGatewayAttempt> */
    public function findByQuotation(string $quotationId): array;

    public function save(QuotationGatewayAttempt $attempt): void;
}
