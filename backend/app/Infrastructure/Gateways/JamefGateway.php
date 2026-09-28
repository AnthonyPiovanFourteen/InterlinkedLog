<?php

namespace App\Infrastructure\Gateways;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierQuote;
use App\Domain\Entities\Quotation;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Domain\Services\CarrierGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Jamef — três serviços sob o mesmo host:
 *
 *   POST /auth/v1/login                        accessToken + expiresIn
 *   GET  /infraestrutura/v1/lista-filiais?cep= codigoFilial da origem
 *   POST /calculo-frete/v1/cotacao             total + previsaoEntrega
 *
 * Como a Rodonaves, exige resolver um código interno (a filial) antes de cotar.
 * E, ao contrário de todas as outras, devolve o prazo como DATA de previsão de
 * entrega, não como número de dias — a conversão é feita aqui.
 *
 * Todas as respostas vêm embrulhadas num array 'dado'.
 */
class JamefGateway implements CarrierGateway
{
    private const HOST = 'https://api.jamef.com.br';

    private const TIMEOUT_SECONDS = 8;

    private const QUOTE_CACHE_TTL_MINUTES = 30;

    private const BRANCH_CACHE_DAYS = 30;

    private const TOKEN_SAFETY_SECONDS = 120;

    /** 1 = rodoviário. */
    private const TIPO_TRANSPORTE_RODOVIARIO = '1';

    public function name(): string
    {
        return 'jamef';
    }

    public function requiredSecrets(): array
    {
        // documento_devedor: CNPJ de quem paga o frete, exigido na cotação.
        return ['username', 'password', 'documento_devedor'];
    }

    public function secretHints(): array
    {
        return [
            'username' => 'Usuário da API Jamef, fornecido no cadastro de integração.',
            'password' => 'Senha do mesmo usuário.',
            'documento_devedor' => 'CNPJ de quem paga o frete — normalmente o da sua própria empresa. Pode digitar com ou sem pontuação.',
        ];
    }

    public function documentationUrl(): ?string
    {
        return 'https://developers.jamef.com.br/documentacao';
    }

    public function supports(Carrier $carrier): bool
    {
        return str_contains(mb_strtolower($carrier->name), 'jamef');
    }

    public function quote(Quotation $quotation, Carrier $carrier, CarrierCredential $credential): ?CarrierQuote
    {
        $token = $this->token($credential);
        $payload = $this->payload($quotation, $credential, $token);

        $cacheKey = 'carrier_quote:jamef:'.$credential->companyId.':'.md5(json_encode($payload));

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::QUOTE_CACHE_TTL_MINUTES),
            fn () => $this->post('/calculo-frete/v1/cotacao', $payload, $token, 'cotação')
        );

        if ($data === null) {
            return null;
        }

        $total = (float) ($data['total'] ?? 0);
        $deadline = $this->deadlineInDays($data['previsaoEntrega'] ?? null);

        if ($total <= 0 || $deadline <= 0) {
            throw new CarrierGatewayException('Jamef devolveu cotação sem valor ou previsão');
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

    /**
     * A Jamef devolve a data prevista de entrega (dd/mm/aaaa), não a quantidade
     * de dias — o modelo interno guarda dias, então convertemos.
     */
    private function deadlineInDays(mixed $previsao): int
    {
        if (! is_string($previsao) || $previsao === '') {
            return 0;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, substr($previsao, 0, 10));
            } catch (Throwable) {
                continue;
            }

            if ($date) {
                return max(1, (int) ceil(now()->startOfDay()->diffInDays($date->startOfDay(), false)));
            }
        }

        return 0;
    }

    private function token(CarrierCredential $credential): string
    {
        $key = 'carrier_token:jamef:'.$credential->companyId;
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::HOST.'/auth/v1/login', [
                    'username' => (string) $credential->secret('username'),
                    'password' => (string) $credential->secret('password'),
                ]);
        } catch (Throwable $e) {
            throw new CarrierGatewayException('Jamef: falha ao autenticar — '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new CarrierGatewayException('Jamef: autenticação respondeu '.$response->status());
        }

        $token = (string) ($response->json('dado.0.accessToken') ?? '');

        if ($token === '') {
            throw new CarrierGatewayException('Jamef: autenticação sem accessToken');
        }

        $expiresIn = (int) ($response->json('dado.0.expiresIn') ?? 3600);
        Cache::put($key, $token, now()->addSeconds(max(60, $expiresIn - self::TOKEN_SAFETY_SECONDS)));

        return $token;
    }

    /** CEP → código da filial de origem. Estável, cacheado por 30 dias. */
    private function branchCode(string $cep, string $token): ?string
    {
        $digits = preg_replace('/\D/', '', $cep);

        return Cache::remember("jamef_branch:{$digits}", now()->addDays(self::BRANCH_CACHE_DAYS), function () use ($digits, $token) {
            try {
                $response = Http::withToken($token)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->get(self::HOST.'/infraestrutura/v1/lista-filiais', ['cep' => $digits]);
            } catch (Throwable $e) {
                throw new CarrierGatewayException('Jamef indisponível (filiais): '.$e->getMessage(), 0, $e);
            }

            if ($response->failed()) {
                return null;
            }

            $code = $response->json('dado.0.codigoFilial');

            return $code !== null ? (string) $code : null;
        });
    }

    /** @return array<string,mixed>|null */
    private function post(string $path, array $payload, string $token, string $label): ?array
    {
        try {
            $response = Http::asJson()
                ->withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::HOST.$path, $payload);
        } catch (Throwable $e) {
            throw new CarrierGatewayException("Jamef indisponível ({$label}): ".$e->getMessage(), 0, $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new CarrierGatewayException("Jamef ({$label}) respondeu ".$response->status());
        }

        $dado = $response->json('dado');

        // Lista vazia é resposta legítima: não atende a rota.
        if (! is_array($dado) || $dado === []) {
            return null;
        }

        return $dado[0];
    }

    private function payload(Quotation $quotation, CarrierCredential $credential, string $token): array
    {
        $payload = [
            'tipoTransporte' => self::TIPO_TRANSPORTE_RODOVIARIO,
            'documentoDevedor' => $this->digits((string) $credential->secret('documento_devedor')),
            'cepOrigem' => $this->digits($quotation->originCep),
            'cepDestino' => $this->digits($quotation->destinationCep),
            'quantidadeVolume' => max(1, $quotation->boxes),
            'pesoMercadoria' => round($quotation->weight, 2),
            'valorNotaFiscal' => round($quotation->cargoValue, 2),
            'metragemCubica' => round($quotation->volume, 4),
            'documentoRemetente' => $this->digits($quotation->senderCnpj),
            'documentoDestino' => $this->digits($quotation->receiverCnpj),
        ];

        $branch = $this->branchCode($quotation->originCep, $token);

        if ($branch !== null) {
            $payload['filialOrigem'] = $branch;
        }

        return $payload;
    }

    private function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }
}
