<?php

namespace Tests\Feature;

use App\Models\FreightTableRoute;
use App\Models\FreightTableWeightRange;

class QuotationFlowTest extends ApiTestCase
{
    public function test_quotation_for_seeded_route_returns_results_with_expected_values(): void
    {
        $quotation = $this->createQuotation();

        $this->assertSame('VALIDA', $quotation['status']);
        $this->assertCount(8, $quotation['results']);

        foreach ($quotation['results'] as $result) {
            $this->assertSame(142, $result['freight_value']);
            $this->assertSame(698.90, $result['fees']);
            $this->assertSame(840.90, $result['final_value']);
            $this->assertSame(3, $result['deadline']);
        }

        $bestPrice = collect($quotation['results'])->filter(fn ($r) => ($r['best_price'] ?? false) === true);
        $this->assertCount(1, $bestPrice);
    }

    public function test_quotation_uses_weight_ranges_and_deadline_of_matched_route(): void
    {
        $carrier = $this->createCarrier(['name' => 'Transportadora MultiRota']);
        $this->createFreightTable($carrier['id'], [
            'name' => 'Tabela MultiRota',
            'routes' => [
                ['city' => 'Londrina', 'state' => 'PR', 'deadline' => 2],
                ['city' => 'Curitiba', 'state' => 'PR', 'deadline' => 1],
            ],
            'weight_ranges' => [
                ['start' => 0, 'end' => 30, 'value' => 100],
                ['start' => 31, 'end' => 100, 'value' => 200],
            ],
        ]);

        $routes = FreightTableRoute::whereHas(
            'freightTable',
            fn ($q) => $q->where('carrier_id', $carrier['id']),
        )->get();

        $londrina = $routes->firstWhere('destination_city', 'Londrina');
        $curitiba = $routes->firstWhere('destination_city', 'Curitiba');

        $londrinaRanges = FreightTableWeightRange::where('freight_table_route_id', $londrina->id)
            ->orderBy('min_weight')->get();
        $londrinaRanges[1]->update(['deadline_days' => 5]);

        $curitibaRanges = FreightTableWeightRange::where('freight_table_route_id', $curitiba->id)
            ->orderBy('min_weight')->get();
        $curitibaRanges[0]->update(['freight_value' => 300, 'deadline_days' => 1]);
        $curitibaRanges[1]->update(['freight_value' => 600, 'deadline_days' => 9]);

        $toLondrina = $this->createQuotation(null, ['destination_cep' => '86020-000']);
        $londrinaResult = collect($toLondrina['results'])->first(fn ($r) => $r['carrier_id'] === $carrier['id']);
        $this->assertSame(200, $londrinaResult['freight_value']);
        $this->assertSame(5, $londrinaResult['deadline']);

        $toCuritiba = $this->createQuotation(null, ['destination_cep' => '80000-000']);
        $curitibaResult = collect($toCuritiba['results'])->first(fn ($r) => $r['carrier_id'] === $carrier['id']);
        $this->assertSame(600, $curitibaResult['freight_value']);
        $this->assertSame(9, $curitibaResult['deadline']);
    }

    public function test_percentage_fee_is_charged_as_percentage_only(): void
    {
        $carrier = $this->createCarrier(['name' => 'Transportadora Percentual']);
        $this->createFreightTable($carrier['id'], [
            'name' => 'Tabela Percentual',
            'fees' => [
                ['type' => 'ad_valorem', 'value' => 10, 'percentage' => 10],
                ['type' => 'despacho', 'value' => 25, 'percentage' => 0],
            ],
        ]);

        $quotation = $this->createQuotation(null, ['cargo_value' => 1000]);
        $result = collect($quotation['results'])->first(fn ($r) => $r['carrier_id'] === $carrier['id']);

        $this->assertSame(125, $result['fees']);

        $adValorem = collect($result['fees_breakdown'])->first(fn ($b) => $b['type'] === 'ad_valorem');
        $this->assertSame(100, $adValorem['amount']);
    }

    public function test_quotation_for_route_without_freight_table_returns_no_results(): void
    {
        $quotation = $this->createQuotation(null, [
            'origin_cep' => '70000-000',
            'destination_cep' => '69000-000',
        ]);

        $this->assertSame('VALIDA', $quotation['status']);
        $this->assertSame([], $quotation['results']);
    }
}
