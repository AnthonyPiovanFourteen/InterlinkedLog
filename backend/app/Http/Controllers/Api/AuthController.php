<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Auth\LoginUserUseCase;
use App\Application\UseCases\Auth\RegisterUserUseCase;
use App\Domain\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        private LoginUserUseCase $loginUseCase,
        private RegisterUserUseCase $registerUseCase,
        private AuthService $authService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->loginUseCase->execute(
            $request->input('email'),
            $request->input('password'),
        );

        if (!$result) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        return response()->json([
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role' => $result['user']->role,
                'company_id' => $result['user']->companyId,
                'status' => $result['user']->status,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique_check:users',
            'password' => 'required|string|min:6',
            'company_name' => 'required|string|max:255',
            'company_cnpj' => 'nullable|string|max:18',
            'role' => 'nullable|string|in:Admin,Usuário',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->registerUseCase->execute($request->all());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role' => $result['user']->role,
            ],
            'company' => [
                'id' => $result['company']->id,
                'name' => $result['company']->name,
                'cnpj' => $result['company']->cnpj,
            ],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $this->authService->logout($token);

        return response()->json(['message' => 'Logout realizado com sucesso']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        return response()->json($user);
    }
}
