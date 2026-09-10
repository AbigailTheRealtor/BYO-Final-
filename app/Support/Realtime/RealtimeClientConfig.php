<?php

namespace App\Support\Realtime;

use Throwable;

/**
 * RealtimeClientConfig — whether a browser may open a realtime socket, and with what.
 *
 * THE ONE READER of config/broadcasting.php's 'client' block, and the only thing that
 * answers "is realtime on?" for the front end. A Blade layout asks it; nothing else does.
 *
 *
 * ── WHY THE BROWSER MUST BE TOLD AT RUN TIME ────────────────────────────────────────────
 *
 * The client used to configure itself at BUILD time. resources/js/bootstrap.js read
 * process.env.MIX_PUSHER_APP_KEY, which Laravel Mix bakes into the bundle when `npm run
 * production` runs, while the server read PUSHER_APP_KEY from the environment when a
 * request arrived. Those are two different moments reading two different variables, and on
 * this deployment they are not even the same machine state: the container is rebuilt from
 * the image and the environment comes from Replit Secrets, so the bundle carries whatever
 * was visible during the build phase for as long as it is not rebuilt.
 *
 * That is a mismatch with no symptom. A stale bundle keeps talking to the previous app key
 * after the server has moved to a new one, and the only evidence is a socket that connects
 * and receives nothing. So the page now carries the answer: the same PUSHER_APP_KEY the
 * 'pusher' connection uses is rendered into the response that needs it, and there is no
 * second source left to drift.
 *
 *
 * ── THE GATE IS EXPLICIT, AND COMPLETENESS IS NOT INFERENCE ─────────────────────────────
 *
 * enabled() requires BROADCAST_CLIENT_ENABLED to be set true. It is never inferred from a
 * key being present, because "someone put a Pusher key in the environment" and "we intend
 * browsers to open sockets" are different decisions, and the first is a thing a person does
 * while trying things out.
 *
 * Given that opt-in, the key and cluster must both be non-empty — not as a second gate but
 * because there is no such thing as a Pusher client without them. The old code answered
 * that completeness check with a hardcoded fallback key, so an install with no credentials
 * at all still opened a socket to a stranger's Pusher app on every page load, guests
 * included. Missing credentials now mean OFF.
 *
 * And the server driver must actually be 'pusher'. Under BROADCAST_DRIVER=log or =null no
 * event ever reaches Pusher, so a browser socket is guaranteed to sit silent — a connection
 * that cannot possibly carry anything is not a feature, it is a leak of the fact that
 * somebody set half of a switch.
 *
 * Every one of those reads FAILS CLOSED. A config that did not load, or a container that is
 * not booted, is indistinguishable from one that says nothing, and "nothing" here must mean
 * "do not open a socket".
 */
final class RealtimeClientConfig
{
    /**
     * Is realtime switched on and completely configured?
     *
     * Every clause must hold. See the class docblock for why none of them is redundant.
     */
    public static function enabled(): bool
    {
        try {
            if (! self::conf('broadcasting.client.enabled', false)) {
                return false;
            }

            if (self::conf('broadcasting.default', null) !== 'pusher') {
                return false;
            }

            if (self::key() === null || self::cluster() === null) {
                return false;
            }

            return self::serverCredentialsPresent();
        } catch (Throwable $e) {
            // Fail closed. An unreadable config must not be read as permission.
            return false;
        }
    }

    /**
     * The public Pusher app key, or null when it is absent or blank.
     *
     * Public by design: Pusher hands this to every browser that connects. The secret is
     * PUSHER_APP_SECRET, it is not in the 'client' block, and it must never be rendered.
     */
    public static function key(): ?string
    {
        return self::nonEmpty(self::conf('broadcasting.client.key', null));
    }

    /** The Pusher cluster, or null when it is absent or blank. */
    public static function cluster(): ?string
    {
        return self::nonEmpty(self::conf('broadcasting.client.cluster', null));
    }

    /**
     * Does the SERVER half of the Pusher connection have the credentials it needs?
     *
     * Returns a boolean and nothing else. It reads whether the secret and app id are
     * present; it never returns them, and no caller can obtain them through this class.
     *
     * Two reasons this belongs in the same gate:
     *
     * A browser socket is pointless without them — the server cannot publish, so the
     * client connects and receives nothing, which is the exact mismatch this class exists
     * to prevent. Half a switch is not a feature.
     *
     * And it is load-bearing for availability. BroadcastServiceProvider requires
     * routes/channels.php, and Broadcast::channel() resolves the broadcaster, which
     * constructs Pusher\Pusher — whose constructor is typed `string $secret`. A null there
     * is a TypeError thrown during provider boot, which is not a broken notification, it
     * is HTTP 500 on every page in the application. So an incomplete configuration must
     * read as OFF, before anything tries to build a client out of it.
     */
    private static function serverCredentialsPresent(): bool
    {
        return self::nonEmpty(self::conf('broadcasting.connections.pusher.app_id', null)) !== null
            && self::nonEmpty(self::conf('broadcasting.connections.pusher.secret', null)) !== null;
    }

    /**
     * Read config through the container when there is one, and treat its absence as silence.
     *
     * Same trap as LandlordScreeningPolicy::conf(): this class is called from Blade, so it
     * can also be reached by a unit test that never booted an application. There, config()
     * raises rather than returning a default — and a raised exception inside a layout is a
     * blank page, which is a much worse outcome than "realtime is off".
     *
     * @param  mixed  $default
     * @return mixed
     */
    private static function conf(string $path, $default)
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return $default;
        }

        return config($path, $default);
    }

    /**
     * @param  mixed  $value
     */
    private static function nonEmpty($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
