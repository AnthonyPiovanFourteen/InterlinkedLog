<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\Quotation;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Repositories\FreightTableRepository;
use App\Domain\Services\QuotationEngineService;

class QuotationEngine implements QuotationEngineService
{
    private array $cepMap = [
        '01000' => ['São Paulo', 'SP'], '02000' => ['São Paulo', 'SP'],
        '20000' => ['Rio de Janeiro', 'RJ'], '21000' => ['Rio de Janeiro', 'RJ'],
        '30000' => ['Belo Horizonte', 'MG'], '31000' => ['Belo Horizonte', 'MG'],
        '40000' => ['Salvador', 'BA'], '41000' => ['Salvador', 'BA'],
        '50000' => ['Recife', 'PE'], '51000' => ['Recife', 'PE'],
        '60000' => ['Fortaleza', 'CE'], '61000' => ['Fortaleza', 'CE'],
        '70000' => ['Brasília', 'DF'], '71000' => ['Brasília', 'DF'],
        '80000' => ['Curitiba', 'PR'], '81000' => ['Curitiba', 'PR'],
        '90000' => ['Porto Alegre', 'RS'], '91000' => ['Porto Alegre', 'RS'],
        '74000' => ['Goiânia', 'GO'], '69000' => ['Manaus', 'AM'],
        '17500' => ['Marília', 'SP'],
        '86020' => ['Londrina', 'PR'],
    ];

    public function __construct(
        private CarrierRepository $carrierRepository,
        private FreightTableRepository $freightTableRepository,
    ) {}

    public function cepToCity(string $cep): array
    {
        $cep = preg_replace('/\D/', '', $cep);
        $prefix = substr($cep, 0, 5);
        return $this->cepMap[$prefix] ?? ['São Paulo', 'SP'];
    }

    public function process(Quotation $quotation): array
    {
        $results = [];
        $carriers = $this->carrierRepository->findAll();

        foreach ($carriers as $carrier) {
            if ($carrier->status !== 'Ativo') continue;

            $table = $this->freightTableRepository->findActiveByCarrierAndRoute(
                $carrier->id,
                $quotation->originCity,
                $quotation->destinationCity,
                $quotation->destinationState,
            );

            if (!$table) continue;

            $route = null;
            foreach ($table->routes as $r) {
                if (strtolower($r['city']) === strtolower($quotation->destinationCity)) {
                    $route = $r;
                    break;
                }
            }

            if (!$route) continue;

            $deadline = $route['deadline'];

            $weightRange = null;
            foreach ($table->weightRanges as $w) {
                if ($quotation->weight >= $w['start'] && $quotation->weight <= $w['end']) {
                    $weightRange = $w;
                    break;
                }
            }

            $freightValue = $weightRange ? $weightRange['value'] : 0;

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

    private function calculateFees(array $fees, float $cargoValue): float
    {
        $total = 0.0;
        foreach ($fees as $fee) {
            $total += $fee['value'] ?? 0;
            if (!empty($fee['percentage'])) {
                $total += $cargoValue * ($fee['percentage'] / 100);
            }
        }
        return $total;
    }

    private function getFeesBreakdown(array $fees, float $cargoValue): array
    {
        $breakdown = [];
        foreach ($fees as $fee) {
            $amount = ($fee['value'] ?? 0);
            if (!empty($fee['percentage'])) {
                $amount += $cargoValue * ($fee['percentage'] / 100);
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
        usort($results, fn($a, $b) => $a['final_value'] <=> $b['final_value']);

        if (count($results) > 0) $results[0]['best_price'] = true;

        $byDeadline = $results;
        usort($byDeadline, fn($a, $b) => $a['deadline'] <=> $b['deadline']);
        if (count($byDeadline) > 0) {
            foreach ($results as &$r) {
                if ($r['carrier_id'] === $byDeadline[0]['carrier_id']) {
                    $r['best_deadline'] = true;
                    break;
                }
            }
        }

        $byCB = $results;
        usort($byCB, fn($a, $b) => ($a['final_value'] / max(1, $a['deadline'])) <=> ($b['final_value'] / max(1, $b['deadline'])));
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
