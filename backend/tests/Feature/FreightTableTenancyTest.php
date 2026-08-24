<?php

namespace Tests\Feature;

use App\Models\FreightTable;

class FreightTableTenancyTest extends ApiTestCase
{
    public function test_freight_table_is_invisible_to_other_tenants(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');
        $carrier = $this->createCarrier();
        $tableA = $this->createFreightTable($carrier['id']);

        $tablesB = $this->getJson('/api/v1/freight-tables', $this->authHeaders($tenantB['token']))
            ->assertOk()
            ->json('data');
        $this->assertSame([], $tablesB);

        $this->getJson('/api/v1/freight-tables/' . $tableA['id'], $this->authHeaders($tenantB['token']))
            ->assertStatus(404);

        $this->getJson('/api/v1/freight-tables/' . $tableA['id'], $this->authHeaders())
            ->assertOk();
    }

    public function test_quotation_uses_only_own_tenant_freight_tables(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');
        $carrier = $this->createCarrier(['name' => 'Transportadora Compartilhada']);

        $this->createFreightTable($carrier['id'], [
            'name' => 'Tabela Cara do Tenant A',
            'weight_ranges' => [['start' => 0, 'end' => 100, 'value' => 150]],
        ]);

        $this->createFreightTable($carrier['id'], [
            'name' => 'Tabela Barata do Tenant B',
            'weight_ranges' => [['start' => 0, 'end' => 100, 'value' => 1]],
        ], $tenantB['token']);

        $this->assertSame(
            2,
            FreightTable::withoutGlobalScopes()->where('carrier_id', $carrier['id'])->count(),
            'Cada tenant deveria ter a própria tabela para a mesma transportadora',
        );

        $quotationA = $this->createQuotation();
        $resultA = collect($quotationA['results'])->first(fn($r) => $r['carrier_id'] === $carrier['id']);
        $this->assertSame(150, $resultA['freight_value']);

        $quotationB = $this->createQuotation($tenantB['token']);
        $resultB = collect($quotationB['results'])->first(fn($r) => $r['carrier_id'] === $carrier['id']);
        $this->assertSame(1, $resultB['freight_value']);
    }
}