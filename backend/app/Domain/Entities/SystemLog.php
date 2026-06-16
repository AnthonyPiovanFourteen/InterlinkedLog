<?php

namespace App\Domain\Entities;

class SystemLog
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $companyId,
        public readonly string $userId,
        public readonly string $userName,
        public readonly string $level,
        public readonly string $event,
        public readonly string $message,
        public readonly string $createdAt = '',
    ) {}

    public static function create(
        string $id,
        string $companyId,
        string $userId,
        string $userName,
        string $level,
        string $event,
        string $message,
    ): self {
        return new self(
            id: $id,
            companyId: $companyId,
            userId: $userId,
            userName: $userName,
            level: $level,
            event: $event,
            message: $message,
        );
    }
}
