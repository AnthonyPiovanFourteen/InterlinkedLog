<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\AuditLog as AuditLogEntity;
use App\Domain\Repositories\AuditLogRepository;
use App\Models\AuditLog;
use Illuminate\Support\Str;

class EloquentAuditLogRepository implements AuditLogRepository
{
    public function findByCompany(string $companyId): array
    {
        return AuditLog::where('company_id', $companyId)
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function save(AuditLogEntity $log): void
    {
        $id = $log->id ?? Str::orderedUuid()->toString();
        AuditLog::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'company_id' => $log->companyId,
                'user_id' => $log->userId,
                'user_name' => $log->userName,
                'module' => $log->module,
                'action' => $log->action,
                'entity_type' => $log->entityType,
                'entity_id' => $log->entityId,
                'old_values' => $log->oldValues,
                'new_values' => $log->newValues,
                'created_at' => $log->createdAt ?: now(),
            ]
        );
    }

    private function toEntity(AuditLog $model): AuditLogEntity
    {
        return new AuditLogEntity(
            id: $model->id,
            companyId: $model->company_id,
            userId: $model->user_id,
            userName: $model->user_name,
            module: $model->module,
            action: $model->action,
            entityType: $model->entity_type,
            entityId: $model->entity_id,
            oldValues: $model->old_values,
            newValues: $model->new_values,
            createdAt: $model->created_at ? (is_string($model->created_at) ? $model->created_at : $model->created_at->toIso8601String()) : '',
        );
    }
}
