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
 * Cloud Server — Popup Settings Controller
 *
 * Owner-only admin menu for configuring the popup banner that users
 * see when they enter their own server page.
 */
class PopupSettingsController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private SettingsRepository $settings,
    ) {
    }

    /**
     * Render the popup settings form.
     */
    public function index(): View
    {
        return view('admin.cloud_server.popup', [
            'enabled' => (string) $this->settings->get('cloud:popup:enabled', '0') === '1',
            'imageUrl' => (string) $this->settings->get('cloud:popup:image_url', ''),
            'text' => (string) $this->settings->get('cloud:popup:text', ''),
            'title' => (string) $this->settings->get('cloud:popup:title', 'Pengumuman'),
        ]);
    }

    /**
     * Save the popup configuration. Settings are persisted to the
     * settings table and the cached config is flushed so the React
     * SPA picks up the new values on the next page load.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'   => ['nullable', 'in:1,0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'title'     => ['nullable', 'string', 'max:191'],
            'text'      => ['nullable', 'string', 'max:5000'],
        ]);

        $this->settings->set('cloud:popup:enabled', !empty($data['enabled']) ? '1' : '0');
        $this->settings->set('cloud:popup:image_url', (string) ($data['image_url'] ?? ''));
        $this->settings->set('cloud:popup:title', (string) ($data['title'] ?? ''));
        $this->settings->set('cloud:popup:text', (string) ($data['text'] ?? ''));

        // Flush the cached popup config so the React SPA picks up new values.
        Cache::forget('cloud:popup:config');

        $this->alert->success('Pengaturan popup berhasil disimpan.')->flash();

        return redirect()->route('admin.cloud.popup');
    }
}
