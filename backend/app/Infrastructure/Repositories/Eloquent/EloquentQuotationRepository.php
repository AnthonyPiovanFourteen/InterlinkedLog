<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\Quotation as QuotationEntity;
use App\Domain\Repositories\QuotationRepository;
use App\Models\Quotation;
use App\Models\QuotationResult;
use Illuminate\Support\Str;

class EloquentQuotationRepository implements QuotationRepository
{
    public function findById(string $id): ?QuotationEntity
    {
        $model = Quotation::with('results')->find($id);
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function findByCompany(string $companyId, array $filters = []): array
    {
        $query = Quotation::with('results')->where('company_id', $companyId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nf_number', 'like', "%{$search}%")
                  ->orWhere('destination_city', 'like', "%{$search}%");
            });
        }

        return $query->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function save(QuotationEntity $quotation): void
    {
        $id = $quotation->id ?? Str::orderedUuid()->toString();
        Quotation::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'company_id' => $quotation->companyId,
                'user_id' => $quotation->userId,
                'nf_number' => $quotation->nfNumber,
                'sender_cnpj' => $quotation->senderCnpj,
                'receiver_cnpj' => $quotation->receiverCnpj,
                'origin_cep' => $quotation->originCep,
                'destination_cep' => $quotation->destinationCep,
                'origin_city' => $quotation->originCity,
                'destination_city' => $quotation->destinationCity,
                'destination_state' => $quotation->destinationState,
                'weight' => $quotation->weight,
                'boxes' => $quotation->boxes,
                'volume' => $quotation->volume,
                'cargo_value' => $quotation->cargoValue,
                'status' => $quotation->status,
                'valid_until' => $quotation->validUntil,
                'created_at' => $quotation->createdAt ?: now(),
                'updated_at' => $quotation->updatedAt ?: now(),
            ]
        );

        if (!empty($quotation->results)) {
            QuotationResult::where('quotation_id', $id)->delete();

            foreach ($quotation->results as $result) {
                QuotationResult::create([
                    'id' => uuid_create(),
                    'quotation_id' => $id,
                    'carrier_id' => $result['carrier_id'],
                    'carrier_name' => $result['carrier_name'],
                    'freight_value' => $result['freight_value'],
                    'fees' => $result['fees'],
                    'final_value' => $result['final_value'],
                    'deadline' => $result['deadline'],
                    'fees_breakdown' => $result['fees_breakdown'] ?? null,
                ]);
            }
        }
    }

    private function toEntity(Quotation $model): QuotationEntity
    {
        $results = $model->results->map(function ($r) {
            return [
                'carrier_id' => $r->carrier_id,
                'carrier_name' => $r->carrier_name,
                'freight_value' => (float) $r->freight_value,
                'fees' => (float) $r->fees,
                'final_value' => (float) $r->final_value,
                'deadline' => (int) $r->deadline,
                'fees_breakdown' => $r->fees_breakdown,
            ];
        })->toArray();

        return new QuotationEntity(
            id: $model->id,
            companyId: $model->company_id,
            userId: $model->user_id,
            nfNumber: $model->nf_number,
            senderCnpj: $model->sender_cnpj,
            receiverCnpj: $model->receiver_cnpj,
            originCep: $model->origin_cep,
            destinationCep: $model->destination_cep,
            originCity: $model->origin_city,
            destinationCity: $model->destination_city,
            destinationState: $model->destination_state,
            weight: (float) $model->weight,
            boxes: (int) $model->boxes,
            volume: (float) $model->volume,
            cargoValue: (float) $model->cargo_value,
            status: $model->status,
            results: $results,
            validUntil: $model->valid_until,
            createdAt: $model->created_at?->toIso8601String() ?? '',
            updatedAt: $model->updated_at?->toIso8601String() ?? '',
        );
    }
}
