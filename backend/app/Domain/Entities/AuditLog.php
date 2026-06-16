<?php

namespace App\Domain\Entities;

class AuditLog
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $companyId,
        public readonly string $userId,
        public readonly string $userName,
        public readonly string $module,
        public readonly string $action,
        public readonly string $entityType,
        public readonly ?string $entityId,
        public readonly ?string $oldValues,
        public readonly ?string $newValues,
        public readonly string $createdAt = '',
    ) {}

    public static function create(
        string $id,
        string $companyId,
        string $userId,
        string $userName,
        string $module,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?string $oldValues = null,
        ?string $newValues = null,
    ): self {
        return new self(
            id: $id,
            companyId: $companyId,
            userId: $userId,
            userName: $userName,
            module: $module,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }
}
