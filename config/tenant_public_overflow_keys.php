<?php

/*
|--------------------------------------------------------------------------
| Tenant public "Additional Information" — explicit public allowlist
|--------------------------------------------------------------------------
|
| The tenant detail view used to end with a fallback section whose rule was, in
| its own words: "Any populated key NOT in this list will appear in the
| Additional Information section." That is a DENY-LIST, and it has the property
| every deny-list has — it is correct only for the keys someone remembered.
|
| WHAT THAT MEANT IN PRACTICE. `/offer-listing/tenant/view/{id}` has no auth
| middleware. So the default disposition of every tenant meta key was PUBLIC,
| and staying private depended on a developer adding the key to a 200-entry
| array in a Blade file at the moment they introduced it. A new key shipped by
| someone who had never read that array was published to the open internet the
| first time a tenant filled it in, with no code change and no review. The audit
| found no present-tense leak — the sensitive keys are all currently listed —
| but "currently remembered" is not a boundary.
|
| THE INVERSION. A key now appears in that section only by being named here.
| Unknown keys, new keys and retired keys all render nowhere, and making
| something public is a deliberate, reviewable edit to this file rather than an
| omission somewhere else.
|
| WHY THIS STARTS EMPTY. The section was never a designed surface — it is the
| overflow of a deny-list, and everything the page means to show has a named
| section above it (that is what `$knownKeys` enumerated). There is therefore no
| known-good list to seed this with, and inventing one from field names would be
| guessing which of a consumer's answers are safe to broadcast. Empty is the
| conservative default and the honest one: nothing is published that nobody
| decided to publish.
|
| ADDING A KEY. Only tenant-authored, non-sensitive, listing-shaped facts belong
| here — never screening, financial, household, health, accommodation or
| background disclosures, which are owner-only under Fair Housing Phase 1.
| Add the key, and add a test asserting it renders.
|
*/

return [

    'public_keys' => [
        // Intentionally empty. See the header: a key becomes public by being
        // named here, and nothing has yet been reviewed for that.
    ],
];
