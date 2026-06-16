<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\Role;
use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct(private UserRepository $userRepository) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $users = $this->userRepository->findByCompany($companyId);

        $data = array_map(fn(User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'status' => $u->status,
            'company_id' => $u->companyId,
            'last_access_at' => $u->lastAccessAt,
            'created_at' => $u->createdAt,
        ], $users);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:' . implode(',', Role::all()),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($this->userRepository->findByEmail($request->input('email'))) {
            return response()->json(['message' => 'Email já cadastrado'], 422);
        }

        $user = User::create(
            id: uuid_create(),
            name: $request->input('name'),
            email: $request->input('email'),
            passwordHash: password_hash($request->input('password'), PASSWORD_BCRYPT),
            role: $request->input('role'),
            companyId: $companyId,
        );

        $this->userRepository->save($user);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $user = $this->userRepository->findById($id);

        if (!$user || $user->companyId !== $companyId) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'company_id' => $user->companyId,
                'last_access_at' => $user->lastAccessAt,
                'created_at' => $user->createdAt,
                'updated_at' => $user->updatedAt,
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $user = $this->userRepository->findById($id);

        if (!$user || $user->companyId !== $companyId) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:' . implode(',', Role::all()),
            'status' => 'sometimes|string|in:Ativo,Inativo',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = new User(
            id: $user->id,
            name: $request->input('name', $user->name),
            email: $user->email,
            passwordHash: $user->passwordHash,
            role: $request->input('role', $user->role),
            status: $request->input('status', $user->status),
            companyId: $user->companyId,
            lastAccessAt: $user->lastAccessAt,
            createdAt: $user->createdAt,
            updatedAt: now()->toIso8601String(),
        );

        $this->userRepository->save($updated);

        return response()->json([
            'data' => [
                'id' => $updated->id,
                'name' => $updated->name,
                'email' => $updated->email,
                'role' => $updated->role,
                'status' => $updated->status,
            ],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $user = $this->userRepository->findById($id);

        if (!$user || $user->companyId !== $companyId) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $currentUserId = $request->attributes->get('user_id');
        if ($id === $currentUserId) {
            return response()->json(['message' => 'Não é possível excluir seu próprio usuário'], 422);
        }

        $this->userRepository->delete($id);

        return response()->json(['message' => 'Usuário removido com sucesso']);
    }
}
