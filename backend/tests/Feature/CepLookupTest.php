<?php

namespace Tests\Feature;

use App\Domain\Exceptions\CepLookupUnavailableException;
use App\Domain\Services\CepLookupService;
use Illuminate\Support\Facades\Http;

class CepLookupTest extends ApiTestCase
{
    public function test_mapped_cep_resolves_without_http(): void
    {
        Http::fake();

        $this->assertSame(['São Paulo', 'SP'], app(CepLookupService::class)->lookup('01000-000'));
        Http::assertNothingSent();
    }

    public function test_unmapped_cep_resolves_through_viacep(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'localidade' => 'Americana',
                'uf' => 'SP',
            ], 200),
        ]);

        $this->assertSame(['Americana', 'SP'], app(CepLookupService::class)->lookup('13465-000'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '13465000'));
    }

    public function test_unmapped_unknown_cep_returns_null(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true], 200),
        ]);

        $this->assertNull(app(CepLookupService::class)->lookup('99999-999'));
    }

    public function test_unavailable_viacep_throws_explicit_exception(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response('', 500),
        ]);

        $this->expectException(CepLookupUnavailableException::class);
        app(CepLookupService::class)->lookup('13465-000');
    }

    public function test_second_lookup_comes_from_cache_without_new_request(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'localidade' => 'Americana',
                'uf' => 'SP',
            ], 200),
        ]);

        $service = app(CepLookupService::class);
        $service->lookup('13465-000');
        $service->lookup('13465-000');

        Http::assertSentCount(1);
    }

    public function test_quotation_with_unmapped_unknown_cep_returns_422(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true], 200),
        ]);

        $this->postJson('/api/v1/quotations', [
            'nf_number' => '000100',
            'sender_cnpj' => '12.345.678/0001-99',
            'receiver_cnpj' => '98.765.432/0001-88',
            'origin_cep' => '01000-000',
            'destination_cep' => '99999-999',
            'weight' => 45,
            'boxes' => 10,
            'volume' => 0.15,
            'cargo_value' => 5000,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'CEP não encontrado: 99999-999');
    }

    public function test_quotation_with_unavailable_viacep_returns_503(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response('', 500),
        ]);

        $this->postJson('/api/v1/quotations', [
            'nf_number' => '000100',
            'sender_cnpj' => '12.345.678/0001-99',
            'receiver_cnpj' => '98.765.432/0001-88',
            'origin_cep' => '01000-000',
            'destination_cep' => '13465-000',
            'weight' => 45,
            'boxes' => 10,
            'volume' => 0.15,
            'cargo_value' => 5000,
        ], $this->authHeaders())
            ->assertStatus(503)
            ->assertJsonPath('message', 'Serviço de CEP indisponível no momento, tente novamente');
    }
}
