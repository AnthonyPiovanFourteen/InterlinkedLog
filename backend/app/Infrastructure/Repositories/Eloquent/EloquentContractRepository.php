<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\Contract as ContractEntity;
use App\Domain\Repositories\ContractRepository;
use App\Models\Contract;
use Illuminate\Support\Str;

class EloquentContractRepository implements ContractRepository
{
    public function findById(string $id): ?ContractEntity
    {
        $model = Contract::find($id);
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function findByCompany(string $companyId, array $filters = []): array
    {
        $query = Contract::where('company_id', $companyId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }

        return $query->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function save(ContractEntity $contract): void
    {
        $id = $contract->id ?? Str::orderedUuid()->toString();
        Contract::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'company_id' => $contract->companyId,
                'quotation_id' => $contract->quotationId,
                'carrier_id' => $contract->carrierId,
                'carrier_name' => $contract->carrierName,
                'nf_number' => $contract->nfNumber,
                'origin_city' => $contract->originCity,
                'destination_city' => $contract->destinationCity,
                'destination_state' => $contract->destinationState,
                'freight_value' => $contract->freightValue,
                'fees' => $contract->fees,
                'final_value' => $contract->finalValue,
                'deadline' => $contract->deadline,
                'status' => $contract->status,
                'document_number' => $contract->documentNumber,
                'cte_number' => $contract->cteNumber,
                'cancelled_at' => $contract->cancelledAt,
                'cancel_reason' => $contract->cancelReason,
                'created_at' => $contract->createdAt ?: now(),
                'updated_at' => $contract->updatedAt ?: now(),
            ]
        );
    }

    private function toEntity(Contract $model): ContractEntity
    {
        return new ContractEntity(
            id: $model->id,
            companyId: $model->company_id,
            quotationId: $model->quotation_id,
            nfNumber: $model->nf_number,
            carrierId: $model->carrier_id,
            carrierName: $model->carrier_name,
            originCity: $model->origin_city,
            destinationCity: $model->destination_city,
            destinationState: $model->destination_state,
            freightValue: (float) $model->freight_value,
            fees: (float) $model->fees,
            finalValue: (float) $model->final_value,
            deadline: (int) $model->deadline,
            status: $model->status,
            documentNumber: $model->document_number,
            cteNumber: $model->cte_number,
            cancelledAt: $model->cancelled_at,
            cancelReason: $model->cancel_reason,
            createdAt: $model->created_at?->toIso8601String() ?? '',
            updatedAt: $model->updated_at?->toIso8601String() ?? '',
        );
    }
}
