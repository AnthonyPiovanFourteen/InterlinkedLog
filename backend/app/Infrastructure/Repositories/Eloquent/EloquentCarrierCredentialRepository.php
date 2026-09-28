<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\CarrierCredential as CredentialEntity;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Models\CarrierCredential;
use Illuminate\Support\Str;

class EloquentCarrierCredentialRepository implements CarrierCredentialRepository
{
    public function activeForCompany(string $companyId): array
    {
        return CarrierCredential::where('company_id', $companyId)
            ->where('active', true)
            ->get()
            ->mapWithKeys(fn ($m) => [$m->carrier_id => $this->toEntity($m)])
            ->all();
    }

    public function findForCarrier(string $companyId, string $carrierId): ?CredentialEntity
    {
        $model = CarrierCredential::where('company_id', $companyId)
            ->where('carrier_id', $carrierId)
            ->where('active', true)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function allForCompany(string $companyId): array
    {
        return CarrierCredential::where('company_id', $companyId)
            ->orderBy('gateway')
            ->get()
            ->map(fn ($m) => $this->toEntity($m))
            ->all();
    }

    public function findById(string $companyId, string $id): ?CredentialEntity
    {
        $model = CarrierCredential::where('company_id', $companyId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function delete(string $companyId, string $id): void
    {
        CarrierCredential::where('company_id', $companyId)->where('id', $id)->delete();
    }

    public function save(CredentialEntity $credential): void
    {
        $id = $credential->id ?? Str::orderedUuid()->toString();

        CarrierCredential::updateOrCreate(
            ['id' => $id],
            [
                'id' => $id,
                'company_id' => $credential->companyId,
                'carrier_id' => $credential->carrierId,
                'gateway' => $credential->gateway,
                'secrets' => $credential->secrets,
                'active' => $credential->active,
                'created_at' => $credential->createdAt ?: now(),
                'updated_at' => $credential->updatedAt ?: now(),
            ]
        );
    }

    private function toEntity(CarrierCredential $model): CredentialEntity
    {
        return new CredentialEntity(
            id: $model->id,
            companyId: $model->company_id,
            carrierId: $model->carrier_id,
            gateway: $model->gateway,
            secrets: $model->secrets ?? [],
            active: (bool) $model->active,
            createdAt: $model->created_at?->toIso8601String() ?? '',
            updatedAt: $model->updated_at?->toIso8601String() ?? '',
        );
    }
}
