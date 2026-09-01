<?php

namespace App\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Request;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(Request::class)->attributes->get('company_id');

        if ($companyId) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
    }
}
