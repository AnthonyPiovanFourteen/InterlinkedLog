<?php

namespace App\Infrastructure\Tenancy;

use App\Domain\Services\TenantContext;

/** Escopo do processo: registrado como singleton no container. */
class RuntimeTenantContext implements TenantContext
{
    private ?string $companyId = null;

    public function companyId(): ?string
    {
        return $this->companyId;
    }

    public function setCompanyId(?string $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function runAs(string $companyId, callable $operation): mixed
    {
        $previous = $this->companyId;
        $this->companyId = $companyId;

        try {
            return $operation();
        } finally {
            $this->companyId = $previous;
        }
    }
}
