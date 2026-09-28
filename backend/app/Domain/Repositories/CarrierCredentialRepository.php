<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\CarrierCredential;

interface CarrierCredentialRepository
{
    /** Credenciais ativas do tenant, indexadas por carrier_id. */
    public function activeForCompany(string $companyId): array;

    public function findForCarrier(string $companyId, string $carrierId): ?CarrierCredential;

    /** Todas as credenciais do tenant, ativas ou não, para listagem. */
    public function allForCompany(string $companyId): array;

    public function findById(string $companyId, string $id): ?CarrierCredential;

    public function save(CarrierCredential $credential): void;

    public function delete(string $companyId, string $id): void;
}
