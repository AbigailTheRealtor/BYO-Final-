window._ = require('lodash');
window.axios = require('axios');
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

// ---------------------------------------------------------------------------
// Laravel Echo / Pusher
//
// Realtime is DORMANT unless the server says otherwise on the page itself.
//
// The server renders partials/_realtime-config.blade.php into the <head>, and it
// emits the realtime-* meta tags only when App\Support\Realtime\RealtimeClientConfig
// says realtime is on. No tags means off, and off means we construct nothing: no
// Echo, no WebSocket, no POST to /broadcasting/auth, nothing in the console.
//
// This used to read process.env.MIX_PUSHER_APP_KEY, which Laravel Mix bakes into the
// bundle at BUILD time, with a hardcoded key as the fallback. Both halves were wrong.
// The build-time read meant the bundle could keep using an app key the server had
// already moved off, with a connected-but-silent socket as the only symptom. The
// fallback meant an install with no Pusher credentials whatsoever — which is this
// one — still opened a socket to a third party's Pusher app on every page load,
// including for logged-out visitors on the login screen.
//
// Do not reintroduce a default key here. An absent credential must mean off.
// ---------------------------------------------------------------------------

function metaContent(name) {
    const tag = document.querySelector('meta[name="' + name + '"]');

    if (!tag) {
        return null;
    }

    const value = (tag.content || '').trim();

    return value === '' ? null : value;
}

function realtimeConfig() {
    // The broadcaster tag is what distinguishes "the server enabled realtime" from
    // "somebody happened to leave a key tag on the page".
    if (metaContent('realtime-broadcaster') !== 'pusher') {
        return null;
    }

    const key = metaContent('realtime-key');
    const cluster = metaContent('realtime-cluster');
    const csrfToken = metaContent('csrf-token');

    // A Pusher client is not constructible without all three: the first two are the
    // connection itself, and the third is what /broadcasting/auth will demand the
    // moment a private channel is subscribed.
    if (!key || !cluster || !csrfToken) {
        return null;
    }

    return { key, cluster, csrfToken };
}

const realtime = realtimeConfig();

if (realtime) {
    try {
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: realtime.key,
            cluster: realtime.cluster,
            forceTLS: true,
            encrypted: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': realtime.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            },
            enabledTransports: ['ws', 'wss']
        });
    } catch (e) {
        console.warn('Echo initialization skipped:', e.message);
    }
}

// ---------------------------------------
// Real-time notifications via Echo
//
// user.{id} is a PRIVATE channel, so subscribing costs a POST to /broadcasting/auth.
// A guest has no id to subscribe with, and the id must be validated rather than merely
// present: the meta tag is written as content="{{ auth()->id() }}", which renders as
// an EMPTY string for a logged-out visitor if any template ever emits it outside its
// @auth wrapper. That produced a subscription to the literal channel "user." — an
// unauthenticated auth request that can only ever be refused. The digits check below is
// what makes that structurally impossible rather than dependent on four templates all
// remembering their @auth.
// ---------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    if (!window.Echo) {
        return;
    }

    const userId = metaContent('user-id');

    if (!userId || !/^[0-9]+$/.test(userId)) {
        return;
    }

    window.Echo.private('user.' + userId)
        .listen('.notification.created', (e) => {
            // Dispatch custom event to header JS
            const event = new CustomEvent('newNotification', { detail: e });
            document.dispatchEvent(event);

            // Browser notification
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('New Notification', { body: e.data?.message || e.message });
            }
        });

    // Request browser notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
});
