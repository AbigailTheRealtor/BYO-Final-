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
| and staying private depended on a developer adding the key to a ~247-entry
| array in a Blade file at the moment they introduced it. A new key shipped by
| someone who had never read that array was published to the open internet the
| first time a tenant filled it in, with no code change and no review.
|
| THAT WAS NOT HYPOTHETICAL. An earlier draft of this file claimed the audit
| "found no present-tense leak". THAT CLAIM WAS WRONG and is corrected here.
| The pre-PR census walked the two tenant Livewire components and this view
| together: of the 477 meta keys the components write, 247 were absent from
| `$knownKeys` and therefore published by the fallback. Among them, on a route
| with no auth middleware, were `eviction_explanation`,
| `prior_felony_explanation`, `pre_approval_amount`, `cash_budget`,
| `outstanding_balance`, `retained_deposits`, `person_meeting` and the nine
| `meeting_details_*` keys carrying a tenant's name, email, phone and meeting
| time. Those are exactly the disclosures Phase 1 made owner-only. The leak was
| present-tense; the inversion below is what closed it.
|
| THE INVERSION. A key now appears in that section only by being named here.
| Unknown keys, new keys and retired keys all render nowhere, and making
| something public is a deliberate, reviewable edit to this file rather than an
| omission somewhere else.
|
| HOW THIS LIST WAS BUILT (static code census, no database).
|   1. Every `saveMeta('key')` in TenantOfferListing + TenantOfferListingEdit  → 477
|   2. Minus every key named in the view's own `$knownKeys`                    → 247
|   3. Minus every key with no `wire:model` binding in offer-tenant-tabs/ —
|      i.e. never actually authored by a tenant on this form                   →  39
|   4. Each survivor classified from its ACTUAL control and label, not its name.
|
| Step 4 is the one that matters, and step 3 is what makes the list honest:
| a key the tenant form cannot write is not made public by listing it here.
|
| WHY NAMES ARE NOT EVIDENCE. `number_of_unit` reads like a unit COUNT and is
| labelled "Unit Number:" — the tenant's own address fragment, sitting one line
| away from `address`, `state` and `property_zip`, all three of which the named
| sections already suppress. It is excluded. `restrictions` reads like a neutral
| property fact and is a free-text box captioned "Restrictions include:" with a
| ban icon — the single most likely place on this form for "no children" to be
| typed. It is excluded too. Both would have been allowlisted by a census that
| trusted key names.
|
| ADDING A KEY. Only tenant-authored, non-sensitive, listing-shaped facts belong
| here — never screening, financial, household, health, accommodation or
| background disclosures, which are owner-only under Fair Housing Phase 1. Add
| the key, and add a test asserting it renders.
|
*/

return [

    /*
    | CLASS A — objective property / lease facts, confirmed against the control
    | and caption each one actually renders with in
    | offer-tenant-tabs/commission-based/. Nine of the 39 candidates qualified.
    |
    | Every entry below is a description of a SPACE, not of a person, and none
    | is a screening, financial, household or contact disclosure.
    */
    'public_keys' => [

        // "Utilities:" — select (Included in Rent / …). leasing-terms.
        'utilities',

        // "Storage space available include:" — text. leasing-terms.
        'storage_space',

        // "Bathroom facilities:" — select (Private / Shared / …). leasing-terms.
        'bathroom_facilities',

        // "The room available for lease is approximately:" — text. leasing-terms.
        'room_size',

        // "Tenants have access to common areas such as:" — text. leasing-terms.
        'common_areas_access',

        // "Common areas are cleaned and maintained:" — text. leasing-terms.
        'common_areas_cleaning',

        // "Maintenance issues are handled by:" — select (Landlord / …). leasing-terms.
        'maintenance_by',

        // "Maintenance response time is typically:" — text. leasing-terms.
        'maintenance_response_time',

        // "Total acreage" — select. property-details.
        'total_acreage',
    ],

    /*
    |----------------------------------------------------------------------
    | Deliberately NOT public — recorded so the reasoning is not re-litigated
    |----------------------------------------------------------------------
    |
    | Documentation only. Nothing reads this array: privacy here is the DEFAULT,
    | so a key is private by being absent from `public_keys`, never by being
    | listed below. It exists because the next person to widen the allowlist
    | should see which candidates were already considered and rejected.
    |
    | CLASS B — private by category (Fair Housing Phase 1 owner-only, or PII):
    |   eviction_explanation, prior_felony_explanation   screening disclosures
    |   person_meeting, meeting_details_first_name,      contact / meeting PII
    |     meeting_details_last_name, meeting_details_email,
    |     meeting_details_phone, meeting_details_meeting_date,
    |     meeting_details_meeting_time, meeting_details_time_zone,
    |     meeting_details_instructions, meeting_details_additional_details
    |   number_of_unit                                   labelled "Unit Number:"
    |   broker_fee_days_after_lease, broker_fee_days_after_rent,
    |     broker_fee_timing_other, lease_fee_percentage_monthly_rent,
    |     lease_fee_percentage_net                       broker compensation, a
    |                                                    section this view has
    |                                                    already removed
    |
    | CLASS C — ambiguous, so private by default (the rule is: do not guess):
    |   restrictions                free text, "Restrictions include:"
    |   guests_allowed              occupancy-adjacent lease term
    |   rental_purpose_other        free text on a pre-screening answer
    |   attend_showings_count, showings_count, open_house_count,
    |     virtual_showings_count, virtual_tours_count, staging_duration
    |                               requested-service quantities
    |   service_completion_date, service_completion_time, service_time_zone
    |                               service workflow metadata
    |
    | The remaining 208 of the 247 fallback-only keys have no `wire:model`
    | binding in offer-tenant-tabs/ at all — legacy, other-role or derived keys
    | a tenant cannot author here. They stay private by default and are not
    | enumerated.
    */
];
