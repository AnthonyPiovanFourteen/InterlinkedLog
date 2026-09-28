<?php

namespace Tests\Feature;

use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\QuotationGatewayAttempt;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Jobs\QuoteCarrierJob;
use App\Models\Carrier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class ProgressiveQuotationTest extends ApiTestCase
{
    private function braspressCarrier(): Carrier
    {
        return Carrier::create([
            'id' => Str::orderedUuid()->toString(),
            'name' => 'Braspress Transportes',
            'cnpj' => '48.740.351/0001-52',
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
            gateway: 'braspress',
            secrets: ['username' => 'u', 'password' => 'p'],
        ));
    }

    public function test_without_credentials_no_job_is_dispatched(): void
    {
        Queue::fake();

        $this->createQuotation();

        Queue::assertNothingPushed();
    }

    public function test_one_job_per_gateway_with_company_id_in_payload(): void
    {
        Queue::fake();
        $this->credentialFor($this->braspressCarrier());

        $quotation = $this->createQuotation();

        Queue::assertPushed(QuoteCarrierJob::class, function (QuoteCarrierJob $job) use ($quotation) {
            // O companyId viaja no payload: o worker não tem request HTTP.
            $this->assertSame($this->adminCompanyId, $job->companyId);
            $this->assertSame($quotation['id'], $job->quotationId);
            $this->assertSame('braspress', $job->gatewayName);

            return true;
        });
    }

    public function test_pending_carrier_is_visible_before_the_api_answers(): void
    {
        Queue::fake();
        $this->credentialFor($this->braspressCarrier());

        $quotation = $this->createQuotation();

        $response = $this->getJson("/api/v1/quotations/{$quotation['id']}", $this->authHeaders());

        $response->assertOk();
        $this->assertSame(['braspress'], $response->json('data.carriers.pending'));
    }

    public function test_api_result_is_appended_to_table_results(): void
    {
        $carrier = $this->braspressCarrier();
        $this->credentialFor($carrier);
        Http::fake(['https://api.braspress.com/*' => Http::response(['id' => 99, 'prazo' => 2, 'totalFrete' => 111.11])]);

        // QUEUE_CONNECTION=sync nos testes: o job roda inline.
        $quotation = $this->createQuotation();

        $response = $this->getJson("/api/v1/quotations/{$quotation['id']}", $this->authHeaders());
        $results = collect($response->json('data.results'));

        $fromApi = $results->firstWhere('source', 'api');
        $this->assertNotNull($fromApi, 'resultado de API não foi acrescentado');
        $this->assertSame(111.11, $fromApi['final_value']);
        $this->assertSame('braspress', $fromApi['gateway']);
        // Os resultados de tabela continuam lá.
        $this->assertGreaterThan(0, $results->where('source', 'tabela')->count());
        $this->assertSame([], $response->json('data.carriers.pending'));
    }

    public function test_unserved_route_is_recorded_without_error(): void
    {
        $carrier = $this->braspressCarrier();
        $this->credentialFor($carrier);
        Http::fake(['https://api.braspress.com/*' => Http::response('', 404)]);

        $quotation = $this->createQuotation();

        $attempts = collect($this->getJson("/api/v1/quotations/{$quotation['id']}", $this->authHeaders())
            ->json('data.carriers.attempts'));

        $this->assertSame(QuotationGatewayAttempt::NOT_SERVED, $attempts->firstWhere('gateway', 'braspress')['status']);
        $this->assertNull($attempts->firstWhere('gateway', 'braspress')['message']);
    }

    public function test_carrier_failure_is_reported_without_breaking_the_quotation(): void
    {
        $carrier = $this->braspressCarrier();
        $this->credentialFor($carrier);
        Http::fake(['https://api.braspress.com/*' => Http::response('', 503)]);

        $quotation = $this->createQuotation();

        $response = $this->getJson("/api/v1/quotations/{$quotation['id']}", $this->authHeaders());
        $attempt = collect($response->json('data.carriers.attempts'))->firstWhere('gateway', 'braspress');

        $this->assertSame(QuotationGatewayAttempt::UNAVAILABLE, $attempt['status']);
        $this->assertStringContainsString('503', $attempt['message']);
        // A cotação segue válida com os resultados de tabela.
        $this->assertGreaterThan(0, count($response->json('data.results')));
    }

    public function test_ranking_marks_best_price_deadline_and_overall(): void
    {
        $response = $this->getJson('/api/v1/quotations/'.$this->createQuotation()['id'], $this->authHeaders());
        $results = collect($response->json('data.results'));

        $this->assertSame(1, $results->where('best_price', true)->count());
        $this->assertSame(1, $results->where('best_deadline', true)->count());
        $this->assertSame(1, $results->where('best_cost_benefit', true)->count());
    }
}
