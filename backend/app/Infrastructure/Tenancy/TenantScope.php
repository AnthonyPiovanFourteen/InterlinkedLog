<?php

namespace App\Infrastructure\Tenancy;

use App\Domain\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(TenantContext::class)->companyId();

        if ($companyId) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
    }
}
