window._ = require('lodash');
window.axios = require('axios');
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

// Initialize Echo - using process.env for Laravel Mix (Webpack)
try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: process.env.MIX_PUSHER_APP_KEY || '3a4373231eb68d1c839d',
            cluster: process.env.MIX_PUSHER_APP_CLUSTER || 'eu',
            forceTLS: true,
            encrypted: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken.content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            },
            enabledTransports: ['ws', 'wss']
        });
    }
} catch (e) {
    console.warn('Echo initialization skipped:', e.message);
}

// ---------------------------------------
// Real-time notifications via Echo
// ---------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    const userIdMeta = document.querySelector('meta[name="user-id"]');
    if (!userIdMeta) return;

    const userId = userIdMeta.content;
    const channelName = 'user.' + userId;

    window.Echo.private(channelName)
        .listen('.notification.created', (e) => {
            console.log('New notification received:', e);

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
