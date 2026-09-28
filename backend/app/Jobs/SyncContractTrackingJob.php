<?php

namespace App\Jobs;

use App\Domain\Entities\TrackingEvent;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Domain\Repositories\ContractRepository;
use App\Domain\Repositories\TrackingEventRepository;
use App\Domain\Services\TenantContext;
use App\Domain\Services\TrackingGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Sincroniza o rastreio de UM contrato com a API da transportadora.
 *
 * Só roda em contrato com modo automático. Os eventos são deduplicados pelo
 * external_id, então o job pode rodar quantas vezes quiser sem duplicar.
 */
class SyncContractTrackingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public string $companyId,
        public string $contractId,
    ) {}

    public function handle(
        TenantContext $tenant,
        ContractRepository $contracts,
        CarrierCredentialRepository $credentials,
        TrackingEventRepository $events,
    ): void {
        $tenant->runAs($this->companyId, function () use ($contracts, $credentials, $events) {
            $contract = $contracts->findById($this->contractId);

            if (! $contract || ! $contract->isAutoTracked()) {
                return;
            }

            $credential = $credentials->findForCarrier($this->companyId, $contract->carrierId);

            if (! $credential) {
                return;
            }

            $gateway = $this->resolveGateway($contract->trackingGateway);

            if (! $gateway) {
                return;
            }

            try {
                $fetched = $gateway->events($contract, $credential);
            } catch (CarrierGatewayException) {
                // Transportadora fora do ar: tenta de novo no próximo ciclo.
                return;
            }

            foreach ($fetched as $event) {
                $events->save(new TrackingEvent(
                    id: Str::orderedUuid()->toString(),
                    contractId: $contract->id,
                    title: $event['title'],
                    date: $event['date'],
                    time: $event['time'],
                    observation: null,
                    origin: TrackingEvent::ORIGIN_API,
                    externalId: $event['external_id'],
                ));
            }
        });
    }

    private function resolveGateway(?string $name): ?TrackingGateway
    {
        if ($name === null) {
            return null;
        }

        foreach (app()->tagged('tracking.gateways') as $gateway) {
            if ($gateway->name() === $name) {
                return $gateway;
            }
        }

        return null;
    }
}
