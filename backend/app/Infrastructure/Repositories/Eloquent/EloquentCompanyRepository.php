<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\Company as CompanyEntity;
use App\Domain\Repositories\CompanyRepository;
use App\Models\Company;
use Illuminate\Support\Str;

class EloquentCompanyRepository implements CompanyRepository
{
    public function findById(string $id): ?CompanyEntity
    {
        $model = Company::find($id);
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function findByCnpj(string $cnpj): ?CompanyEntity
    {
        $model = Company::where('cnpj', $cnpj)->first();
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function save(CompanyEntity $company): void
    {
        $id = $company->id ?? Str::orderedUuid()->toString();
        Company::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'name' => $company->name,
                'cnpj' => $company->cnpj,
                'type' => $company->plan,
                'created_at' => $company->createdAt ?: now(),
                'updated_at' => $company->updatedAt ?: now(),
            ]
        );
    }

    public function delete(string $id): void
    {
        Company::destroy($id);
    }

    private function toEntity(Company $model): CompanyEntity
    {
        return new CompanyEntity(
            id: $model->id,
            name: $model->name,
            cnpj: $model->cnpj,
            plan: $model->type,
            status: $model->status,
            createdAt: $model->created_at?->toIso8601String() ?? '',
            updatedAt: $model->updated_at?->toIso8601String() ?? '',
        );
    }
}
