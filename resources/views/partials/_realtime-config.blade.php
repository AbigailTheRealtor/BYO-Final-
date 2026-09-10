{{--
    Realtime (Laravel Echo / Pusher) client configuration.

    Included in the <head> of every layout that loads the compiled js/app.js bundle:
    layouts/main, layouts/master, layouts/app, layouts/guest. Those four are the whole
    list — resources/js/bootstrap.js runs wherever that bundle does, so a layout that
    loads the bundle without this partial is a layout where realtime silently cannot work.

    When realtime is off — which is the shipped default — NOTHING is emitted, and
    bootstrap.js finds no configuration and constructs no Echo instance. That is the
    entire mechanism: the absence of these tags is the "off" signal, so an unconfigured
    install cannot open a socket even if the bundle it is serving was built somewhere else.

    The key rendered here is the PUBLIC Pusher app key, which Pusher hands to every
    connecting browser by design. PUSHER_APP_SECRET is not in the config block this
    reads and must never appear on a page.
--}}
@if (\App\Support\Realtime\RealtimeClientConfig::enabled())
    <meta name="realtime-broadcaster" content="pusher">
    <meta name="realtime-key" content="{{ \App\Support\Realtime\RealtimeClientConfig::key() }}">
    <meta name="realtime-cluster" content="{{ \App\Support\Realtime\RealtimeClientConfig::cluster() }}">
@endif
