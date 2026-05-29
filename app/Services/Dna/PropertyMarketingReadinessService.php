<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;

/**
 * PropertyMarketingReadinessService — Phase U Deterministic Marketing Readiness Review
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a DETERMINISTIC READINESS REVIEWER ONLY. It calls
 * PropertyMarketingBriefService to obtain Phase R brief output and inspects
 * seven named information groups to determine presence or absence of each group.
 * It generates no narrative text, performs no AI inference, and contacts no
 * external system of any kind.
 *
 * This service MUST NEVER:
 *   - Call any AI system, language model, embedding service, or ML inference engine.
 *   - Call any external API, HTTP endpoint, or third-party service of any kind.
 *   - Generate narrative marketing copy, ad copy, listing descriptions, or persuasive text.
 *   - Perform audience targeting, buyer persona inference, or demographic grouping.
 *   - Apply protected-class inference, steering, or characterization of any kind.
 *   - Infer, imply, or state what "type of person" would suit, enjoy, or be attracted to a property.
 *   - Use neighborhood demographic data, school demographic data, or community composition
 *     assumptions in any output string or decision logic.
 *   - Rank, sort, order, or weight any group, record, or output entry by any signal.
 *   - Endorse, steer toward, or evaluate any listing, buyer, tenant, seller, landlord, or agent.
 *   - Determine fitness, screening, acceptance, or rejection for any party or property.
 *   - Forecast any outcome, likelihood, probability, or suitability of any transaction event or party.
 *   - Write to or read from any database table, queue, cache layer, session, or persistent store.
 *   - Modify, recalculate, or influence any DNA profile, score, or completeness metric.
 *   - Depend on any service other than PropertyMarketingBriefService.
 *   - Surface its output in any public page, agent-facing view, client-facing view, API response,
 *     PDF, email, websocket broadcast, or cache layer without a separately approved visibility phase.
 *   - Introduce any route, controller, Livewire component, Blade view, JavaScript, migration,
 *     seeder, or database schema change.
 *   - Generate dynamic strings at runtime — all non-passthrough output strings are static constants
 *     defined within this service.
 *   - Emit any output key as null or absent — all five top-level keys must always be present even
 *     when the input profile is completely empty.
 *   - Include percentages, rankings, scores, recommendations, or AI-style insights in any output.
 *   - Reference ideal-buyer language, "best-fit" language, or targeted-audience language.
 * ==================================================================================
 */
class PropertyMarketingReadinessService
{
    // =========================================================================
    // GROUP NAMES
    // The seven named information groups inspected by this service.
    // These string constants are used as the 'group' key in review_items and
    // as entries in present_groups / missing_groups.
    // =========================================================================

    private const GROUP_PROPERTY_ATTRIBUTES           = 'Property Attributes';
    private const GROUP_TRANSACTION_DETAILS           = 'Transaction Details';
    private const GROUP_QUANTITATIVE_DATA             = 'Quantitative Data';
    private const GROUP_PRESENTATION_ASSETS           = 'Presentation Assets';
    private const GROUP_TIMING_INFORMATION            = 'Timing Information';
    private const GROUP_FINANCING_INFORMATION         = 'Financing Information';
    private const GROUP_TRANSACTION_STRUCTURE         = 'Transaction Structure Information';

    // =========================================================================
    // REQUIRED GROUPS FOR MARKETING READINESS
    // is_marketing_ready is true only when all three of these groups are present.
    // Optional groups (Presentation Assets, Timing, Financing, Transaction Structure)
    // do not affect the readiness determination.
    // =========================================================================

    private const REQUIRED_GROUPS = [
        self::GROUP_PROPERTY_ATTRIBUTES,
        self::GROUP_TRANSACTION_DETAILS,
        self::GROUP_QUANTITATIVE_DATA,
    ];

    // =========================================================================
    // NAMED ATTRIBUTE BUCKETS
    // The named buckets within property_attribute_context that are inspected
    // when determining whether the Property Attributes group is present.
    // =========================================================================

    private const NAMED_ATTRIBUTE_BUCKETS = [
        'property_type',
        'property_style',
        'property_condition',
        'amenities',
        'parking',
        'features',
        'policies',
        'community',
        'use_classification',
        'governance',
    ];

    // =========================================================================
    // NAMED TRANSACTION BUCKETS
    // The named buckets within transaction_context that are inspected when
    // determining whether the Transaction Details group is present.
    // =========================================================================

    private const NAMED_TRANSACTION_BUCKETS = [
        'timing',
        'transaction_structure',
        'financing',
        'presentation',
    ];

    // =========================================================================
    // MISSING REASON MAP
    // Pre-written static reason strings emitted only when a group's status is
    // 'missing'. No dynamic text is generated. All strings are static constants.
    // =========================================================================

    private const MISSING_REASON_MAP = [
        self::GROUP_PROPERTY_ATTRIBUTES => 'No named property attribute bucket contains any data.',
        self::GROUP_TRANSACTION_DETAILS => 'No named transaction context bucket contains any data.',
        self::GROUP_QUANTITATIVE_DATA   => 'No quantitative data records are present on this profile.',
        self::GROUP_PRESENTATION_ASSETS => 'The presentation bucket in transaction context contains no records.',
        self::GROUP_TIMING_INFORMATION  => 'The timing bucket in transaction context contains no records.',
        self::GROUP_FINANCING_INFORMATION => 'The financing bucket in transaction context contains no records.',
        self::GROUP_TRANSACTION_STRUCTURE => 'The transaction structure bucket in transaction context contains no records.',
    ];

    public function __construct(
        private readonly PropertyMarketingBriefService $briefService
    ) {}

    /**
     * Build a structured marketing readiness review array from a persisted PropertyDnaProfile.
     *
     * Calls PropertyMarketingBriefService::build() to obtain Phase R brief output,
     * then inspects seven named information groups deterministically using static
     * detection rules and constants. No AI, no external APIs, no database access, and
     * no dynamic string generation occur at any point in this method or its helpers.
     *
     * All five top-level keys are always present in the returned array, even when
     * the profile is completely empty. No key is ever null or absent.
     *
     * Output structure:
     * [
     *     'is_marketing_ready' => bool,    // true only when all three required groups are present
     *     'present_groups'     => string[], // names of groups detected as present
     *     'missing_groups'     => string[], // names of groups detected as missing
     *     'review_items'       => array,    // one entry per group with status and optional reason
     *     'summary'            => array,    // exactly present_group_count and missing_group_count
     * ]
     *
     * Each review_items entry:
     * [
     *     'group'  => string,              // one of the seven group name constants
     *     'status' => 'present'|'missing', // detection result
     *     'reason' => string,              // static string; present ONLY when status is 'missing'
     * ]
     *
     * @param  PropertyDnaProfile $profile  A persisted, cast profile model instance.
     * @return array
     */
    public function build(PropertyDnaProfile $profile): array
    {
        $brief = $this->briefService->build($profile);

        $attributeContext    = (array) ($brief['property_attribute_context'] ?? []);
        $transactionContext  = (array) ($brief['transaction_context']        ?? []);
        $quantitativeContext = (array) ($brief['quantitative_context']       ?? []);

        $groupResults = $this->detectGroups($attributeContext, $transactionContext, $quantitativeContext);

        $presentGroups = [];
        $missingGroups = [];
        $reviewItems   = [];

        foreach ($groupResults as $groupName => $isPresent) {
            if ($isPresent) {
                $presentGroups[] = $groupName;
                $reviewItems[]   = [
                    'group'  => $groupName,
                    'status' => 'present',
                ];
            } else {
                $missingGroups[] = $groupName;
                $reviewItems[]   = [
                    'group'  => $groupName,
                    'status' => 'missing',
                    'reason' => self::MISSING_REASON_MAP[$groupName],
                ];
            }
        }

        $allRequiredPresent = $this->allRequiredGroupsPresent($groupResults);

        return [
            'is_marketing_ready' => $allRequiredPresent,
            'present_groups'     => $presentGroups,
            'missing_groups'     => $missingGroups,
            'review_items'       => $reviewItems,
            'summary'            => [
                'present_group_count' => count($presentGroups),
                'missing_group_count' => count($missingGroups),
            ],
        ];
    }

    // =========================================================================
    // DETECTION HELPERS
    // Each private method applies one group's detection rule deterministically.
    // No dynamic string generation occurs. All logic is pure boolean inspection
    // of the brief output arrays.
    // =========================================================================

    /**
     * Run all seven group detection rules and return a keyed bool map.
     *
     * @param  array $attributeContext
     * @param  array $transactionContext
     * @param  array $quantitativeContext
     * @return array<string, bool>  Group name => true (present) or false (missing).
     */
    private function detectGroups(
        array $attributeContext,
        array $transactionContext,
        array $quantitativeContext
    ): array {
        return [
            self::GROUP_PROPERTY_ATTRIBUTES => $this->detectPropertyAttributes($attributeContext),
            self::GROUP_TRANSACTION_DETAILS => $this->detectTransactionDetails($transactionContext),
            self::GROUP_QUANTITATIVE_DATA   => $this->detectQuantitativeData($quantitativeContext),
            self::GROUP_PRESENTATION_ASSETS => $this->detectTransactionBucket($transactionContext, 'presentation'),
            self::GROUP_TIMING_INFORMATION  => $this->detectTransactionBucket($transactionContext, 'timing'),
            self::GROUP_FINANCING_INFORMATION => $this->detectTransactionBucket($transactionContext, 'financing'),
            self::GROUP_TRANSACTION_STRUCTURE => $this->detectTransactionBucket($transactionContext, 'transaction_structure'),
        ];
    }

    /**
     * Property Attributes group is present when at least one named attribute
     * bucket contains at least one record.
     *
     * @param  array $attributeContext
     * @return bool
     */
    private function detectPropertyAttributes(array $attributeContext): bool
    {
        foreach (self::NAMED_ATTRIBUTE_BUCKETS as $bucket) {
            if (!empty($attributeContext[$bucket])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transaction Details group is present when at least one named transaction
     * bucket contains at least one record.
     *
     * @param  array $transactionContext
     * @return bool
     */
    private function detectTransactionDetails(array $transactionContext): bool
    {
        foreach (self::NAMED_TRANSACTION_BUCKETS as $bucket) {
            if (!empty($transactionContext[$bucket])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quantitative Data group is present when quantitative_context is non-empty.
     *
     * @param  array $quantitativeContext
     * @return bool
     */
    private function detectQuantitativeData(array $quantitativeContext): bool
    {
        return !empty($quantitativeContext);
    }

    /**
     * Detect presence for a single named transaction_context bucket.
     * Used for Presentation Assets, Timing Information, Financing Information,
     * and Transaction Structure Information groups.
     *
     * @param  array  $transactionContext
     * @param  string $bucket
     * @return bool
     */
    private function detectTransactionBucket(array $transactionContext, string $bucket): bool
    {
        return !empty($transactionContext[$bucket]);
    }

    /**
     * Determine whether all three required groups are present.
     *
     * @param  array<string, bool> $groupResults
     * @return bool
     */
    private function allRequiredGroupsPresent(array $groupResults): bool
    {
        foreach (self::REQUIRED_GROUPS as $requiredGroup) {
            if (empty($groupResults[$requiredGroup])) {
                return false;
            }
        }

        return true;
    }
}
