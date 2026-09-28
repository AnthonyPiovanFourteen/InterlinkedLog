<?php

namespace Tests\Feature;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Domain\Services\CepLookupService;
use App\Infrastructure\Gateways\LoggiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoggiGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://api.loggi.com/v2/oauth2/token';

    private const QUOTE_URL = 'https://api.loggi.com/v1/companies/777/quotations';

    private function gateway(): LoggiGateway
    {
        $cep = $this->createMock(CepLookupService::class);
        $cep->method('lookupAddress')->willReturnCallback(fn (string $c) => [
            'cep' => preg_replace('/\D/', '', $c),
            'logradouro' => 'Alameda Santos',
            'bairro' => 'Jardim Paulista',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
        ]);

        return new LoggiGateway($cep);
    }

    private function carrier(string $name = 'Loggi'): Carrier
    {
        return new Carrier(
            id: 'carrier-loggi', name: $name, cnpj: '18.007.017/0001-58',
            originCity: 'São Paulo', originState: 'SP', status: CarrierStatus::ATIVA,
        );
    }

    private function credential(array $extra = []): CarrierCredential
    {
        return new CarrierCredential(
            id: 'cred-loggi', companyId: 'emp-1', carrierId: 'carrier-loggi',
            gateway: 'loggi',
            secrets: array_merge([
                'client_id' => 'cid', 'client_secret' => 'csec', 'company_id' => '777',
            ], $extra),
        );
    }

    private function quotation(): Quotation
    {
        return Quotation::create(
            id: 'q1', companyId: 'emp-1', userId: 'u1',
            nfNumber: '000100', senderCnpj: '60.701.190/0001-04', receiverCnpj: '30.539.356/8867-00',
            originCep: '01419-100', destinationCep: '01418-200',
            originCity: 'São Paulo', destinationCity: 'São Paulo', destinationState: 'SP',
            weight: 12.0, boxes: 1, volume: 0.3025, cargoValue: 87.35,
            validUntil: '2026-12-31',
        );
    }

    /** Resposta com dois serviços: o mais barato deve vencer. */
    private function quoteResponse(): array
    {
        return ['packagesQuotations' => [['quotations' => [
            [
                'price' => [
                    'totalAmount' => ['currencyCode' => 'BRL', 'units' => 20, 'nanos' => 0],
                    'baseAmount' => ['currencyCode' => 'BRL', 'units' => '15', 'nanos' => 0],
                    'taxesAndFees' => [
                        'icms' => ['amount' => ['units' => 5, 'nanos' => 0], 'rateTax' => '0.12'],
                    ],
                ],
                'sloInDays' => 1,
                'freightType' => 'FREIGHT_TYPE_EXPRESS',
                'freightTypeLabel' => 'Loggi Expresso',
            ],
            [
                'price' => [
                    'totalAmount' => ['currencyCode' => 'BRL', 'units' => 7, 'nanos' => 150000000],
                    'baseAmount' => ['currencyCode' => 'BRL', 'units' => '3', 'nanos' => 590000000],
                    'taxesAndFees' => [
                        'pis' => ['amount' => ['units' => '0', 'nanos' => 410000000], 'rateTax' => '0.0165'],
                        'cofins' => ['amount' => ['units' => '1', 'nanos' => 880000000], 'rateTax' => '0.0760'],
                        'icms' => ['amount' => ['units' => '2', 'nanos' => 970000000], 'rateTax' => '0.12'],
                    ],
                ],
                'sloInDays' => 3,
                'freightType' => 'FREIGHT_TYPE_ECONOMIC',
                'freightTypeLabel' => 'Loggi Econômico',
            ],
        ]]]];
    }

    public function test_quote_picks_cheapest_option_and_splits_base_from_taxes(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['idToken' => 'jwt', 'expiresIn' => '300']),
            self::QUOTE_URL => Http::response($this->quoteResponse()),
        ]);

        $quote = $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        $this->assertNotNull($quote);
        // Money: units 7 + nanos 150000000 = 7,15
        $this->assertSame(7.15, $quote->finalValue);
        // baseAmount "3" + 590000000 = 3,59 → frete sem tributo
        $this->assertSame(3.59, $quote->freightValue);
        $this->assertSame(3.56, $quote->fees);
        $this->assertSame(3, $quote->deadline);
        $this->assertSame('Loggi Econômico', $quote->service);
    }

    public function test_taxes_are_itemised_in_the_breakdown(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['idToken' => 'jwt', 'expiresIn' => '300']),
            self::QUOTE_URL => Http::response($this->quoteResponse()),
        ]);

        $breakdown = $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential())->feesBreakdown;

        // Ao contrário da Braspress, a Loggi discrimina cada tributo.
        $this->assertSame(
            ['cofins' => 1.88, 'icms' => 2.97, 'pis' => 0.41],
            collect($breakdown)->pluck('amount', 'type')->sortKeys()->all()
        );
    }

    public function test_payload_uses_loggi_field_names_and_units(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['idToken' => 'jwt', 'expiresIn' => '300']),
            self::QUOTE_URL => Http::response($this->quoteResponse()),
        ]);

        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/quotations')) {
                return false;
            }
            $body = $request->data();

            $this->assertSame('01419100', $body['shipFrom']['correios']['cep']);
            $this->assertSame('Alameda Santos', $body['shipFrom']['correios']['logradouro']);
            $this->assertSame('S/N', $body['shipFrom']['correios']['numero']);
            // 12 kg → 12000 g; 0,3025 m³ → cubo de ~67 cm
            $this->assertSame(12000, $body['packages'][0]['weightG']);
            $this->assertSame(67, $body['packages'][0]['lengthCm']);
            // 87,35 → units 87 + nanos 350000000
            $this->assertSame(87, $body['packages'][0]['goodsValue']['units']);
            $this->assertSame(350000000, $body['packages'][0]['goodsValue']['nanos']);
            $this->assertSame(['PICKUP_TYPE_SPOT'], $body['pickupTypes']);

            return true;
        });
    }

    public function test_token_is_reused_across_quotes(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['idToken' => 'jwt', 'expiresIn' => '300']),
            self::QUOTE_URL => Http::response($this->quoteResponse()),
        ]);

        $gw = $this->gateway();
        $gw->quote($this->quotation(), $this->carrier(), $this->credential());
        // Segunda carga diferente: refaz a cotação, mas não a autenticação.
        $other = Quotation::create(
            id: 'q2', companyId: 'emp-1', userId: 'u1',
            nfNumber: '000200', senderCnpj: '1', receiverCnpj: '2',
            originCep: '01419-100', destinationCep: '01418-200',
            originCity: 'São Paulo', destinationCity: 'São Paulo', destinationState: 'SP',
            weight: 30.0, boxes: 1, volume: 0.5, cargoValue: 200.0,
            validUntil: '2026-12-31',
        );
        $gw->quote($other, $this->carrier(), $this->credential());

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2/token'));
    }

    public function test_credential_without_company_id_raises(): void
    {
        $this->expectException(CarrierGatewayException::class);
        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential(['company_id' => '']));
    }

    public function test_auth_failure_raises_gateway_exception(): void
    {
        Http::fake([self::TOKEN_URL => Http::response('', 401)]);

        $this->expectException(CarrierGatewayException::class);
        $this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential());
    }

    public function test_empty_quotation_list_returns_null(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['idToken' => 'jwt', 'expiresIn' => '300']),
            self::QUOTE_URL => Http::response(['packagesQuotations' => []]),
        ]);

        $this->assertNull($this->gateway()->quote($this->quotation(), $this->carrier(), $this->credential()));
    }

    public function test_supports_only_loggi(): void
    {
        $this->assertTrue($this->gateway()->supports($this->carrier()));
        $this->assertFalse($this->gateway()->supports($this->carrier('Braspress')));
    }
}
