<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\SystemLog as SystemLogEntity;
use App\Domain\Repositories\SystemLogRepository;
use App\Models\SystemLog;
use Illuminate\Support\Str;

class EloquentSystemLogRepository implements SystemLogRepository
{
    public function findByCompany(string $companyId): array
    {
        return SystemLog::where('company_id', $companyId)
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function save(SystemLogEntity $log): void
    {
        $id = $log->id ?? Str::orderedUuid()->toString();
        SystemLog::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'company_id' => $log->companyId,
                'user_id' => $log->userId,
                'user_name' => $log->userName,
                'level' => $log->level,
                'event' => $log->event,
                'message' => $log->message,
                'created_at' => $log->createdAt ?: now(),
            ]
        );
    }

    private function toEntity(SystemLog $model): SystemLogEntity
    {
        return new SystemLogEntity(
            id: $model->id,
            companyId: $model->company_id,
            userId: $model->user_id,
            userName: $model->user_name,
            level: $model->level,
            event: $model->event,
            message: $model->message,
            createdAt: $model->created_at ? (is_string($model->created_at) ? $model->created_at : $model->created_at->toIso8601String()) : '',
        );
    }
}
