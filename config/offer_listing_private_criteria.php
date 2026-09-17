<?php

/*
|--------------------------------------------------------------------------
| Buyer / Tenant public detail pages — owner-only meta keys
|--------------------------------------------------------------------------
|
| WHY THIS FILE EXISTS.
|
| `/offer-listing/buyer/view/{id}` and `/offer-listing/tenant/view/{id}` have
| NO auth middleware. routes/web.php documents that deliberately: a visitor must
| be able to open a card from the public search pages. The controllers gate only
| archived and draft/unapproved listings. Everything else these pages render is
| rendered to the open internet.
|
| A Buyer or Tenant listing is a CONSUMER's listing. What it publishes is that
| consumer's search criteria — and, until this file existed, also their income,
| their credit, their eviction and felony answers, their service and support
| animal status, their accessibility needs, their household size, their street
| address and their phone number. Those are not search criteria. Several are
| protected-class-adjacent under Fair Housing, and a public page is the widest
| possible audience for them.
|
| THE DISTINCTION THIS FILE ENCODES.
|
|   PUBLIC  — what this person is LOOKING FOR. A buyer's maximum purchase
|             budget, a tenant's maximum rent, desired areas, property type,
|             beds, baths, square footage, acreage, lease length, move-in dates,
|             pet-friendly requirement, furnishings, structured amenities.
|             Publishing these is the entire point of the listing.
|
|   PRIVATE — what QUALIFIES this person, where they LIVE, who they live WITH,
|             and how to REACH them. A budget is what someone wants to spend; a
|             pre-approval amount, cash reserves, down payment, credit score and
|             income are what a lender or landlord would use to judge them. The
|             two look similar and are not the same fact.
|
| HOW IT IS ENFORCED.
|
| `App\Support\OfferListing\CriteriaPrivacyPolicy` is the ONE reader; a test
| asserts that. Both controllers redact the `$meta` array through it BEFORE
| handing it to the view, and every meta read on both Blade files goes through
| that array ($str / $arr / $val and the overflow loop all close over it) — so
| the gate is at the hand-off, not at ~30 individual render sites. Editing call
| sites is how a field comes back: one new `$row(...)` added by someone who has
| not read this file would republish it. Here, a redacted key is simply absent,
| `$str()` returns '', the section's own emptiness guard collapses the heading,
| and a row that was never written cannot be forgotten.
|
| REMOVED, NOT BLANKED. A redacted key is unset rather than set to ''. `$str()`
| already returns '' for a missing key and `$arr()` returns [], so every reader
| behaves; and the tenant page's "Additional Information" loop iterates `$meta`
| itself, which a blanked-but-present key would still enter.
|
| THE OWNER IS THE ONLY EXCEPTION, deliberately. The gate is
| `auth()->check() && auth()->id() === $auction->user_id` — the same comparison
| these pages already use for their owner tools and for Important Places. "Any
| logged-in user", or "any agent", would be a broader audience wearing the word
| "authorized", and inventing one is a product decision, not a rendering fix.
|
| A KEY IS PRIVATE BY BEING NAMED HERE. This is an allowlist of private keys
| over a page that renders a fixed set of rows, not a deny-list over unknown
| input: the two Blade files render only keys they name, and the tenant
| overflow section is already governed by its own allowlist in
| config/tenant_public_overflow_keys.php. Adding a row that renders a
| qualification, household, address, health or contact fact means adding its key
| here in the same change.
|
| SAME NAME, DIFFERENT MEANING — read before copying a key between roles.
| `minimum_annual_net_income` is PRIVATE for tenant (the Pre-Screening tab asks
| "Estimated Monthly Net Household Income" beside it — it is the tenant's own
| income) and PUBLIC for buyer (the Property Preferences tab asks for "the
| minimum annual net income (after expenses) the PROPERTY must generate" — an
| investment criterion, sitting beside `minimum_cap_rate`). The key is identical
| and the two facts are not related. The lists below are therefore per-role and
| are not derived from one another.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | BUYER — owner-only
    |----------------------------------------------------------------------
    */
    'buyer' => [

        // --- Lender / financing qualification --------------------------------
        // "Buyer Pre-Approved for a Loan:" and the amount beside it. This is the
        // lender's judgement of the buyer, not the buyer's stated budget.
        'pre_approved',
        'pre_approval_amount',
        'pre_approval_letter',
        'lender_name',
        'lender_contact',
        'proof_of_funds',

        // --- Cash and down-payment capacity ----------------------------------
        // What the buyer HAS. `maximum_budget` / `buyer_budget` / `max_purchase_price`
        // — what they intend to SPEND — stay public; that is the listing.
        'cash_budget',
        'down_payment_amount',
        'down_payment_type',

        // --- Credit -----------------------------------------------------------
        'credit_score_range',
        // Misspelled in the schema ('scroe'). Named as stored, on purpose: a key
        // corrected only here would stop matching the row it is meant to redact.
        'credit_scroe_rating',

        // --- Household composition -------------------------------------------
        // Occupant counts are household composition, which is familial-status
        // adjacent under Fair Housing and is not a property criterion.
        'number_occupant',
        'number_of_occupants',
        'number_occupied',

        // --- The buyer's own current home ------------------------------------
        // Rendered under Home Sale Contingency as "Property Address" — it is the
        // address of the house the buyer currently lives in. The contingency
        // itself, its period and its target date stay public: they are terms of
        // the offer being sought. `unit_number` is that address's unit.
        'home_sale_contingency_address',
        'home_sale_contingency_details',
        'unit_number',
        'current_address',

        // --- Workplace location ----------------------------------------------
        // A commute destination is where this person works. It is the same class
        // of fact as an Important Place address, which is already owner-only
        // everywhere else in this application.
        'commute_destination_zip',
        'max_commute_minutes',
        'commute_mode',

        // --- Contact ----------------------------------------------------------
        // The public contact path is the "Ask a Question" form, which posts to
        // the listing owner without publishing their address book entry.
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'person_meeting',
        'meeting_details_first_name',
        'meeting_details_last_name',
        'meeting_details_email',
        'meeting_details_phone',
    ],

    /*
    |----------------------------------------------------------------------
    | TENANT — owner-only
    |----------------------------------------------------------------------
    */
    'tenant' => [

        // --- Income and income qualification ---------------------------------
        // "Estimated Monthly Net Household Income" and the annual figure beside
        // it. `budget` / `desired_rental_amount` / `maximum_budget` — the rent
        // they are looking for — stay public.
        'monthly_income',
        'minimum_annual_net_income',
        'income_verification_requirement',

        // --- Move-in financial capacity --------------------------------------
        // What the tenant can put down. Distinct from the rent they are seeking.
        'move_in_funds_available',
        'move_in_budget_upfront',
        'security_deposit_budget',
        'first_month_rent_available',
        'last_month_rent_available',

        // --- Credit -----------------------------------------------------------
        'credit_score_range',
        'credit_scroe_rating',

        // --- Eviction, criminal and background screening ----------------------
        // Already owner-only as named rows since Fair Housing P0-D; named here so
        // the rule lives with the rest of it and survives a future row edit.
        'prior_eviction',
        'eviction_explanation',
        'prior_felony',
        'prior_felony_explanation',
        'screening_concerns',
        'screening_concerns_explanation',

        // --- Disability and accommodation ------------------------------------
        // A service animal is NOT a pet and this is NOT a pet preference. It is a
        // disclosure of disability-related accommodation need, and publishing it
        // publishes the disability. `pets`, `number_of_pets`, `type_of_pets`,
        // `breed_of_pets` and `weight_of_pets` remain public: those are the
        // pet-friendly housing requirement, which is a real search criterion.
        'service_animal',
        'support_animal',
        'emotional_support_animal',
        'accessibility_requirements',

        // --- Current residence / private address ------------------------------
        // `address` here is the tenant's own street address, rendered beside the
        // desired State / Cities / Counties / ZIPs. Those four stay public — they
        // are where the tenant wants to live. This one is where they live now.
        'address',
        'unit_number',
        'number_of_unit',
        'current_address',
        'current_status',
        // Neither has a `wire:model` anywhere in offer-tenant-tabs/, so a tenant
        // cannot author them on this form; both describe the residence the tenant
        // is in NOW rather than the one they are looking for, and both render as
        // named rows on a public page.
        'occupied_until',
        'occupancy_status',

        // --- Workplace location ----------------------------------------------
        'commute_destination_zip',
        'max_commute_minutes',
        'commute_mode',

        // --- Household and guests ---------------------------------------------
        'number_of_occupants',
        'number_occupant',
        'number_occupied',
        'guests_allowed',

        // --- Contact ----------------------------------------------------------
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'person_meeting',
        'meeting_details_first_name',
        'meeting_details_last_name',
        'meeting_details_email',
        'meeting_details_phone',
    ],
];
