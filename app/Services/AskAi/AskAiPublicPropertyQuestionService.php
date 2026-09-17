<?php

namespace App\Services\AskAi;

use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use App\Support\Listing\ListingPriceDisplay;
use App\Support\Listing\FloodZoneCode;
use App\Support\OfferListing\CriteriaPrivacyPolicy;
use App\Support\OfferListing\PublicProviderTextPolicy;
use App\Support\AskAi\PublicAnswerPiiScreen;

/**
 * AskAiPublicPropertyQuestionService — "Questions About This Property" (Batches 1, 2b)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Turns the approved catalog in
 * AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() into verified,
 * precomputed question/answer pairs for the PUBLIC Seller and Landlord listing pages,
 * rendered in the Ask AI card. Every answer is a fixed sentence built from structured
 * listing values the page already assembled into its Ask AI chip context.
 *
 * This service MUST NEVER:
 *   - Call a language model, the free-text classifier, the intent normaliser, the
 *     knowledge search, or any HTTP service. It has no constructor dependencies, so it
 *     cannot reach any of them.
 *   - Read a database. Its inputs are the chip context and the decoded meta array the
 *     controller already holds.
 *   - Answer from an owner-authored Knowledge Base answer (faq_answers.*). Those remain
 *     owner-only under P0 / P0.1 / P0.2.
 *   - Answer for a buyer or tenant listing. Those are search criteria (decision D2).
 *   - Make a fact public that is not already public. Visibility is SnapshotFactVisibility's
 *     decision, with exactly one narrow exception: PUBLIC_QUESTION_ADMISSIONS names a
 *     source this surface may restate because the listing page itself already publishes
 *     it. An admission applies to this surface only — it never touches
 *     SnapshotFactVisibility, snapshots, the Ask AI redactor or Agent AI.
 * ==================================================================================
 *
 * THE AVAILABILITY RULE (evaluate()) — a question is shown only when ALL hold:
 *   1. its role is seller or landlord, and the entry is in the catalog for that role;
 *   2. source_path is exactly one 'listing.<key>' path, and <key> is a context key the
 *      context builder defines for that role (CANONICAL_SOURCE_MAP);
 *   3. SnapshotFactVisibility::classify(<key>, role) is 'public_allowed' for the source
 *      AND every supporting path — or, for the SOURCE only, the entry is
 *      source_kind 'admitted_listing' and the key is in PUBLIC_QUESTION_ADMISSIONS;
 *   4. the source value is present and meaningful;
 *   5. the named formatter exists and accepts the value (a formatter returns null for
 *      anything it cannot state exactly, which hides the question);
 *   6. every named guard passes. Guards only ever HIDE — they resolve ambiguity between
 *      the context value and what the page itself publishes, and never expose a value.
 * Anything unrecognised — role, path, key, source kind, formatter, guard — fails closed.
 *
 * COMPOSITES (Batch 2c) answer one question from several declared structured sources and
 * need every required part; a narrower entry naming the composite in 'narrower_of' stands in
 * when the composite is unavailable and is suppressed when it is available, so near-identical
 * questions never both show.
 *
 * META IS READ FOR TWO THINGS ONLY: hide-only guards, and an entry's declared
 * 'other_companion' / 'other_companions' — the exact stored selections of a field (the key
 * named in 'selected_in') and the free-text "Other" value beside it (the key named in 'meta_key').
 * Both keys are named by the catalog entry; nothing else in $meta is ever read into an
 * answer. The companion text is used only when "Other" is actually selected, the same
 * substitution the listing page performs, and only when it is a short, single-line,
 * non-placeholder value; text that cannot be restated safely hides the whole question.
 */
class AskAiPublicPropertyQuestionService
{
    /** Roles whose listings describe a property. Mirrors SnapshotFactVisibility. */
    private const PROPERTY_ROLES = ['seller', 'landlord'];

    /**
     * Roles whose listings describe SEARCH CRITERIA rather than a property (Batch 2d).
     *
     * SnapshotFactVisibility::classify() returns OWNER_ONLY for every one of their keys and
     * KEEPS DOING SO — decision D2 is unchanged by this batch and a test asserts it. These
     * roles therefore have no public tier to draw on, and the two catalogs below are not an
     * exception to one: they ARE the tier, and the only way a buyer or tenant value reaches
     * this surface.
     */
    private const CRITERIA_ROLES = ['buyer', 'tenant'];

    /** Every role this surface will answer for. */
    private const ELIGIBLE_ROLES = ['seller', 'landlord', 'buyer', 'tenant'];

    /**
     * Context keys defined by AskAiContextBuilderService::extractListingFields() outside
     * CANONICAL_SOURCE_MAP. Named here so a criteria entry may source one and a typo still
     * fails closed on "not a declared key" rather than silently reading nothing.
     */
    private const CONTEXT_BASE_KEYS = [
        'listing_type', 'listing_id', 'listing_title', 'city', 'state', 'county',
        'property_type', 'listing_status', 'created_at', 'updated_at',
    ];

    /**
     * PUBLIC_BUYER_CRITERIA — the complete set of buyer context keys this surface may read.
     *
     * WHAT MAKES A KEY ELIGIBLE. It must describe the PROPERTY THE BUYER IS LOOKING FOR, and
     * it must already be published on the public buyer listing page. What QUALIFIES the buyer
     * is not eligible however public it looks: a budget is what someone intends to SPEND and
     * belongs here; a pre-approval amount, cash reserves, a down payment, a credit score and a
     * lender's opinion are what a seller would use to JUDGE them and never do. The two are one
     * row apart on the form and are not the same fact.
     *
     * This is an ALLOWLIST over a 40-key context that also carries pre_approved,
     * pre_approval_amount, credit_score_range, number_of_units, commute_destination_zip and
     * the buyer's own address. A deny-list over that would have to stay complete forever
     * against a context map that gains keys without asking this file.
     *
     * Nothing here is read by any model provider, by Agent AI, by the snapshot builder, by
     * the generic Ask AI context or by the Knowledge Base. This surface renders fixed
     * sentences into the page. (Provider names are deliberately not spelled out anywhere in
     * this file: PublicPropertyQuestionAvailabilityTest proves the isolation by scanning
     * this source for them, and a mention in a comment would defeat that scan.)
     */
    private const PUBLIC_BUYER_CRITERIA = [
        'max_price'                => 'Published as "Max Purchase Budget" — the amount the buyer intends to spend.',
        'cities'                   => 'Published under "Preferred Locations".',
        'counties'                 => 'Published under "Preferred Locations".',
        'property_type'            => 'Published as "Property Type" in Purchase Criteria.',
        'bedrooms'                 => 'Published as "Bedrooms"; the form asks "Minimum Bedrooms Needed".',
        'bathrooms'                => 'Published as "Bathrooms"; the form asks "Minimum Bathrooms Needed".',
        'square_feet'              => 'Published as "Min. Heated Sq Ft".',
        'total_acreage'            => 'Published as "Min. Acreage" from the form\'s own acreage bands.',
        'pool'                     => 'Published as "Pool" — a Yes/No requirement, never a count.',
        'garage'                   => 'Published as "Garage" — a Yes/No requirement, never a count.',
        'closing_date'             => 'Published as "Target Closing Date" from a closed timeframe list.',
        'non_negotiable_amenities' => 'Published as "Non-Negotiable Amenities".',
        'water_view'               => 'Published as "View Preference".',
    ];

    /**
     * PUBLIC_CRITERIA_META_SOURCES — the narrow PAGE accessor, for criteria a listing
     * publishes but the shared AI context does not carry.
     *
     * WHY THIS EXISTS INSTEAD OF THREE MORE CONTEXT KEYS.
     *
     * Three tenant criteria the listing page publishes — the rent budget, the pets Yes/No
     * and the furnishings select — had no key in AskAiContextBuilderService's
     * CANONICAL_SOURCE_MAP. Adding them there is the obvious move and it is the wrong one:
     * that map feeds extractListingFields(), which feeds the generic Ask AI context, Agent
     * AI and the snapshot path. Three fields would have been widened into every AI surface
     * in order to make one deterministic card work, and Batch 2d's whole premise is that
     * buyer and tenant criteria are a PAGE-Q&A admission and nothing else.
     *
     * So this surface reads them from the page's OWN `$meta` array — the same array the
     * Blade file reads, already redacted for a non-owner by CriteriaPrivacyPolicy — through
     * this allowlist, and nothing else in $meta is reachable.
     *
     * FOUR THINGS ARE CHECKED BEFORE A KEY HERE IS READ (see criteriaMetaValue()):
     *   1. the logical name is declared below for that role;
     *   2. every underlying meta key is PUBLIC under CriteriaPrivacyPolicy — the same
     *      Phase A decision that governs whether the page itself may print it, so this card
     *      can never publish something the page redacts;
     *   3. no underlying meta key is a SnapshotFactVisibility RESTRICTED key. This is what
     *      makes `max_rent`, `min_rent` and `rental_price` structurally unusable here, not
     *      merely unused;
     *   4. the entry declares source_kind 'criteria_meta' and a criteria role.
     *
     * Keys are read in the PAGE'S OWN precedence order, so the answer and the row it
     * restates cannot disagree about which stored value won.
     */
    private const PUBLIC_CRITERIA_META_SOURCES = [
        'tenant' => [
            // The page's "Rent Budget" row and hero read budget ?: desired_rental_amount ?:
            // maximum_budget. NOT max_rent / min_rent / rental_price: those are a LANDLORD's
            // advertised rent range, they are RESTRICTED for every role, and rule 3 refuses
            // them even if someone lists them here.
            'rent_budget' => [
                'keys' => ['budget', 'desired_rental_amount', 'maximum_budget'],
                'why'  => 'Published as "Rent Budget"; the form asks "Maximum Monthly Lease Price".',
            ],
            // The form's own "Pets:" Yes/No — whether the tenant needs a pet-friendly
            // property. Never service_animal or emotional_support_animal, which are
            // accommodation disclosures and are private under Phase A anyway (rule 2).
            'pets_allowed' => [
                'keys' => ['pets'],
                'why'  => 'Published as "Pets" — the pet-friendly housing requirement.',
            ],
            // `tenant_require` holds "Furnished" / "Unfurnished" / "Turnkey" from the
            // "Furnishings Needed:" select. Its name reads like an occupant requirement and
            // is not one; the formatter refuses any value outside that vocabulary.
            'furnishings' => [
                'keys' => ['tenant_require'],
                'why'  => 'Published from the form\'s "Furnishings Needed" select.',
            ],
        ],
    ];

    /**
     * PUBLIC_TENANT_CRITERIA — the complete set of tenant context keys this surface may read.
     *
     * The same rule, and a sharper version of the same danger. The tenant context carries
     * monthly_income, credit_score_range, prior_eviction, prior_felony, service_animal,
     * emotional_support_animal, accessibility_requirements, number_of_occupants, the tenant's
     * own address and their commute destination. Every one of those is a disclosure about a
     * PERSON, several are protected-class adjacent under Fair Housing, and none is a property
     * search criterion. None appears below, and none can reach this surface by any other path.
     *
     * A SERVICE OR SUPPORT ANIMAL IS NOT A PET. `pets_allowed` is the form's own "Pets:"
     * Yes/No — whether the tenant needs a property that allows pets, which is a housing
     * requirement. `service_animal` and `emotional_support_animal` are accommodation
     * disclosures, they are absent from this list, and they are never read beside it.
     */
    private const PUBLIC_TENANT_CRITERIA = [
        'cities'                   => 'Published as "Cities" under Location Preferences.',
        'counties'                 => 'Published as "Counties" under Location Preferences.',
        'zip_codes'                => 'Published as "ZIP Codes" under Location Preferences.',
        'property_type'            => 'Published as "Property Type" under Desired Property Features.',
        'bedrooms'                 => 'Published as "Bedrooms"; the form asks "Minimum Bedrooms Needed".',
        'bathrooms'                => 'Published as "Bathrooms"; the form asks "Minimum Bathrooms Needed".',
        'square_feet'              => 'Published as "Minimum Heated Sq Ft".',
        'total_acreage'            => 'Published as "Min Acreage"; the form asks "Minimum Total Acreage Needed".',
        'desired_lease_length'     => 'Published as "Desired Lease Length" — the tenant\'s own desired term. NOT min_lease_period, which is a seller/HOA leasing RESTRICTION on a property and is not a tenant field at all.',
        'move_in_date_earliest'    => 'Published as "Earliest Move-In Date".',
        'move_in_date_latest'      => 'Published as "Latest Move-In Date".',
        'non_negotiable_amenities' => 'Published as "Required Amenities".',
        'appliances'               => 'Published as "Appliances Needed".',
        'property_items'           => 'Published as "Property Items / Features".',
        'water_view'               => 'Published as "View Preferences".',
        'pool'                     => 'Published as "Pool Needed" — a Yes/No requirement.',
    ];

    /**
     * Sources this surface may restate although SnapshotFactVisibility keeps them owner-only
     * for the AI context. Each is already published on the listing page. Used ONLY by
     * entries declaring source_kind 'admitted_listing', and only as the source_path — never
     * as a supporting path.
     */
    private const PUBLIC_QUESTION_ADMISSIONS = [
        'seller' => [
            // The page's "Offered Financing" row and hero badges publish the financing
            // types (product decision 2026-09-15). Seller-financing TERMS — down payment,
            // interest rate, term, balloon — stay RESTRICTED and are never read here.
            'offered_financing' => 'Offered financing types are published on the seller listing page.',
        ],
    ];

    /**
     * BATCH 4 — the knowledge-base answers a public listing page may restate.
     *
     * THE KNOWLEDGE BASE IS OWNER-ONLY AND STAYS OWNER-ONLY. Every KB answer is private:
     * the snapshot rows are OWNER_ONLY, the non-owner context redaction removes the whole
     * `faq_answers` structure, and none of that is relaxed. This constant is a separate,
     * narrower admission for ONE surface — the deterministic public question card — and
     * it names individual keys, never the store.
     *
     * WHAT EARNED A PLACE HERE. Only factual, property-descriptive questions whose answer
     * a shopper could get from a listing sheet or a showing: systems and their age,
     * utilities, what conveys, parking, notice periods. Deliberately absent, and not to be
     * added without a separate decision: the owner's MOTIVATION and negotiating posture
     * (why they are selling, leaseback, concessions, flexibility), DISCLOSURE and defect
     * history (known issues, foundation, pest, flood damage, mould, claims, deferred
     * maintenance), anything describing WHO the property suits (neighbourhood character,
     * ideal tenant/buyer fit, target industries, redevelopment vision), OTHER PEOPLE'S
     * data (existing tenant lease terms, rent roll, payment history, every `business_*`
     * key), and the whole `insight` category, which exists to prompt interpretation
     * rather than to state a fact. Buyer and Tenant knowledge bases are absent entirely
     * and have no group here at all.
     *
     * THE GROUP IS DECLARED, AND THE DECLARATION IS CHECKED. Each key is filed under the
     * config group it belongs to. At read time the key must ALSO be in
     * AskAiFaqConfigService::gatedKeys() for this listing's property type AND still sit in
     * the group declared here. Two independent statements of the same fact: if a key is
     * ever moved between groups in the config, it stops being publishable instead of
     * quietly becoming public for a different property type.
     *
     * @var array<string, array<string, list<string>>> role => config group => keys
     */
    private const PUBLIC_SAFE_KB_KEYS = [
        'seller' => [
            'universal' => [
                'items_excluded_from_sale',
            ],
            'residential' => [
                'roof_age_and_condition',
                'hvac_system_age',
                'water_heater_age_type',
                'recent_renovations_list',
                'solar_panels_owned_leased',
                'smart_home_ev_features',
                'pool_spa_equipment_condition',
                'average_utility_costs',
                'internet_utility_providers',
                'storage_space_available',
            ],
            'income' => [
                'professional_management',
                'income_utilities_split',
                'income_building_systems_age',
            ],
            'commercial' => [
                'commercial_building_systems',
                'commercial_restroom_count',
                'commercial_parking_loading',
                'commercial_systems_age',
                'commercial_recent_improvements',
                'commercial_ceiling_height',
            ],
            'land' => [
                'land_survey_available',
                'land_utilities_available',
            ],
        ],
        'landlord' => [
            'universal' => [
                'maintenance_request_response_time',
                'planned_renovations',
                'notice_to_vacate_required',
            ],
            'residential' => [
                'internet_providers',
                'furnished_or_unfurnished',
                'ev_charging_available',
                'short_term_rentals_allowed',
                'utilities_individually_metered',
                'renters_insurance_required',
                'lawn_landscaping_responsibility',
                'guest_parking',
            ],
            'commercial' => [
                'commercial_loading_dock_freight_elevator',
                'commercial_electrical_capacity',
                'commercial_hvac_zones',
                'commercial_cam_structure',
                'commercial_parking_ratio',
            ],
        ],
    ];

    /**
     * How an owner's own words are attributed when a public page restates them.
     *
     * These are STATEMENTS BY THE OWNER, not facts the platform verified, and the page
     * must say so in the answer itself rather than in a footnote a reader may not
     * connect to it. The substantive answer follows verbatim.
     */
    private const KB_ATTRIBUTION = [
        'seller'   => 'According to the seller: ',
        'landlord' => 'According to the landlord: ',
    ];

    /**
     * Answers that are typed but say nothing, compared after lowercasing, trimming and
     * stripping trailing punctuation.
     *
     * "No" and "None" are NOT here and must never be added. "Is there EV charging? — No"
     * is a complete, useful, meaningful answer; treating it as blank would hide the very
     * questions a shopper most wants settled and would quietly favour listings whose
     * answer happens to be yes.
     */
    private const KB_PLACEHOLDER_ANSWERS = [
        'n/a', 'na', 'n.a.', 'unknown', 'not sure', 'unsure', 'tbd', 't.b.d.',
        'not provided', 'idk', 'no idea', 'not applicable', 'not available',
        '-', '--', '---', '?', '??', '...', '.', 'x', 'none of the above',
    ];

    /**
     * Where knowledge-base questions start in the display order.
     *
     * Far above the field-sourced catalog (seller's highest is 180) so the verified
     * structured facts a shopper came for lead the card, and the owner's own statements
     * follow them rather than interleaving with them.
     */
    private const KB_ORDER_BASE = 1000;

    /** A published KB answer longer than this is withheld rather than truncated. */
    private const KB_MAX_ANSWER_LENGTH = 1200;

    /**
     * The listing meta key holding the owner's explicit publication acknowledgement.
     *
     * ONE LISTING-LEVEL FLAG, NOT 38 TOGGLES. The allowlist, the property-type gating,
     * the Fair Housing policy and the PII screen already decide WHICH answers may be
     * published; what was missing is the owner agreeing that any of them may be. A
     * per-question toggle would ask an owner to re-answer a question they have already
     * answered, 38 times, and would make "which of these did I turn on" a thing they must
     * remember. This asks once.
     *
     * NO SCHEMA CHANGE. It is an ordinary sibling of `listing_ai_faq` in the same EAV meta
     * store, written by the same saveMeta() calls in the same components, and read from
     * the same fully-decoded $meta array the controllers already build from every meta
     * row. Nothing new had to be created to hold one boolean.
     */
    public const KB_PUBLICATION_ACK_META_KEY = 'listing_ai_faq_public_ack';

    /**
     * The stored values that count as an acknowledgement.
     *
     * An EAV meta value is a string, and a checkbox round-trips through Livewire and
     * json_encode as any of these. Matched EXACTLY, after lowercasing and trimming:
     * absent, empty, '0', 'false', 'off', 'no', a stray blob or any unrecognised value is
     * NOT confirmed. This is the same fail-closed parsing the platform's provider
     * switches use, and for the same reason — an unreadable value is not permission.
     */
    private const KB_PUBLICATION_ACK_TRUTHY = ['1', 'true', 'on', 'yes'];

    /**
     * Frequencies with an unambiguous English phrase, keyed by the normalised spelling
     * (lower case, '_' and spaces as '-'), so 'Semi-Annually', 'semi_annually' and
     * 'semi-annually' all agree. Anything else — 'Bi-Monthly' included, which reads as both
     * twice a month and every two months — gets no period at all.
     */
    private const HOA_FREQUENCY_PHRASES = [
        'monthly'       => 'per month',
        'quarterly'     => 'per quarter',
        'semi-annually' => 'every six months',
        'annually'      => 'per year',
    ];

    /** Stored values that say nothing; never published as an answer or an "Other" text. */
    private const PLACEHOLDER_VALUES = [
        'other', 'n/a', 'na', 'none', 'unknown', 'tbd', 't.b.d.', 'not applicable',
        'not available', 'see remarks', 'see private remarks', 'per remarks', '-', '--', '?', '.',
    ];

    /** Longest free-text value ("Other" text, zoning code) restated verbatim. */
    private const MAX_VERBATIM_LENGTH = 60;

    /** Deterministic range for a plausible construction year. */
    private const YEAR_BUILT_MIN = 1600;
    private const YEAR_BUILT_MAX = 2100;

    /**
     * The available questions for one listing, in catalog order.
     *
     * @param  string               $role     'seller' | 'landlord' (anything else returns [])
     * @param  array                $context  AskAiContextBuilderService::buildChipContext() output
     * @param  array<string,mixed>  $meta     The listing's decoded meta array (guards only)
     * @param  array<string,mixed>  $viewer   Batch 4. What the PAGE already decided about this
     *                                        viewer, passed in rather than re-derived:
     *                                        'address_withheld' (bool) and the listing's own
     *                                        'address' / 'unit' for the PII screen's
     *                                        withheld-address rule.
     *
     *                                        REQUIRED for knowledge-base questions, and a
     *                                        real boolean: an absent or non-boolean
     *                                        'address_withheld', or a withheld address with
     *                                        nothing to screen against, withholds every KB
     *                                        answer as 'kb_address_visibility_unknown'. An
     *                                        earlier version defaulted the flag to true and
     *                                        called that fail-closed; it was not, because an
     *                                        empty fragment list means the withheld-address
     *                                        rule never runs.
     *
     *                                        Field-sourced (non-KB) questions ignore it
     *                                        entirely and are unaffected by its absence.
     * @return list<array{id: string, question: string, answer: string, source_path: string}>
     */
    public function forListing(string $role, array $context, array $meta, array $viewer = []): array
    {
        $role = strtolower(trim($role));
        if (!in_array($role, self::ELIGIBLE_ROLES, true)) {
            return [];
        }

        $catalog = array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry(),
            static fn ($entry): bool => is_array($entry) && ($entry['role'] ?? null) === $role
        );

        // Batch 4 — the curated knowledge-base questions, built from the canonical KB
        // config for THIS listing's role and property type. They are ordinary catalog
        // entries from here on, which is the point: they sort, evaluate, suppress and
        // emit their aliases through exactly the same machinery, so the typed matcher
        // and the card need to know nothing about where a question came from.
        foreach ($this->kbCatalog($role, $meta) as $id => $entry) {
            // A static catalog id always wins. Nothing collides today and a test pins
            // that, but a silent overwrite of a field-sourced question by a KB one is
            // not a failure mode worth leaving open.
            if (!array_key_exists($id, $catalog)) {
                $catalog[$id] = $entry;
            }
        }
        // Display order; usort is stable (PHP 8), so equal orders keep catalog order.
        $ids = array_keys($catalog);
        usort($ids, static fn ($a, $b): int => ((int) ($catalog[$a]['order'] ?? PHP_INT_MAX)) <=> ((int) ($catalog[$b]['order'] ?? PHP_INT_MAX)));

        $available = [];
        foreach ($ids as $id) {
            $result = $this->evaluate($catalog[$id], $role, $context, $meta, $viewer);
            if ($result['available']) {
                $available[$id] = $result['answer'];
            }
        }

        $questions = [];
        foreach ($available as $id => $answer) {
            $entry = $catalog[$id];

            // narrower_of: this entry is the narrower fallback of a richer composite. When that
            // composite is itself available for this listing it answers the same question more
            // completely, so the narrower one is not shown beside it. A narrower_of that names
            // nothing in this role's catalog suppresses nothing.
            $richer = $entry['narrower_of'] ?? null;
            if (is_string($richer) && $richer !== $id && isset($available[$richer])) {
                continue;
            }

            $questions[] = [
                'id'          => (string) $id,
                'question'    => (string) $entry['question'],
                'answer'      => $answer,
                'source_path' => (string) $entry['source_path'],
                // Batch 3: the typed-question matcher's vocabulary for THIS question.
                //
                // Emitted here, inside the loop that already decided the question is
                // available, and nowhere else — so a question that did not pass evaluation
                // has no aliases to ship. That is the security boundary as a structural
                // property rather than a rule someone has to remember: there is no separate
                // catalog for the browser to receive, and no code path that could hand it
                // the vocabulary of a question this listing cannot answer.
                'aliases'     => $this->aliases($entry),
            ];
        }

        return $questions;
    }

    /**
     * A question's typed-input vocabulary, cleaned the same way the browser cleans a query.
     *
     * Normalised HERE as well as in the browser so the two sides cannot drift: a stored
     * alias with stray case, padding or punctuation would otherwise be unmatchable by an
     * input that had been normalised. Blank entries are dropped and duplicates collapse, so
     * "move in" and "Move-In" cannot both occupy the list.
     *
     * @return list<string>
     */
    private function aliases(array $entry): array
    {
        $aliases = $entry['aliases'] ?? [];
        if (!is_array($aliases)) {
            return [];
        }

        $clean = [];
        foreach ($aliases as $alias) {
            if (!is_string($alias)) {
                continue;
            }
            $normalised = self::normalizeQuery($alias);
            if ($normalised !== '') {
                $clean[] = $normalised;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * The one definition of how a typed question is cleaned before it is compared.
     *
     * PUBLIC AND STATIC because it is a CONTRACT, not a helper: the browser implements the
     * same steps in JavaScript, and a test drives this method with the same inputs to prove
     * the two agree. Deliberately tiny — lower-case, collapse whitespace, straighten quotes,
     * treat a hyphen as a space, and drop sentence punctuation. No stemming, no fuzzy
     * distance, no typo correction, no library: every one of those turns "did not match"
     * into "matched something else", which is the failure this surface exists to avoid.
     */
    public static function normalizeQuery(string $text): string
    {
        // Curly quotes and the dashes people actually type, flattened first so the
        // punctuation rules below see one spelling of each.
        $text = strtr($text, [
            "\u{2019}" => "'", "\u{2018}" => "'", "\u{02BC}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-',
            "\u{2013}" => '-', "\u{2014}" => '-', "\u{2015}" => '-',
        ]);

        $text = mb_strtolower($text, 'UTF-8');

        // A hyphen is a space: "move-in date" and "move in date" are one question.
        $text = str_replace(['-', '_', '/'], ' ', $text);

        // Sentence punctuation only. Apostrophes stay, so "the buyer's budget" keeps its
        // shape and the displayed question remains matchable exactly as written.
        $text = str_replace(['?', '!', '.', ',', ':', ';', '"'], ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Apply the availability rule to one catalog entry.
     *
     * @return array{available: bool, reason: string, answer: string|null}
     */
    public function evaluate(array $entry, string $role, array $context, array $meta, array $viewer = []): array
    {
        $role = strtolower(trim($role));

        // 1. Role and catalog membership.
        if (!in_array($role, self::ELIGIBLE_ROLES, true)) {
            return $this->hidden('role_not_public_eligible');
        }
        if (($entry['role'] ?? null) !== $role || !is_string($entry['question'] ?? null) || trim($entry['question']) === '') {
            return $this->hidden('not_in_catalog_for_role');
        }

        // 2 + 3. One exact, defined, public (or explicitly admitted) source — and every
        // supporting path public in its own right.
        $sourceKind = $entry['source_kind'] ?? 'listing';
        if (!in_array($sourceKind, ['listing', 'admitted_listing', 'criteria_meta', 'kb'], true)) {
            return $this->hidden('source_kind_unknown');
        }

        // Batch 4 — a knowledge-base answer. Its whole admission is decided here, in one
        // place, and it shares none of the context/formatter path below: a KB answer is
        // the owner's own sentence, not a listing fact to be formatted, so there is no
        // source_path into the shared context to resolve and nothing for a formatter to
        // reject. Keeping it inside evaluate() is what makes forListing()'s ordering,
        // suppression and alias emission apply to it unchanged.
        if ($sourceKind === 'kb') {
            return $this->evaluateKb($entry, $role, $meta, $viewer);
        }
        // A criteria entry has exactly two admissions — its role's public criteria catalog
        // (context keys) and its narrow page-meta allowlist. Refusing the property roles'
        // mechanism here means a catalog entry that tried to borrow it fails visibly instead
        // of quietly resolving some other way; and 'criteria_meta' is refused for a property
        // role for the mirror-image reason.
        if ($sourceKind === 'admitted_listing' && in_array($role, self::CRITERIA_ROLES, true)) {
            return $this->hidden('source_kind_not_available_for_criteria_role');
        }
        if ($sourceKind === 'criteria_meta' && !in_array($role, self::CRITERIA_ROLES, true)) {
            return $this->hidden('source_kind_not_available_for_property_role');
        }

        $metaSourced = $sourceKind === 'criteria_meta';
        $sourceKey   = $metaSourced
            ? $this->criteriaMetaName($entry['source_path'] ?? null, $role)
            : $this->publicListingKey($entry['source_path'] ?? null, $role, $sourceKind === 'admitted_listing');
        if ($sourceKey['reason'] !== null) {
            return $this->hidden($sourceKey['reason']);
        }

        $supportingPaths = $entry['supporting_paths'] ?? [];
        if (!is_array($supportingPaths)) {
            return $this->hidden('supporting_paths_invalid');
        }
        $supporting = [];
        foreach ($supportingPaths as $path) {
            $check = $this->publicListingKey($path, $role);
            if ($check['reason'] !== null) {
                return $this->hidden('supporting_' . $check['reason']);
            }
            $supporting[$check['key']] = $this->listingValue($context, $check['key']);
        }

        // 4. A meaningful value — from the page's own meta for a criteria_meta source,
        //    otherwise from the shared context.
        $value = $metaSourced
            ? $this->criteriaMetaValue($sourceKey['key'], $role, $meta)
            : $this->listingValue($context, $sourceKey['key']);
        if ($this->isEmpty($value)) {
            return $this->hidden('value_missing');
        }

        // 6 (before formatting, so a guard never depends on formatter output).
        $guards = $entry['guards'] ?? [];
        if (!is_array($guards)) {
            return $this->hidden('guards_invalid');
        }
        foreach ($guards as $guard) {
            $passed = $this->guardPasses((string) $guard, $context, $meta);
            if ($passed === null) {
                return $this->hidden('guard_unknown:' . $guard);
            }
            if ($passed === false) {
                return $this->hidden('guard_failed:' . $guard);
            }
        }

        // The declared "Other" companion(s): the stored selections and the "Other" text. One
        // field declares 'other_companion'; a composite reading several declares
        // 'other_companions' (name => spec). Declaring both is malformed.
        if (array_key_exists('other_companion', $entry) && array_key_exists('other_companions', $entry)) {
            return $this->hidden('other_companion_invalid');
        }
        $specs = array_key_exists('other_companions', $entry)
            ? $entry['other_companions']
            : ['default' => $entry['other_companion'] ?? null];
        if (!is_array($specs) || $specs === []) {
            return $this->hidden('other_companion_invalid');
        }
        $companions = [];
        foreach ($specs as $name => $spec) {
            $resolved = $this->companion($spec, $meta);
            if ($resolved === false || !is_string($name)) {
                return $this->hidden('other_companion_invalid');
            }
            if (($resolved['unsafe'] ?? false) === true) {
                return $this->hidden('other_companion_unsafe');
            }
            $companions[$name] = $resolved;
        }
        $companion = $companions['default'] ?? null;

        // 5. A deterministic formatter that accepts this exact value.
        $formatter = (string) ($entry['formatter'] ?? '');
        if (!$this->hasFormatter($formatter)) {
            return $this->hidden('formatter_missing');
        }
        $answer = $this->format($formatter, $value, $supporting, $companion, $companions, $role);
        if ($answer === null) {
            return $this->hidden('formatter_rejected_value');
        }

        return ['available' => true, 'reason' => 'available', 'answer' => $answer];
    }

    // =========================================================================
    // Source resolution
    // =========================================================================

    /**
     * The sources this surface may restate beyond SnapshotFactVisibility's public tier.
     *
     * @return array<string, array<string, string>> role => [key => reason]
     */
    public static function publicQuestionAdmissions(): array
    {
        return self::PUBLIC_QUESTION_ADMISSIONS;
    }

    /**
     * The explicit public criteria catalogs for the criteria roles (Batch 2d).
     *
     * @return array<string, array<string, string>> role => [context key => reason]
     */
    public static function publicCriteria(): array
    {
        return [
            'buyer'  => self::PUBLIC_BUYER_CRITERIA,
            'tenant' => self::PUBLIC_TENANT_CRITERIA,
        ];
    }

    /** Roles whose listings are search criteria rather than a property. */
    public static function criteriaRoles(): array
    {
        return self::CRITERIA_ROLES;
    }

    /**
     * Resolve 'listing.<key>' to a key that is defined for the role AND public — or, when
     * $allowAdmission is set (an 'admitted_listing' source), explicitly admitted.
     *
     * @return array{key: string|null, reason: string|null}
     */
    private function publicListingKey(mixed $path, string $role, bool $allowAdmission = false): array
    {
        if (!is_string($path) || preg_match('/^listing\.([a-z0-9_]+)$/', $path, $m) !== 1) {
            return ['key' => null, 'reason' => 'source_path_invalid'];
        }

        $key = $m[1];

        $declared = array_key_exists($key, AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role] ?? [])
            || in_array($key, self::CONTEXT_BASE_KEYS, true);
        if (!$declared) {
            return ['key' => null, 'reason' => 'source_not_in_context_map'];
        }

        // A compliance-restricted key is refused for every role, ahead of every allowlist.
        if (SnapshotFactVisibility::classify($key, $role) === SnapshotFactVisibility::RESTRICTED) {
            return ['key' => null, 'reason' => 'not_public_allowed'];
        }

        // Buyer / tenant: the explicit criteria catalog is the ONLY admission, and it governs
        // supporting paths exactly as it governs the source. There is no public tier to fall
        // back to — SnapshotFactVisibility answers OWNER_ONLY for every key of these roles
        // (D2, unchanged) — so a key absent from the catalog has no way through. The seller's
        // 'admitted_listing' mechanism is deliberately NOT honoured here: it widens a public
        // tier, and these roles have none.
        if (in_array($role, self::CRITERIA_ROLES, true)) {
            return isset(self::publicCriteria()[$role][$key])
                ? ['key' => $key, 'reason' => null]
                : ['key' => null, 'reason' => 'not_public_allowed'];
        }

        if (SnapshotFactVisibility::classify($key, $role) === SnapshotFactVisibility::PUBLIC_ALLOWED) {
            return ['key' => $key, 'reason' => null];
        }

        // RESTRICTED keys are never admitted, whatever the admission list says, and the
        // admission mechanism belongs to the property roles alone — it widens a public tier,
        // and a criteria role has none. (Unreachable for a criteria role, which returned
        // above; stated so the two mechanisms cannot be confused for each other later.)
        if (
            $allowAdmission
            && in_array($role, self::PROPERTY_ROLES, true)
            && SnapshotFactVisibility::classify($key, $role) === SnapshotFactVisibility::OWNER_ONLY
            && isset(self::PUBLIC_QUESTION_ADMISSIONS[$role][$key])
        ) {
            return ['key' => $key, 'reason' => null];
        }

        return ['key' => null, 'reason' => 'not_public_allowed'];
    }

    /**
     * Resolve 'criteria_meta.<name>' to a declared page-meta source for a criteria role.
     *
     * @return array{key: string|null, reason: string|null}
     */
    private function criteriaMetaName(mixed $path, string $role): array
    {
        if (!is_string($path) || preg_match('/^criteria_meta\.([a-z0-9_]+)$/', $path, $m) !== 1) {
            return ['key' => null, 'reason' => 'source_path_invalid'];
        }

        return isset(self::PUBLIC_CRITERIA_META_SOURCES[$role][$m[1]])
            ? ['key' => $m[1], 'reason' => null]
            : ['key' => null, 'reason' => 'not_public_allowed'];
    }

    /**
     * The value of a declared page-meta source, in the page's own precedence order.
     *
     * Every underlying key is re-checked here rather than trusted from the declaration, so
     * the guarantee holds against the CONSTANT as it is at run time and not merely against
     * the version somebody reviewed:
     *
     *   - a key that CriteriaPrivacyPolicy makes owner-only on this page cannot be read,
     *     which means this card can never publish something the page itself redacts;
     *   - a key that SnapshotFactVisibility marks RESTRICTED cannot be read, which is what
     *     makes `max_rent`, `min_rent` and `rental_price` structurally unusable here.
     *
     * A refused key does not fall through to the next one — the whole source resolves empty,
     * so a restricted key listed ahead of a usable one hides the question instead of quietly
     * handing the answer to the fallback.
     */
    private function criteriaMetaValue(string $name, string $role, array $meta): mixed
    {
        $keys = self::PUBLIC_CRITERIA_META_SOURCES[$role][$name]['keys'] ?? [];
        if (!is_array($keys) || $keys === []) {
            return null;
        }

        foreach ($keys as $key) {
            if (!is_string($key) || preg_match('/^[a-z0-9_]+$/', $key) !== 1) {
                return null;
            }
            if (CriteriaPrivacyPolicy::isPrivate($role, $key)) {
                return null;
            }
            if (SnapshotFactVisibility::classify($key, $role) === SnapshotFactVisibility::RESTRICTED) {
                return null;
            }
        }

        foreach ($keys as $key) {
            $value = $meta[$key] ?? null;
            if (!$this->isEmpty($value)) {
                return is_array($value) ? json_encode($value) : $value;
            }
        }

        return null;
    }

    /**
     * The declared page-meta sources, for tests and documentation.
     *
     * @return array<string, array<string, array{keys: list<string>, why: string}>>
     */
    public static function publicCriteriaMetaSources(): array
    {
        return self::PUBLIC_CRITERIA_META_SOURCES;
    }


    // =========================================================================
    // Batch 4 — public-safe knowledge-base questions
    // =========================================================================

    /** The Batch 4 allowlist, for tests and audits. @return array<string,array<string,list<string>>> */
    public static function publicSafeKbKeys(): array
    {
        return self::PUBLIC_SAFE_KB_KEYS;
    }

    /** Every allowlisted key for one role, flattened. @return list<string> */
    public static function publicSafeKbKeysForRole(string $role): array
    {
        $groups = self::PUBLIC_SAFE_KB_KEYS[strtolower(trim($role))] ?? [];
        $keys   = [];

        foreach ($groups as $group) {
            foreach ($group as $key) {
                $keys[] = (string) $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Is this key one the public card may EVER carry, for this role?
     *
     * The single predicate the knowledge-base FORM asks so it can mark the questions an
     * owner should know are publishable. It answers membership of the allowlist only —
     * not whether any particular listing's answer will actually appear, which depends on
     * the property type, what was written, and both safety screens.
     */
    public static function isPublicSafeKbKey(string $role, string $key): bool
    {
        return in_array($key, self::publicSafeKbKeysForRole($role), true);
    }

    /**
     * Build the KB questions that are even CANDIDATES for this listing.
     *
     * Three independent narrowings, in order, each able to produce nothing:
     *   1. the role has an allowlist at all (seller and landlord only — buyer and tenant
     *      have no entry in PUBLIC_SAFE_KB_KEYS, so their knowledge bases can produce no
     *      candidate here by construction, not by a check someone must remember);
     *   2. the key is gated ON for this listing's property type by the existing
     *      AskAiFaqConfigService gating — the SAME definition the form renders from, so a
     *      residential listing cannot offer a commercial question and an absent or
     *      unrecognised property type falls back to universal-only;
     *   3. the key still sits in the group this class declared for it.
     *
     * Nothing about the ANSWER is consulted here. Whether the owner filled it in, and
     * whether what they wrote is publishable, is evaluateKb()'s decision — so a question
     * that fails those checks never becomes an available question and therefore never
     * emits a display question or an alias.
     *
     * @return array<string, array> catalog id => entry
     */
    private function kbCatalog(string $role, array $meta): array
    {
        $allowlist = self::PUBLIC_SAFE_KB_KEYS[$role] ?? null;

        if (!is_array($allowlist) || $allowlist === []) {
            return [];
        }

        $propertyType = $meta['property_type'] ?? '';
        $propertyType = is_string($propertyType) ? $propertyType : '';

        // The gating SSOT — role + property type, universal-only fail-safe.
        $gatedKeys = AskAiFaqConfigService::gatedKeys($role, $propertyType);

        if ($gatedKeys === []) {
            return [];
        }

        $configured = $this->kbConfiguredEntries($role);
        $catalog    = [];
        $order      = self::KB_ORDER_BASE;

        foreach ($allowlist as $declaredGroup => $keys) {
            foreach ($keys as $key) {
                $key = (string) $key;

                if (!in_array($key, $gatedKeys, true)) {
                    continue;
                }

                $configuredEntry = $configured[$key] ?? null;

                // Fail closed on any disagreement with the canonical config: an unknown
                // key, a key that has moved group, or one with no question text. The
                // alternative — publishing under a guessed or stale label — is how a
                // shopper ends up reading an answer under the wrong question.
                if (!is_array($configuredEntry)
                    || $configuredEntry['group'] !== (string) $declaredGroup
                    || !is_string($configuredEntry['label'])
                    || trim($configuredEntry['label']) === '') {
                    continue;
                }

                $catalog['kb_' . $role . '_' . $key] = [
                    'role'        => $role,
                    // The canonical question text, read from the config the owner answered
                    // under. Never a second copy: a divergent label would ask the public a
                    // subtly different question from the one the owner was answering.
                    'question'    => trim($configuredEntry['label']),
                    'source_kind' => 'kb',
                    'source_path' => 'kb.' . $key,
                    'kb_key'      => $key,
                    'kb_group'    => (string) $declaredGroup,
                    'category'    => 'owner_knowledge',
                    'order'       => $order,
                    // Batch 3 vocabulary. Deliberately EMPTY: exact matching on the
                    // displayed question already works, and inventing aliases for 38
                    // questions — or worse, harvesting them from the owner's answer text —
                    // is how collisions and unintended meanings get in. Aliases here are a
                    // later, audited decision.
                    'aliases'     => [],
                ];

                $order += 10;
            }
        }

        return $catalog;
    }

    /**
     * Flatten one role's KB config to key => ['group' => …, 'label' => …].
     *
     * Reads every group rather than the gated ones, because this is the map used to
     * VERIFY a key's declared group; asking the gated set would make the check circular.
     *
     * @return array<string, array{group:string, label:mixed}>
     */
    private function kbConfiguredEntries(string $role): array
    {
        $configKey = AskAiFaqConfigService::CONFIG_MAP[$role] ?? null;

        if ($configKey === null) {
            return [];
        }

        $config = AskAiFaqConfigService::rawConfig($configKey);
        $out    = [];

        foreach (($config['groups'] ?? []) as $groupName => $categories) {
            if (!is_array($categories)) {
                continue;
            }
            foreach ($categories as $questions) {
                if (!is_array($questions)) {
                    continue;
                }
                foreach ($questions as $key => $entry) {
                    if (is_array($entry) && !isset($out[(string) $key])) {
                        $out[(string) $key] = [
                            'group' => (string) $groupName,
                            'label' => $entry['label'] ?? null,
                        ];
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Decide one knowledge-base question, end to end.
     *
     * Every gate hides; none rewrites. There is no path in this method that returns a
     * modified version of what the owner wrote — an answer is published as typed (bar
     * whitespace collapsing) or it is not published.
     */
    private function evaluateKb(array $entry, string $role, array $meta, array $viewer): array
    {
        // A KB question exists for the property roles only. Buyer and tenant knowledge
        // bases are not publishable by any route, and this is the read-time restatement
        // of that: even a hand-built entry claiming source_kind 'kb' is refused here.
        if (!in_array($role, self::PROPERTY_ROLES, true)) {
            return $this->hidden('kb_not_available_for_role');
        }

        // The owner's explicit publication acknowledgement. Checked BEFORE the key, the
        // answer and both screens, because it is the broadest refusal: without it this
        // listing has no publishable knowledge base at all, and there is nothing to be
        // gained by reading the owner's answers first. Legacy listings — every listing
        // that existed before this gate — carry no acknowledgement and therefore publish
        // nothing, which is the whole point: merging Batch 4 must not make a single
        // previously private answer public.
        if (!self::kbPublicationConfirmed($meta)) {
            return $this->hidden('kb_owner_publication_not_confirmed');
        }

        $key = $entry['kb_key'] ?? null;
        if (!is_string($key) || $key === '' || !$this->isPublicSafeKbKeyInGroup($role, $key, $entry['kb_group'] ?? null)) {
            return $this->hidden('kb_key_not_public_safe');
        }

        // Re-assert the property-type gating at read time. kbCatalog() already applied it;
        // asking again here means a directly-invoked evaluate() cannot bypass it.
        $propertyType = is_string($meta['property_type'] ?? null) ? $meta['property_type'] : '';
        if (!in_array($key, AskAiFaqConfigService::gatedKeys($role, $propertyType), true)) {
            return $this->hidden('kb_key_not_gated_for_property_type');
        }

        $answer = $this->kbStoredAnswer($meta, $key);
        if ($answer === null) {
            return $this->hidden('kb_answer_missing');
        }

        if (!$this->kbAnswerIsMeaningful($answer, $key, $role)) {
            return $this->hidden('kb_answer_not_meaningful');
        }

        if (mb_strlen($answer) > self::KB_MAX_ANSWER_LENGTH) {
            // Withheld whole rather than truncated: a cut-off sentence can invert its own
            // meaning, and an ellipsis on a public page reads as the platform editing the
            // owner.
            return $this->hidden('kb_answer_too_long');
        }

        // Fair Housing. Role-neutral, shares its vocabulary with the Phase 3 landlord
        // policy, and returns a verdict rather than a cleaned string.
        if (!PublicProviderTextPolicy::isPublishable($answer)) {
            return $this->hidden('kb_answer_blocked_by_provider_text_policy');
        }

        // Address visibility must be DECIDED, not assumed.
        //
        // This gate used to default a missing flag to "withheld" and call that failing
        // closed. It was not: addressFragments() with no address returns an empty list,
        // so the withheld-address rule simply never ran and the answer published with
        // LESS screening than if the caller had said anything at all. A caller that
        // forgets the viewer context is exactly the caller whose page state we cannot
        // reason about, so the answer is withheld instead.
        //
        // Two distinct unknowns land here, and both refuse:
        //   - no explicit boolean decision about whether this viewer may see the address;
        //   - a decision of "withheld" that supplies no address to screen against, which
        //     would silently skip the screening this branch exists to perform.
        $addressWithheld = $viewer['address_withheld'] ?? null;
        if (!is_bool($addressWithheld)) {
            return $this->hidden('kb_address_visibility_unknown');
        }

        $withheld = PublicAnswerPiiScreen::addressFragments(
            $addressWithheld,
            $viewer['address'] ?? null,
            $viewer['unit'] ?? null,
        );

        if ($addressWithheld && $withheld === []) {
            return $this->hidden('kb_address_visibility_unknown');
        }

        if (!PublicAnswerPiiScreen::isPublishable($answer, $withheld)) {
            // The reason names the category only. It never carries the matched text, and
            // never the withheld address — a diagnostic that quoted either would publish
            // the thing the screen just refused.
            return $this->hidden('kb_answer_blocked_by_pii_screen');
        }

        $attribution = self::KB_ATTRIBUTION[$role] ?? null;
        if ($attribution === null) {
            return $this->hidden('kb_attribution_missing');
        }

        return ['available' => true, 'reason' => 'available', 'answer' => $attribution . $answer];
    }

    /**
     * Has the OWNER acknowledged that selected knowledge-base answers may be published?
     *
     * Public so the knowledge-base form and the tests can ask the same question the read
     * path asks, rather than each deciding for itself what a stored acknowledgement looks
     * like.
     *
     * DEFAULT IS NOT CONFIRMED, and it is never inferred. A listing saved before this
     * gate existed has no such meta row; a listing whose owner never ticked the box has
     * no such meta row; both read as false. Un-ticking the box writes a falsey value and
     * every KB-derived public answer stops at the next render — there is no cache and no
     * snapshot in this path, so revocation takes effect immediately.
     *
     * Deliberately NOT inferred from the presence of answers, from a past save, or from
     * the listing being published: none of those is the owner saying yes to this.
     */
    public static function kbPublicationConfirmed(array $meta): bool
    {
        $value = $meta[self::KB_PUBLICATION_ACK_META_KEY] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (!is_string($value)) {
            // Arrays, objects, null — an acknowledgement is a single scalar yes.
            return false;
        }

        return in_array(strtolower(trim($value)), self::KB_PUBLICATION_ACK_TRUTHY, true);
    }

    /** Allowlist membership, asserted against the group the catalog entry declared. */
    private function isPublicSafeKbKeyInGroup(string $role, string $key, mixed $group): bool
    {
        if (!is_string($group) || $group === '') {
            return false;
        }

        $keys = self::PUBLIC_SAFE_KB_KEYS[$role][$group] ?? null;

        return is_array($keys) && in_array($key, $keys, true);
    }

    /**
     * The owner's stored answer for one key, whitespace-normalised.
     *
     * Reads the page's own meta and nothing else — no database query, no snapshot, no
     * generic Ask AI context. `listing_ai_faq` arrives already decoded when the meta
     * value was JSON; the string branch covers a meta array built by a caller that did
     * not decode.
     */
    private function kbStoredAnswer(array $meta, string $key): ?string
    {
        $store = $meta['listing_ai_faq'] ?? null;

        if (is_string($store)) {
            $decoded = json_decode($store, true);
            $store   = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (!is_array($store) || !array_key_exists($key, $store)) {
            return null;
        }

        $value = $store[$key];

        if (!is_string($value)) {
            return null;
        }

        $value = str_replace(
            ["\xE2\x80\x99", "\xE2\x80\x98", "\xC2\xA0"],
            ["'", "'", ' '],
            $value
        );

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value === '' ? null : $value;
    }

    /**
     * Does this answer actually say something?
     *
     * "No" and "None" pass — see KB_PLACEHOLDER_ANSWERS. So does any ordinary sentence.
     * What does not: the placeholder-equivalents, and the configured placeholder text
     * itself, which owners paste or leave behind surprisingly often and which would
     * otherwise publish the platform's own example back as though it were an answer.
     */
    private function kbAnswerIsMeaningful(string $answer, string $key, string $role): bool
    {
        $compare = strtolower(trim($answer));
        $compare = trim($compare, " \t\n\r\0\x0B.!,;:");

        if ($compare === '') {
            return false;
        }

        if (in_array($compare, self::KB_PLACEHOLDER_ANSWERS, true)) {
            return false;
        }

        $placeholder = $this->kbConfiguredPlaceholder($role, $key);

        return !(is_string($placeholder) && strtolower(trim($placeholder)) === strtolower(trim($answer)));
    }

    /** The configured example text for one key, if the config defines one. */
    private function kbConfiguredPlaceholder(string $role, string $key): ?string
    {
        $configKey = AskAiFaqConfigService::CONFIG_MAP[$role] ?? null;

        if ($configKey === null) {
            return null;
        }

        foreach (AskAiFaqConfigService::rawConfig($configKey)['groups'] ?? [] as $categories) {
            if (!is_array($categories)) {
                continue;
            }
            foreach ($categories as $questions) {
                if (is_array($questions) && isset($questions[$key]['placeholder'])) {
                    $placeholder = $questions[$key]['placeholder'];

                    return is_string($placeholder) ? $placeholder : null;
                }
            }
        }

        return null;
    }

    /**
     * Resolve an entry's declared "Other" companion from meta.
     *
     * Returns null when the entry declares none; false when the declaration is malformed;
     * otherwise the stored selections, the usable "Other" text (null when "Other" is not
     * selected or its text is blank or a placeholder) and whether that text is unsafe to
     * restate (too long, multi-line, or — with reject_figures — carrying figures).
     *
     * @return array{selections: list<string>, other: string|null, unsafe: bool}|false|null
     */
    private function companion(mixed $spec, array $meta): array|false|null
    {
        if ($spec === null) {
            return null;
        }
        if (
            !is_array($spec)
            || !is_string($spec['selected_in'] ?? null) || preg_match('/^[a-z0-9_]+$/', $spec['selected_in']) !== 1
            || !is_string($spec['meta_key'] ?? null) || preg_match('/^[a-z0-9_]+$/', $spec['meta_key']) !== 1
        ) {
            return false;
        }

        $selections    = $this->storedSelections($meta[$spec['selected_in']] ?? null);
        $otherSelected = in_array('other', array_map('strtolower', $selections), true);

        $other  = null;
        $unsafe = false;
        if ($otherSelected) {
            $raw = $meta[$spec['meta_key']] ?? null;
            $text = is_scalar($raw) && !is_bool($raw) ? (string) $raw : '';
            $verdict = $this->verbatim($text, (bool) ($spec['reject_figures'] ?? false));
            if ($verdict === false) {
                $unsafe = true;
            } else {
                $other = $verdict;
            }
        }

        return ['selections' => $selections, 'other' => $other, 'unsafe' => $unsafe];
    }

    /** A stored multi-select (JSON array) or single value, as trimmed, non-empty strings. */
    private function storedSelections(mixed $raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $items   = is_array($decoded) ? $decoded : [$raw];
        } else {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($v): string => is_scalar($v) && !is_bool($v) ? trim((string) $v) : '', $items),
            static fn (string $v): bool => $v !== ''
        ));
    }

    /**
     * A free-text value that may be restated word for word.
     *
     * @return string|null|false  string = usable; null = blank or placeholder (nothing to say);
     *                            false = present but unsafe to restate
     */
    private function verbatim(string $text, bool $rejectFigures = false): string|null|false
    {
        $clean = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');
        if ($clean === '' || in_array(strtolower($clean), self::PLACEHOLDER_VALUES, true)) {
            return null;
        }
        if (
            preg_match('/[\r\n\x00-\x1F\x7F]/', $clean) === 1
            || mb_strlen($clean) > self::MAX_VERBATIM_LENGTH
            || ($rejectFigures && preg_match('/[0-9%$]/', $clean) === 1)
        ) {
            return false;
        }

        return $clean;
    }

    private function listingValue(array $context, string $key): mixed
    {
        $listing = $context['listing'] ?? null;

        return is_array($listing) ? ($listing[$key] ?? null) : null;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || is_bool($value)) {
            return true;
        }
        if (is_array($value)) {
            return $value === [];
        }
        if (!is_scalar($value)) {
            return true;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '[]' || $text === '{}';
    }

    // =========================================================================
    // Guards — hide-only
    // =========================================================================

    /** @return bool|null  null = unknown guard (the caller fails closed) */
    private function guardPasses(string $guard, array $context, array $meta): ?bool
    {
        if (str_starts_with($guard, 'meta_present:')) {
            $metaKey = substr($guard, strlen('meta_present:'));

            return $metaKey !== '' && !$this->isEmpty($meta[$metaKey] ?? null);
        }

        return match ($guard) {
            'not_bidding_period'      => ($meta['auction_type'] ?? null) !== 'Bidding Period',
            'mls_price_not_divergent' => !ListingPriceDisplay::forSeller($meta)->showsSeparateTerms(),
            'acreage_not_overridden'  => $this->acreageNotOverridden($context, $meta),
            // Batch 2d
            'buyer_budget_not_divergent'  => $this->moneyFieldsAgree($meta, 'maximum_budget', ['buyer_budget', 'max_purchase_price', 'purchase_price']),
            'tenant_rent_not_divergent'   => $this->moneyFieldsAgree($meta, 'budget', ['desired_rental_amount', 'maximum_budget'], true),
            default                   => null,
        };
    }

    /**
     * Every populated legacy figure states the same amount as the canonical one.
     *
     * The buyer page prints "Max Purchase Budget" (maximum_budget ?: buyer_budget) and "Max
     * Purchase Price" (max_purchase_price ?: purchase_price) as SEPARATE rows, and the tenant
     * page's rent budget falls back through three keys. When those disagree the page shows a
     * reader two numbers and lets them judge; a single sentence cannot, and picking one would
     * publish a budget the listing does not actually claim. So the question hides.
     *
     * Compared as NUMBERS, not strings — "450000", "450,000" and "$450,000" are one amount,
     * and hiding the question over a comma would be a formatting bug wearing a safety rule.
     * An empty legacy key is not a disagreement; an unparseable one is.
     */
    private function moneyFieldsAgree(array $meta, string $canonicalKey, array $legacyKeys, bool $canonicalMayFallBack = false): bool
    {
        $canonical = $this->moneyValue($meta[$canonicalKey] ?? null);

        // The tenant page's rent row falls back through its keys in order, so a listing that
        // stored only a later key still publishes a budget and must still be answerable.
        // The buyer page keeps the stricter rule: its canonical key is the one the "Max
        // Purchase Budget" row is built from, and its other keys feed a SEPARATE row.
        if ($canonical === null && $canonicalMayFallBack) {
            foreach ($legacyKeys as $key) {
                if ($this->isEmpty($meta[$key] ?? null)) {
                    continue;
                }
                $canonical = $this->moneyValue($meta[$key]);
                break;
            }
        }

        if ($canonical === null) {
            return false;
        }

        foreach ($legacyKeys as $key) {
            $raw = $meta[$key] ?? null;
            if ($this->isEmpty($raw)) {
                continue;
            }
            $other = $this->moneyValue($raw);
            if ($other === null || abs($other - $canonical) >= 0.005) {
                return false;
            }
        }

        return true;
    }

    /** A stored money figure as a number; null when it is not one. */
    private function moneyValue(mixed $raw): ?float
    {
        if (!is_scalar($raw) || is_bool($raw)) {
            return null;
        }
        $clean = str_replace(['$', ',', ' '], '', trim((string) $raw));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? (float) $clean : null;
    }

    /**
     * The seller page prints `min_acreage ?: total_acreage`. A legacy min_acreage that
     * disagrees with total_acreage would make the answer contradict the page.
     */
    private function acreageNotOverridden(array $context, array $meta): bool
    {
        $min = $meta['min_acreage'] ?? null;
        if ($this->isEmpty($min)) {
            return true;
        }

        return is_scalar($min) && trim((string) $min) === trim((string) $this->listingValue($context, 'total_acreage'));
    }

    // =========================================================================
    // Formatters — fixed sentences; null means "cannot state this exactly"
    // =========================================================================

    private function hasFormatter(string $formatter): bool
    {
        return in_array($formatter, [
            'asking_price', 'bedroom_count', 'bathroom_count', 'heated_square_feet', 'year_built',
            'annual_property_taxes', 'hoa_fee', 'acreage_band', 'appliance_list', 'utility_list',
            'pets_allowed',
            // Batch 2b
            'has_pool', 'has_garage', 'zoning', 'roof_type_list', 'leasing_restrictions',
            'community_amenity_list', 'financing_types',
            // Batch 2c
            'hoa_fee_coverage', 'cdd_fee',
            // Batch 2d — buyer / tenant criteria. Every sentence says what the PERSON is
            // seeking; none states a fact about a property.
            'criteria_max_purchase_budget', 'criteria_max_rent', 'criteria_search_areas',
            'criteria_property_type', 'criteria_min_bedrooms', 'criteria_min_bathrooms',
            'criteria_min_square_feet', 'criteria_min_acreage_band', 'criteria_wants_pool',
            'criteria_search_areas_counties',
            'criteria_wants_garage', 'criteria_closing_timeframe', 'criteria_lease_term',
            'criteria_move_in_window', 'criteria_pets_needed', 'criteria_furnishings',
            'criteria_feature_list',
            // Batch 2e
            'flood_zone',
        ], true);
    }

    /**
     * @param array<string,mixed>                                                                 $supporting
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null               $companion
     * @param array<string, array{selections: list<string>, other: string|null, unsafe: bool}|null> $companions
     * @param string                                                                              $role       the listing's role, for criteria wording
     */
    private function format(string $formatter, mixed $value, array $supporting, ?array $companion = null, array $companions = [], string $role = ''): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        // The noun a criteria sentence is about, from the ROLE and never from stored data.
        $subject = $this->criteriaSubject($role);

        // Seller and landlord name the same HOA facts differently in context.
        $hasHoa = $supporting['hoa_association'] ?? $supporting['has_hoa'] ?? null;

        return match ($formatter) {
            'asking_price'           => $this->withMoney($text, fn (string $m) => "The asking price is {$m}."),
            'bedroom_count'          => $this->bedrooms($text),
            'bathroom_count'         => $this->bathrooms($text),
            'heated_square_feet'     => $this->heatedSquareFeet($text),
            'year_built'             => $this->yearBuilt($text),
            'annual_property_taxes'  => $this->annualTaxes($text, $supporting['tax_year'] ?? null),
            'hoa_fee'                => $this->hoaFee(
                                            $text,
                                            $hasHoa,
                                            $supporting['hoa_payment_schedule'] ?? $supporting['association_fee_frequency'] ?? null,
                                            $companion['other'] ?? null
                                        ),
            'acreage_band'           => $this->acreageBand($text),
            'appliance_list'         => $this->list($text, 'Appliances listed for this property'),
            'utility_list'           => $this->list($text, 'Utilities listed for this property'),
            'pets_allowed'           => $this->petsAllowed(
                                            $text,
                                            $supporting['number_of_pets_allowed'] ?? null,
                                            $supporting['max_pet_weight'] ?? null
                                        ),
            'has_pool'               => $this->yesNo($text, 'This property has a pool.', 'This property does not have a pool.'),
            'has_garage'             => $this->yesNo($text, 'This property has a garage.', 'This property does not have a garage.'),
            'zoning'                 => $this->zoning($text),
            'roof_type_list'         => $this->selectionList($companion, 'Roof type listed for this property', 'Roof types listed for this property'),
            'community_amenity_list' => $this->hoaOnly($hasHoa, fn () => $this->selectionList($companion, 'Community amenity listed for this property', 'Community amenities listed for this property')),
            'leasing_restrictions'   => $this->hoaOnly($hasHoa, fn () => $this->yesNo(
                                            $text,
                                            'The listing indicates there are leasing restrictions.',
                                            'The listing indicates there are no leasing restrictions.'
                                        )),
            'financing_types'        => $this->financingTypes($companion),
            'hoa_fee_coverage'       => $this->hoaFeeCoverage(
                                            $text,
                                            $hasHoa,
                                            $supporting['hoa_payment_schedule'] ?? $supporting['association_fee_frequency'] ?? null,
                                            $companions['frequency'] ?? null,
                                            $companions['includes'] ?? null
                                        ),
            'cdd_fee'                => $this->cddFee($text, $supporting['annual_cdd_fee'] ?? null),

            // ---- Batch 2d: buyer / tenant criteria ----
            'criteria_max_purchase_budget' => $this->withMoney($text, fn (string $m) => "The buyer is looking for a purchase price up to {$m}."),
            'criteria_max_rent'            => $this->withMoney($text, fn (string $m) => "The tenant is looking for rent up to {$m}."),
            'criteria_search_areas'          => $this->searchAreas($subject, $text, $supporting, 'cities'),
            'criteria_search_areas_counties' => $this->searchAreas($subject, $text, $supporting, 'counties'),
            'criteria_property_type'       => $this->criteriaPropertyType($subject, $text),
            'criteria_min_bedrooms'        => $this->criteriaMinCount($subject, $text, 'bedroom', 'bedrooms'),
            'criteria_min_bathrooms'       => $this->criteriaMinBathrooms($subject, $text),
            'criteria_min_square_feet'     => $this->criteriaMinSquareFeet($subject, $text),
            'criteria_min_acreage_band'    => $this->criteriaAcreageBand($subject, $text),
            'criteria_wants_pool'          => $this->yesNo(
                                                  $text,
                                                  "The {$subject} is looking for a property with a pool.",
                                                  "The {$subject} has not listed a pool as a requirement."
                                              ),
            'criteria_wants_garage'        => $this->yesNo(
                                                  $text,
                                                  "The {$subject} is looking for a property with a garage.",
                                                  "The {$subject} has not listed a garage as a requirement."
                                              ),
            'criteria_closing_timeframe'   => $this->criteriaClosingTimeframe($text),
            'criteria_lease_term'          => $this->criteriaLeaseTerm($text),
            'criteria_move_in_window'      => $this->criteriaMoveInWindow($text, $supporting['move_in_date_latest'] ?? null),
            'criteria_pets_needed'         => $this->yesNo(
                                                  $text,
                                                  'The tenant is looking for a property that allows pets.',
                                                  'The tenant has not listed a need for pets to be allowed.'
                                              ),
            'criteria_furnishings'         => $this->criteriaFurnishings($text),
            'criteria_feature_list'        => $this->criteriaFeatureList($subject, $companions),

            // ---- Batch 2e: FEMA flood zone (seller / landlord) ----
            'flood_zone'                   => $this->floodZone($text),

            default                  => null,
        };
    }

    // =========================================================================
    // Batch 2d formatters — buyer / tenant criteria
    //
    // WORDING IS PART OF THE CONTRACT. Every sentence names the PERSON and the verb
    // "looking for". A buyer's criteria restated as "This property has 3 bedrooms" would
    // describe a property that does not exist, on a page that is a search request. The
    // $subject argument is the role's noun and is never interpolated from user data.
    // =========================================================================

    /** The noun a criteria answer is about. Never derived from stored data. */
    private function criteriaSubject(string $role): string
    {
        return $role === 'tenant' ? 'tenant' : 'buyer';
    }

    /**
     * The areas being searched, from the listing's own published area lists.
     *
     * Only named areas — cities, counties, ZIP codes. Never a street address, a current
     * home, a commute destination, a radius centre or an Important Place, none of which is
     * in either criteria catalog and none of which can reach this method.
     */
    private function searchAreas(string $subject, string $text, array $supporting, string $sourceCategory): ?string
    {
        $parts = [];

        // The source value is whichever list the entry names; the rest arrive as supporting
        // paths. The narrower counties-only entry therefore has to say which it is, or a
        // county would be printed as a bare place name beside a genuine city.
        $cities   = $this->areaItems($sourceCategory === 'cities'   ? $text : ($supporting['cities'] ?? null));
        $counties = $this->areaItems($sourceCategory === 'counties' ? $text : ($supporting['counties'] ?? null));

        if ($cities !== []) {
            $parts[] = $this->joinList($cities);
        }

        if ($counties !== []) {
            $named = array_map(
                static fn (string $c): string => preg_match('/count(y|ies)$/i', $c) === 1 ? $c : $c . ' County',
                $counties
            );
            $parts[] = $this->joinList($named);
        }

        $zips = $this->areaItems($supporting['zip_codes'] ?? null);
        if ($zips !== []) {
            $zips = array_values(array_filter($zips, static fn (string $z): bool => preg_match('/^\d{5}(-\d{4})?$/', $z) === 1));
            if ($zips !== []) {
                $parts[] = (count($zips) === 1 ? 'ZIP code ' : 'ZIP codes ') . $this->joinList($zips);
            }
        }

        if ($parts === []) {
            return null;
        }

        // "in A, in B, and in C". Two rules, both load-bearing:
        //   - the preposition repeats on every group, because each group is ITSELF a list
        //     joined with "and"; one shared "in" reads as a single run-on list
        //     ("Seminole, Pinellas County and ZIP codes 33772 and 33776");
        //   - the last group is always preceded by a comma, because without one a
        //     multi-item first group produces a double "and" ("Seminole and St. Petersburg
        //     and in Pinellas County").
        $first = array_shift($parts);
        $tail  = array_map(static fn (string $part): string => 'in ' . $part, $parts);

        if ($tail === []) {
            $areas = $first;
        } else {
            $last  = array_pop($tail);
            $areas = $first . ($tail === [] ? '' : ', ' . implode(', ', $tail)) . ', and ' . $last;
        }

        return "The {$subject} is looking in {$areas}.";
    }

    /**
     * A comma-joined or JSON area list as clean, restatable names.
     *
     * A value that still looks like raw JSON was not decoded by the context builder and is
     * refused rather than printed as a bracketed blob.
     */
    private function areaItems(mixed $raw): array
    {
        if (!is_scalar($raw) || is_bool($raw)) {
            return [];
        }
        $text = trim((string) $raw);
        if ($text === '' || str_starts_with($text, '[') || str_starts_with($text, '{')) {
            return [];
        }

        $items = [];
        foreach (explode(',', $text) as $item) {
            $value = $this->verbatim($item);
            if (is_string($value)) {
                $items[] = $value;
            }
        }

        return array_values(array_unique($items));
    }

    private function criteriaPropertyType(string $subject, string $text): ?string
    {
        $value = $this->verbatim($text);

        // Stated as a labelled value rather than folded into the sentence: the stored
        // vocabulary includes both "Residential" and "Residential Property", and any
        // phrasing that reads well for one reads badly for the other.
        return is_string($value) ? "The {$subject} is looking for this property type: {$value}." : null;
    }

    /** A whole-number minimum. A count is never derived from a Yes/No flag. */
    private function criteriaMinCount(string $subject, string $text, string $singular, string $plural): ?string
    {
        if (preg_match('/^\d+$/', $text) !== 1 || (int) $text < 1) {
            return null;
        }
        $n = (int) $text;

        return "The {$subject} is looking for at least {$n} " . ($n === 1 ? $singular : $plural) . '.';
    }

    private function criteriaMinBathrooms(string $subject, string $text): ?string
    {
        $number = $this->plainNumber($text);
        if ($number === null) {
            return null;
        }

        return "The {$subject} is looking for at least {$number} " . ($number === '1' ? 'bathroom' : 'bathrooms') . '.';
    }

    private function criteriaMinSquareFeet(string $subject, string $text): ?string
    {
        $raw = str_replace([',', ' '], '', $text);
        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw <= 0) {
            return null;
        }

        return "The {$subject} is looking for at least " . $this->groupedNumber($raw, false) . ' heated square feet.';
    }

    /** Only an exact band from the form's own option list; 'Non-Applicable' is not a size. */
    private function criteriaAcreageBand(string $subject, string $text): ?string
    {
        $bands = array_values(array_diff(
            (array) config('property_types.acreage_options', []),
            ['Non-Applicable']
        ));

        return in_array($text, $bands, true)
            ? "The {$subject} is looking for a lot of {$text}."
            : null;
    }

    /**
     * The buyer's target closing timeframe, from the form's own closed option list.
     *
     * Matched against the exact options rather than parsed, so a legacy free-text or date
     * value hides the question instead of being restated as a timeframe it may not mean.
     */
    private function criteriaClosingTimeframe(string $text): ?string
    {
        $options = [
            'ASAP (Ready Now)'      => 'as soon as possible',
            'Within 1 Month'        => 'within 1 month',
            'Within 2 Months'       => 'within 2 months',
            'Within 3 Months'       => 'within 3 months',
            'Within 4 Months'       => 'within 4 months',
            'Within 5 Months'       => 'within 5 months',
            'Within 6 Months'       => 'within 6 months',
            'Over 6 Months'         => 'in more than 6 months',
            'Flexible / Open-Ended' => 'on a flexible timeline',
        ];

        return isset($options[$text])
            ? 'The buyer is looking to close ' . $options[$text] . '.'
            : null;
    }

    /**
     * The tenant's own DESIRED lease term.
     *
     * Sourced from `desired_lease_length` and from nothing else. `min_lease_period` is a
     * seller / HOA restriction on how briefly a property may be let — a different party's
     * rule about a property, not this tenant's preference — and it is absent from the tenant
     * criteria catalog, so it cannot be read here.
     */
    private function criteriaLeaseTerm(string $text): ?string
    {
        $items = $this->areaItems($text);

        return $items === []
            ? null
            : 'The tenant is looking for a lease term of ' . $this->joinList($items, 'or') . '.';
    }

    /**
     * The move-in window, from the two stored dates. Each is formatted only when it parses
     * as a real date; a window whose end precedes its start is refused rather than reversed.
     */
    private function criteriaMoveInWindow(string $text, mixed $latest): ?string
    {
        $from = $this->criteriaDate($text);
        $to   = $this->criteriaDate(is_scalar($latest) && !is_bool($latest) ? (string) $latest : '');

        if ($from === null) {
            return null;
        }
        if ($to === null) {
            return "The tenant is looking to move in on or after {$from['label']}.";
        }
        if ($to['sort'] < $from['sort']) {
            return null;
        }
        if ($to['sort'] === $from['sort']) {
            return "The tenant is looking to move in on {$from['label']}.";
        }

        return "The tenant is looking to move in between {$from['label']} and {$to['label']}.";
    }

    /** @return array{label: string, sort: string}|null */
    private function criteriaDate(string $text): ?array
    {
        $clean = trim($text);
        if ($clean === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($clean);
        } catch (\Throwable) {
            return null;
        }

        $year = (int) $date->format('Y');
        if ($year < 1900 || $year > 2200) {
            return null;
        }

        return ['label' => $date->format('F j, Y'), 'sort' => $date->format('Y-m-d')];
    }

    /**
     * "Furnished" / "Unfurnished" / "Turnkey" — the form's own furnishings vocabulary.
     *
     * `tenant_require` is a MULTI-SELECT and is stored as a JSON array, so the raw context
     * value is '["Furnished"]' rather than 'Furnished'. Decoded here through the same reader
     * the multi-select companions use.
     *
     * FAIL CLOSED ON ANYTHING UNRECOGNISED. This key's name reads like an occupant
     * requirement and historical rows may hold occupant-category values left behind by an
     * older form — the very concept Fair Housing Phase 1 retired. Mapping only the three
     * furnishings words, and hiding the question if ANY stored item is not one of them,
     * means such a value is never restated as a housing preference.
     */
    private function criteriaFurnishings(string $text): ?string
    {
        $vocabulary = [
            'furnished'   => 'furnished',
            'unfurnished' => 'unfurnished',
            'turnkey'     => 'turnkey',
        ];

        $items = [];
        foreach ($this->storedSelections($text) as $selection) {
            // A comma-joined value reaches here when the row was never JSON.
            foreach (explode(',', $selection) as $part) {
                $key = strtolower(trim($part));
                if ($key === '') {
                    continue;
                }
                if (!isset($vocabulary[$key])) {
                    return null;
                }
                $items[] = $vocabulary[$key];
            }
        }

        $items = array_values(array_unique($items));
        if ($items === []) {
            return null;
        }

        $article = $items[0] === 'unfurnished' ? 'an' : 'a';

        return "The tenant is looking for {$article} " . $this->joinList($items, 'or') . ' property.';
    }

    /**
     * The structured features being sought, merged from the entry's declared multi-selects.
     *
     * Reads only the exact stored selections of the fields the catalog entry names, through
     * the same companion mechanism the property questions use. No free text beyond an
     * "Other" value that is short, single-line and not a placeholder.
     *
     * @param array<string, array{selections: list<string>, other: string|null, unsafe: bool}|null> $companions
     */
    private function criteriaFeatureList(string $subject, array $companions): ?string
    {
        $items = [];
        foreach ($companions as $companion) {
            foreach ($this->selectionItems($companion) as $item) {
                $items[] = $item;
            }
        }
        $items = array_values(array_unique($items));

        return $items === []
            ? null
            : "The {$subject} is looking for these features: " . implode(', ', $items) . '.';
    }

    private function bedrooms(string $text): ?string
    {
        if (preg_match('/^\d+$/', $text) !== 1 || (int) $text < 1) {
            return null;
        }
        $n = (int) $text;

        return 'This property has ' . $n . ($n === 1 ? ' bedroom.' : ' bedrooms.');
    }

    private function bathrooms(string $text): ?string
    {
        $number = $this->plainNumber($text);
        if ($number === null) {
            return null;
        }

        return 'This property has ' . $number . ($number === '1' ? ' bathroom.' : ' bathrooms.');
    }

    private function heatedSquareFeet(string $text): ?string
    {
        $raw = str_replace(',', '', $text);
        if (preg_match('/^\d+(\.\d+)?$/', $raw) !== 1 || (float) $raw <= 0) {
            return null;
        }

        return 'The heated square footage is ' . $this->groupedNumber($raw, false) . ' square feet.';
    }

    private function yearBuilt(string $text): ?string
    {
        if (preg_match('/^\d{4}$/', $text) !== 1) {
            return null;
        }
        $year = (int) $text;
        if ($year < self::YEAR_BUILT_MIN || $year > self::YEAR_BUILT_MAX) {
            return null;
        }

        return "This property was built in {$year}.";
    }

    private function annualTaxes(string $text, mixed $taxYear): ?string
    {
        return $this->withMoney($text, function (string $money) use ($taxYear): string {
            $year = is_scalar($taxYear) ? trim((string) $taxYear) : '';

            return preg_match('/^\d{4}$/', $year) === 1
                ? "Annual property taxes are {$money} for tax year {$year}."
                : "Annual property taxes are {$money}.";
        });
    }

    /**
     * Only published when the listing currently says it HAS an HOA: an amount left behind
     * after the seller answered "No" or "Unknown" is a stale child value, not a fee.
     */
    private function hoaFee(string $text, mixed $hasHoa, mixed $frequency, ?string $frequencyOther): ?string
    {
        $clause = $this->hoaFeeClause($text, $hasHoa, $frequency, $frequencyOther);

        return $clause === null ? null : $clause . '.';
    }

    /**
     * "The HOA fee is $250 per month" — the fee statement without its full stop, shared by the
     * fee question and the fee-and-coverage composite so the two can never word it differently.
     */
    private function hoaFeeClause(string $text, mixed $hasHoa, mixed $frequency, ?string $frequencyOther): ?string
    {
        if (!$this->isYes($hasHoa)) {
            return null;
        }

        return $this->withMoney($text, function (string $money) use ($frequency, $frequencyOther): string {
            $key = is_scalar($frequency) && !is_bool($frequency)
                ? (string) preg_replace('/[\s_]+/', '-', strtolower(trim((string) $frequency)))
                : '';

            if ($key === 'one-time') {
                return "The HOA fee is a one-time fee of {$money}";
            }

            $phrase = self::HOA_FREQUENCY_PHRASES[$key] ?? null;
            if ($phrase !== null) {
                return "The HOA fee is {$money} {$phrase}";
            }

            // "Other" with the owner's own frequency text: restated as written, never
            // reinterpreted into a period.
            if ($key === 'other' && $frequencyOther !== null) {
                return "The HOA fee is {$money} (frequency: {$frequencyOther})";
            }

            return "The HOA fee is {$money}";
        });
    }

    /**
     * Batch 2c composite: the fee AND what it covers, from structured fields only. Both halves
     * are required — no valid fee, or nothing it covers, and this returns null so the narrower
     * fee question stands in (see narrower_of). Nothing is inferred about coverage.
     *
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $frequency
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $includes
     */
    private function hoaFeeCoverage(string $text, mixed $hasHoa, mixed $frequencyValue, ?array $frequency, ?array $includes): ?string
    {
        $clause = $this->hoaFeeClause($text, $hasHoa, $frequencyValue, $frequency['other'] ?? null);
        if ($clause === null) {
            return null;
        }

        $items = [];
        foreach ($this->selectionItems($includes) as $item) {
            // Form options read as ordinary words mid-sentence ("Common Area Maintenance" →
            // "common area maintenance"; "Cable TV" → "cable TV"). The owner's own "Other"
            // text is restated exactly as written.
            $items[] = ($includes !== null && $item === $includes['other'])
                ? $item
                : $this->sentenceCase($item);
        }
        if ($items === []) {
            return null;
        }

        return $clause . ' and includes ' . $this->joinList($items) . '.';
    }

    /** "A", "A and B", "A, B and C". */
    private function joinList(array $items, string $conjunction = 'and'): string
    {
        $items = array_values($items);
        if (count($items) <= 2) {
            return implode(" {$conjunction} ", $items);
        }
        $last = array_pop($items);

        return implode(', ', $items) . " {$conjunction} " . $last;
    }

    /** Lower-case capitalised words ("Grounds" → "grounds"); leave acronyms ("TV") and anything else. */
    private function sentenceCase(string $item): string
    {
        return implode(' ', array_map(
            static fn (string $word): string => preg_match('/^[A-Z][a-z]+$/', $word) === 1 ? strtolower($word) : $word,
            explode(' ', $item)
        ));
    }

    /**
     * Batch 2c: "Is there a CDD fee?" from has_cdd and annual_cdd_fee. A stated "No" is
     * restated as nothing listed (never "no CDD exists"); "Yes" with a valid amount states
     * the annual fee; "Yes" without one says only that a CDD is listed. Anything else hides.
     */
    private function cddFee(string $hasCdd, mixed $annualFee): ?string
    {
        return match (strtolower($hasCdd)) {
            'no'  => 'There is no CDD fee listed for this property.',
            'yes' => (is_scalar($annualFee) && !is_bool($annualFee)
                        ? $this->withMoney(trim((string) $annualFee), fn (string $m) => "The annual CDD fee is {$m}.")
                        : null)
                     ?? 'This property is listed as having a CDD.',
            default => null,
        };
    }

    private function isYes(mixed $value): bool
    {
        return is_scalar($value) && !is_bool($value) && strtolower(trim((string) $value)) === 'yes';
    }

    /** Exactly "Yes" or "No" (any case); anything else — Unknown, Not Applicable, Optional — hides. */
    private function yesNo(string $text, string $yes, string $no): ?string
    {
        return match (strtolower($text)) {
            'yes'   => $yes,
            'no'    => $no,
            default => null,
        };
    }

    /**
     * Facts the page publishes only inside its HOA / Association block — leasing restrictions
     * and community amenities — are published only while the listing says it HAS an HOA.
     *
     * @param callable(): ?string $answer
     */
    private function hoaOnly(mixed $hasHoa, callable $answer): ?string
    {
        return $this->isYes($hasHoa) ? $answer() : null;
    }

    /** A zoning designation, restated word for word when it is short, single-line and real. */
    private function zoning(string $text): ?string
    {
        $value = $this->verbatim($text);

        return is_string($value) ? "The zoning is listed as {$value}." : null;
    }

    /**
     * A multi-select answered from its stored selections: "Other" becomes the owner's "Other"
     * text when that text is usable and is dropped otherwise; placeholders are dropped.
     *
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $companion
     */
    private function selectionItems(?array $companion): array
    {
        if ($companion === null) {
            return [];
        }

        $items = [];
        foreach ($companion['selections'] as $selection) {
            if (strtolower($selection) === 'other') {
                if ($companion['other'] !== null) {
                    $items[] = $companion['other'];
                }
                continue;
            }
            $value = $this->verbatim($selection);
            if (is_string($value)) {
                $items[] = $value;
            }
        }

        return array_values(array_unique($items));
    }

    /** @param array{selections: list<string>, other: string|null, unsafe: bool}|null $companion */
    private function selectionList(?array $companion, string $singular, string $plural): ?string
    {
        $items = $this->selectionItems($companion);
        if ($items === []) {
            return null;
        }

        return (count($items) === 1 ? $singular : $plural) . ': ' . implode(', ', $items) . '.';
    }

    /**
     * The financing types the seller will consider, exactly as selected. The "Other" text is
     * read with reject_figures, so a typed rate, amount or percentage hides the question
     * rather than publishing a financing TERM.
     *
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $companion
     */
    private function financingTypes(?array $companion): ?string
    {
        $items = $this->selectionItems($companion);
        if ($items === []) {
            return null;
        }

        if (count($items) === 1) {
            return "The seller has indicated they will consider the following financing type: {$items[0]}.";
        }

        $last = array_pop($items);

        return 'The seller has indicated they will consider the following financing types: '
            . implode(', ', $items) . ' and ' . $last . '.';
    }

    /** Only an exact band from the form's own option list; 'Non-Applicable' is not a size. */
    private function acreageBand(string $text): ?string
    {
        $bands = array_values(array_diff(
            (array) config('property_types.acreage_options', []),
            ['Non-Applicable']
        ));

        return in_array($text, $bands, true) ? "The total acreage is {$text}." : null;
    }

    private function list(string $text, string $lead): ?string
    {
        // A value that still looks like raw JSON was not decoded by the context builder.
        if (str_starts_with($text, '[') || str_starts_with($text, '{')) {
            return null;
        }

        $items = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $text)),
            static fn (string $item): bool => $item !== '' && strtolower($item) !== 'other'
        )));

        return $items === [] ? null : $lead . ': ' . rtrim(implode(', ', $items), '.') . '.';
    }

    /**
     * The Yes / No pet policy, optionally followed (for "Yes" only) by the listing's own
     * structured limits: a whole-number count of pets allowed and a maximum weight per pet.
     * A value that is not exactly a number is left out rather than interpreted, and the
     * "No" sentence is never changed. Only pet-POLICY fields of the listing are passed in —
     * never an applicant's pets, breeds, service or support animals.
     */
    private function petsAllowed(string $text, mixed $count = null, mixed $maxWeight = null): ?string
    {
        return match (strtolower($text)) {
            'yes' => trim('Pets are allowed at this property. ' . $this->petCountSentence($count) . ' ' . $this->petWeightSentence($maxWeight)),
            'no'  => "Pets are not allowed under the property's pet policy. Assistance animals are handled separately under applicable law.",
            default => null,
        };
    }

    private function petCountSentence(mixed $count): string
    {
        if (!is_scalar($count) || is_bool($count) || preg_match('/^\s*(\d{1,2})\s*$/', (string) $count, $m) !== 1) {
            return '';
        }
        $n = (int) $m[1];
        if ($n < 1) {
            return '';
        }

        return $n === 1 ? 'Up to 1 pet is permitted.' : "Up to {$n} pets are permitted.";
    }

    private function petWeightSentence(mixed $weight): string
    {
        if (!is_scalar($weight) || is_bool($weight) || preg_match('/^\s*(\d{1,3})\s*(lbs?\.?)?\s*$/i', (string) $weight, $m) !== 1) {
            return '';
        }
        $lbs = (int) $m[1];
        if ($lbs < 1) {
            return '';
        }

        return "The maximum weight per pet is {$lbs} lbs.";
    }

    // =========================================================================
    // Number helpers
    // =========================================================================

    /** @param callable(string): string $sentence */
    private function withMoney(string $text, callable $sentence): ?string
    {
        // Read the sign before stripping currency characters, or "-5000" becomes "5000".
        if (str_starts_with($text, '-')) {
            return null;
        }

        $raw = str_replace(['$', ',', ' '], '', $text);
        if (preg_match('/^\d+(\.\d+)?$/', $raw) !== 1 || (float) $raw <= 0) {
            return null;
        }

        return $sentence('$' . $this->groupedNumber($raw, true));
    }

    /** "2.50" → "2.5", "3.0" → "3"; null for anything that is not a positive number. */
    private function plainNumber(string $text): ?string
    {
        if (preg_match('/^\d+(\.\d+)?$/', $text) !== 1 || (float) $text <= 0) {
            return null;
        }

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : ltrim($text, '0');
    }

    /** "1850" → "1,850", "1856.4" → "1,856.40" as money. Never rounds a stated figure. */
    private function groupedNumber(string $raw, bool $money): string
    {
        $fraction = null;
        if (str_contains($raw, '.')) {
            [$raw, $fraction] = explode('.', $raw, 2);
            $fraction = rtrim($fraction, '0');
        }

        $whole = number_format((int) $raw);

        if ($fraction === null || $fraction === '') {
            return $whole;
        }

        return $whole . '.' . ($money ? str_pad($fraction, 2, '0') : $fraction);
    }

    /**
     * The property's FEMA flood zone, from the stored designation and nothing else.
     *
     * WHAT THIS MAY NOT SAY. Every sentence below states the DESIGNATION and, for the three
     * codes whose meaning is unambiguous, what that designation is. None of them says the
     * property is "not in a flood zone", carries "no flood risk", "cannot flood", or that
     * insurance "is not required" — and Zone X in particular is where that temptation lives,
     * which is why its sentence ends by saying flood risk is not zero. Whether a lender or
     * insurer requires cover depends on the loan, the carrier and the current map panel,
     * none of which this application stores; inferring it from a letter code would be
     * inventing a determination on the owner's behalf.
     *
     * An unrecognised-but-valid code gets the bare designation with no risk gloss. That is
     * deliberate: A, AH, AO, V, D and AR each carry their own meaning, and a generic
     * risk sentence would be wrong for at least one of them.
     *
     * The value is re-normalised here rather than trusted. `flood_zone_code` is written by a
     * form whose own select offers `Unknown` and `Other` (with a free-text branch), and by
     * an importer that until recently uppercased whatever it was handed — so the column
     * genuinely holds sentinels, prose and, historically, the literal string `yes`.
     */
    private function floodZone(string $text): ?string
    {
        $code = FloodZoneCode::canonical($text);
        if ($code === null) {
            return null;
        }

        return match ($code) {
            'X'  => 'This property is in FEMA Flood Zone X, which is generally outside the '
                  . 'Special Flood Hazard Area and considered lower flood risk. Flood risk is not zero.',
            'AE' => 'This property is in FEMA Flood Zone AE, which is within a Special Flood Hazard Area.',
            'VE' => 'This property is in FEMA Flood Zone VE, a coastal high-hazard Special Flood Hazard Area.',
            default => "This property is in FEMA Flood Zone {$code}.",
        };
    }

    /** @return array{available: false, reason: string, answer: null} */
    private function hidden(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'answer' => null];
    }
}
