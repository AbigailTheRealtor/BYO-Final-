{{--
    REFERENCE ONLY — do not @include this partial directly.

    Blade @include does not propagate variables defined inside an included view
    back to the parent scope, so $notificationTypeMessages would be undefined
    in the parent after inclusion. The map below is therefore inlined via @php
    blocks in the two consumers:

        resources/views/dashboard.blade.php          (before the @foreach loop)
        resources/views/layouts/partials/header.blade.php  (before the @forelse loop and in renderNotifications() JS)

    Keep this file as the single authoritative list of type→message entries.
    When adding a new notification type, update all three locations.
--}}
@php
$notificationTypeMessages = [
    'bid_submitted'          => 'New bid received on your listing.',
    'bid_accepted'           => 'Your bid was accepted.',
    'bid_rejected'           => 'Your bid was rejected.',
    'bid_modified'           => 'A bid on your listing was updated.',
    'counter_bid_submitted'  => 'You received a counter proposal.',
    'counter_bid_accepted'   => 'Your counter proposal was accepted.',
    'counter_bid_rejected'   => 'Your counter proposal was rejected.',
    'agent_hired'            => 'You have successfully hired an agent.',
    'offer_submitted'        => 'New offer received on your listing.',
    'offer_accepted'         => 'Your offer was accepted.',
    'offer_rejected'         => 'Your offer was rejected.',
    'offer_countered'        => 'You received a counter offer.',
    'offer_withdrawn'        => 'An offer was withdrawn.',
    'offer_expired'          => 'An offer has expired.',
    'offer_listing_status'   => 'Your listing status has been updated.',
    'showing_requested'      => 'New showing request received.',
    'showing_approved'       => 'Your showing request was approved.',
    'showing_declined'       => 'Your showing request was declined.',
    'showing_canceled'       => 'A showing was canceled.',
    'hire_agent_lead'        => 'New agent hire request received.',
];
@endphp
