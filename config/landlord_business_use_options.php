<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preferred Business Use — the option list
    |--------------------------------------------------------------------------
    |
    | COMMERCIAL LANDLORD LISTINGS ONLY. This is the landlord's leasing and
    | marketing preference: which business activities they would like their
    | agent to target for the premises.
    |
    | IT IS NOT "PERMITTED USE", AND THE DISTINCTION IS THE REASON THIS FIELD
    | EXISTS AT ALL. Permitted use is a legal constraint — zoning, certificate
    | of occupancy, exclusive-use covenants — and it already has a field:
    | `zoning_allows`, in Leasing Terms, commercial-gated, with its own tooltip
    | naming zoning explicitly. Do not merge them and do not add a second
    | permitted-use field here. One is a fact about the property; the other is
    | an instruction to the agent, and a landlord can legitimately prefer a
    | narrower use than zoning allows.
    |
    | IT REPLACES `tenant_type_preference`, WHICH WAS RETIRED FOR FAIR HOUSING
    | REASONS. That field mixed occupant categories (Individual / Family, Young
    | Professionals, Students) with business categories (Office Tenant, Retail
    | Business) in one control that rendered on residential and commercial
    | listings alike, and its value was published on a route with no auth
    | middleware. Every option below names a BUSINESS ACTIVITY. None names a
    | person, a household, a demographic, an age group, a profession-as-identity
    | or an employment status.
    |
    | WORDING IS PART OF THE CONTRACT. Never introduce an option, label,
    | tooltip, placeholder or validation message here using "tenant profile",
    | "tenant demographic", "type of person", "preferred occupant" or
    | "preferred clientele". HireAgentFairHousingWordingTest asserts their
    | absence from the landlord views at source.
    |
    | ONE LIST, READ BY FOUR CONSUMERS: the form partial, the validator's
    | Rule::in, CompatibilityPreferencePolicy's allowlist, and the retirement
    | command's deterministic mapping. Four copies would drift, and a drifted
    | copy in the validator means a legitimate answer is rejected while a
    | retired one is accepted.
    |
    */

    'options' => [
        'Office',
        'Retail',
        'Restaurant / Food Service',
        'Medical / Dental',
        'Professional Services',
        'Warehouse / Industrial / Flex',
        'Light Manufacturing',
        'Personal Services',
        'Automotive',
        'Educational / Institutional',
        'Other',
    ],

    /*
    | The sentinel that reveals the free-text companion. Named rather than
    | repeated as a literal, because the reveal condition lives in three places
    | (Blade x-data, Blade x-on:change, and the review partial's resolution
    | branch) and a typo in any one of them silently strands the companion.
    */
    'other_sentinel' => 'Other',

];
