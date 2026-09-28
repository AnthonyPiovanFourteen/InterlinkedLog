<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CepNotFoundException;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Repositories\FreightTableRepository;
use App\Domain\Services\CepLookupService;
use App\Domain\Services\QuotationEngineService;

class QuotationEngine implements QuotationEngineService
{
    /**
     * Linhas da tabela de frete que NÃO são taxas somáveis:
     * - frete_minimo: piso sobre o valor do frete (se o frete calculado for
     *   menor, cobra-se o mínimo). Não é acréscimo.
     * - cubagem: fator kg/m³ (300 no rodoviário). Define o peso cubado; o peso
     *   faturável é o maior entre o real e o cubado. Não é acréscimo.
     */
    private const NON_FEE_TYPES = ['frete_minimo', 'cubagem'];

    private const CUBAGE_FACTOR_FALLBACK = 300.0;

    public function __construct(
        private CarrierRepository $carrierRepository,
        private FreightTableRepository $freightTableRepository,
        private CepLookupService $cepLookupService,
    ) {}

    public function cepToCity(string $cep): array
    {
        $city = $this->cepLookupService->lookup($cep);
        if (! $city) {
            throw new CepNotFoundException($cep);
        }

        return $city;
    }

    public function process(Quotation $quotation): array
    {
        $results = [];
        $carriers = $this->carrierRepository->findAll();

        foreach ($carriers as $carrier) {
            if ($carrier->status !== CarrierStatus::ATIVA) {
                continue;
            }

            $table = $this->freightTableRepository->findActiveByCarrierAndRoute(
                $quotation->companyId,
                $carrier->id,
                $quotation->originCity,
                $quotation->destinationCity,
                $quotation->destinationState,
            );

            if (! $table) {
                continue;
            }

            $route = null;
            foreach ($table->routes as $r) {
                if (mb_strtolower($r['city']) === mb_strtolower($quotation->destinationCity)) {
                    $route = $r;
                    break;
                }
            }

            if (! $route) {
                continue;
            }

            $billableWeight = $this->billableWeight($quotation, $table->fees);
            $weightRange = $this->rangeFor($route['weightRanges'], $billableWeight);

            $freightValue = $weightRange ? $weightRange['value'] : 0;
            $deadline = $weightRange ? $weightRange['deadline'] : ($route['deadline'] ?? 1);

            $freightValue = max($freightValue, $this->minimumFreight($table->fees));

            $totalFees = $this->calculateFees($table->fees, $quotation->cargoValue);

            $finalValue = $freightValue + $totalFees;

            $results[] = [
                'carrier_id' => $carrier->id,
                'carrier_name' => $carrier->name,
                'deadline' => $deadline,
                'freight_value' => round($freightValue, 2),
                'fees' => round($totalFees, 2),
                'final_value' => round($finalValue, 2),
                'fees_breakdown' => $this->getFeesBreakdown($table->fees, $quotation->cargoValue),
            ];
        }

        $results = $this->rank($results);

        return $results;
    }

    /**
     * Peso faturável: o maior entre o peso real e o peso cubado
     * (volume em m³ × fator de cubagem da tabela).
     */
    private function billableWeight(Quotation $quotation, array $fees): float
    {
        $factor = $this->feeValue($fees, 'cubagem') ?? self::CUBAGE_FACTOR_FALLBACK;

        if ($factor <= 0) {
            return $quotation->weight;
        }

        return max($quotation->weight, $quotation->volume * $factor);
    }

    /**
     * Piso sobre o valor do frete. Ausente na tabela, não há piso.
     */
    private function minimumFreight(array $fees): float
    {
        return $this->feeValue($fees, 'frete_minimo') ?? 0.0;
    }

    private function feeValue(array $fees, string $type): ?float
    {
        foreach ($fees as $fee) {
            if (($fee['type'] ?? '') === $type) {
                return (float) ($fee['value'] ?? 0);
            }
        }

        return null;
    }

    /**
     * As faixas da tabela são degraus contínuos: escolhe a primeira cujo teto
     * cobre o peso faturável. Comparar contra 'start' criava lacunas (peso 30,5
     * não casava em [0,30] nem em [31,100]) — alcançáveis agora que o peso
     * cubado produz decimais.
     */
    private function rangeFor(array $ranges, float $weight): ?array
    {
        usort($ranges, fn ($a, $b) => $a['end'] <=> $b['end']);

        foreach ($ranges as $range) {
            if ($weight <= $range['end']) {
                return $range;
            }
        }

        return null;
    }

    private function calculateFees(array $fees, float $cargoValue): float
    {
        // frete_minimo e cubagem são tratados como taxas fixas (R$ 50,00 e
        // R$ 300,00 no seed — cerca de metade do total de taxas). A semântica
        // provável é, respectivamente, piso sobre o valor do frete e fator de
        // cubagem sobre o peso. Aguarda decisão de negócio; não alterar o cálculo.
        $total = 0.0;
        foreach ($fees as $fee) {
            if (in_array($fee['type'] ?? '', self::NON_FEE_TYPES, true)) {
                continue;
            }
            if (! empty($fee['percentage'])) {
                $total += $cargoValue * ($fee['percentage'] / 100);
            } else {
                $total += $fee['value'] ?? 0;
            }
        }

        return $total;
    }

    private function getFeesBreakdown(array $fees, float $cargoValue): array
    {
        $breakdown = [];
        foreach ($fees as $fee) {
            if (in_array($fee['type'] ?? '', self::NON_FEE_TYPES, true)) {
                continue;
            }
            if (! empty($fee['percentage'])) {
                $amount = $cargoValue * ($fee['percentage'] / 100);
            } else {
                $amount = $fee['value'] ?? 0;
            }
            $breakdown[] = [
                'type' => $fee['type'],
                'amount' => round($amount, 2),
            ];
        }

        return $breakdown;
    }

    private function rank(array $results): array
    {
        usort($results, fn ($a, $b) => $a['final_value'] <=> $b['final_value']);

        if (count($results) > 0) {
            $results[0]['best_price'] = true;
        }

        $byDeadline = $results;
        usort($byDeadline, fn ($a, $b) => $a['deadline'] <=> $b['deadline']);
        if (count($byDeadline) > 0) {
            foreach ($results as &$r) {
                if ($r['carrier_id'] === $byDeadline[0]['carrier_id']) {
                    $r['best_deadline'] = true;
                    break;
                }
            }
        }

        $byCB = $results;
        usort($byCB, fn ($a, $b) => ($a['final_value'] / max(1, $a['deadline'])) <=> ($b['final_value'] / max(1, $b['deadline'])));
        if (count($byCB) > 0) {
            foreach ($results as &$r) {
                if ($r['carrier_id'] === $byCB[0]['carrier_id']) {
                    $r['best_cost_benefit'] = true;
                    break;
                }
            }
        }

        return $results;
    }
}
