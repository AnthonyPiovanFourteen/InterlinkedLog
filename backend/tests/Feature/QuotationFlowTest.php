<?php

namespace Tests\Feature;

class QuotationFlowTest extends ApiTestCase
{
    public function test_quotation_for_seeded_route_returns_results_with_expected_values(): void
    {
        $quotation = $this->createQuotation();

        $this->assertSame('VALIDA', $quotation['status']);
        $this->assertCount(8, $quotation['results']);

        foreach ($quotation['results'] as $result) {
            $this->assertSame(142, $result['freight_value']);
            $this->assertSame(704.20, $result['fees']);
            $this->assertSame(846.20, $result['final_value']);
            $this->assertSame(2, $result['deadline']);
        }

        $bestPrice = collect($quotation['results'])->filter(fn($r) => ($r['best_price'] ?? false) === true);
        $this->assertCount(1, $bestPrice);
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