<?php

namespace Tests\Feature;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Quotation;
use App\Domain\Services\CepLookupService;
use App\Infrastructure\Gateways\RodonavesGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RodonavesGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://quotation-apigateway.rte.com.br/token';

    private const CITY_URL = 'https://dne-api.rte.com.br/api/cities/byzipcode*';

    private const QUOTE_URL = 'https://quotation-apigateway.rte.com.br/api/v1/gera-cotacao';

    private const PRAZO_URL = 'https://01wapi.rte.com.br/api/v1/prazo-entrega';

    private function gateway(): RodonavesGateway
    {
        $cep = $this->createMock(CepLookupService::class);
        $cep->method('lookup')->willReturn(['São Paulo', 'SP']);

        return new RodonavesGateway($cep);
    }

    private function carrier(string $name = 'Rodonaves Transportes'): Carrier
    {
        return new Carrier(
            id: 'carrier-rte', name: $name, cnpj: '44.914.548/0001-23',
            originCity: 'São Paulo', originState: 'SP', status: CarrierStatus::ATIVA,
        );
    }

    private function credential(): CarrierCredential
    {
        return new CarrierCredential(
            id: 'cred-rte', companyId: 'emp-1', carrierId: 'carrier-rte',
            gateway: 'rodonaves',
            secrets: [
                'username' => 'u', 'password' => 'p', 'auth_type' => 'DEV',
                'contact_name' => 'João Silva', 'contact_phone' => '1133334444',
                'contact_email' => 'contato@empresa.com',
            ],
        );
    }

    private function quotation(): Quotation
    {
        return Quotation::create(
            id: 'q1', companyId: 'emp-1', userId: 'u1',
            nfNumber: '000100', senderCnpj: '60.701.190/0001-04', receiverCnpj: '30.539.356/8867-00',
            originCep: '14010-000', destinationCep: '38000-000',
            originCity: 'São Paulo', destinationCity: 'Uberaba', destinationState: 'MG',
            weight: 19.50, boxes: 1, volume: 0.36, cargoValue: 10.50,
            validUntil: '2026-12-31',
        );
    }

    private function fakeAll(array $overrides = []): void
    {
        Http::fake(array_merge([
            self::TOKEN_URL => Http::response(['access_token' => 'tok', 'expires_in' => 600]),
            self::CITY_URL => Http::response(['Id' => 8997, 'Description' => 'RIBEIRAO PRETO']),
            self::QUOTE_URL => Http::response([
                'ProtocolId' => 12345, 'FreightValue' => '150.00',
                'Discount' => '10.00', 'ClassName' => 'Standard',
            ]),
            self::PRAZO_URL => Http::response(['DeliveryTime' => 4]),
        ], $overrides));
    }

    public function test_quote_subtracts_discount_and_fetches_deadline_separately(): void
    {
        $this->fakeAll();

        $quote = $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        $this->assertNotNull($quote);
        // FreightValue "150.00" menos Discount "10.00".
        $this->assertSame(140.0, $quote->finalValue);
        // O prazo vem de outra chamada, em outro host.
        $this->assertSame(4, $quote->deadline);
        $this->assertSame('12345', $quote->protocol);
        $this->assertSame('Standard', $quote->service);
    }

    public function test_quotation_payload_uses_rodonaves_city_ids(): void
    {
        $this->fakeAll();

        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'gera-cotacao')) {
                return false;
            }
            $body = $request->data();

            // Id do catálogo da Rodonaves, não nome de cidade.
            $this->assertSame(8997, $body['OriginCityId']);
            $this->assertSame(8997, $body['DestinationCityId']);
            $this->assertSame('14010000', $body['OriginZipCode']);
            $this->assertSame(19.50, $body['TotalWeight']);
            $this->assertSame('João Silva', $body['ContactName']);
            $this->assertSame(1, $body['Packs'][0]['AmountPackages']);

            return true;
        });
    }

    public function test_deadline_request_sends_city_uppercase_without_accents(): void
    {
        $this->fakeAll();

        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'prazo-entrega')) {
                return false;
            }
            $body = $request->data();

            // A API rejeita "São Paulo"; exige "SAO PAULO".
            $this->assertSame('SAO PAULO', $body['OriginCityDescription']);
            $this->assertSame('SP', $body['OriginUFDescription']);
            $this->assertSame('UBERABA', $body['DestinationCityDescription']);
            $this->assertSame('MG', $body['DestinationUFDescription']);

            return true;
        });
    }

    public function test_unknown_city_means_route_not_served(): void
    {
        $this->fakeAll([self::CITY_URL => Http::response('', 400)]);

        $this->assertNull(
            $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential())
        );
    }

    public function test_token_and_city_are_reused_from_cache(): void
    {
        $this->fakeAll();

        $gw = $this->gateway();
        $gw->quote($this->quotation(), $this->carrier(), $this->credential());
        $gw->quote($this->quotation(), $this->carrier(), $this->credential());

        // 1 token + 2 cidades + 1 cotação + 1 prazo; a 2ª cotação vem toda do cache.
        Http::assertSentCount(5);
    }

    public function test_supports_rodonaves_and_rte(): void
    {
        $gw = $this->gateway();
        $this->assertTrue($gw->supports($this->carrier()));
        $this->assertTrue($gw->supports($this->carrier('RTE Rodonaves')));
        $this->assertFalse($gw->supports($this->carrier('Jadlog')));
    }
}
