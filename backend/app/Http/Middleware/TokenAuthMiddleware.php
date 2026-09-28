<?php

namespace App\Http\Middleware;

use App\Domain\Services\AuthService;
use App\Domain\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;

class TokenAuthMiddleware
{
    public function __construct(
        private AuthService $authService,
        private TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token não informado'], 401);
        }

        $user = $this->authService->validateToken($token);

        if (! $user) {
            return response()->json(['message' => 'Token inválido ou expirado'], 401);
        }

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('user_id', $user['id']);
        $request->attributes->set('company_id', $user['company_id']);
        // O escopo de tenant passa a ler daqui, não do binding de request:
        // assim vale também em worker de fila e em comando artisan.
        $this->tenantContext->setCompanyId($user['company_id']);
        $request->attributes->set('user_role', $user['role']);

        return $next($request);
    }
}
