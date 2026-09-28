<?php

namespace Tests\Feature;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Infrastructure\Gateways\BraspressGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BraspressGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.braspress.com/v1/cotacao/calcular/json';

    private function gateway(): BraspressGateway
    {
        return new BraspressGateway;
    }

    private function carrier(string $name = 'Braspress Transportes'): Carrier
    {
        return new Carrier(
            id: 'carrier-1', name: $name, cnpj: '48.740.351/0001-52',
            originCity: 'São Paulo', originState: 'SP', status: CarrierStatus::ATIVA,
        );
    }

    private function credential(): CarrierCredential
    {
        return new CarrierCredential(
            id: 'cred-1', companyId: 'emp-1', carrierId: 'carrier-1',
            gateway: 'braspress', secrets: ['username' => 'u', 'password' => 'p'],
        );
    }

    private function quotation(): Quotation
    {
        return Quotation::create(
            id: 'q1', companyId: 'emp-1', userId: 'u1',
            nfNumber: '000100', senderCnpj: '60.701.190/0001-04', receiverCnpj: '30.539.356/8867-00',
            originCep: '01000-000', destinationCep: '86020-000',
            originCity: 'São Paulo', destinationCity: 'Londrina', destinationState: 'PR',
            weight: 50.55, boxes: 10, volume: 2.0, cargoValue: 100.00,
            validUntil: '2026-12-31',
        );
    }

    public function test_quote_maps_braspress_response(): void
    {
        Http::fake([self::URL => Http::response(['id' => 987654, 'prazo' => 4, 'totalFrete' => 321.45])]);

        $quote = $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        $this->assertNotNull($quote);
        // A Braspress devolve só o total: frete recebe o total, taxas ficam zero
        // e o breakdown vazio — não se inventa composição.
        $this->assertSame(321.45, $quote->freightValue);
        $this->assertSame(0.0, $quote->fees);
        $this->assertSame(321.45, $quote->finalValue);
        $this->assertSame(4, $quote->deadline);
        $this->assertSame([], $quote->feesBreakdown);
        $this->assertSame('987654', $quote->protocol);
        $this->assertSame('api', $quote->toResult()['source']);
    }

    public function test_payload_uses_braspress_field_names_and_digits_only(): void
    {
        Http::fake([self::URL => Http::response(['id' => 1, 'prazo' => 3, 'totalFrete' => 100.0])]);

        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            $this->assertSame(60701190000104, $body['cnpjRemetente']);
            $this->assertSame(1000000, $body['cepOrigem']);
            $this->assertSame(86020000, $body['cepDestino']);
            $this->assertSame('R', $body['modal']);
            $this->assertSame('1', $body['tipoFrete']);
            $this->assertSame(50.55, $body['peso']);
            $this->assertSame(10, $body['volumes']);
            // Cubo derivado: 10 volumes de 0,2 m³ → lado = 0,2^(1/3) ≈ 0,585
            $this->assertEqualsWithDelta(0.585, $body['cubagem'][0]['altura'], 0.002);

            return true;
        });
    }

    public function test_unserved_route_returns_null(): void
    {
        Http::fake([self::URL => Http::response('', 404)]);

        $this->assertNull(
            $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential())
        );
    }

    public function test_server_error_raises_gateway_exception(): void
    {
        Http::fake([self::URL => Http::response('', 500)]);

        $this->expectException(CarrierGatewayException::class);
        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());
    }

    public function test_response_without_value_raises_gateway_exception(): void
    {
        Http::fake([self::URL => Http::response(['id' => 1, 'prazo' => 0, 'totalFrete' => 0])]);

        $this->expectException(CarrierGatewayException::class);
        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());
    }

    public function test_second_identical_quote_comes_from_cache(): void
    {
        Http::fake([self::URL => Http::response(['id' => 1, 'prazo' => 3, 'totalFrete' => 200.0])]);

        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());
        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        Http::assertSentCount(1);
    }

    public function test_supports_only_braspress(): void
    {
        $this->assertTrue($this->gateway()->supports($this->carrier()));
        $this->assertFalse($this->gateway()->supports($this->carrier('Jadlog')));
    }
}
