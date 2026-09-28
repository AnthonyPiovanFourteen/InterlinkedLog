<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\Quotation;
use App\Domain\Entities\SystemLog;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Repositories\SystemLogRepository;
use App\Domain\Services\CarrierGateway;
use App\Domain\Services\CarrierQuoteShadowRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class LoggingCarrierQuoteShadowRunner implements CarrierQuoteShadowRunner
{
    /** Após N falhas seguidas, para de chamar a transportadora por M minutos. */
    private const BREAKER_THRESHOLD = 3;

    private const BREAKER_COOLDOWN_MINUTES = 10;

    /** @param iterable<CarrierGateway> $gateways */
    public function __construct(
        private iterable $gateways,
        private CarrierRepository $carrierRepository,
        private CarrierCredentialRepository $credentialRepository,
        private SystemLogRepository $systemLogRepository,
    ) {}

    public function run(Quotation $quotation, array $localResults): void
    {
        $credentials = $this->credentialRepository->activeForCompany($quotation->companyId);

        if ($credentials === []) {
            return;
        }

        $localByCarrier = [];
        foreach ($localResults as $result) {
            $localByCarrier[$result['carrier_id']] = $result;
        }

        foreach ($this->carrierRepository->findAll() as $carrier) {
            $credential = $credentials[$carrier->id] ?? null;

            if (! $credential) {
                continue;
            }

            foreach ($this->gateways as $gateway) {
                if (! $gateway->supports($carrier) || $gateway->name() !== $credential->gateway) {
                    continue;
                }

                if ($this->breakerOpen($gateway->name(), $quotation->companyId)) {
                    continue;
                }

                try {
                    $quote = $gateway->quote($quotation, $carrier, $credential);
                    $this->resetBreaker($gateway->name(), $quotation->companyId);
                    $this->logComparison($quotation, $gateway->name(), $carrier->name, $quote?->toResult(), $localByCarrier[$carrier->id] ?? null);
                } catch (Throwable $e) {
                    // Falha isolada: nunca derruba a cotação.
                    $this->recordFailure($gateway->name(), $quotation->companyId);
                    $this->log($quotation, 'error', "sombra {$gateway->name()} falhou: ".$e->getMessage());
                }
            }
        }
    }

    private function logComparison(Quotation $quotation, string $gateway, string $carrierName, ?array $api, ?array $local): void
    {
        if ($api === null) {
            $this->log($quotation, 'info', "sombra {$gateway}: {$carrierName} não atende a rota");

            return;
        }

        if ($local === null) {
            $this->log($quotation, 'warning', sprintf(
                'sombra %s: %s cotou %.2f (prazo %d) e a tabela local não devolveu resultado',
                $gateway, $carrierName, $api['final_value'], $api['deadline']
            ));

            return;
        }

        $delta = $api['final_value'] - $local['final_value'];
        $pct = $local['final_value'] > 0 ? ($delta / $local['final_value']) * 100 : 0;

        $this->log($quotation, abs($pct) > 10 ? 'warning' : 'info', sprintf(
            'sombra %s: %s api %.2f/%dd vs tabela %.2f/%dd (delta %+.2f, %+.1f%%)',
            $gateway, $carrierName,
            $api['final_value'], $api['deadline'],
            $local['final_value'], $local['deadline'],
            $delta, $pct
        ));
    }

    private function log(Quotation $quotation, string $level, string $message): void
    {
        $this->systemLogRepository->save(new SystemLog(
            id: Str::orderedUuid()->toString(),
            companyId: $quotation->companyId,
            userId: $quotation->userId,
            userName: 'sistema',
            level: $level,
            event: 'carrier_shadow',
            message: $message,
        ));
    }

    private function breakerKey(string $gateway, string $companyId): string
    {
        return "carrier_breaker:{$gateway}:{$companyId}";
    }

    private function breakerOpen(string $gateway, string $companyId): bool
    {
        return (int) Cache::get($this->breakerKey($gateway, $companyId), 0) >= self::BREAKER_THRESHOLD;
    }

    private function recordFailure(string $gateway, string $companyId): void
    {
        $key = $this->breakerKey($gateway, $companyId);
        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addMinutes(self::BREAKER_COOLDOWN_MINUTES));
    }

    private function resetBreaker(string $gateway, string $companyId): void
    {
        Cache::forget($this->breakerKey($gateway, $companyId));
    }
}
