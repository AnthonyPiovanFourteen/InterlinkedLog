<?php

namespace App\Models\Concerns;

use App\Infrastructure\Tenancy\TenantScope;

trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}