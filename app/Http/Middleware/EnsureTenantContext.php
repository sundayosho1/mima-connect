<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Tenant Context Middleware
 * 
 * Ensures that a tenant context is set before processing the request.
 * Use this middleware for routes that require tenant isolation.
 * 
 * @package App\Http\Middleware
 */
class EnsureTenantContext
{
    /**
     * Handle an incoming request.
     * 
     * @param Request $request
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!TenantContext::hasTenant()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_TENANT_CONTEXT',
                    'message' => 'No tenant context is set. Please ensure you are accessing through a valid tenant subdomain or provide a tenant identifier.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
