<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\AuditLog;

interface AuditLogRepository
{
    public function findByCompany(string $companyId): array;
    public function save(AuditLog $log): void;
}
