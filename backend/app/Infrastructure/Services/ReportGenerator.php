<?php

namespace App\Infrastructure\Services;

use App\Domain\Repositories\ContractRepository;
use App\Domain\Repositories\QuotationRepository;
use App\Domain\Repositories\TrackingEventRepository;
use App\Domain\Services\ReportService;

class ReportGenerator implements ReportService
{
    public function __construct(
        private ContractRepository $contractRepository,
        private QuotationRepository $quotationRepository,
        private TrackingEventRepository $trackingRepository,
    ) {}

    public function dashboard(string $companyId): array
    {
        $contracts = $this->contractRepository->findByCompany($companyId);
        $quotations = $this->quotationRepository->findByCompany($companyId);

        $totalSavings = 0;
        $totalContracted = 0;
        $avgDeliveryTime = 0;
        $carrierCounts = [];

        $deliveryDeltas = [];

        foreach ($contracts as $c) {
            $quotation = $this->quotationRepository->findById($c->quotationId);
            if ($quotation && count($quotation->results) > 1) {
                $maxPrice = max(array_column($quotation->results, 'final_value'));
                $savings = $maxPrice - $c->finalValue;
                $totalSavings += $savings;
            }
            $totalContracted += $c->finalValue;

            $carrierCounts[$c->carrierName] = ($carrierCounts[$c->carrierName] ?? 0) + 1;
        }

        $topCarrier = !empty($carrierCounts) ? array_keys($carrierCounts, max($carrierCounts))[0] : null;

        if (count($deliveryDeltas) > 0) {
            $avgDeliveryTime = array_sum($deliveryDeltas) / count($deliveryDeltas);
        }

        $quotationsByStatus = [];
        foreach ($quotations as $q) {
            $status = $q->status ?? 'RASCUNHO';
            $quotationsByStatus[$status] = ($quotationsByStatus[$status] ?? 0) + 1;
        }

        $quotationsCount = count($quotations);
        $contractsCount = count($contracts);
        $roundedTotalContracted = round($totalContracted, 2);

        return [
            'quotations_count' => $quotationsCount,
            'contracts_count' => $contractsCount,
            'total_savings' => round($totalSavings, 2),
            'total_contracted' => $roundedTotalContracted,
            'avg_delivery_time' => round($avgDeliveryTime, 1),
            'top_carrier' => $topCarrier,
            'carrier_distribution' => $carrierCounts,
            'conversion_rate' => round(($contractsCount / max($quotationsCount, 1)) * 100, 2),
            'avg_ticket' => round($roundedTotalContracted / max($contractsCount, 1), 2),
            'quotations_by_status' => $quotationsByStatus,
        ];
    }

    public function detailed(string $companyId): array
    {
        $contracts = $this->contractRepository->findByCompany($companyId);

        $carrierRanking = [];
        $routeUsage = [];
        $totalValue = 0;

        foreach ($contracts as $c) {
            $totalValue += $c->finalValue;

            $carrierRanking[$c->carrierName] = [
                'carrier' => $c->carrierName,
                'contracts' => ($carrierRanking[$c->carrierName]['contracts'] ?? 0) + 1,
                'total_value' => ($carrierRanking[$c->carrierName]['total_value'] ?? 0) + $c->finalValue,
            ];

            $route = "{$c->originCity} → {$c->destinationCity}";
            $routeUsage[$route] = ($routeUsage[$route] ?? 0) + 1;
        }

        arsort($routeUsage);

        return [
            'total_value' => round($totalValue, 2),
            'contracts_count' => count($contracts),
            'carrier_ranking' => array_values($carrierRanking),
            'top_routes' => array_slice(
                array_map(fn($k, $v) => ['route' => $k, 'count' => $v], array_keys($routeUsage), $routeUsage),
                0, 10,
            ),
            'by_carrier' => array_map(fn($c) => [
                'carrier' => $c->carrierName,
                'count' => 1,
                'value' => $c->finalValue,
            ], $contracts),
        ];
    }

    public function carrierPerformance(string $carrierId, string $companyId): array
    {
        $contracts = $this->contractRepository->findByCompany($companyId, [
            'carrier_id' => $carrierId,
        ]);

        $totalContracts = count($contracts);
        $onTimeCount = 0;
        $lateCount = 0;
        $totalDelayDays = 0;
        $carrierName = '';

        foreach ($contracts as $contract) {
            $carrierName = $contract->carrierName;

            $events = $this->trackingRepository->findByContract($contract->id);
            $collectedDate = null;
            $deliveredDate = null;

            foreach ($events as $event) {
                if ($event->title === 'Coletado') {
                    $collectedDate = $event->date;
                }
                if ($event->title === 'Entregue') {
                    $deliveredDate = $event->date;
                }
            }

            if ($collectedDate && $deliveredDate) {
                $daysTaken = (int) (new \DateTime($deliveredDate))->diff(new \DateTime($collectedDate))->days;
            } else {
                $daysTaken = $contract->deadline;
                if ($contract->status === \App\Domain\Entities\Contract::STATUS_DELIVERED) {
                    $daysTaken = $contract->deadline;
                }
            }

            if ($daysTaken <= $contract->deadline) {
                $onTimeCount++;
            } else {
                $lateCount++;
                $totalDelayDays += ($daysTaken - $contract->deadline);
            }
        }

        $onTimeRate = $totalContracts > 0 ? round(($onTimeCount / $totalContracts) * 100, 2) : 0;
        $avgDelayDays = $lateCount > 0 ? round($totalDelayDays / $lateCount, 2) : 0;

        return [
            'carrier_id' => $carrierId,
            'carrier_name' => $carrierName,
            'total_contracts' => $totalContracts,
            'on_time_count' => $onTimeCount,
            'late_count' => $lateCount,
            'on_time_rate' => $onTimeRate,
            'avg_delay_days' => $avgDelayDays,
        ];
    }
}
