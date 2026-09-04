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
            // Assistance animals are not pets. A restriction written here that
            // reaches for them is the one case this field must catch.
            'extra_categories' => ['assistance_animal_as_pet'],
        ],
        'additional_details' => [
            'label'      => 'Additional details',
            'max_length' => 5000,
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Categories
    |----------------------------------------------------------------------
    |
    | `patterns` are case-insensitive regular expressions run against the
    | whitespace-normalised text. `message` is shown to the landlord verbatim,
    | so it must name what to change rather than scold.
    */
    'categories' => [

        'protected_class_preference' => [
            'message' => 'Describe the requirement, not the person. State the objective criteria an applicant must meet (income, credit, references, lease term) rather than who the tenant should be.',
            'patterns' => [
                // "professionals only", "adults only", "students only"
                '/\b(professionals?|adults?|singles?|students?|couples?|families|christians?|seniors?)\s+only\b/i',
                // "no children", "no kids", "no families"
                '/\bno\s+(children|kids|infants|toddlers|families|family\s+with\s+children)\b/i',
                // "adults preferred", "professionals preferred"
                '/\b(adults?|professionals?|singles?|couples?|seniors?|students?)\s+(are\s+)?preferred\b/i',
                // "prefer a single occupant", "prefer professionals"
                '/\bprefer(?:red|s|ring)?\s+(?:a\s+)?(?:single\s+occupants?|professionals?|adults?\s+without\s+children|childless)\b/i',
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

        'assistance_animal_as_pet' => [
            'message' => 'Assistance animals are accommodation requests, not pets, and cannot be excluded by a pet policy. Ordinary pet terms — species, size, count, breed, pet deposit — are fine here.',
            'patterns' => [
                '/\bno\s+(?:service|assistance|support|emotional\s+support|therapy)\s+(?:animals?|dogs?|pets?)\b/i',
                '/\bno\s+esas?\b/i',
                '/\b(?:service|assistance|emotional\s+support|support)\s+animals?\s+(?:are\s+)?(?:not\s+(?:allowed|permitted|accepted)|excluded|prohibited)\b/i',
                // subjecting an assistance animal to the ordinary pet policy
                '/\b(?:service|assistance|emotional\s+support|support)\s+animals?\s+(?:are\s+)?(?:subject\s+to|count\s+(?:as|toward)|treated\s+as|considered)\s+(?:the\s+)?(?:ordinary\s+)?pets?\b/i',
                '/\b(?:service|assistance|emotional\s+support|support)\s+animals?\s+(?:are\s+)?subject\s+to\s+(?:the\s+)?pet\s+(?:policy|fee|deposit|rent|restrictions?|limits?|weight)\b/i',
                '/\bpet\s+(?:fee|deposit|rent)\s+applies\s+to\s+(?:all\s+)?(?:service|assistance|emotional\s+support|support)\s+animals?\b/i',
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
