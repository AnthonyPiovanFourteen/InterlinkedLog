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
 * Braspress — POST /v1/cotacao/calcular/json, Basic Auth.
 *
 * A resposta traz apenas id, prazo e totalFrete: não há discriminação de taxas,
 * então freightValue recebe o total e fees fica zero. Inventar composição que a
 * origem não informou seria criar dado.
 */
class BraspressGateway implements CarrierGateway
{
    private const BASE_URL = 'https://api.braspress.com';

    private const TIMEOUT_SECONDS = 5;

    private const CACHE_TTL_MINUTES = 30;

    private const MODAL_RODOVIARIO = 'R';

    /** 1 = CIF (frete pago pelo remetente). */
    private const TIPO_FRETE_CIF = '1';

    public function name(): string
    {
        return 'braspress';
    }

    public function requiredSecrets(): array
    {
        return ['username', 'password'];
    }

    public function secretHints(): array
    {
        return [
            'username' => 'Usuário do portal de integração da Braspress, fornecido junto com o contrato de transporte. Não é o login do site.',
            'password' => 'Senha do mesmo usuário de integração.',
        ];
    }

    public function documentationUrl(): ?string
    {
        return 'https://api.braspress.com/home';
    }

    public function supports(Carrier $carrier): bool
    {
        return str_contains(mb_strtolower($carrier->name), 'braspress');
    }

    public function quote(Quotation $quotation, Carrier $carrier, CarrierCredential $credential): ?CarrierQuote
    {
        $payload = $this->payload($quotation);
        $cacheKey = $this->cacheKey($credential, $payload);

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->call($payload, $credential)
        );

        if ($data === null) {
            return null;
        }

        $total = (float) ($data['totalFrete'] ?? 0);
        $deadline = (int) ($data['prazo'] ?? 0);

        if ($total <= 0 || $deadline <= 0) {
            throw new CarrierGatewayException('Braspress devolveu cotação sem valor ou prazo');
        }

        return new CarrierQuote(
            carrierId: $carrier->id,
            carrierName: $carrier->name,
            freightValue: $total,
            fees: 0.0,
            finalValue: $total,
            deadline: $deadline,
            feesBreakdown: [],
            protocol: isset($data['id']) ? (string) $data['id'] : null,
        );
    }

    /**
     * @return array<string,mixed>|null null quando a rota não é atendida (404)
     */
    private function call(array $payload, CarrierCredential $credential): ?array
    {
        try {
            $response = Http::asJson()
                ->withBasicAuth(
                    (string) $credential->secret('username'),
                    (string) $credential->secret('password'),
                )
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::BASE_URL.'/v1/cotacao/calcular/json', $payload);
        } catch (Throwable $e) {
            throw new CarrierGatewayException('Braspress indisponível: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new CarrierGatewayException('Braspress respondeu '.$response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new CarrierGatewayException('Braspress devolveu corpo inesperado');
        }

        return $data;
    }

    /**
     * A Braspress pede dimensões por volume (altura/largura/comprimento), mas a
     * cotação interna só captura volume total em m³. Derivamos um cubo de mesmo
     * volume total — correto para o cálculo de peso cubado, que é o que define o
     * preço. Se a tarifa da Braspress considerar dimensão isolada (excesso de
     * comprimento, por exemplo), a estimativa difere: limitação conhecida, some
     * quando o formulário passar a capturar dimensões.
     */
    private function payload(Quotation $quotation): array
    {
        $volumes = max(1, $quotation->boxes);
        $side = $quotation->volume > 0
            ? round((($quotation->volume / $volumes) ** (1 / 3)), 3)
            : 0.01;

        return [
            'cnpjRemetente' => $this->digits($quotation->senderCnpj),
            'cnpjDestinatario' => $this->digits($quotation->receiverCnpj),
            'modal' => self::MODAL_RODOVIARIO,
            'tipoFrete' => self::TIPO_FRETE_CIF,
            'cepOrigem' => $this->digits($quotation->originCep),
            'cepDestino' => $this->digits($quotation->destinationCep),
            'vlrMercadoria' => round($quotation->cargoValue, 2),
            'peso' => round($quotation->weight, 2),
            'volumes' => $volumes,
            'cubagem' => [[
                'altura' => $side,
                'largura' => $side,
                'comprimento' => $side,
                'volumes' => $volumes,
            ]],
        ];
    }

    private function digits(string $value): int
    {
        return (int) preg_replace('/\D/', '', $value);
    }

    /** Cotação varia por credencial (preço negociado) e pela carga. */
    private function cacheKey(CarrierCredential $credential, array $payload): string
    {
        return 'carrier_quote:'.$this->name().':'.$credential->companyId.':'.md5(json_encode($payload));
    }
}
