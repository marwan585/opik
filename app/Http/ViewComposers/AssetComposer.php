<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Helpers\AssetHashService;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;

class AssetComposer
{
    /**
     * AssetComposer constructor.
     */
    public function __construct(
        private AssetHashService $assetHashService,
        private SettingsRepository $settings,
    ) {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $view->with('asset', $this->assetHashService);
        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Pterodactyl',
            'locale' => config('app.locale') ?? 'en',
            'recaptcha' => [
                'enabled' => config('recaptcha.enabled', false),
                'siteKey' => config('recaptcha.website_key') ?? '',
            ],
        ]);

        // Cloud Server popup banner configuration — read from settings
        // table (cached for 60s to avoid hitting the DB on every request).
        // The React SPA reads window.CloudServerPopup on every server page
        // load and shows the banner if enabled.
        $popup = Cache::remember('cloud:popup:config', 60, function () {
            return [
                'enabled' => (string) $this->settings->get('cloud:popup:enabled', '0') === '1',
                'image_url' => (string) $this->settings->get('cloud:popup:image_url', ''),
                'text' => (string) $this->settings->get('cloud:popup:text', ''),
                'title' => (string) $this->settings->get('cloud:popup:title', 'Pengumuman'),
            ];
        });

        $view->with('cloudServerPopup', $popup);
    }
}
