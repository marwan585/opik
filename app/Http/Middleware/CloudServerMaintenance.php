<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Closure;

/**
 * Cloud Server — Maintenance Mode Middleware
 *
 * If the panel owner (admin ID 1) has toggled maintenance mode ON,
 * block all SPA UI access for non-owner users. The owner still has
 * full access. API endpoints are NOT blocked so wings + existing
 * servers keep running.
 *
 * Behavior:
 *  - Authenticated user with id == ownerId → allowed.
 *  - Authenticated user with id != ownerId → redirect to maintenance page.
 *  - Guest → redirect to maintenance page (no login form).
 *  - AJAX / API requests (Accept: application/json or /api/* path)
 *    → 503 JSON response.
 *
 * Only applies to the SPA routes (handled by IndexController) and
 * auth routes. Admin area is protected separately by AdminOwnerOnly
 * (which already only allows the owner).
 */
class CloudServerMaintenance
{
    public function __construct(private SettingsRepository $settings)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        // Check maintenance state from cache (cached 60s to avoid DB hit on every request)
        $enabled = Cache::remember('cloud:maintenance:state', 60, function () {
            return (string) $this->settings->get('cloud:maintenance:enabled', '0') === '1';
        });

        if (!$enabled) {
            return $next($request);
        }

        $user = Auth::user();

        // Owner is always allowed
        if ($user && AdminOwnerOnly::isOwner($user->id)) {
            return $next($request);
        }

        // Allow the admin login route so the owner can log in during maintenance
        // (the admin area is gated separately by AdminOwnerOnly).
        if ($request->is('admin/*') || $request->is('api/*')) {
            // Admin routes are gated by AdminOwnerOnly middleware; let them through here.
            // API routes must keep working for wings + existing client API users.
            return $next($request);
        }

        // Block everything else (SPA routes, auth routes except admin login).
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Panel sedang dalam mode maintenance. Silakan coba lagi nanti.',
                'maintenance' => true,
            ], 503);
        }

        // Show custom maintenance page for HTML requests
        $message = (string) $this->settings->get('cloud:maintenance:message', 'Panel sedang dalam perbaikan. Silakan coba lagi nanti.');

        return response()->view('errors.maintenance', ['message' => $message], 503);
    }
}
