<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $companyId = $request->attributes->get('company_id');

        if (!$companyId) {
            return response()->json(['message' => 'Empresa não identificada'], 403);
        }

        return $next($request);
    }
}
