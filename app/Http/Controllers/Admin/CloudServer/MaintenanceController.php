<?php

namespace Pterodactyl\Http\Controllers\Admin\CloudServer;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Cloud Server — Maintenance Mode Controller
 *
 * Owner-only admin menu for toggling panel-wide maintenance mode.
 *
 * When maintenance is ON:
 *  - Regular users and other admins (id != 1) cannot access the panel
 *    SPA at all. They see a maintenance page instead.
 *  - The owner (ID 1) can still access everything.
 *  - Admin area (/admin/*) remains accessible to the owner.
 *  - API endpoints (/api/*) remain accessible (so wings + existing
 *    servers keep working — only the SPA UI is locked down).
 */
class MaintenanceController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private SettingsRepository $settings,
    ) {
    }

    public function index(): View
    {
        return view('admin.cloud_server.maintenance', [
            'enabled' => (string) $this->settings->get('cloud:maintenance:enabled', '0') === '1',
            'message' => (string) $this->settings->get('cloud:maintenance:message', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'  => ['nullable', 'in:1,0'],
            'message'  => ['nullable', 'string', 'max:1000'],
        ]);

        $enabled = !empty($data['enabled']);
        $this->settings->set('cloud:maintenance:enabled', $enabled ? '1' : '0');
        $this->settings->set('cloud:maintenance:message', (string) ($data['message'] ?? ''));

        Cache::forget('cloud:maintenance:state');

        $this->alert->success($enabled
            ? 'Maintenance mode AKTIF. User dan admin lain tidak bisa akses panel.'
            : 'Maintenance mode NONAKTIF. Panel kembali normal.'
        )->flash();

        return redirect()->route('admin.cloud.maintenance');
    }
}
