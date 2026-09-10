<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Which product experience this deployment serves
    |--------------------------------------------------------------------------
    |
    | ONE codebase, one database, two product surfaces. This file does not split
    | anything — it names which surface a given deployment is allowed to show.
    |
    |   combined     the platform as it has always been: BidYourOffer AND
    |                BidYourAgent. This is the DEFAULT and it must stay the
    |                default, because it is what production serves today.
    |
    |   bidyouragent BidYourAgent only: Hire Agent for all four roles, agent
    |                bidding, counters, accept/reject, the accepted-bid summary,
    |                messaging, notifications, accounts and agent profiles.
    |                Every BidYourOffer surface is hidden AND refused.
    |
    | `bidyouragent` NARROWS the platform; it never widens it. Nothing in this
    | file grants access to anything — authorization is a separate layer that is
    | unchanged, and a route hidden here is still subject to every auth, verified,
    | owner and agentAuth check it had before.
    |
    */

    'products' => [
        'combined',
        'bidyouragent',
    ],

    'default' => 'combined',

    /*
    |--------------------------------------------------------------------------
    | APP_PRODUCT — the deterministic selector, and the one to use in production
    |--------------------------------------------------------------------------
    |
    | When set, this WINS ABSOLUTELY and the host map below is never consulted.
    | That is the point: APP_PRODUCT is deployment configuration, so it cannot be
    | influenced by anything in a request. A BidYourAgent deployment should set
    | APP_PRODUCT=bidyouragent and stop there.
    |
    | An UNRECOGNISED value THROWS rather than falling back to the default. A typo
    | that silently resolved to `combined` on a BidYourAgent domain is exactly the
    | leak this whole mechanism exists to prevent, and it would look like success.
    | (Same reasoning as CRITERIA_LDNA_GEOGRAPHY_SOURCE.)
    |
    */

    'active' => env('APP_PRODUCT'),

    /*
    |--------------------------------------------------------------------------
    | Host map — capability only, deliberately EMPTY by default
    |--------------------------------------------------------------------------
    |
    | Exact, lower-cased host => product. Consulted ONLY when APP_PRODUCT is
    | unset, so a deployment that sets APP_PRODUCT can never be talked out of it
    | by a Host header.
    |
    | READ THIS BEFORE USING IT. The Host header is supplied by the client. This
    | map is safe for the direction it exists to serve — mapping a host TO
    | `bidyouragent`, which only ever removes surfaces — but a deployment that
    | relies on host mapping ALONE to serve BidYourAgent is trusting a client
    | header for a product boundary. Set APP_PRODUCT there instead.
    |
    | An unmatched host falls through to 'default' below. This ships empty: no
    | DNS, no host, no domain is configured by this change.
    |
    */

    'hosts' => [
        // 'bidyouragent.com'     => 'bidyouragent',
        // 'www.bidyouragent.com' => 'bidyouragent',
    ],

];
