<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\SystemLog;

interface SystemLogRepository
{
    public function findByCompany(string $companyId): array;
    public function save(SystemLog $log): void;
}
