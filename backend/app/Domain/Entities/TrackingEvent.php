<?php

namespace App\Domain\Entities;

class TrackingEvent
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $contractId,
        public readonly string $title,
        public readonly string $date,
        public readonly string $time,
        public readonly ?string $observation,
        public readonly string $createdAt = '',
    ) {}

    public static function create(
        string $id, string $contractId, string $title, string $date, string $time, ?string $observation = null,
    ): self {
        return new self(
            id: $id,
            contractId: $contractId,
            title: $title,
            date: $date,
            time: $time,
            observation: $observation,
        );
    }
}
