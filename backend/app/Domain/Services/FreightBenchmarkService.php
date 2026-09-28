<?php

namespace App\Domain\Services;

use App\Domain\Entities\Quotation;

/**
 * Referência histórica de preço e prazo para uma rota, a partir do que a
 * empresa efetivamente contratou — não do que foi cotado.
 *
 * Cotação que ninguém fechou mede intenção; contrato mede preço praticado.
 */
interface FreightBenchmarkService
{
    /**
     * @return array{sample: int, median_value: float|null, median_deadline: int|null, months: int}
     */
    public function forQuotation(Quotation $quotation): array;
}
