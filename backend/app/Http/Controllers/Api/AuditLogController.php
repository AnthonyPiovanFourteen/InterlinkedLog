<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\AuditLog;
use App\Domain\Repositories\AuditLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $logs = $this->auditLogRepository->findByCompany($companyId);

        usort($logs, fn(AuditLog $a, AuditLog $b) => $b->createdAt <=> $a->createdAt);

        $data = array_map(fn(AuditLog $l) => [
            'id' => $l->id,
            'user_id' => $l->userId,
            'user_name' => $l->userName,
            'module' => $l->module,
            'action' => $l->action,
            'entity_type' => $l->entityType,
            'entity_id' => $l->entityId,
            'old_values' => $l->oldValues,
            'new_values' => $l->newValues,
            'created_at' => $l->createdAt,
        ], $logs);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $user = $request->attributes->get('auth_user');

        $validator = Validator::make($request->all(), [
            'module' => 'required|string|max:255',
            'action' => 'required|string|max:255',
            'entity_type' => 'required|string|max:255',
            'entity_id' => 'nullable|string|max:255',
            'old_values' => 'nullable|string',
            'new_values' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $log = AuditLog::create(
            id: uuid_create(),
            companyId: $companyId,
            userId: $user['id'] ?? '',
            userName: $user['name'] ?? 'Sistema',
            module: $request->input('module'),
            action: $request->input('action'),
            entityType: $request->input('entity_type'),
            entityId: $request->input('entity_id'),
            oldValues: $request->input('old_values'),
            newValues: $request->input('new_values'),
        );

        $this->auditLogRepository->save($log);

        return response()->json([
            'data' => ['id' => $log->id],
        ], 201);
    }
}
