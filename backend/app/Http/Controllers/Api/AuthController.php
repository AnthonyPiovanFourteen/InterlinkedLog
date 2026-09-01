<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Auth\LoginUserUseCase;
use App\Domain\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        private LoginUserUseCase $loginUseCase,
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

        if (! $result) {
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
