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
use Throwable;

/**
 * Loggi — OAuth2 (idToken de curta duração) + POST /v1/companies/{id}/quotations.
 *
 * O oposto da Braspress em quase tudo, de propósito: token com expiração,
 * valores no formato Money do Google (units + nanos), resposta aninhada com
 * vários serviços por pacote, e tributos discriminados. Se os dois adaptadores
 * convivem sem mexer na porta, a abstração é real.
 */
class LoggiGateway implements CarrierGateway
{
    private const BASE_URL = 'https://api.loggi.com';

    private const TIMEOUT_SECONDS = 5;

    private const QUOTE_CACHE_TTL_MINUTES = 30;

    /** Margem para não usar token à beira da expiração. */
    private const TOKEN_SAFETY_SECONDS = 30;

    private const DEFAULT_PICKUP_TYPE = 'PICKUP_TYPE_SPOT';

    /** Sem número: convenção brasileira quando o logradouro não tem numeração. */
    private const NO_NUMBER = 'S/N';

    public function __construct(private CepLookupService $cepLookup) {}

    public function name(): string
    {
        return 'loggi';
    }

    public function requiredSecrets(): array
    {
        return ['client_id', 'client_secret', 'company_id'];
    }

    public function secretHints(): array
    {
        return [
            'client_id' => 'Identificador da aplicação, gerado no painel da Loggi em Integrações → Credenciais.',
            'client_secret' => 'Chave secreta emitida junto do Client ID. A Loggi a exibe uma única vez — se perdeu, gere outra.',
            'company_id' => 'Código numérico da sua empresa na Loggi. Aparece na URL do painel, depois de /companies/.',
        ];
    }

    public function supports(Carrier $carrier): bool
    {
        return str_contains(mb_strtolower($carrier->name), 'loggi');
    }

    public function quote(Quotation $quotation, Carrier $carrier, CarrierCredential $credential): ?CarrierQuote
    {
        $companyId = $credential->secret('company_id');

        if (! $companyId) {
            throw new CarrierGatewayException('Credencial Loggi sem company_id');
        }

        $payload = $this->payload($quotation);
        $cacheKey = 'carrier_quote:loggi:'.$credential->companyId.':'.md5(json_encode($payload));

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::QUOTE_CACHE_TTL_MINUTES),
            fn () => $this->call($payload, $companyId, $credential)
        );

        if ($data === null) {
            return null;
        }

        return $this->toQuote($data, $carrier);
    }

    private function call(array $payload, string $companyId, CarrierCredential $credential): ?array
    {
        $token = $this->token($credential);

        try {
            $response = Http::asJson()
                ->withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::BASE_URL."/v1/companies/{$companyId}/quotations", $payload);
        } catch (Throwable $e) {
            throw new CarrierGatewayException('Loggi indisponível: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new CarrierGatewayException('Loggi respondeu '.$response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new CarrierGatewayException('Loggi devolveu corpo inesperado');
        }

        return $data;
    }

    /**
     * O idToken expira em segundos (300 no exemplo da doc), então é cacheado por
     * credencial pelo prazo informado, menos a margem de segurança.
     */
    private function token(CarrierCredential $credential): string
    {
        $key = 'carrier_token:loggi:'.$credential->companyId;
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::BASE_URL.'/v2/oauth2/token', [
                    'client_id' => (string) $credential->secret('client_id'),
                    'client_secret' => (string) $credential->secret('client_secret'),
                ]);
        } catch (Throwable $e) {
            throw new CarrierGatewayException('Loggi: falha ao autenticar — '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new CarrierGatewayException('Loggi: autenticação respondeu '.$response->status());
        }

        $token = (string) ($response->json('idToken') ?? '');
        $expiresIn = (int) ($response->json('expiresIn') ?? 0);

        if ($token === '') {
            throw new CarrierGatewayException('Loggi: autenticação sem idToken');
        }

        $ttl = max(1, $expiresIn - self::TOKEN_SAFETY_SECONDS);
        Cache::put($key, $token, now()->addSeconds($ttl));

        return $token;
    }

    /**
     * A resposta traz vários serviços por pacote (Econômico, Expresso...).
     * Escolhemos o de menor valor total e registramos qual foi, em vez de
     * devolver todos — o modelo interno é um resultado por transportadora.
     */
    private function toQuote(array $data, Carrier $carrier): ?CarrierQuote
    {
        $options = [];

        foreach ($data['packagesQuotations'] ?? [] as $package) {
            foreach ($package['quotations'] ?? [] as $option) {
                $options[] = $option;
            }
        }

        if ($options === []) {
            return null;
        }

        usort($options, fn ($a, $b) => $this->money($a['price']['totalAmount'] ?? null)
            <=> $this->money($b['price']['totalAmount'] ?? null));

        $best = $options[0];
        $total = $this->money($best['price']['totalAmount'] ?? null);
        $base = $this->money($best['price']['baseAmount'] ?? null);
        $deadline = (int) ($best['sloInDays'] ?? 0);

        if ($total <= 0 || $deadline <= 0) {
            throw new CarrierGatewayException('Loggi devolveu cotação sem valor ou prazo');
        }

        $breakdown = [];
        foreach ($best['price']['taxesAndFees'] ?? [] as $type => $entry) {
            $amount = $this->money($entry['amount'] ?? null);
            if ($amount > 0) {
                $breakdown[] = ['type' => $type, 'amount' => round($amount, 2)];
            }
        }

        return new CarrierQuote(
            carrierId: $carrier->id,
            carrierName: $carrier->name,
            freightValue: $base,
            fees: round($total - $base, 2),
            finalValue: $total,
            deadline: $deadline,
            feesBreakdown: $breakdown,
            protocol: null,
            service: $best['freightTypeLabel'] ?? ($best['freightType'] ?? null),
        );
    }

    /** Formato Money do Google: units (inteiro ou string) + nanos (10^-9). */
    private function money(?array $money): float
    {
        if ($money === null) {
            return 0.0;
        }

        return (float) ($money['units'] ?? 0) + ((int) ($money['nanos'] ?? 0) / 1_000_000_000);
    }

    private function payload(Quotation $quotation): array
    {
        return [
            'shipFrom' => ['correios' => $this->address($quotation->originCep)],
            'shipTo' => ['correios' => $this->address($quotation->destinationCep)],
            'packages' => [$this->package($quotation)],
            'pickupTypes' => [self::DEFAULT_PICKUP_TYPE],
        ];
    }

    /**
     * A Loggi exige logradouro e bairro, que a cotação interna não captura —
     * são resolvidos pelo CEP. Quando o CEP vem do mapa local (sem logradouro),
     * vão vazios: limitação conhecida, some quando o formulário capturar
     * endereço.
     */
    private function address(string $cep): array
    {
        $resolved = $this->cepLookup->lookupAddress($cep);

        if ($resolved === null) {
            throw new CarrierGatewayException("Loggi: CEP não resolvido — {$cep}");
        }

        return [
            'logradouro' => $resolved['logradouro'],
            'numero' => self::NO_NUMBER,
            'complemento' => '',
            'bairro' => $resolved['bairro'],
            'cep' => $resolved['cep'],
            'cidade' => $resolved['cidade'],
            'uf' => $resolved['uf'],
        ];
    }

    /**
     * Peso em gramas e dimensões em centímetros. Como só há volume total em m³,
     * derivamos um cubo de mesmo volume — mesma limitação do adaptador Braspress.
     */
    private function package(Quotation $quotation): array
    {
        $sideCm = $quotation->volume > 0
            ? (int) round(($quotation->volume ** (1 / 3)) * 100)
            : 1;

        $units = (int) floor($quotation->cargoValue);
        $nanos = (int) round(($quotation->cargoValue - $units) * 1_000_000_000);

        return [
            'weightG' => (int) round($quotation->weight * 1000),
            'lengthCm' => $sideCm,
            'widthCm' => $sideCm,
            'heightCm' => $sideCm,
            'goodsValue' => ['currencyCode' => 'BRL', 'units' => $units, 'nanos' => $nanos],
        ];
    }
}
