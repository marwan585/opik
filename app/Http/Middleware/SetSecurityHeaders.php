<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;

class SetSecurityHeaders
{
    /**
     * Ideally we move away from X-Frame-Options/X-XSS-Protection and implement a
     * proper standard CSP, but I can guarantee that will break for a lot of folks
     * using custom plugins and who knows what image embeds.
     *
     * We'll circle back to that at a later date when it can be more fully controlled
     * by the admin to support those cases without too much trouble.
     */
    private static array $headers = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'no-referrer-when-downgrade',
    ];

    /**
     * Enforces some basic security headers on all responses returned by the software.
     * If a header has already been set in another location within the code it will be
     * skipped over here.
     *
     * Cloud Server addition: also set aggressive no-cache headers on HTML responses
     * (text/html) so that mobile browsers (Chrome Android, Safari iOS) never serve
     * a stale HTML shell that references an old JS bundle URL. Static assets
     * (JS/CSS/images) are still cacheable by the browser because they are
     * content-hashed (e.g. bundle.ca459b3d.js).
     *
     * @param (\Closure(mixed): \Illuminate\Http\Response) $next
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $response = $next($request);

        foreach (static::$headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        // Cloud Server: prevent mobile browsers from caching HTML pages.
        // JS/CSS assets remain cached (they have content hashes in their URLs).
        $contentType = $response->headers->get('Content-Type', '');
        if (str_starts_with($contentType, 'text/html')) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
