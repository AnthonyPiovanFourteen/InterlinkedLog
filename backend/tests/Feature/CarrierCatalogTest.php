<?php

namespace Tests\Feature;

use App\Models\Carrier;

class CarrierCatalogTest extends ApiTestCase
{
    public function test_carrier_created_via_api_with_freight_table_appears_in_quotation(): void
    {
        $carrier = $this->createCarrier(['name' => 'Transportadora Nova']);
        $this->assertSame('Ativa', Carrier::find($carrier['id'])->status);

        $this->createFreightTable($carrier['id'], [
            'name' => 'Tabela Nova',
            'weight_ranges' => [['start' => 0, 'end' => 100, 'value' => 150]],
        ]);

        $quotation = $this->createQuotation();

        $result = collect($quotation['results'])->first(fn($r) => $r['carrier_id'] === $carrier['id']);
        $this->assertNotNull($result, 'A transportadora criada via API deveria aparecer na cotação');
        $this->assertSame(150, $result['freight_value']);
    }

    public function test_carrier_created_by_other_tenant_is_visible_in_global_catalog(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');
        $carrierB = $this->createCarrier(
            ['name' => 'Transportadora do Tenant B'],
            $tenantB['token'],
        );

        $carriersA = $this->getJson('/api/v1/carriers', $this->authHeaders())
            ->assertOk()
            ->json('data');

        $this->assertTrue(collect($carriersA)->contains(fn($c) => $c['id'] === $carrierB['id']));
    }

    public function test_non_admin_user_cannot_create_or_alter_carriers(): void
    {
        $login = $this->postJson('/api/v1/login', [
            'email' => 'marina@interlinked.io',
            'password' => 'admin123',
        ]);
        $login->assertOk();
        $userToken = $login->json('token');

        $this->postJson('/api/v1/carriers', [
            'name' => 'Bloqueada',
            'cnpj' => '11.111.111/0001-11',
            'origin_city' => 'São Paulo',
            'origin_state' => 'SP',
        ], $this->authHeaders($userToken))->assertStatus(403);

        $carrier = $this->createCarrier();

        $this->patchJson('/api/v1/carriers/' . $carrier['id'], [
            'name' => 'Tentativa',
        ], $this->authHeaders($userToken))->assertStatus(403);

        $this->deleteJson('/api/v1/carriers/' . $carrier['id'], [], $this->authHeaders($userToken))
            ->assertStatus(403);

        $this->assertDatabaseHas('carriers', ['id' => $carrier['id']]);
    }
}