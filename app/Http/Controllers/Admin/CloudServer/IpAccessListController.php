<?php

namespace Pterodactyl\Http\Controllers\Admin\CloudServer;

use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Models\User;

/**
 * Cloud Server — IP Access List Controller
 *
 * Shows every IP address that has successfully authenticated against
 * the panel, with the user that logged in, the user-agent, and the
 * last-seen timestamp. Data is sourced from the existing activity_logs
 * table (no new migration needed).
 */
class IpAccessListController extends Controller
{
    public function index(): View
    {
        // Query distinct (ip, user) combinations from successful logins.
        $rows = DB::table('activity_logs')
            ->select(
                'ip',
                DB::raw('MAX(timestamp) as last_seen'),
                DB::raw('MIN(timestamp) as first_seen'),
                DB::raw('COUNT(*) as login_count'),
                'actor_id'
            )
            ->where('event', 'auth:success')
            ->where('actor_type', User::class)
            ->groupBy('ip', 'actor_id')
            ->orderByDesc('last_seen')
            ->limit(500)
            ->get();

        // Eager-load user data for the actor_ids we got back.
        $userIds = $rows->pluck('actor_id')->unique()->filter()->values()->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Also extract useragent from properties JSON for the most recent row.
        // We do this in PHP because MySQL JSON functions vary by version.
        $agents = DB::table('activity_logs')
            ->select('ip', 'actor_id', 'properties')
            ->where('event', 'auth:success')
            ->where('actor_type', User::class)
            ->orderByDesc('timestamp')
            ->get()
            ->keyBy(function ($r) { return $r->ip . '|' . $r->actor_id; });

        $entries = $rows->map(function ($r) use ($users, $agents) {
            $agentJson = $agents->get($r->ip . '|' . $r->actor_id)?->properties ?? '{}';
            $decoded = is_string($agentJson) ? json_decode($agentJson, true) : (array) $agentJson;
            $userAgent = $decoded['useragent'] ?? '';
            $user = $users->get($r->actor_id);
            return (object) [
                'ip' => $r->ip,
                'user' => $user,
                'last_seen' => $r->last_seen,
                'first_seen' => $r->first_seen,
                'login_count' => $r->login_count,
                'user_agent' => $userAgent,
            ];
        });

        return view('admin.cloud_server.ip_access', [
            'entries' => $entries,
        ]);
    }
}
