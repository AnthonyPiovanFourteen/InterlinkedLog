<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\SystemLog;
use App\Domain\Repositories\SystemLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SystemLogController extends Controller
{
    public function __construct(
        private SystemLogRepository $systemLogRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $logs = $this->systemLogRepository->findByCompany($companyId);

        usort($logs, fn(SystemLog $a, SystemLog $b) => $b->createdAt <=> $a->createdAt);

        $data = array_map(fn(SystemLog $l) => [
            'id' => $l->id,
            'user_id' => $l->userId,
            'user_name' => $l->userName,
            'level' => $l->level,
            'event' => $l->event,
            'message' => $l->message,
            'created_at' => $l->createdAt,
        ], $logs);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $user = $request->attributes->get('auth_user');

        $validator = Validator::make($request->all(), [
            'level' => 'required|string|in:INFO,WARNING,ERROR',
            'event' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $log = SystemLog::create(
            id: uuid_create(),
            companyId: $companyId,
            userId: $user['id'] ?? '',
            userName: $user['name'] ?? 'Sistema',
            level: $request->input('level'),
            event: $request->input('event'),
            message: $request->input('message'),
        );

        $this->systemLogRepository->save($log);

        return response()->json([
            'data' => ['id' => $log->id],
        ], 201);
    }
}
