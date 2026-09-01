<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hire Agent — the compatibility_preferences allowlist
    |--------------------------------------------------------------------------
    |
    | WHICH KEYS A ROLE'S `{role}_specific` SUB-ARRAY MAY CONTAIN WHEN IT IS
    | PERSISTED. Read only by CompatibilityPreferencePolicy; nothing else may
    | read this file, and CompatibilityPreferencePolicyTest asserts it.
    |
    |
    | ── WHY AN ALLOWLIST AT THE PERSISTENCE BOUNDARY ────────────────────────
    |
    | BECAUSE VALIDATION CANNOT DO THIS JOB, AND BELIEVING IT COULD IS WHAT
    | MADE THIS FILE NECESSARY. `$compatibility_preferences` is a public
    | Livewire property, so a client can set any nested path through the normal
    | syncInput message. Laravel's validate() checks the keys named in rules()
    | and LEAVES EVERY OTHER KEY ON THE PROPERTY — it does not strip what it
    | was not asked about. The persist then wrote the sub-array verbatim:
    |
    |     $stored[$roleKey] = $this->compatibility_preferences[$roleKey];
    |
    | so any key a crafted request injected was written to the database. A
    | `prohibited` rule narrows that only on paths that reach full validation;
    | a draft save or a partial save does not.
    |
    | INTERSECTION, NOT SUBTRACTION. A key survives by being NAMED here. That
    | is the whole point and it is not stylistic: a deny-list only stops what
    | somebody thought to deny, so an injected key, a typo'd key, or a retired
    | key resurrected by a stale browser tab all survive it. With an allowlist
    | they are all dropped by the same line, and nobody had to anticipate them.
    |
    | RETIRED KEYS ARE ABSENT, NOT LISTED-AND-EXCLUDED. `tenant_type_preference`
    | and `tenant_type_preference_other` (Fair Housing retirement) and landlord
    | `risk_tolerance` (replaced by `applicant_screening_approach`) do not
    | appear below. There is nowhere to add them back "temporarily" without it
    | being visible in this diff, which is the property we want.
    |
    |
    | ── WHY LANDLORD IS SPLIT BY PROPERTY TYPE ──────────────────────────────
    |
    | `preferred_business_use` is a commercial leasing preference and must never
    | reach a residential listing — not through the UI, which is gated, and not
    | through a crafted request, which the UI gate cannot touch. The base set
    | applies to every landlord listing; `landlord_commercial_only` is added on
    | top when, and only when, the property type is exactly the commercial
    | value below.
    |
    | ANYTHING THAT IS NOT THE COMMERCIAL VALUE IS TREATED AS RESIDENTIAL. That
    | includes null, '', a legacy spelling and an unrecognised string. property
    | _type is stored as EAV meta and can be missing on an older row, so a
    | permissive default would be a hole rather than a convenience. The
    | conservative direction costs a commercial landlord a re-answer; the
    | permissive one lets a commercial-only key onto a home.
    |
    */

    'commercial_property_type' => 'Commercial Property',

    'roles' => [

        'landlord' => [
            'primary_leasing_goal',
            'primary_leasing_goal_other',
            'lease_duration_preference',
            'property_management_involvement',
            'communication_style',
            'preferred_contact_method',
            'response_time_expectation',
            'preferred_agent_working_style',
            'negotiation_style',
            'representation_priorities',
            'applicant_screening_approach',
            'concessions_willingness',
            'lease_terms_flexibility',
            'additional_representation_notes',
        ],

        'landlord_commercial_only' => [
            'preferred_business_use',
            'preferred_business_use_other',
        ],

        'seller' => [
            'communication_style',
            'negotiation_style',
            'primary_transaction_goal',
            'representation_priorities',
            'preferred_agent_working_style',
            'preferred_contact_method',
            'response_time_expectation',
            'willing_to_negotiate_on',
            'firm_on_price',
            'primary_transaction_goal_other',
            'target_sale_timeline',
            'flexibility_on_timeline',
            'post_sale_plan',
            'qualities_most_important',
            'past_agent_experience',
            'what_did_not_work_before',
            'decision_making_style',
            'involvement_level',
            'additional_decision_makers',
            'showing_availability',
            'open_house_preference',
            'additional_compatibility_notes',
        ],

        'buyer' => [
            'primary_transaction_goal',
            'representation_priorities',
            'communication_style',
            'negotiation_style',
            'preferred_agent_working_style',
            'primary_transaction_goal_other',
            'representation_priorities_other',
            'risk_tolerance',
            'decision_making_style',
            'timeline_flexibility',
            'preferred_contact_method',
            'availability_windows',
            'communication_frequency',
            'support_level',
            'deal_breakers',
            'additional_compatibility_notes',
            'preferred_agent_working_style_other',
        ],

        'tenant' => [
            'primary_rental_goal',
            'other_primary_rental_goal',
            'representation_priorities',
            'other_representation_priorities',
            'timeline_urgency',
            'other_timeline_urgency',
            'budget_flexibility',
            'communication_style',
            'other_communication_style',
            'contact_frequency',
            'preferred_contact_method',
            'preferred_agent_working_style',
            'negotiation_style',
            'decision_making_style',
            'concerns_or_barriers',
            'additional_compatibility_notes',
            'most_important_agent_traits',
            'other_most_important_agent_traits',
            'desired_level_of_agent_involvement',
            'other_desired_level_of_agent_involvement',
        ],

    ],

];
