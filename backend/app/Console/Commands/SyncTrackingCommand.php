<?php

namespace App\Console\Commands;

use App\Domain\Entities\Contract;
use App\Jobs\SyncContractTrackingJob;
use App\Models\Contract as ContractModel;
use Illuminate\Console\Command;

/**
 * Enfileira o sincronismo de rastreio dos contratos em andamento.
 *
 * Roda sem contexto de tenant: varre todas as empresas e cada job define o
 * próprio tenant a partir do payload.
 */
class SyncTrackingCommand extends Command
{
    protected $signature = 'tracking:sync';

    protected $description = 'Enfileira a sincronização de rastreio dos contratos automáticos em andamento';

    public function handle(): int
    {
        $contracts = ContractModel::withoutGlobalScopes()
            ->where('tracking_mode', Contract::TRACKING_AUTO)
            ->whereNotIn('status', [Contract::STATUS_DELIVERED, Contract::STATUS_CANCELLED])
            ->get(['id', 'company_id']);

        foreach ($contracts as $contract) {
            SyncContractTrackingJob::dispatch($contract->company_id, $contract->id);
        }

        $this->info("Sincronização enfileirada para {$contracts->count()} contrato(s).");

        return self::SUCCESS;
    }
}
