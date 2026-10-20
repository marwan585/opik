<?php

namespace Pterodactyl\Listeners;

use Pterodactyl\Facades\Activity;
use Illuminate\Auth\Events\Failed;
use Pterodactyl\Events\Auth\DirectLogin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Pterodactyl\Extensions\Illuminate\Events\Contracts\SubscribesToEvents;

class AuthenticationListener implements SubscribesToEvents
{
    /**
     * Handles an authentication event by logging the user and information about
     * the request.
     */
    public function login(Failed|DirectLogin $event): void
    {
        $activity = Activity::withRequestMetadata();
        if ($event->user) {
            // Cloud Server: explicitly set the ACTOR so that activity_logs.actor_type
            // and actor_id columns are populated. Without this, the actor stays NULL
            // for auth events because the auth guard may not yet have the user bound
            // at the moment the event fires (e.g. for the DirectLogin event fired
            // during the login flow).
            $activity = $activity->subject($event->user)
                                 ->actor($event->user);
        }

        if ($event instanceof Failed) {
            foreach ($event->credentials as $key => $value) {
                // Cloud Server: never persist the plaintext password into the
                // activity log properties JSON, even in development.
                if ($key === 'password') {
                    continue;
                }
                $activity = $activity->property($key, $value);
            }
        }

        $activity->event($event instanceof Failed ? 'auth:fail' : 'auth:success')->log();
    }

    public function reset(PasswordReset $event): void
    {
        // Cloud Server: explicitly set the actor on password reset events too
        // so the activity log entry has proper actor_type/actor_id columns.
        Activity::event('event:password-reset')
            ->withRequestMetadata()
            ->subject($event->user)
            ->actor($event->user)
            ->log();
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Failed::class, [self::class, 'login']);
        $events->listen(DirectLogin::class, [self::class, 'login']);
        $events->listen(PasswordReset::class, [self::class, 'reset']);
    }
}
