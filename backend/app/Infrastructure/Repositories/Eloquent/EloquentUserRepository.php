<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\User as UserEntity;
use App\Domain\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EloquentUserRepository implements UserRepository
{
    public function findById(string $id): ?UserEntity
    {
        $model = User::find($id);
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function findByEmail(string $email): ?UserEntity
    {
        $model = User::where('email', $email)->first();
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function findByToken(string $token): ?UserEntity
    {
        $userId = Cache::get("user_token:{$token}");
        if (!$userId) return null;
        return $this->findById($userId);
    }

    public function save(UserEntity $user): void
    {
        $id = $user->id ?? Str::orderedUuid()->toString();
        User::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->passwordHash,
                'role' => $user->role,
                'status' => $user->status,
                'company_id' => $user->companyId,
                'last_access_at' => $user->lastAccessAt,
                'created_at' => $user->createdAt ?: now(),
                'updated_at' => $user->updatedAt ?: now(),
            ]
        );
    }

    public function delete(string $id): void
    {
        User::destroy($id);
    }

    public function findByCompany(string $companyId): array
    {
        return User::where('company_id', $companyId)
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function setToken(string $userId, ?string $token): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $oldToken = Cache::get("user_id_token:{$userId}");
        if ($oldToken) {
            Cache::forget("user_token:{$oldToken}");
        }

        if ($token) {
            Cache::put("user_token:{$token}", $userId, now()->addDay());
            Cache::put("user_id_token:{$userId}", $token, now()->addDay());
        } else {
            Cache::forget("user_id_token:{$userId}");
        }
    }

    private function toEntity(User $model): UserEntity
    {
        return new UserEntity(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            passwordHash: $model->password,
            role: $model->role,
            status: $model->status,
            companyId: $model->company_id,
            lastAccessAt: $model->last_access_at,
            createdAt: $model->created_at?->toIso8601String() ?? '',
            updatedAt: $model->updated_at?->toIso8601String() ?? '',
        );
    }
}
