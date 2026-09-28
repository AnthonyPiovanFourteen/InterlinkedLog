<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\Contract;
use App\Domain\Entities\Quotation;
use App\Domain\Services\FreightBenchmarkService;
use App\Models\Contract as ContractModel;

class ContractFreightBenchmark implements FreightBenchmarkService
{
    private const MONTHS = 6;

    /** Carga é "parecida" quando o peso está nesta margem. */
    private const WEIGHT_TOLERANCE = 0.30;

    public function forQuotation(Quotation $quotation): array
    {
        // O escopo de tenant já limita a consulta à própria empresa.
        $contracts = ContractModel::query()
            ->where('status', '!=', Contract::STATUS_CANCELLED)
            ->where('origin_city', $quotation->originCity)
            ->where('destination_city', $quotation->destinationCity)
            ->where('destination_state', $quotation->destinationState)
            ->where('created_at', '>=', now()->subMonths(self::MONTHS))
            ->whereHas('quotation', function ($q) use ($quotation) {
                $q->whereBetween('weight', [
                    $quotation->weight * (1 - self::WEIGHT_TOLERANCE),
                    $quotation->weight * (1 + self::WEIGHT_TOLERANCE),
                ]);
            })
            ->get(['final_value', 'deadline']);

        if ($contracts->isEmpty()) {
            return ['sample' => 0, 'median_value' => null, 'median_deadline' => null, 'months' => self::MONTHS];
        }

        return [
            'sample' => $contracts->count(),
            // Mediana, não média: um frete atípico distorce a média.
            'median_value' => round($this->median($contracts->pluck('final_value')->map(fn ($v) => (float) $v)->all()), 2),
            'median_deadline' => (int) round($this->median($contracts->pluck('deadline')->map(fn ($v) => (float) $v)->all())),
            'months' => self::MONTHS,
        ];
    }

    /** @param  array<int,float>  $values */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
