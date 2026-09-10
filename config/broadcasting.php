<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Browser Client
    |--------------------------------------------------------------------------
    |
    | Whether the compiled front-end bundle may construct Laravel Echo and open a
    | Pusher WebSocket. This is a SEPARATE, EXPLICIT decision from 'default' above,
    | and it is read in exactly one place: App\Support\Realtime\RealtimeClientConfig.
    |
    | It is its own switch because the browser and the server used to be configured
    | from different sources — the client key was baked into the bundle at BUILD time
    | from MIX_PUSHER_APP_KEY, while the server read PUSHER_APP_KEY at RUN time — so
    | the two could disagree with nothing anywhere to notice it. The credentials
    | below are therefore the very same env keys the 'pusher' connection uses; there
    | is no second source left for them to drift from.
    |
    | Default false. An unconfigured install must not open a socket.
    |
    */

    'client' => [
        'enabled' => env('BROADCAST_CLIENT_ENABLED', false),

        // Deliberately the same env keys as the 'pusher' connection below. The app
        // key is public by design — it is handed to every browser — and the secret
        // is not here and must never be.
        'key' => env('PUSHER_APP_KEY'),
        'cluster' => env('PUSHER_APP_CLUSTER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER', 'mt1') . '.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
