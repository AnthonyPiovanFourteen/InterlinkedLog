<?php

namespace App\Domain\Services;

use App\Domain\Entities\Quotation;

interface QuotationEngineService
{
    public function process(Quotation $quotation): array;

    public function cepToCity(string $cep): array;

    /**
     * Reordena e marca melhor preço, melhor prazo e melhor custo-benefício
     * sobre o conjunto informado — necessário porque os resultados de API
     * chegam depois dos de tabela.
     *
     * @param  array<int,array<string,mixed>>  $results
     * @return array<int,array<string,mixed>>
     */
    public function rankResults(array $results): array;
}
