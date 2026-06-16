<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Contract;

interface ContractRepository
{
    public function findById(string $id): ?Contract;
    public function findByCompany(string $companyId, array $filters = []): array;
    public function save(Contract $contract): void;
}
