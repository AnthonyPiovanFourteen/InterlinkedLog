<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\TrackingEvent;

interface TrackingEventRepository
{
    public function findByContract(string $contractId): array;
    public function save(TrackingEvent $event): void;
}
