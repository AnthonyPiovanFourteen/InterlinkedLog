<?php

namespace App\Domain\Services;

interface ReportService
{
    public function dashboard(string $companyId): array;
    public function detailed(string $companyId): array;
    public function carrierPerformance(string $carrierId, string $companyId): array;
}
