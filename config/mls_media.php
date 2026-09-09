<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MLS media import — master gate
    |--------------------------------------------------------------------------
    |
    | With this false, no MLS media is extracted, no MLS media is written to a
    | listing, and no MLS-sourced image is rendered anywhere.
    |
    | Defaulted ON as of the 2026-09-04 parity work — but read the next flag
    | before assuming that means photographs appear. This is the ENGINEERING
    | switch: it says the extraction, ordering, permission handling, cover
    | selection and idempotent gallery sync are built and tested. It is not, and
    | cannot be, a statement about the licence. `license_acknowledged` below is
    | still false, and both are required, so the feature remains inert until the
    | owner sets that one deliberately. Turning this on simply reduces the
    | owner's remaining action to a single, unambiguous flag.
    |
    | This is deliberately NOT config/mls_direct_import.php's `prefill_enabled`.
    | That flag governs importing objective FACTS into a form — text the user
    | reviews and can correct. This one governs republishing the MLS's own
    | photographs on a consumer-facing page, which is a different act with a
    | different licensing posture. One switch governing both would mean enabling
    | field prefill also enabled photo republication.
    |
    */

    'enabled' => env('MLS_MEDIA_IMPORT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Data-license acknowledgement
    |--------------------------------------------------------------------------
    |
    | The SECOND of two switches, and the reason there are two.
    |
    | `enabled` above is an engineering switch — someone turning the feature on
    | in an environment. This one is a statement about PERMISSION to republish
    | the MLS's own photographs. A single flag would let "let's see if it works
    | in staging" become "we are now republishing licensed imagery" with no
    | second thought in between, so both are still required and both are still
    | read on every extract, every write and every render.
    |
    | OWNER AUTHORISATION — 2026-09-04, DEFAULT CHANGED false → true
    | -------------------------------------------------------------
    | The locked owner decision of 2026-07-05
    | (docs/mls-direct-import-design-and-plan.md, item 1) excluded MLS photos
    | pending *written Stellar MLS confirmation*, because photo reuse, retention
    | and rehosting were the named licensing risk.
    |
    | On 2026-09-04 the owner explicitly SUPERSEDED that internal policy and
    | authorised MLS photo display on imported listings, having been told that a
    | repository audit located **no** written Stellar approval addressing this
    | public imported-listing use. This default therefore rests on an owner
    | product/policy decision, and on nothing else. It is NOT a record that new
    | Stellar documentation was found — none was.
    |
    | The full decision, including what the audit did and did not find, is
    | written down in docs/mls-direct-import-design-and-plan.md under
    | "Owner decision — 2026-09-04". Read that before changing this line.
    |
    | Setting this true does not make a use permitted that is not; it records
    | that the owner has taken the decision and removes this code's objection.
    |
    | WHAT IT DOES NOT OVERRIDE
    | -------------------------
    | Per-listing and per-media restrictions from the feed itself:
    | IDXParticipationYN, InternetEntireListingDisplayYN,
    | InternetAddressDisplayYN and each media object's own `Permission`. Photo
    | authorisation is a decision about OUR posture, never about an individual
    | listing's. A media object the feed has not marked Public is still refused.
    |
    */

    'license_acknowledged' => env('MLS_MEDIA_LICENSE_ACKNOWLEDGED', true),

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
    | A SAFETY CEILING, NOT A PRODUCT LIMIT.
    |
    | This used to be 50, mirroring the manual uploader's own ceiling. That was
    | the wrong comparison and it cost real data: the 2026-09-04 payload audit
    | found 186 of 1,202 cached listings carrying more than 50 photographs — one
    | with 100 — so more than one listing in seven silently lost the tail of its
    | own gallery.
    |
    | The manual uploader's 50 exists because every user upload is bytes we
    | store, serve and pay for. MLS media is referenced at the provider's URL and
    | copied nowhere (see `hosting_mode`), so none of that applies, and no MLS
    | rule requires it either. What remains is a backstop against a malformed
    | response claiming thousands of images, which is what 250 is for — well
    | above anything Stellar publishes, and still a number a page can render.
    |
    | Applied after ordering, so a cap that IS hit drops the tail rather than an
    | arbitrary subset, and the drop is logged at warning level rather than
    | silently.
    |
    */

    'max_images' => (int) env('MLS_MEDIA_MAX_IMAGES', 250),

];
