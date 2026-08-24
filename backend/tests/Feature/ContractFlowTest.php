<?php

namespace Tests\Feature;

use App\Models\Contract;

class ContractFlowTest extends ApiTestCase
{
    public function test_contract_from_valid_quotation_creates_contract_updates_status_and_generates_tracking_event(): void
    {
        $quotation = $this->createQuotation();
        $carrierId = $quotation['results'][0]['carrier_id'];

        $response = $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $carrierId,
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'Agendado')
            ->assertJsonPath('data.nf_number', '000100');
        $this->assertMatchesRegularExpression('/^CT-e \d{12}$/', $response->json('data.document_number'));

        $contractId = $response->json('data.id');
        $this->assertDatabaseHas('contracts', [
            'id' => $contractId,
            'quotation_id' => $quotation['id'],
            'company_id' => $this->adminCompanyId,
            'status' => 'Agendado',
        ]);
        $this->assertSame(1, Contract::where('quotation_id', $quotation['id'])->count());

        $this->getJson('/api/v1/quotations/' . $quotation['id'], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'CONTRATADA');

        $events = $this->getJson('/api/v1/tracking/' . $contractId, $this->authHeaders())
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $events);
        $this->assertSame('Coleta Agendada', $events[0]['title']);
        $this->assertSame('Contratação confirmada', $events[0]['observation']);
        $this->assertSame(now()->format('Y-m-d'), $events[0]['date']);
    }

    public function test_contract_with_quotation_from_another_tenant_returns_404(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');
        $carrierB = $this->postJson('/api/v1/carriers', [
            'name' => 'Transportadora do Tenant B',
            'cnpj' => '11.111.111/0001-11',
            'origin_city' => 'São Paulo',
            'origin_state' => 'SP',
        ], $this->authHeaders($tenantB['token']))->assertStatus(201)->json('data');

        $this->createFreightTable($carrierB['id'], token: $tenantB['token']);
        $quotationB = $this->createQuotation($tenantB['token']);
        $this->assertNotEmpty($quotationB['results']);

        $response = $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotationB['id'],
            'carrier_id' => $quotationB['results'][0]['carrier_id'],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $this->assertSame(0, Contract::where('quotation_id', $quotationB['id'])->count());
    }

    public function test_contract_with_already_contracted_quotation_returns_422(): void
    {
        $quotation = $this->createQuotation();
        $carrierId = $quotation['results'][0]['carrier_id'];

        $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $carrierId,
        ], $this->authHeaders())->assertStatus(201);

        $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $carrierId,
        ], $this->authHeaders())->assertStatus(422);

        $this->assertSame(1, Contract::where('quotation_id', $quotation['id'])->count());
    }

    public function test_contracts_index_lists_only_contracts_of_authenticated_tenant(): void
    {
        $contractsA = $this->getJson('/api/v1/contracts', $this->authHeaders())
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $contractsA);
        $this->assertSame('OC-' . date('Ymd') . '-0001', $contractsA[0]['document_number']);

        $tenantB = $this->createTenant('b@interlinked.io');
        $contractsB = $this->getJson('/api/v1/contracts', $this->authHeaders($tenantB['token']))
            ->assertOk()
            ->json('data');

        $this->assertSame([], $contractsB);
    }

    public function test_contract_with_inexistent_carrier_in_results_returns_422(): void
    {
        $quotation = $this->createQuotation();

        $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => '00000000-0000-0000-0000-000000000000',
        ], $this->authHeaders())->assertStatus(422);
    }
}