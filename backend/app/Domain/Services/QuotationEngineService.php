<?php

namespace App\Domain\Services;

use App\Domain\Entities\Quotation;

interface QuotationEngineService
{
    public function process(Quotation $quotation): array;

    public function cepToCity(string $cep): array;
}
