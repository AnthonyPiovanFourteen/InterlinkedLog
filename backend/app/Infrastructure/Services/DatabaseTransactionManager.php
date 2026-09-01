<?php

namespace App\Infrastructure\Services;

use App\Domain\Services\TransactionManager;
use Illuminate\Support\Facades\DB;

class DatabaseTransactionManager implements TransactionManager
{
    public function run(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}
