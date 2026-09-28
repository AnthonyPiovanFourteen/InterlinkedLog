<?php

namespace Tests\Feature;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Infrastructure\Gateways\JamefGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JamefGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN = 'https://api.jamef.com.br/auth/v1/login';

    private const FILIAIS = 'https://api.jamef.com.br/infraestrutura/v1/lista-filiais*';

    private const COTACAO = 'https://api.jamef.com.br/calculo-frete/v1/cotacao';

    private function carrier(string $name = 'Jamef'): Carrier
    {
        return new Carrier(
            id: 'carrier-jamef', name: $name, cnpj: '22.333.444/0001-55',
            originCity: 'Belo Horizonte', originState: 'MG', status: CarrierStatus::ATIVA,
        );
    }

    private function credential(): CarrierCredential
    {
        return new CarrierCredential(
            id: 'cred-jamef', companyId: 'emp-1', carrierId: 'carrier-jamef',
            gateway: 'jamef',
            secrets: ['username' => 'u', 'password' => 'p', 'documento_devedor' => '12.345.678/0001-99'],
        );
    }

    private function quotation(): Quotation
    {
        return Quotation::create(
            id: 'q1', companyId: 'emp-1', userId: 'u1',
            nfNumber: '000100', senderCnpj: '60.701.190/0001-04', receiverCnpj: '30.539.356/8867-00',
            originCep: '01000-000', destinationCep: '86020-000',
            originCity: 'São Paulo', destinationCity: 'Londrina', destinationState: 'PR',
            weight: 45, boxes: 10, volume: 0.15, cargoValue: 5000,
            validUntil: '2026-12-31',
        );
    }

    private function fakeAll(array $overrides = []): void
    {
        Http::fake(array_merge([
            self::LOGIN => Http::response(['dado' => [['accessToken' => 'tok', 'expiresIn' => 3600]]]),
            self::FILIAIS => Http::response(['dado' => [['codigoFilial' => '018']]]),
            self::COTACAO => Http::response(['dado' => [[
                'total' => 275.40,
                'previsaoEntrega' => now()->addDays(4)->format('d/m/Y'),
            ]]]),
        ], $overrides));
    }

    public function test_quote_converts_delivery_date_into_days(): void
    {
        $this->fakeAll();

        $quote = (new JamefGateway)->quote($this->quotation(), $this->carrier(), $this->credential());

        $this->assertNotNull($quote);
        $this->assertSame(275.40, $quote->finalValue);
        // A Jamef devolve a DATA prevista; o modelo interno guarda dias.
        $this->assertSame(4, $quote->deadline);
        $this->assertSame(0.0, $quote->fees);
    }

    public function test_payload_uses_jamef_field_names_and_resolved_branch(): void
    {
        $this->fakeAll();

        (new JamefGateway)->quote($this->quotation(), $this->carrier(), $this->credential());

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'calculo-frete')) {
                return false;
            }
            $b = $request->data();

            $this->assertSame('1', $b['tipoTransporte']);
            $this->assertSame('12345678000199', $b['documentoDevedor']);
            $this->assertSame('01000000', $b['cepOrigem']);
            $this->assertSame('86020000', $b['cepDestino']);
            $this->assertSame(10, $b['quantidadeVolume']);
            $this->assertSame(45.0, $b['pesoMercadoria']);
            $this->assertSame(5000.0, $b['valorNotaFiscal']);
            $this->assertSame(0.15, $b['metragemCubica']);
            // Filial resolvida pelo CEP antes de cotar.
            $this->assertSame('018', $b['filialOrigem']);

            return true;
        });
    }

    public function test_token_and_branch_are_reused_from_cache(): void
    {
        $this->fakeAll();

        $gw = new JamefGateway;
        $gw->quote($this->quotation(), $this->carrier(), $this->credential());
        $gw->quote($this->quotation(), $this->carrier(), $this->credential());

        // 1 login + 1 filial + 1 cotação; a segunda vem toda do cache.
        Http::assertSentCount(3);
    }

    public function test_empty_dado_means_route_not_served(): void
    {
        $this->fakeAll([self::COTACAO => Http::response(['dado' => []])]);

        $this->assertNull(
            (new JamefGateway)->quote($this->quotation(), $this->carrier(), $this->credential())
        );
    }

    public function test_auth_failure_raises_gateway_exception(): void
    {
        $this->fakeAll([self::LOGIN => Http::response('', 401)]);

        $this->expectException(CarrierGatewayException::class);
        (new JamefGateway)->quote($this->quotation(), $this->carrier(), $this->credential());
    }

    public function test_quote_without_delivery_estimate_raises(): void
    {
        $this->fakeAll([self::COTACAO => Http::response(['dado' => [['total' => 100.0]]])]);

        $this->expectException(CarrierGatewayException::class);
        (new JamefGateway)->quote($this->quotation(), $this->carrier(), $this->credential());
    }

    public function test_requires_payer_document_as_secret(): void
    {
        $this->assertContains('documento_devedor', (new JamefGateway)->requiredSecrets());
    }

    public function test_supports_only_jamef(): void
    {
        $gw = new JamefGateway;
        $this->assertTrue($gw->supports($this->carrier()));
        $this->assertFalse($gw->supports($this->carrier('Braspress')));
    }
}
