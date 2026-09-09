<?php

/*
|--------------------------------------------------------------------------
| Landlord provider-authored free text — Fair Housing Phase 3 SSOT
|--------------------------------------------------------------------------
|
| Phase 2 gave the landlord screening DROPDOWNS a write boundary: a value is
| stored because it is named in an allowlist. Prose cannot be allowlisted that
| way — there is no finite set of legitimate sentences — so this file is the
| other half: a small set of HIGH-CONFIDENCE patterns describing the shapes a
| landlord's stated tenant criteria may not take.
|
| THREE RULES GOVERN EVERYTHING IN THIS FILE.
|
| 1. PATTERNS DESCRIBE EXCLUSIONS, NOT VOCABULARY. A word list would be both
|    useless and harmful here, because the same nouns appear in the sentences
|    we most want to keep:
|
|        "Property is wheelchair accessible with a zero-step entry"   (keep)
|        "No wheelchair users"                                        (block)
|        "Two most recent pay stubs or benefit award letter"          (keep)
|        "No housing vouchers"                                        (block)
|
|    Every pattern below therefore matches a REFUSAL OR PREFERENCE STRUCTURE
|    wrapped around the sensitive term — never the term alone. A rule that
|    would fire on either "keep" example above is wrong and must not be added.
|
| 2. AUTHORSHIP IS PART OF THE RULE. These patterns apply ONLY to text a
|    LANDLORD wrote about who may rent. The identical words from a consumer
|    are a lawful accommodation request or a statement about their own life
|    ("I have an emotional support animal", "I receive Section 8 assistance"),
|    and Phase 1 already protects those as private first-person disclosures.
|    `LandlordProviderTextPolicy` is reachable only from landlord provider
|    fields; nothing here may ever be pointed at consumer text.
|
| 3. FALSE POSITIVES COST MORE THAN FALSE NEGATIVES *HERE*. This boundary
|    blocks a landlord from publishing. A rule that fires on a legitimate
|    property fact teaches landlords the feature is broken and to route around
|    it. Phase 3 deliberately catches the explicit, unambiguous cases and lets
|    ambiguous prose through rather than guessing — the structured fields, not
|    this file, are where coverage should grow.
|
| WHY NOT AskAiComplianceGuardrailService. That service sanitises GENERATED
| OUTPUT by dropping offending sentences. Applied to authored input it would
| silently rewrite what a landlord typed, which §12 of this work forbids
| outright. Its category taxonomy informed the categories below; its mechanism
| is deliberately not reused.
|
| Jurisdiction note: source-of-income protection varies by state and locality.
| The `message` strings below therefore say what the PLATFORM requires and
| never assert that a given phrase violates federal law.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Governed provider fields
    |----------------------------------------------------------------------
    |
    | Landlord-authored prose that reaches a public page or an AI prompt.
    | `max_length` is a storage bound, not a moderation decision: it is
    | enforced on the write for every field here, safe or not.
    |
    | Anything NOT listed here is not moderated by this policy. Adding a key
    | is a deliberate act — a field becomes governed by being named.
    */
    'fields' => [
        'landlord_approval_conditions' => [
            'label'      => 'Landlord approval conditions',
            'max_length' => 1000,
        ],
        'pet_restrictions' => [
            'label'      => 'Pet restrictions',
            'max_length' => 500,
            // Ordinary pet TERMS applied to an assistance animal — "subject to the
            // pet fee", "counts toward the two-pet limit" — only mean anything on a
            // field that is a pet policy. Outright assistance-animal EXCLUSIONS are
            // not listed here: they are universal (see `assistance_animal_exclusion`).
            'extra_categories' => ['assistance_animal_as_pet'],
        ],
        'additional_details' => [
            'label'      => 'Additional details',
            'max_length' => 5000,
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | MLS-sourced provider prose — explicit semantic aliases
    |----------------------------------------------------------------------
    |
    | Current main publishes an imported Stellar/Bridge payload on the landlord
    | and seller listing pages through `_mls_property_facts.blade.php`. The
    | landlord page has NO auth middleware, so those rows are anonymous
    | publication — the same audience as the landlord's own prose, arriving by a
    | different route and, before this map, past no boundary at all.
    |
    | A Bridge field is governed by being named here, and it inherits the
    | SEMANTICS of the landlord field it is mapped to. Nothing else about the MLS
    | payload is moderated: this is a short, explicit alias map, deliberately not
    | a sanitiser applied to all 347 rendered fields.
    |
    | WHY SO FEW ENTRIES. Every rendered field was classified against REAL VALUES
    | in tests/fixtures/mls/bridge/*.json, not against its name, and almost all of
    | them are structured — RESO enum pick-lists that arrive as arrays. Three
    | traps that a name-based reading gets wrong:
    |
    |   OccupantType         "Owner" / "Tenant" / "Vacant" — a single enum of
    |                        occupancy STATUS. It is a structured fact and is
    |                        deliberately NOT governed.
    |   STELLAR_RealtorInfo  labelled "Listing Notes", which reads like prose, but
    |                        arrives as an enum array ("Brochure Available",
    |                        "Survey Available"). Not governed.
    |   Disclosures          also an enum pick-list, not disclosure narrative.
    |                        Not governed.
    |
    | Moderating a structured enum would be pure false-positive risk: those values
    | cannot express an exclusion, and suppressing one would delete a property
    | fact for no safety gain.
    |
    | READ-TIME ONLY. This map is applied when a stored blob is read back for
    | rendering (`MlsSupplementalDetails::fromStored()`). The imported payload is
    | stored complete and is never edited, so MLS import completeness is
    | untouched and a rule change here re-governs history on the next page load.
    */
    'mls_prose_aliases' => [
        // Free narrative about pets — the field the pre-PR audit named. It is
        // "Pet Restrictions" on the page, one word from the landlord's own
        // `pet_restrictions`, and it inherits that field's semantics including
        // the pet-policy-specific assistance-animal rules.
        'STELLAR_PetRestrictions' => 'pet_restrictions',

        // Free narrative about lease restrictions — fixtures carry sentences
        // ("Lease term: Minimum 6 months, maximum 12 months."). Screening-shaped
        // prose belongs to the general approval-conditions semantics.
        'STELLAR_AdditionalLeaseRestrictions' => 'landlord_approval_conditions',

        // Open-house narrative, published on the same anonymous page. General
        // marketing-prose semantics, i.e. the additional-details rules.
        'OpenHouseRemarks' => 'additional_details',
    ],

    /*
    |----------------------------------------------------------------------
    | Categories
    |----------------------------------------------------------------------
    |
    | `patterns` are case-insensitive regular expressions run against the
    | whitespace-normalised text. `message` is shown to the landlord verbatim,
    | so it must name what to change rather than scold.
    |
    | SCOPE. A category is UNIVERSAL by default — it is evaluated on every
    | governed field. `'opt_in' => true` narrows it to the fields that name it in
    | their own `extra_categories`, and is for rules that are meaningless
    | elsewhere rather than for rules that are merely inconvenient.
    |
    | Getting that distinction wrong is what the Phase 3 pre-PR audit found:
    | assistance-animal EXCLUSIONS were opt-in to `pet_restrictions`, so
    | "No emotional support animals" typed into Landlord approval conditions or
    | Additional details published untouched. A refusal to house an assistance
    | animal is a disability/accommodation exclusion in whichever box it is typed;
    | only the "an assistance animal is subject to ordinary PET TERMS" rules are
    | genuinely specific to a pet policy. Hence the two categories below.
    */
    'categories' => [

        'protected_class_preference' => [
            'message' => 'Describe the requirement, not the person. State the objective criteria an applicant must meet (income, credit, references, lease term) rather than who the tenant should be.',
            'patterns' => [
                // "professionals only", "adults only", "students only"
                '/\b(professionals?|adults?|singles?|students?|couples?|families|christians?|seniors?)\s+only\b/i',
                // "no children", "no kids", "no families", "no-children", "No child".
                // The singular and the hyphenated spelling are here because the pre-PR
                // audit found both slipping past a `children|kids` alternation.
                '/\bno[\s-]+(child|children|kid|kids|infants?|toddlers?|babies|families|family\s+with\s+children)\b/i',
                // "Children are not allowed", "kids not permitted" — the same exclusion
                // written as a statement rather than as a "no X" phrase.
                '/\b(children|kids|infants?|toddlers?|babies|families)\s+(?:are\s+|is\s+)?not\s+(?:allowed|permitted|accepted|welcome)\b/i',
                '/\b(?:do\s+not|don\'?t|cannot|can\'?t|will\s+not|won\'?t)\s+(?:allow|permit|accept|rent\s+to)\s+(?:any\s+)?(children|kids|families|students?)\b/i',
                // "adults preferred", "professionals preferred"
                '/\b(adults?|professionals?|singles?|couples?|seniors?|students?)\s+(are\s+)?preferred\b/i',
                // "prefer a single occupant", "prefer professionals", "we prefer adults".
                // The noun list is person-CATEGORIES only, so "prefer applicants with
                // good credit" and "prefer a 12-month lease" stay allowed.
                '/\b(?:we\s+)?prefer(?:red|s|ring)?\s+(?:to\s+rent\s+to\s+)?(?:a\s+|an\s+)?(?:single\s+occupants?|professionals?|adults?|mature\s+adults?|singles?|couples?|seniors?|retirees?|students?|childless(?:\s+couples?)?)\b/i',
                // "suitable for adults", "not suitable for children"
                '/\b(?:not\s+)?suitable\s+for\s+(?:children|kids|families|adults\s+only)\b/i',
                // explicit religion / national origin / race gating
                '/\b(?:must\s+be|only)\s+(?:christian|catholic|muslim|jewish|white|black|hispanic|asian)\b/i',
                '/\bmust\s+speak\s+english\b/i',
            ],
        ],

        'disability_exclusion' => [
            'message' => 'Remove conditions about an applicant\'s disability or ability. You may describe the property\'s features (for example "second-floor unit, no elevator") and state neutral, objective requirements.',
            'patterns' => [
                // "no wheelchair users" / "wheelchair users not accepted"
                '/\bno\s+wheelchair(?:\s+users?|s)?\b/i',
                '/\bwheelchair\s+users?\s+(?:are\s+)?(?:not\s+(?:accepted|permitted|allowed)|excluded)\b/i',
                // "must be able to live independently"
                '/\bmust\s+be\s+able\s+to\s+live\s+independently\b/i',
                '/\bindependent\s+living\s+required\b/i',
                // "no disabled", "able-bodied only"
                '/\bno\s+disabled\s+(?:applicants?|tenants?|persons?)\b/i',
                '/\bable[\s-]?bodied\s+(?:only|applicants?\s+only|preferred)\b/i',
                // "no caregivers", "no home health aides"
                '/\bno\s+(?:caregivers?|home\s+health\s+aides?|live[\s-]in\s+aides?)\b/i',
            ],
        ],

        /*
         * UNIVERSAL. Refusing an assistance animal is a disability/accommodation
         * exclusion, not a pet term, so it is caught in every governed field —
         * approval conditions and additional details included. This category was
         * split out of `assistance_animal_as_pet` when the pre-PR audit proved the
         * opt-in scoping let the same sentence publish from the other two boxes.
         *
         * Note what these patterns require: an assistance-animal noun WRAPPED IN A
         * REFUSAL. "Service animals welcome" and "ESA documentation accepted" do not
         * match, and must never be made to.
         */
        'assistance_animal_exclusion' => [
            'message' => 'Assistance animals are accommodation requests, not pets, and cannot be refused by a pet policy or a condition of approval. Ordinary pet terms — species, size, count, breed, pet deposit — are fine.',
            'patterns' => [
                '/\bno\s+(?:service|assistance|support|emotional\s+support|therapy|companion)\s+(?:animals?|dogs?|pets?)\b/i',
                '/\bno\s+esas?\b/i',
                '/\b(?:service|assistance|emotional\s+support|support|therapy|companion)\s+(?:animals?|dogs?)\s+(?:are\s+|will\s+be\s+)?(?:not\s+(?:allowed|permitted|accepted|welcome)|excluded|prohibited|refused|denied)\b/i',
                // "we do not allow service animals" / "we don't accept ESAs"
                '/\b(?:do\s+not|don\'?t|cannot|can\'?t|will\s+not|won\'?t)\s+(?:allow|permit|accept|take)\s+(?:any\s+)?(?:service|assistance|emotional\s+support|support|therapy|companion)\s+(?:animals?|dogs?)\b/i',
                '/\b(?:do\s+not|don\'?t|will\s+not|won\'?t)\s+(?:allow|permit|accept)\s+esas?\b/i',
            ],
        ],

        /*
         * PET-POLICY SPECIFIC. Not a refusal — an attempt to run an assistance
         * animal through the ordinary pet machinery (fee, deposit, weight limit,
         * pet count). That only has a meaning on a field that IS a pet policy,
         * which is why this one stays opt-in and `pet_restrictions` names it.
         */
        'assistance_animal_as_pet' => [
            'opt_in'  => true,
            'message' => 'Assistance animals are accommodation requests, not pets, so ordinary pet fees, deposits, weight limits and pet counts cannot be applied to them. Those terms are fine for actual pets.',
            'patterns' => [
                '/\b(?:service|assistance|emotional\s+support|support|therapy|companion)\s+animals?\s+(?:are\s+)?(?:subject\s+to|count\s+(?:as|toward)|treated\s+as|considered)\s+(?:the\s+)?(?:ordinary\s+)?pets?\b/i',
                '/\b(?:service|assistance|emotional\s+support|support|therapy|companion)\s+animals?\s+(?:are\s+)?subject\s+to\s+(?:the\s+)?(?:same\s+)?pet\s+(?:policy|fee|deposit|rent|restrictions?|limits?|weight)\b/i',
                '/\bpet\s+(?:fee|deposit|rent)\s+applies\s+to\s+(?:all\s+)?(?:service|assistance|emotional\s+support|support|therapy|companion)\s+animals?\b/i',
                '/\b(?:service|assistance|emotional\s+support|support|therapy|companion)\s+animals?\s+(?:still\s+)?(?:pay|require)\s+(?:a\s+|the\s+)?pet\s+(?:fee|deposit|rent)\b/i',
            ],
        ],

        'source_of_income_exclusion' => [
            'message' => 'Remove exclusions based on where lawful income comes from. State the amount you require instead (for example "income 3x rent"); all lawful, verifiable income counts toward it.',
            'patterns' => [
                '/\bno\s+section\s*8\b/i',
                '/\bsection\s*8\s+(?:not\s+(?:accepted|allowed|considered)|need\s+not\s+apply)\b/i',
                '/\bno\s+(?:housing\s+)?vouchers?\b/i',
                '/\bvouchers?\s+not\s+accepted\b/i',
                '/\bno\s+(?:housing\s+)?(?:assistance|subsid(?:y|ies)|hud)\b/i',
                '/\bno\s+(?:welfare|snap|tanf|ssi|ssdi|disability\s+income|unemployment)\b/i',
                '/\b(?:employment|w-?2|wage|earned)\s+income\s+only\b/i',
                '/\bmust\s+be\s+employed\s+full[\s-]?time\b/i',
                '/\bno\s+(?:retirees?|pensions?|retirement\s+income|social\s+security)\b/i',
            ],
        ],

        'steering' => [
            'message' => 'Remove statements about the kind of person the property or area suits. Describe the property and the neighbourhood\'s features instead.',
            'patterns' => [
                '/\b(?:better|best|ideally|well)\s+suited\s+(?:to|for)\s+(?:young|old|single|married|childless|professional|student|elderly|retired|male|female|christian|white|black|hispanic|asian)/i',
                '/\b(?:perfect|ideal|great)\s+for\s+(?:young\s+professionals?|singles?|childless|couples?\s+without\s+children|mature\s+adults?)\b/i',
                '/\bneighbou?rhood\s+is\s+(?:mostly|mainly|predominantly|largely)\s+(?:young|old|white|black|hispanic|asian|christian|jewish|families|professionals?|students?|retirees?)\b/i',
                '/\bthis\s+(?:area|building|community)\s+is\s+(?:not\s+)?(?:for|suited\s+to)\s+(?:families|children|students?|seniors?)\b/i',
            ],
        ],
    ],
];
