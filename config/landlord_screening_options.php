<?php

/*
|--------------------------------------------------------------------------
| Landlord Applicant-Requirements screening options (Fair Housing Phase 2)
|--------------------------------------------------------------------------
|
| Single source of truth for the landlord screening controls on the Create /
| Edit Offer Listing "Applicant Requirements" tab.
|
| WHY A CONFIG AND NOT JUST BLADE OPTIONS.
| Phase 2's audit established that every key below is a public Livewire
| property persisted verbatim by `saveMeta()`, with no validation rule
| anywhere. Removing an <option> from a Blade file therefore removes it from
| the UI and from nothing else: a crafted Livewire payload still sets the
| property and the save still writes it. So the option list has to live
| somewhere a *write* can be checked against, and both the form and the
| policy have to read the same list — otherwise they drift and the gate
| silently stops matching what the product actually offers.
|
| This file has exactly two readers: LandlordScreeningPolicy (which enforces
| it) and the Applicant Requirements Blade partial (which renders it). A test
| asserts that.
|
| THE SENTINEL. Values are canonically lower-case 'No requirement'. The
| components historically defaulted the properties to 'No Requirement'
| (capital R), which matched no <option> at all, so the select rendered empty
| and the public view compared against both spellings in different places.
| `LandlordScreeningPolicy::normalize()` is the one place that resolves it.
|
*/

return [

    /*
    | Fields retired outright by Phase 2. These are no longer rendered, no
    | longer hydrated into form state, no longer persisted, and no longer
    | readable by Ask AI.
    |
    | `employment_requirement` asked landlords to require an employment
    | STATUS ("Employed", "Retired allowed", "Student allowed"). That gates
    | tenancy on how income is earned rather than whether rent can be paid:
    | it excludes retirees, people on disability or Social Security, students,
    | and anyone on lawful non-wage income, and "Retired allowed" / "Student
    | allowed" frame lawful income as a landlord-granted permission. The
    | legitimate question underneath — can the applicant meet an objective
    | income requirement — is already carried in full by
    | income_qualification_method + min_income_requirement /
    | min_monthly_income_fixed + income_verification_requirement, which Phase 2
    | leaves intact. Retiring the gate is therefore the narrowest fix: nothing
    | replaces it, because nothing needs to.
    |
    | `employment_verification_requirement` goes with it. Requiring "proof of
    | employment" is the same exclusion one step removed — a retiree cannot
    | produce it. Income documentation is requested through
    | income_verification_requirement instead, relabelled accordingly.
    */
    'retired_fields' => [
        'employment_requirement',
        'custom_employment_requirement',
        'employment_verification_requirement',
    ],

    /*
    | Current fields. Each entry is the complete set of values a landlord may
    | hold. Anything not listed cannot be written and is not rendered.
    |
    |   type        'single' | 'multi'
    |   options     the allowlist, in render order
    |   custom_key  companion free-text key, unlocked only by `custom_when`
    |   normalize   stale stored value => current value. Wording-only moves
    |               where the meaning survives the rename.
    |   suppress    stale stored values that are NOT re-expressed as anything.
    |               They read as "no answer": hidden from the public page and
    |               from Ask AI, and hydrated as empty so the owner is asked to
    |               choose again. Used where re-labelling would put a policy in
    |               the landlord's mouth that they never selected.
    */
    'fields' => [

        /*
        | Criminal history.
        |
        | "No criminal background" was a blanket, lifetime, any-record ban. It
        | is removed and — unlike the renames below — it is SUPPRESSED rather
        | than mapped forward: re-labelling it "Individualized review of
        | convictions" would silently credit a listing with an individualized
        | process it never had, and would tell applicants something about the
        | landlord's policy that the landlord never said. A suppressed value
        | renders nothing until the owner picks a current option.
        |
        | "Case-by-case review" IS mapped forward. It already meant an
        | individualized look, so the rename keeps the meaning and narrows the
        | subject matter to convictions, which only ever helps the applicant.
        |
        | No lookback window is offered here. Phase 2 deliberately ships the
        | narrow set: fixed 3/5/7-year options would be read as a legal
        | standard, and the applicable limits are state and local, not federal.
        */
        'criminal_background_requirement' => [
            'type'       => 'single',
            'options'    => [
                'No requirement',
                'Individualized review of convictions',
                'Other',
            ],
            'custom_key'  => 'custom_criminal_background_requirement',
            'custom_when' => 'Other',
            'normalize'   => [
                'Case-by-case review' => 'Individualized review of convictions',
            ],
            'suppress'    => [
                'No criminal background',
            ],
        ],

        'eviction_history_requirement' => [
            'type'    => 'single',
            'options' => [
                'No requirement',
                'No prior evictions',
                'Evictions older than 7 years accepted',
                'Evictions older than 5 years accepted',
                'Evictions older than 3 years accepted',
                'Exceptions considered under documented criteria',
                'Other',
            ],
            'custom_key'  => 'custom_eviction_requirement',
            'custom_when' => 'Other',
            'normalize'   => [
                'Case-by-case review' => 'Exceptions considered under documented criteria',
            ],
            'suppress'    => [],
        ],

        'bankruptcy_requirement' => [
            'type'    => 'single',
            'options' => [
                'No requirement',
                'No bankruptcy',
                'Bankruptcy discharged more than 7 years ago accepted',
                'Bankruptcy discharged more than 5 years ago accepted',
                'Bankruptcy discharged more than 2 years ago accepted',
                'Exceptions considered under documented criteria',
                'Other',
            ],
            'custom_key'  => 'custom_bankruptcy_requirement',
            'custom_when' => 'Other',
            'normalize'   => [
                'Case-by-case review' => 'Exceptions considered under documented criteria',
            ],
            'suppress'    => [],
        ],

        /*
        | Credit flexibility.
        |
        | "Compensating factors considered" is renamed to the generic
        | documented-criteria wording, NOT to the new deposit / co-signer
        | option. A landlord who selected it may have meant rental history,
        | reserves, or anything else; asserting a specific remedy they never
        | chose would misstate their policy. The deposit / co-signer option
        | exists going forward for landlords who do mean that.
        */
        'credit_score_flexibility' => [
            'type'    => 'single',
            'options' => [
                'No additional flexibility',
                'Strict requirement',
                'Exceptions considered under documented criteria',
                'Additional deposit or qualified co-signer may be considered',
            ],
            'custom_key'  => null,
            'custom_when' => null,
            'normalize'   => [
                'Case-by-case review'             => 'Exceptions considered under documented criteria',
                'Compensating factors considered' => 'Exceptions considered under documented criteria',
            ],
            'suppress'    => [],
        ],

        /*
        | Pet policy. Multi-select, stored as a JSON array.
        |
        | Only the standardless "Case-by-case review" entry changes. Assistance
        | animals are NOT a pet policy and are handled by the Phase 1 work;
        | nothing here touches that wording.
        */
        'pet_policy_requirement' => [
            'type'    => 'multi',
            'options' => [
                'Dogs allowed',
                'Cats allowed',
                'Small pets allowed',
                'Large pets allowed',
                'Exotic pets allowed',
                'No pets',
                'Exceptions considered under documented criteria',
            ],
            'custom_key'  => null,
            'custom_when' => null,
            'normalize'   => [
                'Case-by-case review' => 'Exceptions considered under documented criteria',
            ],
            'suppress'    => [],
        ],

        /*
        | Income documentation. The field and its options are unchanged; only
        | the label and tooltip move, from "Employment verification" framing to
        | documentation of income from any lawful source.
        */
        'income_verification_requirement' => [
            'type'    => 'single',
            'options' => [
                'No requirement',
                'Required',
                'Preferred',
            ],
            'custom_key'  => null,
            'custom_when' => null,
            'normalize'   => [],
            'suppress'    => [],
        ],
    ],

    /*
    | Fixed, non-editable copy. Lives here so the Blade partial and the test
    | that asserts the wording read one string.
    */
    'copy' => [
        'income_documentation_label'   => 'Income documentation required',
        'income_documentation_tooltip' => 'Whether applicants must provide documents showing their income meets the requirement above.',
        'income_sources_helper'        => 'All lawful, verifiable income counts toward this requirement, including wages, self-employment or business income, retirement or benefit income, housing assistance, and other lawful sources.',
        'criminal_label'               => 'Criminal history policy',
        'criminal_tooltip'             => 'How conviction history is considered as part of the screening criteria. Applicable requirements may vary by state and locality.',
        'criminal_helper'              => 'Screening criteria should be applied consistently and in accordance with applicable law.',
    ],

    /*
    | Length ceiling for the one surviving screening free-text field. Phase 2
    | is not the content-moderation phase (that is Phase 3); this is only a
    | bound on an unbounded input, so an oversized payload cannot be stored.
    */
    'custom_text_max_length' => 500,
];
