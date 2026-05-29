<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;

/**
 * PropertyMarketingBriefService — Phase R Deterministic Property Marketing Brief Builder
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a DETERMINISTIC BRIEF BUILDER ONLY. It calls
 * PropertyMarketingContextService to obtain Phase P context and assembles
 * nine named sections into a structured internal marketing brief array.
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
 *   - Rank, sort, order, or weight any tag, record, bucket, section, or brief entry by any signal.
 *   - Endorse, steer toward, or evaluate any listing, buyer, tenant, seller, landlord, or agent.
 *   - Determine fitness, screening, acceptance, or rejection for any party or property.
 *   - Forecast any outcome, likelihood, probability, or suitability of any transaction event or party.
 *   - Write to or read from any database table, queue, cache layer, session, or persistent store.
 *   - Modify, recalculate, or influence any DNA profile, score, or completeness metric.
 *   - Modify PropertyDnaExplanationService, PropertyMarketingContextService, PropertyDnaGenerator,
 *     PropertyDnaProfile, any compatibility service, any scoring service, or any explanation map.
 *   - Depend on any service other than PropertyMarketingContextService.
 *   - Surface its output in any public page, agent-facing view, client-facing view, API response,
 *     PDF, email, websocket broadcast, or cache layer without a separately approved visibility phase.
 *   - Introduce any route, controller, Livewire component, Blade view, JavaScript, migration,
 *     seeder, or database schema change.
 *   - Generate dynamic strings at runtime — all non-passthrough output strings are static constants
 *     defined within this service.
 *   - Emit any output key as null or absent — all nine top-level keys must always be present even
 *     when the input profile is completely empty.
 *   - Include percentages, rankings, scores, recommendations, or AI-style insights in any section.
 *   - Reference ideal-buyer language, "best-fit" language, or targeted-audience language.
 * ==================================================================================
 */
class PropertyMarketingBriefService
{
    // =========================================================================
    // MINIMUM BUCKET RECORD THRESHOLD
    // A bucket containing fewer records than this constant triggers a
    // seller_landlord_questions entry for that bucket dimension.
    // Buckets with zero records also trigger the missing_information_checklist.
    //
    // THRESHOLD DECISION — BUCKET_MINIMUM = 1:
    // The task specification states: "Emit the associated pre-written question
    // entry only when a bucket is empty or contains fewer records than a defined
    // minimum constant." A value of 1 means count($records) < 1, which fires
    // only when a bucket has zero records (i.e., is completely empty). This is
    // the intended threshold: the question is surfaced when a named dimension
    // has no data at all. It should NOT be raised above 1 without a separate
    // governance decision, because doing so would emit questions for buckets
    // that have at least one record, conflating "sparse" with "missing".
    // =========================================================================

    private const BUCKET_MINIMUM = 1;

    // =========================================================================
    // MARKETING ASSET CHECKLIST MAP
    // Keyed by full archetype tag string from the presentation bucket.
    // Values are pre-written neutral checklist entries — not ad copy.
    // =========================================================================

    private const MARKETING_ASSET_MAP = [
        'marketing:video-tour' => 'Video tour is recorded and available as a marketing asset for this listing.',
    ];

    private const MARKETING_ASSET_FALLBACK = 'A marketing or presentation asset is recorded on this listing.';

    // =========================================================================
    // MISSING INFORMATION CHECKLIST MAP
    // Keyed by "context_group:bucket_name". Covers all named buckets in both
    // attribute_context and transaction_context. The unrecognized fallback
    // buckets are intentionally excluded — they are not named data dimensions.
    // Values are pre-written neutral missing-data checklist entries.
    // =========================================================================

    private const MISSING_INFO_MAP = [
        'attribute_context:property_type'      => 'No property type data recorded.',
        'attribute_context:property_style'      => 'No architectural style data recorded.',
        'attribute_context:property_condition'  => 'No property condition data recorded.',
        'attribute_context:amenities'           => 'No amenity data recorded.',
        'attribute_context:parking'             => 'No parking data recorded.',
        'attribute_context:features'            => 'No feature or terms data recorded.',
        'attribute_context:policies'            => 'No occupancy or use policy data recorded.',
        'attribute_context:community'           => 'No community designation data recorded.',
        'attribute_context:use_classification'  => 'No use classification data recorded.',
        'attribute_context:governance'          => 'No governance or association data recorded.',
        'transaction_context:timing'                => 'No timing or availability information recorded.',
        'transaction_context:transaction_structure' => 'No transaction or lease structure information recorded.',
        'transaction_context:financing'             => 'No financing option data recorded.',
        'transaction_context:presentation'          => 'No marketing asset or presentation data recorded.',
    ];

    // =========================================================================
    // SELLER / LANDLORD QUESTION MAP
    // Keyed by "context_group:bucket_name". Covers the same named buckets as
    // MISSING_INFO_MAP. The unrecognized fallback buckets are intentionally
    // excluded — they are not addressable named data dimensions.
    // Values are pre-written neutral clarifying questions — not generated text.
    // =========================================================================

    private const SELLER_LANDLORD_QUESTION_MAP = [
        'attribute_context:property_type'      => 'What is the primary property type for this listing?',
        'attribute_context:property_style'      => 'What is the architectural style or classification of the property?',
        'attribute_context:property_condition'  => 'What is the current physical condition of the property?',
        'attribute_context:amenities'           => 'What on-site amenities are available at this property?',
        'attribute_context:parking'             => 'What parking facilities are available at this property?',
        'attribute_context:features'            => 'Are there notable physical features or special terms that apply to this property?',
        'attribute_context:policies'            => 'Are there occupancy or use policy restrictions that apply to this property?',
        'attribute_context:community'           => 'Is this property part of a designated community or community association?',
        'attribute_context:use_classification'  => 'Is this property intended for residential, commercial, or mixed use?',
        'attribute_context:governance'          => 'Is this property subject to an HOA, condominium association, or other governance structure?',
        'transaction_context:timing'                => 'When is the property expected to be available for move-in or occupancy?',
        'transaction_context:transaction_structure' => 'Are there special transaction or lease structure terms such as a lease option or lease-purchase arrangement?',
        'transaction_context:financing'             => 'Are seller financing, an assumable loan, or other special financing arrangements available for this listing?',
        'transaction_context:presentation'          => 'Are photographs, a video tour, or other marketing assets available and ready for this listing?',
    ];

    // =========================================================================
    // LISTING PREPARATION NOTE MAP
    // Keyed by full archetype tag string. Covers tags from the timing,
    // transaction_structure, and financing buckets only.
    // Values are pre-written factual internal preparation notes — not ad copy,
    // not negotiation advice, and not suitability statements.
    // =========================================================================

    private const LISTING_PREPARATION_NOTE_MAP = [
        // timing bucket
        'timing:move-in-specified'       => 'Move-in timing has been specified on the listing; verify the availability date is accurate before publishing.',
        // transaction_structure bucket
        'structure:lease-option'         => 'A lease option arrangement is indicated on the listing; confirm all lease option terms are fully documented.',
        'structure:lease-purchase'       => 'A lease-purchase arrangement is indicated on the listing; confirm all lease-purchase terms are fully documented.',
        // financing bucket
        'financing:seller-financed'      => 'Seller financing is indicated on the listing; confirm financing terms are documented and current.',
        'financing:assumable'            => 'An assumable loan is indicated on the listing; confirm loan eligibility requirements and details are documented.',
    ];

    private const LISTING_PREPARATION_NOTE_FALLBACK = 'A transaction, timing, or financing term is recorded on the listing; verify all associated details are complete and accurate.';

    // =========================================================================
    // FALLBACK STRINGS FOR MAP MISSES
    // These constants cover the rare case where a bucket name does not resolve
    // in MISSING_INFO_MAP or SELLER_LANDLORD_QUESTION_MAP (e.g., a future Phase P
    // bucket that has not yet been added to these maps). Using named constants
    // ensures every non-passthrough output string originates from a static
    // constant defined in this service — no inline literals anywhere.
    // =========================================================================

    private const MISSING_INFO_ATTRIBUTE_FALLBACK = 'No data recorded for this attribute dimension.';
    private const MISSING_INFO_TRANSACTION_FALLBACK = 'No data recorded for this transaction dimension.';
    private const QUESTION_ATTRIBUTE_FALLBACK = 'What information is available for this attribute dimension?';
    private const QUESTION_TRANSACTION_FALLBACK = 'What information is available for this transaction dimension?';

    // =========================================================================
    // NAMED ATTRIBUTE BUCKETS
    // The ordered set of named attribute_context buckets inspected by
    // missing_information_checklist and seller_landlord_questions.
    // The unrecognized fallback bucket is intentionally excluded.
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
    // The ordered set of named transaction_context buckets inspected by
    // missing_information_checklist and seller_landlord_questions.
    // The unrecognized fallback bucket is intentionally excluded.
    // =========================================================================

    private const NAMED_TRANSACTION_BUCKETS = [
        'timing',
        'transaction_structure',
        'financing',
        'presentation',
    ];

    // =========================================================================
    // PREPARATION NOTE SOURCE BUCKETS
    // The transaction_context buckets inspected when building
    // listing_preparation_notes.
    // =========================================================================

    private const PREPARATION_NOTE_BUCKETS = [
        'timing',
        'transaction_structure',
        'financing',
    ];

    public function __construct(
        private readonly PropertyMarketingContextService $contextService
    ) {}

    /**
     * Build a structured marketing brief array from a persisted PropertyDnaProfile.
     *
     * Calls PropertyMarketingContextService::build() to obtain Phase P context,
     * then assembles nine named sections deterministically from that context using
     * static maps and constants. No AI, no external APIs, no database access, and
     * no dynamic string generation occur at any point in this method or its helpers.
     *
     * All nine top-level keys are always present in the returned array, even when
     * the profile is completely empty. No key is ever null or absent.
     *
     * Output structure:
     * [
     *     'property_attribute_context'    => array,  // pass-through of Phase P attribute_context
     *     'transaction_context'           => array,  // pass-through of Phase P transaction_context
     *     'quantitative_context'          => array,  // pass-through of Phase P quantitative_context
     *     'marketing_asset_checklist'     => array,  // derived from presentation bucket
     *     'missing_information_checklist' => array,  // derived from empty named buckets
     *     'seller_landlord_questions'     => array,  // pre-written questions for empty/sparse buckets
     *     'listing_preparation_notes'     => array,  // derived from timing/structure/financing buckets
     *     'neutral_feature_summary'       => array,  // factual attribute + quantitative entries
     *     'summary'                       => array,  // six deterministic integer counts
     * ]
     *
     * @param  PropertyDnaProfile $profile  A persisted, cast profile model instance.
     * @return array
     */
    public function build(PropertyDnaProfile $profile): array
    {
        $context = $this->contextService->build($profile);

        $attributeContext    = (array) ($context['attribute_context']    ?? []);
        $transactionContext  = (array) ($context['transaction_context']  ?? []);
        $quantitativeContext = (array) ($context['quantitative_context'] ?? []);

        $brief = [];

        // --- Pass-through sections (Steps 2) ---
        $brief['property_attribute_context'] = $attributeContext;
        $brief['transaction_context']        = $transactionContext;
        $brief['quantitative_context']       = $quantitativeContext;

        // --- Derived sections ---
        $brief['marketing_asset_checklist']     = $this->buildMarketingAssetChecklist($transactionContext);
        $brief['missing_information_checklist'] = $this->buildMissingInformationChecklist($attributeContext, $transactionContext);
        $brief['seller_landlord_questions']     = $this->buildSellerLandlordQuestions($attributeContext, $transactionContext);
        $brief['listing_preparation_notes']     = $this->buildListingPreparationNotes($transactionContext);
        $brief['neutral_feature_summary']       = $this->buildNeutralFeatureSummary($attributeContext, $quantitativeContext);

        // --- Summary section (always last, counts from completed brief) ---
        $brief['summary'] = $this->buildSummary($brief, $attributeContext, $transactionContext, $quantitativeContext);

        return $brief;
    }

    // =========================================================================
    // SECTION BUILDERS
    // Each private method assembles one named brief section deterministically.
    // No dynamic string generation occurs in any method. All output strings
    // are either verbatim Phase P passthrough values or static constants.
    // =========================================================================

    /**
     * Build the marketing_asset_checklist section.
     *
     * Iterates the presentation bucket of transaction_context.
     * For each record, looks up the tag in MARKETING_ASSET_MAP and emits the
     * corresponding pre-written neutral checklist entry.
     * Tags not found in the map receive the static MARKETING_ASSET_FALLBACK entry.
     *
     * @param  array $transactionContext  The transaction_context group from Phase P.
     * @return array<int, array{tag: string, checklist_entry: string}>
     */
    private function buildMarketingAssetChecklist(array $transactionContext): array
    {
        $checklist = [];
        $presentationRecords = (array) ($transactionContext['presentation'] ?? []);

        foreach ($presentationRecords as $record) {
            $record = (array) $record;
            $tag    = (string) ($record['tag'] ?? '');

            $entry = self::MARKETING_ASSET_MAP[$tag] ?? self::MARKETING_ASSET_FALLBACK;

            $checklist[] = [
                'tag'            => $tag,
                'checklist_entry' => $entry,
            ];
        }

        return $checklist;
    }

    /**
     * Build the missing_information_checklist section.
     *
     * Iterates every named bucket in attribute_context and transaction_context
     * (excluding the unrecognized fallback buckets). For each bucket that is
     * empty, emits the corresponding pre-written static checklist item from
     * MISSING_INFO_MAP. The summary counts from Phase P context are used only
     * as a cross-check; per-bucket inspection is authoritative.
     *
     * @param  array $attributeContext    The attribute_context group from Phase P.
     * @param  array $transactionContext  The transaction_context group from Phase P.
     * @return array<int, array{context_group: string, bucket: string, checklist_entry: string}>
     */
    private function buildMissingInformationChecklist(array $attributeContext, array $transactionContext): array
    {
        $checklist = [];

        foreach (self::NAMED_ATTRIBUTE_BUCKETS as $bucket) {
            $records = (array) ($attributeContext[$bucket] ?? []);
            if (empty($records)) {
                $key   = 'attribute_context:' . $bucket;
                $entry = self::MISSING_INFO_MAP[$key] ?? self::MISSING_INFO_ATTRIBUTE_FALLBACK;
                $checklist[] = [
                    'context_group'  => 'attribute_context',
                    'bucket'         => $bucket,
                    'checklist_entry' => $entry,
                ];
            }
        }

        foreach (self::NAMED_TRANSACTION_BUCKETS as $bucket) {
            $records = (array) ($transactionContext[$bucket] ?? []);
            if (empty($records)) {
                $key   = 'transaction_context:' . $bucket;
                $entry = self::MISSING_INFO_MAP[$key] ?? self::MISSING_INFO_TRANSACTION_FALLBACK;
                $checklist[] = [
                    'context_group'  => 'transaction_context',
                    'bucket'         => $bucket,
                    'checklist_entry' => $entry,
                ];
            }
        }

        return $checklist;
    }

    /**
     * Build the seller_landlord_questions section.
     *
     * Uses SELLER_LANDLORD_QUESTION_MAP, which is keyed by "context_group:bucket".
     * Emits the associated pre-written question entry only when a named bucket
     * contains fewer records than BUCKET_MINIMUM (i.e., is empty or sparse).
     * All question strings are static constants — no dynamic text is generated.
     *
     * @param  array $attributeContext    The attribute_context group from Phase P.
     * @param  array $transactionContext  The transaction_context group from Phase P.
     * @return array<int, array{context_group: string, bucket: string, question: string}>
     */
    private function buildSellerLandlordQuestions(array $attributeContext, array $transactionContext): array
    {
        $questions = [];

        foreach (self::NAMED_ATTRIBUTE_BUCKETS as $bucket) {
            $records = (array) ($attributeContext[$bucket] ?? []);
            if (count($records) < self::BUCKET_MINIMUM) {
                $key      = 'attribute_context:' . $bucket;
                $question = self::SELLER_LANDLORD_QUESTION_MAP[$key] ?? self::QUESTION_ATTRIBUTE_FALLBACK;
                $questions[] = [
                    'context_group' => 'attribute_context',
                    'bucket'        => $bucket,
                    'question'      => $question,
                ];
            }
        }

        foreach (self::NAMED_TRANSACTION_BUCKETS as $bucket) {
            $records = (array) ($transactionContext[$bucket] ?? []);
            if (count($records) < self::BUCKET_MINIMUM) {
                $key      = 'transaction_context:' . $bucket;
                $question = self::SELLER_LANDLORD_QUESTION_MAP[$key] ?? self::QUESTION_TRANSACTION_FALLBACK;
                $questions[] = [
                    'context_group' => 'transaction_context',
                    'bucket'        => $bucket,
                    'question'      => $question,
                ];
            }
        }

        return $questions;
    }

    /**
     * Build the listing_preparation_notes section.
     *
     * Iterates the timing, transaction_structure, and financing buckets of
     * transaction_context. For each record, looks up its tag in
     * LISTING_PREPARATION_NOTE_MAP and emits the corresponding pre-written
     * factual note. Tags not found in the map receive the static
     * LISTING_PREPARATION_NOTE_FALLBACK entry.
     *
     * No rankings, suitability statements, or negotiation advice appear in any note.
     *
     * @param  array $transactionContext  The transaction_context group from Phase P.
     * @return array<int, array{bucket: string, tag: string, note: string}>
     */
    private function buildListingPreparationNotes(array $transactionContext): array
    {
        $notes = [];

        foreach (self::PREPARATION_NOTE_BUCKETS as $bucket) {
            $records = (array) ($transactionContext[$bucket] ?? []);
            foreach ($records as $record) {
                $record = (array) $record;
                $tag    = (string) ($record['tag'] ?? '');

                $note = self::LISTING_PREPARATION_NOTE_MAP[$tag] ?? self::LISTING_PREPARATION_NOTE_FALLBACK;

                $notes[] = [
                    'bucket' => $bucket,
                    'tag'    => $tag,
                    'note'   => $note,
                ];
            }
        }

        return $notes;
    }

    /**
     * Build the neutral_feature_summary section.
     *
     * Produces a structured array of factual entries derived from attribute_context
     * records (all named buckets, excluding unrecognized) and quantitative_context
     * records. Each entry states a property attribute or quantitative trait verbatim
     * using the tag/trait/value and explanation strings passed through from Phase P.
     *
     * This section MUST NOT contain:
     *   - Demographic language or characterizations.
     *   - Ideal-buyer or ideal-tenant language.
     *   - Neighborhood, community, or school demographic assumptions.
     *   - Audience assumptions of any kind.
     *   - Any Fair Housing risk language.
     *
     * All description values are the explanation strings verbatim from Phase P context,
     * which themselves originate from Phase O static constant maps.
     *
     * @param  array $attributeContext    The attribute_context group from Phase P.
     * @param  array $quantitativeContext The quantitative_context flat array from Phase P.
     * @return array<int, array>
     */
    private function buildNeutralFeatureSummary(array $attributeContext, array $quantitativeContext): array
    {
        $summary = [];

        foreach (self::NAMED_ATTRIBUTE_BUCKETS as $bucket) {
            $records = (array) ($attributeContext[$bucket] ?? []);
            foreach ($records as $record) {
                $record = (array) $record;
                $summary[] = [
                    'source'      => 'attribute',
                    'bucket'      => $bucket,
                    'tag'         => (string) ($record['tag']         ?? ''),
                    'description' => (string) ($record['explanation'] ?? ''),
                ];
            }
        }

        foreach ($quantitativeContext as $record) {
            $record = (array) $record;
            $summary[] = [
                'source'      => 'quantitative',
                'trait'       => (string) ($record['trait']       ?? ''),
                'value'       => (string) ($record['value']       ?? ''),
                'description' => (string) ($record['explanation'] ?? ''),
            ];
        }

        return $summary;
    }

    /**
     * Build the summary section.
     *
     * Returns exactly six deterministic integer counts derived from the already-
     * assembled brief sections and the raw Phase P context groups. No percentages,
     * rankings, scores, recommendations, or AI-style insights are included.
     *
     * Counts:
     *   total_brief_sections_populated  — count of non-empty top-level sections.
     *   total_attribute_records         — total records across all attribute_context buckets.
     *   total_transaction_records       — total records across all transaction_context buckets.
     *   total_quantitative_records      — count of quantitative_context records.
     *   empty_attribute_bucket_count    — count of empty named attribute_context buckets.
     *   empty_transaction_bucket_count  — count of empty named transaction_context buckets.
     *
     * Note: total_brief_sections_populated counts summary itself as always populated,
     * so its minimum value is 1. The other eight sections are counted only if non-empty.
     *
     * For property_attribute_context and transaction_context, "non-empty" means at least
     * one record in ANY bucket — including the unrecognized fallback bucket — so that
     * profiles whose tags land only in the unrecognized bucket are not undercounted.
     *
     * @param  array $brief               The partially assembled brief (all sections except summary).
     * @param  array $attributeContext    The attribute_context group from Phase P.
     * @param  array $transactionContext  The transaction_context group from Phase P.
     * @param  array $quantitativeContext The quantitative_context flat array from Phase P.
     * @return array{
     *     total_brief_sections_populated: int,
     *     total_attribute_records: int,
     *     total_transaction_records: int,
     *     total_quantitative_records: int,
     *     empty_attribute_bucket_count: int,
     *     empty_transaction_bucket_count: int
     * }
     */
    private function buildSummary(
        array $brief,
        array $attributeContext,
        array $transactionContext,
        array $quantitativeContext
    ): array {
        // Count populated top-level sections. Summary is always present (counted as 1).
        $populatedSections = 1;

        // property_attribute_context — non-empty if any bucket (including unrecognized) has records.
        // Iterating all values of the context group covers named buckets and the unrecognized fallback.
        foreach ($attributeContext as $bucketRecords) {
            if (!empty($bucketRecords)) {
                $populatedSections++;
                break;
            }
        }

        // transaction_context — non-empty if any bucket (including unrecognized) has records.
        foreach ($transactionContext as $bucketRecords) {
            if (!empty($bucketRecords)) {
                $populatedSections++;
                break;
            }
        }

        // quantitative_context — non-empty if array has at least one record.
        if (!empty($quantitativeContext)) {
            $populatedSections++;
        }

        // Derived sections — non-empty if the built array has at least one entry.
        $derivedKeys = [
            'marketing_asset_checklist',
            'missing_information_checklist',
            'seller_landlord_questions',
            'listing_preparation_notes',
            'neutral_feature_summary',
        ];

        foreach ($derivedKeys as $key) {
            if (!empty($brief[$key])) {
                $populatedSections++;
            }
        }

        // Count total records across all attribute_context buckets (all buckets including unrecognized).
        $totalAttributeRecords = 0;
        foreach ($attributeContext as $bucketRecords) {
            $totalAttributeRecords += count((array) $bucketRecords);
        }

        // Count total records across all transaction_context buckets (all buckets including unrecognized).
        $totalTransactionRecords = 0;
        foreach ($transactionContext as $bucketRecords) {
            $totalTransactionRecords += count((array) $bucketRecords);
        }

        // Count empty named attribute buckets (unrecognized excluded — it is a fallback, not a dimension).
        $emptyAttributeBuckets = 0;
        foreach (self::NAMED_ATTRIBUTE_BUCKETS as $bucket) {
            if (empty($attributeContext[$bucket])) {
                $emptyAttributeBuckets++;
            }
        }

        // Count empty named transaction buckets (unrecognized excluded).
        $emptyTransactionBuckets = 0;
        foreach (self::NAMED_TRANSACTION_BUCKETS as $bucket) {
            if (empty($transactionContext[$bucket])) {
                $emptyTransactionBuckets++;
            }
        }

        return [
            'total_brief_sections_populated'  => $populatedSections,
            'total_attribute_records'         => $totalAttributeRecords,
            'total_transaction_records'       => $totalTransactionRecords,
            'total_quantitative_records'      => count($quantitativeContext),
            'empty_attribute_bucket_count'    => $emptyAttributeBuckets,
            'empty_transaction_bucket_count'  => $emptyTransactionBuckets,
        ];
    }

}
