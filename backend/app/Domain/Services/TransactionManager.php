<?php

namespace App\Domain\Services;

interface TransactionManager
{
    public function run(callable $operation): mixed;
}
