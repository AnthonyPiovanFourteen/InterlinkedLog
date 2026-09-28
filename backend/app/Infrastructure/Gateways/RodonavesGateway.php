<?php

namespace App\Infrastructure\Gateways;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierQuote;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Domain\Services\CarrierGateway;
use App\Domain\Services\CepLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rodonaves (RTE) — a mais fragmentada das quatro: três hosts e quatro chamadas
 * para uma cotação completa.
 *
 *   POST {quotation}/token                      → access_token
 *   GET  {dne}/api/cities/byzipcode?zipCode=    → Id interno da cidade (×2)
 *   POST {quotation}/api/v1/gera-cotacao        → FreightValue (sem prazo)
 *   POST {wapi}/api/v1/prazo-entrega            → DeliveryTime
 *
 * A cotação não devolve prazo e exige o Id de cidade do catálogo da própria
 * Rodonaves — por isso cities/byzipcode e prazo-entrega não são redundantes com
 * o ViaCEP nem com a cotação.
 */
class RodonavesGateway implements CarrierGateway
{
    private const QUOTATION_HOST = 'https://quotation-apigateway.rte.com.br';

    private const DNE_HOST = 'https://dne-api.rte.com.br';

    private const WAPI_HOST = 'https://01wapi.rte.com.br';

    private const TIMEOUT_SECONDS = 5;

    private const CACHE_TTL_MINUTES = 30;

    private const CITY_CACHE_DAYS = 30;

    private const TOKEN_SAFETY_SECONDS = 30;

    public function __construct(private CepLookupService $cepLookup) {}

    public function name(): string
    {
        return 'rodonaves';
    }

    public function requiredSecrets(): array
    {
        return ['username', 'password'];
    }

    public function supports(Carrier $carrier): bool
    {
        $name = mb_strtolower($carrier->name);

        return str_contains($name, 'rodonaves') || str_contains($name, 'rte');
    }

    public function quote(Quotation $quotation, Carrier $carrier, CarrierCredential $credential): ?CarrierQuote
    {
        $token = $this->token($credential);

        $originCityId = $this->cityId($quotation->originCep, $token);
        $destinationCityId = $this->cityId($quotation->destinationCep, $token);

        if ($originCityId === null || $destinationCityId === null) {
            return null;
        }

        $payload = $this->payload($quotation, $credential, $originCityId, $destinationCityId);
        $cacheKey = 'carrier_quote:rodonaves:'.$credential->companyId.':'.md5(json_encode($payload));

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->post(self::QUOTATION_HOST.'/api/v1/gera-cotacao', $payload, $token, 'cotação')
        );

        if ($data === null) {
            return null;
        }

        // FreightValue vem como string ("150.00"); Discount é abatimento.
        $freight = (float) ($data['FreightValue'] ?? 0);
        $discount = (float) ($data['Discount'] ?? 0);
        $total = round($freight - $discount, 2);

        if ($total <= 0) {
            throw new CarrierGatewayException('Rodonaves devolveu cotação sem valor');
        }

        return new CarrierQuote(
            carrierId: $carrier->id,
            carrierName: $carrier->name,
            freightValue: $total,
            fees: 0.0,
            finalValue: $total,
            deadline: $this->deliveryTime($quotation, $token),
            feesBreakdown: [],
            protocol: isset($data['ProtocolId']) ? (string) $data['ProtocolId'] : null,
            service: $data['ClassName'] ?? null,
        );
    }

    /**
     * OAuth2 password grant (form-urlencoded). A documentação pública não
     * especifica o corpo da resposta; lemos access_token/expires_in, que é o
     * padrão do grant — é o único ponto deste adaptador que precisa ser
     * confirmado contra uma chamada real.
     */
    private function token(CarrierCredential $credential): string
    {
        $key = 'carrier_token:rodonaves:'.$credential->companyId;
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::QUOTATION_HOST.'/token', [
                    'auth_type' => $credential->secret('auth_type') ?? 'DEV',
                    'grant_type' => 'password',
                    'username' => (string) $credential->secret('username'),
                    'password' => (string) $credential->secret('password'),
                ]);
        } catch (Throwable $e) {
            throw new CarrierGatewayException('Rodonaves: falha ao autenticar — '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new CarrierGatewayException('Rodonaves: autenticação respondeu '.$response->status());
        }

        $token = (string) ($response->json('access_token') ?? $response->json('accessToken') ?? '');

        if ($token === '') {
            throw new CarrierGatewayException('Rodonaves: autenticação sem access_token');
        }

        $ttl = max(1, (int) ($response->json('expires_in') ?? 600) - self::TOKEN_SAFETY_SECONDS);
        Cache::put($key, $token, now()->addSeconds($ttl));

        return $token;
    }

    /** CEP → Id de cidade no catálogo da Rodonaves. Estável, cacheado por 30 dias. */
    private function cityId(string $cep, string $token): ?int
    {
        $digits = preg_replace('/\D/', '', $cep);

        return Cache::remember("rodonaves_city:{$digits}", now()->addDays(self::CITY_CACHE_DAYS), function () use ($digits, $token) {
            try {
                $response = Http::withToken($token)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->get(self::DNE_HOST.'/api/cities/byzipcode', ['zipCode' => $digits]);
            } catch (Throwable $e) {
                throw new CarrierGatewayException('Rodonaves indisponível (cidades): '.$e->getMessage(), 0, $e);
            }

            if ($response->status() === 400 || $response->status() === 404) {
                return null;
            }

            if ($response->failed()) {
                throw new CarrierGatewayException('Rodonaves (cidades) respondeu '.$response->status());
            }

            $id = $response->json('Id');

            return $id !== null ? (int) $id : null;
        });
    }

    /**
     * O prazo é uma chamada separada, em outro host, e exige o nome da cidade
     * em MAIÚSCULAS SEM ACENTO — a API rejeita "São Paulo", aceita "SAO PAULO".
     */
    private function deliveryTime(Quotation $quotation, string $token): int
    {
        // Quotation guarda a UF só do destino; a de origem vem do CEP.
        $origin = $this->cepLookup->lookup($quotation->originCep);

        if ($origin === null) {
            throw new CarrierGatewayException("Rodonaves: CEP de origem não resolvido — {$quotation->originCep}");
        }

        $payload = [
            'OriginCityDescription' => $this->plain($quotation->originCity),
            'OriginUFDescription' => strtoupper($origin[1]),
            'DestinationCityDescription' => $this->plain($quotation->destinationCity),
            'DestinationUFDescription' => strtoupper($quotation->destinationState),
        ];

        $data = Cache::remember(
            'rodonaves_prazo:'.md5(json_encode($payload)),
            now()->addDays(self::CITY_CACHE_DAYS),
            fn () => $this->post(self::WAPI_HOST.'/api/v1/prazo-entrega', $payload, $token, 'prazo')
        );

        return (int) ($data['DeliveryTime'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    private function post(string $url, array $payload, string $token, string $label): ?array
    {
        try {
            $response = Http::asJson()
                ->withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($url, $payload);
        } catch (Throwable $e) {
            throw new CarrierGatewayException("Rodonaves indisponível ({$label}): ".$e->getMessage(), 0, $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new CarrierGatewayException("Rodonaves ({$label}) respondeu ".$response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new CarrierGatewayException("Rodonaves ({$label}) devolveu corpo inesperado");
        }

        return $data;
    }

    private function payload(Quotation $quotation, CarrierCredential $credential, int $originCityId, int $destinationCityId): array
    {
        $sideCm = $quotation->volume > 0
            ? round(($quotation->volume ** (1 / 3)) * 100, 1)
            : 1.0;

        return [
            'OriginZipCode' => preg_replace('/\D/', '', $quotation->originCep),
            'OriginCityId' => $originCityId,
            'DestinationZipCode' => preg_replace('/\D/', '', $quotation->destinationCep),
            'DestinationCityId' => $destinationCityId,
            'TotalWeight' => round($quotation->weight, 2),
            'EletronicInvoiceValue' => round($quotation->cargoValue, 2),
            'CustomerTaxIdRegistration' => preg_replace('/\D/', '', $quotation->senderCnpj),
            'ReceiverCpfcnp' => preg_replace('/\D/', '', $quotation->receiverCnpj),
            'ContactName' => (string) ($credential->secret('contact_name') ?? ''),
            'ContactPhoneNumber' => (string) ($credential->secret('contact_phone') ?? ''),
            'CustomerEmail' => (string) ($credential->secret('contact_email') ?? ''),
            'TotalPackages' => max(1, $quotation->boxes),
            'Packs' => [[
                'AmountPackages' => max(1, $quotation->boxes),
                'Weight' => round($quotation->weight, 2),
                'Length' => $sideCm,
                'Height' => $sideCm,
                'Width' => $sideCm,
            ]],
        ];
    }

    /**
     * Maiúsculas sem acento, como a API de prazo exige.
     *
     * Str::ascii em vez de iconv //TRANSLIT: no musl do Alpine o iconv devolve
     * "S~ao Paulo" em vez de "Sao Paulo", enquanto a tabela do Laravel é
     * determinística e independe da libc.
     */
    private function plain(string $city): string
    {
        return strtoupper(Str::ascii($city));
    }
}
