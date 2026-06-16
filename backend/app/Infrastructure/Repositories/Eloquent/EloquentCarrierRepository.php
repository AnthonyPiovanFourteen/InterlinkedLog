<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\Carrier as CarrierEntity;
use App\Domain\Repositories\CarrierRepository;
use App\Models\Carrier;
use Illuminate\Support\Str;

class EloquentCarrierRepository implements CarrierRepository
{
    public function findById(string $id): ?CarrierEntity
    {
        $model = Carrier::find($id);
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function findAll(): array
    {
        return Carrier::all()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function findByOrigin(string $city, string $state): array
    {
        return Carrier::where('status', 'Ativa')
            ->where('origin_city', 'like', $city)
            ->where('origin_uf', $state)
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function save(CarrierEntity $carrier): void
    {
        $id = $carrier->id ?? Str::orderedUuid()->toString();
        Carrier::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'name' => $carrier->name,
                'cnpj' => $carrier->cnpj,
                'origin_city' => $carrier->originCity,
                'origin_uf' => $carrier->originState,
                'status' => $carrier->status,
                'contact_name' => $carrier->contactName,
                'contact_phone' => $carrier->contactPhone,
                'contact_email' => $carrier->contactEmail,
                'created_at' => $carrier->createdAt ?: now(),
                'updated_at' => $carrier->updatedAt ?: now(),
            ]
        );
    }

    public function delete(string $id): void
    {
        Carrier::destroy($id);
    }

    private function toEntity(Carrier $model): CarrierEntity
    {
        return new CarrierEntity(
            id: $model->id,
            name: $model->name,
            cnpj: $model->cnpj,
            originCity: $model->origin_city,
            originState: $model->origin_uf,
            status: $model->status,
            contactName: $model->contact_name ?? '',
            contactPhone: $model->contact_phone ?? '',
            contactEmail: $model->contact_email ?? '',
            createdAt: $model->created_at?->toIso8601String() ?? '',
            updatedAt: $model->updated_at?->toIso8601String() ?? '',
        );
    }
}
