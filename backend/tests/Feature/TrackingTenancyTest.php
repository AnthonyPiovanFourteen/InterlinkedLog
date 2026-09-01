<?php

namespace Tests\Feature;

class TrackingTenancyTest extends ApiTestCase
{
    private function createContractedFlow(?string $token = null): array
    {
        $carrier = $this->createCarrier(['name' => 'Transportadora Rastreio'], $token);
        $this->createFreightTable($carrier['id'], token: $token);
        $quotation = $this->createQuotation($token);

        $response = $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $quotation['results'][0]['carrier_id'],
        ], $this->authHeaders($token));
        $response->assertStatus(201);

        return ['contract_id' => $response->json('data.id')];
    }

    public function test_tracking_of_other_tenant_contract_returns_404(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');
        $flowB = $this->createContractedFlow($tenantB['token']);

        $this->getJson('/api/v1/tracking/'.$flowB['contract_id'], $this->authHeaders())
            ->assertStatus(404);
    }

    public function test_tracking_of_own_contract_returns_events(): void
    {
        $flowA = $this->createContractedFlow();

        $events = $this->getJson('/api/v1/tracking/'.$flowA['contract_id'], $this->authHeaders())
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $events);
        $this->assertSame('Coleta Agendada', $events[0]['title']);
        $this->assertSame('Contratação confirmada', $events[0]['observation']);
    }

    public function test_tracking_of_inexistent_contract_returns_404(): void
    {
        $this->getJson('/api/v1/tracking/00000000-0000-0000-0000-000000000000', $this->authHeaders())
            ->assertStatus(404);
    }
}
