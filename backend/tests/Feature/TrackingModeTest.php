<?php

namespace Tests\Feature;

use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\Contract;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Domain\Repositories\ContractRepository;
use App\Jobs\SyncContractTrackingJob;
use App\Models\Carrier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrackingModeTest extends ApiTestCase
{
    private function jadlogCarrier(): Carrier
    {
        return Carrier::create([
            'id' => Str::orderedUuid()->toString(),
            'name' => 'Jadlog',
            'cnpj' => '04.884.082/0001-35',
            'origin_city' => 'São Paulo',
            'origin_uf' => 'SP',
            'status' => 'Ativa',
        ]);
    }

    private function credentialFor(Carrier $carrier): void
    {
        app(CarrierCredentialRepository::class)->save(new CarrierCredential(
            id: Str::orderedUuid()->toString(),
            companyId: $this->adminCompanyId,
            carrierId: $carrier->id,
            gateway: 'jadlog',
            secrets: ['token' => 'tok', 'modalidade' => '3', 'cubage_factor' => '300'],
        ));
    }

    /** Contrata usando a primeira transportadora dos resultados da cotação. */
    private function contract(): array
    {
        $quotation = $this->createQuotation();

        return $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $quotation['results'][0]['carrier_id'],
        ], $this->authHeaders())->json('data');
    }

    public function test_contract_without_credential_is_manual(): void
    {
        $contract = app(ContractRepository::class)->findById($this->contract()['id']);

        $this->assertSame(Contract::TRACKING_MANUAL, $contract->trackingMode);
        $this->assertFalse($contract->isAutoTracked());
    }

    public function test_manual_contract_accepts_events(): void
    {
        $id = $this->contract()['id'];

        $this->postJson("/api/v1/tracking/{$id}/events", [
            'title' => 'Em Trânsito', 'date' => '2026-09-28', 'time' => '14:30',
        ], $this->authHeaders())->assertStatus(201);
    }

    public function test_auto_contract_refuses_manual_events(): void
    {
        $carrier = $this->jadlogCarrier();
        $this->credentialFor($carrier);
        Http::fake(['*' => Http::response(['frete' => [['vltotal' => 50.0, 'prazo' => 3]]])]);

        $quotation = $this->createQuotation();
        $apiResult = collect($this->getJson("/api/v1/quotations/{$quotation['id']}", $this->authHeaders())
            ->json('data.results'))->firstWhere('gateway', 'jadlog');

        $contract = $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $apiResult['carrier_id'],
        ], $this->authHeaders())->json('data');

        $stored = app(ContractRepository::class)->findById($contract['id']);
        $this->assertSame(Contract::TRACKING_AUTO, $stored->trackingMode);
        $this->assertSame('jadlog', $stored->trackingGateway);

        // Automático e manual não se misturam.
        $this->postJson("/api/v1/tracking/{$contract['id']}/events", [
            'title' => 'Em Trânsito', 'date' => '2026-09-28', 'time' => '14:30',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Rastreio automático: use observações em vez de criar eventos');
    }

    public function test_observation_is_allowed_on_any_mode(): void
    {
        $id = $this->contract()['id'];

        $eventId = collect($this->getJson("/api/v1/tracking/{$id}", $this->authHeaders())->json('data'))
            ->first()['id'];

        $this->patchJson("/api/v1/tracking/{$id}/events/{$eventId}", [
            'observation' => 'Cliente avisado por telefone',
        ], $this->authHeaders())->assertOk();

        $events = $this->getJson("/api/v1/tracking/{$id}", $this->authHeaders())->json('data');
        $this->assertSame('Cliente avisado por telefone', collect($events)->firstWhere('id', $eventId)['observation']);
    }

    public function test_sync_imports_events_and_is_idempotent(): void
    {
        $carrier = $this->jadlogCarrier();
        $this->credentialFor($carrier);

        $contract = app(ContractRepository::class)->findById($this->contract()['id']);
        // Força o modo automático para exercitar o sincronismo neste contrato.
        app(ContractRepository::class)->save(new Contract(
            id: $contract->id, companyId: $contract->companyId, quotationId: $contract->quotationId,
            nfNumber: $contract->nfNumber, carrierId: $carrier->id, carrierName: 'Jadlog',
            originCity: $contract->originCity, destinationCity: $contract->destinationCity,
            destinationState: $contract->destinationState, freightValue: $contract->freightValue,
            fees: $contract->fees, finalValue: $contract->finalValue, deadline: $contract->deadline,
            status: $contract->status, documentNumber: $contract->documentNumber,
            trackingMode: Contract::TRACKING_AUTO, trackingGateway: 'jadlog',
        ));

        Http::fake(['https://prd-traffic.jadlogtech.com.br/*' => Http::response(['consulta' => [[
            'tracking' => ['eventos' => [
                ['data' => '2026-03-22 15:12:06', 'status' => 'COLETA SOLICITADA', 'unidade' => 'PA EXTREMA'],
                ['data' => '2026-03-22 15:37:42', 'status' => 'EMISSAO', 'unidade' => 'PA EXTREMA'],
            ]],
        ]]])]);

        $before = count($this->getJson("/api/v1/tracking/{$contract->id}", $this->authHeaders())->json('data'));

        SyncContractTrackingJob::dispatchSync($this->adminCompanyId, $contract->id);
        $after = $this->getJson("/api/v1/tracking/{$contract->id}", $this->authHeaders())->json('data');

        $this->assertCount($before + 2, $after);
        $this->assertSame('COLETA SOLICITADA', collect($after)->firstWhere('title', 'COLETA SOLICITADA')['title']);

        // Rodar de novo não duplica: dedupe pelo external_id.
        SyncContractTrackingJob::dispatchSync($this->adminCompanyId, $contract->id);
        $this->assertCount(
            $before + 2,
            $this->getJson("/api/v1/tracking/{$contract->id}", $this->authHeaders())->json('data')
        );
    }
}
