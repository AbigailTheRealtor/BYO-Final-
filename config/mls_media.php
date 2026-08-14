<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MLS media import — master gate
    |--------------------------------------------------------------------------
    |
    | Default OFF. With this false, no MLS media is extracted, no MLS media is
    | written to a listing, and no MLS-sourced image is rendered anywhere. The
    | feature is inert.
    |
    | This is deliberately NOT config/mls_direct_import.php's `prefill_enabled`.
    | That flag governs importing objective FACTS into a form — text the user
    | reviews and can correct. This one governs republishing the MLS's own
    | photographs on a consumer-facing page, which is a different act with a
    | different licensing posture. One switch governing both would mean enabling
    | field prefill also enabled photo republication.
    |
    */

    'enabled' => env('MLS_MEDIA_IMPORT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Data-license acknowledgement
    |--------------------------------------------------------------------------
    |
    | The SECOND of two switches, and the reason there are two.
    |
    | docs/mls-direct-import-design-and-plan.md records a locked owner decision
    | (2026-07-05): MLS photos are excluded pending *written Stellar MLS
    | confirmation*, because photo reuse, retention and rehosting are the named
    | licensing risk. That decision is not superseded by this code existing.
    |
    | `enabled` alone is an engineering switch — someone turning the feature on
    | in an environment. This flag is a separate, explicit statement that the
    | written confirmation has been obtained and that the terms permit what the
    | configured hosting mode does. Both must be true. A single flag would let
    | "let's see if it works in staging" become "we are now republishing
    | licensed imagery" with no second thought in between.
    |
    | Setting this to true without holding that confirmation does not make the
    | use permitted; it only removes this code's objection to it.
    |
    */

    'license_acknowledged' => env('MLS_MEDIA_LICENSE_ACKNOWLEDGED', false),

    /*
    |--------------------------------------------------------------------------
    | Hosting mode
    |--------------------------------------------------------------------------
    |
    | 'reference' — the ONLY supported value today.
    |
    | Permitted MLS images are referenced at the provider's own URL and rendered
    | from there. No bytes are ever downloaded, copied, transformed, or written
    | to BYO storage; `auction/images/` is untouched by MLS import. The listing
    | stores the media's identifiers and its provider URL, nothing more.
    |
    | Retention and rehosting are precisely the acts the locked decision names,
    | so a 'cached' mode is not implemented rather than implemented-and-disabled:
    | code that copies licensed imagery should not exist until the licence that
    | permits it does. MlsMediaPolicy rejects any other value outright instead of
    | falling back to the default — a typo here must not silently choose a
    | hosting posture nobody selected.
    |
    */

    'hosting_mode' => env('MLS_MEDIA_HOSTING_MODE', 'reference'),

    /*
    |--------------------------------------------------------------------------
    | Roles the MLS media import applies to
    |--------------------------------------------------------------------------
    |
    | Mirrors mls_direct_import.prefill_roles. Buyer and Tenant listings describe
    | search criteria across many areas rather than one property, so there is no
    | property whose photographs could be attached to them.
    |
    */

    'roles' => ['seller', 'landlord'],

    /*
    |--------------------------------------------------------------------------
    | Permitted media categories
    |--------------------------------------------------------------------------
    |
    | RESO `MediaCategory` values that may be shown on a listing page. An
    | allow-list, so a category the feed introduces later — or one this codebase
    | has never seen — is excluded until somebody adds it deliberately.
    |
    | Documents are absent on purpose: a "media" record may be a seller's
    | disclosure, a survey or a lease, none of which belong in a photo gallery
    | and some of which are not public at all. Branded virtual tours are absent
    | because they carry the listing brokerage's own marketing.
    |
    | Comparison is case-insensitive and space-insensitive; an entry with no
    | category at all is handled by `allow_uncategorised` below.
    |
    */

    'allowed_categories' => [
        'Photo',
        'Image',
        'Property Photo',
        'Floor Plan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uncategorised media
    |--------------------------------------------------------------------------
    |
    | Some feeds omit MediaCategory entirely on ordinary listing photographs.
    | When true, an entry with no category is treated as a photo provided it
    | satisfies every other rule (https URL, permitted MIME hint, not private).
    |
    | Left true because the alternative — dropping the entire gallery for a feed
    | that simply does not populate the column — reads to the user as "this
    | listing has no photos", which is a false statement about their property.
    | Set false to require an explicit category.
    |
    */

    'allow_uncategorised' => true,

    /*
    |--------------------------------------------------------------------------
    | Maximum images attached per listing
    |--------------------------------------------------------------------------
    |
    | Matches the 50-photo ceiling the manual uploader already enforces, so an
    | imported gallery cannot exceed what a user could have uploaded by hand.
    | Applied after ordering, so the cap drops the tail rather than an arbitrary
    | subset — and the drop is logged, never silent.
    |
    */

    'max_images' => (int) env('MLS_MEDIA_MAX_IMAGES', 50),

];
