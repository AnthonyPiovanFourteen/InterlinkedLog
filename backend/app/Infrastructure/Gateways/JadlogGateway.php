<?php

namespace App\Infrastructure\Gateways;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierQuote;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Domain\Services\CarrierGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Jadlog — POST /embarcador/api/frete/valor.
 *
 * O token vai cru no header Authorization, sem "Bearer". A resposta traz
 * vltotal e prazo, sem discriminar taxas.
 *
 * A documentação exige que o PESO ENVIADO seja o maior entre o real e o
 * cubado. O fator de cubagem é negociado por contrato, então vem da credencial
 * — o adaptador não inventa o número.
 */
class JadlogGateway implements CarrierGateway
{
    private const URL = 'https://www.jadlog.com.br/embarcador/api/frete/valor';

    private const TIMEOUT_SECONDS = 5;

    private const CACHE_TTL_MINUTES = 30;

    /** D = entrega no domicílio. */
    private const TIPO_ENTREGA_DOMICILIO = 'D';

    public function name(): string
    {
        return 'jadlog';
    }

    public function requiredSecrets(): array
    {
        return ['token', 'modalidade', 'cubage_factor'];
    }

    public function supports(Carrier $carrier): bool
    {
        return str_contains(mb_strtolower($carrier->name), 'jadlog');
    }

    public function quote(Quotation $quotation, Carrier $carrier, CarrierCredential $credential): ?CarrierQuote
    {
        $payload = $this->payload($quotation, $credential);
        $cacheKey = 'carrier_quote:jadlog:'.$credential->companyId.':'.md5(json_encode($payload));

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->call($payload, $credential)
        );

        if ($data === null) {
            return null;
        }

        $total = (float) ($data['vltotal'] ?? 0);
        $deadline = (int) ($data['prazo'] ?? 0);

        if ($total <= 0 || $deadline <= 0) {
            throw new CarrierGatewayException('Jadlog devolveu cotação sem valor ou prazo');
        }

        return new CarrierQuote(
            carrierId: $carrier->id,
            carrierName: $carrier->name,
            freightValue: $total,
            fees: 0.0,
            finalValue: $total,
            deadline: $deadline,
            feesBreakdown: [],
        );
    }

    private function call(array $payload, CarrierCredential $credential): ?array
    {
        $token = $credential->secret('token');

        if (! $token) {
            throw new CarrierGatewayException('Credencial Jadlog sem token');
        }

        try {
            $response = Http::asJson()
                // Sem "Bearer": a Jadlog espera o token cru.
                ->withHeaders(['Authorization' => $token])
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::URL, $payload);
        } catch (Throwable $e) {
            throw new CarrierGatewayException('Jadlog indisponível: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new CarrierGatewayException('Jadlog respondeu '.$response->status());
        }

        $frete = $response->json('frete');

        if (! is_array($frete) || $frete === []) {
            throw new CarrierGatewayException('Jadlog devolveu corpo inesperado');
        }

        $first = $frete[0];

        // A Jadlog sinaliza rota não atendida devolvendo erro no item.
        if (! empty($first['error']) || ! empty($first['erro'])) {
            return null;
        }

        return $first;
    }

    private function payload(Quotation $quotation, CarrierCredential $credential): array
    {
        $modalidade = $credential->secret('modalidade');

        if ($modalidade === null) {
            throw new CarrierGatewayException('Credencial Jadlog sem modalidade');
        }

        return ['frete' => [array_filter([
            'cepori' => $this->digits($quotation->originCep),
            'cepdes' => $this->digits($quotation->destinationCep),
            'frap' => 'N',
            'peso' => $this->billableWeight($quotation, $credential),
            'cnpj' => $this->digits($quotation->senderCnpj),
            'conta' => $credential->secret('conta'),
            'contrato' => $credential->secret('contrato'),
            'modalidade' => (int) $modalidade,
            'tpentrega' => self::TIPO_ENTREGA_DOMICILIO,
            'tpseguro' => 'N',
            'vldeclarado' => round($quotation->cargoValue, 2),
            'vlcoleta' => 0,
        ], fn ($v) => $v !== null)]];
    }

    /**
     * A Jadlog exige o maior entre peso real e peso cubado. O fator (kg/m³) é
     * negociado por contrato e vem da credencial: sem ele, não há como calcular
     * o peso cubado, e enviar só o peso real subfaturaria carga leve e volumosa.
     */
    private function billableWeight(Quotation $quotation, CarrierCredential $credential): float
    {
        $factor = $credential->secret('cubage_factor');

        if ($factor === null || (float) $factor <= 0) {
            throw new CarrierGatewayException(
                'Credencial Jadlog sem cubage_factor: a API exige o maior peso entre real e cubado'
            );
        }

        return round(max($quotation->weight, $quotation->volume * (float) $factor), 2);
    }

    private function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }
}
