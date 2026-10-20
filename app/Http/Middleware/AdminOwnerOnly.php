<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        return response()->view('admin.violation', [
            'ownerId' => self::ownerId(),
            'attemptedRoute' => $request->path(),
            'adminId' => $user->id,
            'adminName' => $user->username ?? $user->email,
        ], 403);
    }
}
