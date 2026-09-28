<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\CarrierCredential;

interface CarrierCredentialRepository
{
    /** Credenciais ativas do tenant, indexadas por carrier_id. */
    public function activeForCompany(string $companyId): array;

    public function findForCarrier(string $companyId, string $carrierId): ?CarrierCredential;

    public function save(CarrierCredential $credential): void;
}
