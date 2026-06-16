<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Company;

interface CompanyRepository
{
    public function findById(string $id): ?Company;
    public function findByCnpj(string $cnpj): ?Company;
    public function save(Company $company): void;
    public function delete(string $id): void;
}
