<?php

namespace Tests\Feature;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Infrastructure\Gateways\JadlogGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JadlogGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.jadlog.com.br/embarcador/api/frete/valor';

    private function carrier(string $name = 'Jadlog'): Carrier
    {
        return new Carrier(
            id: 'carrier-jad', name: $name, cnpj: '04.884.082/0001-35',
            originCity: 'São Paulo', originState: 'SP', status: CarrierStatus::ATIVA,
        );
    }

    private function credential(array $extra = []): CarrierCredential
    {
        return new CarrierCredential(
            id: 'cred-jad', companyId: 'emp-1', carrierId: 'carrier-jad',
            gateway: 'jadlog',
            secrets: array_merge([
                'token' => 'tok-123', 'modalidade' => '3', 'cubage_factor' => '300',
            ], $extra),
        );
    }

    /** 10 kg reais, 0,2 m³ → cubado 60 kg com fator 300: deve vencer o cubado. */
    private function quotation(float $weight = 10.0, float $volume = 0.2): Quotation
    {
        return Quotation::create(
            id: 'q1', companyId: 'emp-1', userId: 'u1',
            nfNumber: '000100', senderCnpj: '60.701.190/0001-04', receiverCnpj: '30.539.356/8867-00',
            originCep: '01000-000', destinationCep: '86020-000',
            originCity: 'São Paulo', destinationCity: 'Londrina', destinationState: 'PR',
            weight: $weight, boxes: 1, volume: $volume, cargoValue: 55.00,
            validUntil: '2026-12-31',
        );
    }

    public function test_quote_maps_vltotal_and_prazo(): void
    {
        Http::fake([self::URL => Http::response(['frete' => [['vltotal' => 7.50, 'prazo' => 5]]])]);

        $quote = (new JadlogGateway)->quote($this->quotation(), $this->carrier(), $this->credential());

        $this->assertNotNull($quote);
        $this->assertSame(7.50, $quote->finalValue);
        $this->assertSame(5, $quote->deadline);
        // Como a Braspress, a Jadlog não discrimina taxas.
        $this->assertSame(0.0, $quote->fees);
        $this->assertSame([], $quote->feesBreakdown);
    }

    public function test_sends_greater_of_real_and_cubed_weight(): void
    {
        Http::fake([self::URL => Http::response(['frete' => [['vltotal' => 10.0, 'prazo' => 3]]])]);

        (new JadlogGateway)->quote($this->quotation(10.0, 0.2), $this->carrier(), $this->credential());

        Http::assertSent(function (Request $request) {
            $item = $request->data()['frete'][0];
            // A doc da Jadlog exige o maior entre real (10) e cubado (0,2 × 300 = 60).
            $this->assertSame(60.0, $item['peso']);
            $this->assertSame('01000000', $item['cepori']);
            $this->assertSame('86020000', $item['cepdes']);
            $this->assertSame(3, $item['modalidade']);
            $this->assertSame('D', $item['tpentrega']);
            $this->assertSame(55.00, $item['vldeclarado']);

            return true;
        });
    }

    public function test_real_weight_wins_when_greater_than_cubed(): void
    {
        Http::fake([self::URL => Http::response(['frete' => [['vltotal' => 10.0, 'prazo' => 3]]])]);

        (new JadlogGateway)->quote($this->quotation(120.0, 0.1), $this->carrier(), $this->credential());

        Http::assertSent(fn (Request $r) => $r->data()['frete'][0]['peso'] === 120.0);
    }

    public function test_token_goes_raw_without_bearer(): void
    {
        Http::fake([self::URL => Http::response(['frete' => [['vltotal' => 10.0, 'prazo' => 3]]])]);

        (new JadlogGateway)->quote($this->quotation(), $this->carrier(), $this->credential());

        Http::assertSent(fn (Request $r) => $r->header('Authorization')[0] === 'tok-123');
    }

    public function test_missing_cubage_factor_raises(): void
    {
        $this->expectException(CarrierGatewayException::class);
        $this->expectExceptionMessageMatches('/cubage_factor/');

        (new JadlogGateway)->quote($this->quotation(), $this->carrier(), $this->credential(['cubage_factor' => null]));
    }

    public function test_item_with_error_means_route_not_served(): void
    {
        Http::fake([self::URL => Http::response(['frete' => [['error' => 'rota não atendida']]])]);

        $this->assertNull(
            (new JadlogGateway)->quote($this->quotation(), $this->carrier(), $this->credential())
        );
    }

    public function test_supports_only_jadlog(): void
    {
        $gw = new JadlogGateway;
        $this->assertTrue($gw->supports($this->carrier()));
        $this->assertFalse($gw->supports($this->carrier('Braspress')));
    }
}
