<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Tenant\TenantStatus;
use App\Models\Master\Tenant;
use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Resolver Middleware
 * 
 * Resolves the current tenant from the request and sets up the tenant context.
 * Must be applied to all routes that require tenant isolation.
 * 
 * @package App\Http\Middleware
 */
class TenantResolverMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly TenantResolver $resolver
    ) {
    }

    /**
     * Handle an incoming request.
     * 
     * @param Request $request
     * @param Closure(Request): Response $next
     * @param string|null $requireActive Whether to require active tenant ('require_active')
     */
    public function handle(Request $request, Closure $next, ?string $requireActive = null): Response
    {
        // Skip if tenant is already resolved (e.g., from previous middleware)
        if (TenantContext::hasTenant()) {
            return $next($request);
        }

        // Resolve tenant from request
        $tenant = $this->resolver->resolve($request);

        // Handle tenant not found
        if ($tenant === null) {
            return $this->handleTenantNotFound($request);
        }

        // Validate tenant status
        $validationResult = $this->validateTenant($tenant, $requireActive);
        if ($validationResult !== null) {
            return $validationResult;
        }

        // Set up tenant context
        $this->setupTenantContext($request, $tenant);

        // Store in session for subsequent requests
        $this->resolver->storeInSession($request, $tenant);

        return $next($request);
    }

    /**
     * Set up the tenant context.
     */
    private function setupTenantContext(Request $request, Tenant $tenant): void
    {
        // Set tenant context
        TenantContext::set($tenant);

        // Configure database connection
        $this->resolver->configureDatabase($tenant);

        // Set application locale
        app()->setLocale(TenantContext::getLocale());

        // Set timezone for this request
        date_default_timezone_set(TenantContext::getTimezone());
    }

    /**
     * Validate tenant status.
     */
    private function validateTenant(Tenant $tenant, ?string $requireActive): ?Response
    {
        // Check if tenant is terminated
        if ($tenant->status === TenantStatus::TERMINATED) {
            return $this->errorResponse(
                'Tenant account has been terminated.',
                Response::HTTP_GONE
            );
        }

        // Check if tenant requires activation
        if ($requireActive === 'require_active' || $requireActive === null) {
            // Check if tenant is suspended
            if ($tenant->status === TenantStatus::SUSPENDED) {
                return $this->errorResponse(
                    'Tenant account has been suspended. Reason: ' . $tenant->suspension_reason,
                    Response::HTTP_FORBIDDEN
                );
            }

            // Check if tenant is pending
            if ($tenant->status === TenantStatus::PENDING) {
                return $this->errorResponse(
                    'Tenant account is pending activation.',
                    Response::HTTP_FORBIDDEN
                );
            }

            // Check subscription status
            if (!$tenant->subscription_status->isActive()) {
                if ($tenant->subscription_status->value === 'past_due') {
                    return $this->errorResponse(
                        'Subscription payment is past due. Please update your payment method.',
                        Response::HTTP_PAYMENT_REQUIRED
                    );
                }

                if ($tenant->subscription_status->value === 'cancelled') {
                    return $this->errorResponse(
                        'Subscription has been cancelled.',
                        Response::HTTP_FORBIDDEN
                    );
                }

                return $this->errorResponse(
                    'Subscription is not active.',
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        return null;
    }

    /**
     * Handle tenant not found.
     */
    private function handleTenantNotFound(Request $request): Response
    {
        // If this is an API request, return JSON error
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->errorResponse(
                'Tenant not found. Please provide a valid tenant identifier.',
                Response::HTTP_NOT_FOUND
            );
        }

        // For web requests, redirect to main site or show error
        return redirect()->away(config('app.main_site_url', 'https://mimaconnect.com'))
            ->with('error', 'Company not found. Please check your URL.');
    }

    /**
     * Create an error response.
     */
    private function errorResponse(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TENANT_ERROR',
                'message' => $message,
            ],
        ], $status);
    }
}
