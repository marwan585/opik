<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cloud Server — Owner-Only Access Middleware
 *
 * Only the primary owner (user ID 1, also referenced as "admin ID 1" or
 * "server ID 1" in the fork requirements) is allowed to access protected
 * admin menus. Any other admin (root_admin = 1 but id != 1) is blocked
 * and shown the violation page.
 *
 * Protected menus (configured in routes/admin.php via this middleware):
 *   - Locations
 *   - Databases
 *   - Nodes
 *   - Mounts
 *   - Nests
 *   - Settings
 *   - Users (entire menu — view, create, edit, delete)
 *   - User delete
 *   - Server delete
 *   - Per-user server view (admin/servers/view/{server})
 *
 * The owner ID can be overridden via the CLOUD_SERVER_OWNER_ID env var
 * (defaults to 1) so deployments that already have a different primary
 * admin can still use this middleware.
 */
class AdminOwnerOnly
{
    /**
     * The user ID that owns / fully controls the panel.
     * Defaults to 1, can be overridden via env.
     */
    public static function ownerId(): int
    {
        return (int) env('CLOUD_SERVER_OWNER_ID', 1);
    }

    /**
     * Returns true if the given user ID is the panel owner.
     */
    public static function isOwner(?int $userId): bool
    {
        return $userId !== null && $userId === self::ownerId();
    }

    /**
     * Handle an incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();

        // If not logged in or not root admin → let AdminAuthenticate handle it.
        if (!$user || !$user->root_admin) {
            return $next($request);
        }

        // If owner → allow.
        if (self::isOwner($user->id)) {
            return $next($request);
        }

        // Otherwise this is a non-owner admin trying to access a protected route.
        // Show a 403 page with a violation message instead of a generic 403.
        if ($request->expectsJson() || $request->is('api/*')) {
            throw new AccessDeniedHttpException(
                'Akses Ditolak. Menu ini hanya dapat diakses oleh Admin Utama (ID 1).'
            );
        }

        // Render the dedicated violation page for HTML requests.
        // We wrap this in a try/catch so that even if the view fails to
        // render for any reason, the user still gets a clear 403 response
        // instead of a confusing 500 error.
        try {
            return response()->view('admin.violation', [
                'ownerId' => self::ownerId(),
                'attemptedRoute' => $request->path(),
                'adminId' => $user->id,
                'adminName' => $user->username ?? $user->email ?? 'Unknown',
            ], 403);
        } catch (\Throwable $e) {
            // Log the rendering failure so the panel owner can debug.
            Log::error('Cloud Server AdminOwnerOnly: failed to render violation view', [
                'error' => $e->getMessage(),
                'admin_id' => $user->id,
                'route' => $request->path(),
            ]);

            // Fallback: simple plain-text 403 response.
            return response(
                '<!DOCTYPE html><html><head><title>Akses Ditolak</title>' .
                '<style>body{font-family:sans-serif;background:#1a2332;color:#ecf0f1;' .
                'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}' .
                '.box{text-align:center;padding:40px;background:#2c3e50;border-radius:8px;' .
                'border-top:4px solid #e74c3c;max-width:500px;}' .
                'h1{color:#e74c3c;margin:0 0 10px;font-size:48px;}' .
                'p{color:#bdc3c7;line-height:1.6;}</style></head><body>' .
                '<div class="box"><h1>403</h1>' .
                '<h2>Akses Ditolak</h2>' .
                '<p>Menu ini hanya dapat diakses oleh Admin Utama (ID 1).<br>' .
                'Tindakan Anda telah dicatat di audit log.</p>' .
                '<p><a href="' . route('admin.index') . '" style="color:#3498db;">' .
                'Kembali ke Dashboard</a></p>' .
                '</div></body></html>',
                403,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }
    }
}
