<?php

namespace App\Services\AgentAi\Loaders;

use App\Models\AiFaqAnswer;
use App\Models\AskAiKnowledgeSnapshot;
use App\Services\AskAi\AskAiFaqEnrichmentService;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;

/**
 * ExtendedKnowledgeLoader
 *
 * Loads only the public-safe knowledge sources explicitly permitted by
 * docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md — including relevant
 * knowledge snapshot facts, listing FAQ data, Location DNA summaries, and
 * public property intelligence summaries where available.
 *
 * Registration:
 *   source_key: 'extended_knowledge'
 *   priority:   60
 *   scopes:     all four listing scopes (seller, landlord, buyer, tenant)
 *               NOT agent_profile scope
 *
 * GOVERNANCE:
 *   - Only facts with public_allowed=true are loaded from ask_ai_facts.
 *   - school_rating, walk_score, transit_score, bike_score from PropertyDnaProfile
 *     are excluded — they carry fair-housing risk (school quality ratings and
 *     implicit demographics; see audit Section 12.2 "School quality ratings: Forbidden").
 *   - Crime statistics, safety ratings, and neighborhood demographics are NEVER
 *     included from location DNA.
 *   - Offer, counteroffer, accepted-bid-summary, and competing-agent data are
 *     NEVER included.
 *   - No DB writes. No external HTTP calls.
 */
class ExtendedKnowledgeLoader
{
    use LoaderHelpers;

    public const SOURCE_KEY = 'extended_knowledge';
    public const PRIORITY   = 60;
    public const CACHE_TTL  = 3600;

    /**
     * Maximum number of FAQ entries to include per listing.
     * Prevents unbounded growth for listings with many FAQ answers.
     */
    private const MAX_FAQ_ENTRIES = 30;

    /**
     * Maximum number of snapshot facts to include.
     */
    private const MAX_FACTS = 50;

    /**
     * Callable entry point registered with AgentAiContextSourceRegistry.
     *
     * @param  array $scopeContext  {scope, agent_id, listing_type, listing_id}
     * @return array|null           Fragment or null when no knowledge sources found.
     */
    public function __invoke(array $scopeContext): ?array
    {
        $listingType = $scopeContext['listing_type'] ?? null;
        $listingId   = (int) ($scopeContext['listing_id'] ?? 0);

        if (!$listingType || $listingId <= 0) {
            return null;
        }

        $content = [];

        $faqAnswers = $this->loadFaqAnswers($listingType, $listingId);
        if (!empty($faqAnswers)) {
            $content['faq_answers'] = $faqAnswers;
        }

        $snapshotFacts = $this->loadSnapshotFacts($listingType, $listingId);
        if (!empty($snapshotFacts)) {
            $content['snapshot_facts'] = $snapshotFacts;
        }

        if (in_array($listingType, ['seller', 'landlord'], true)) {
            $intelligence = $this->loadPropertyIntelligence($listingType, $listingId);
            if (!empty($intelligence)) {
                $content['property_intelligence'] = $intelligence;
            }
        }

        $locationSummary = $this->loadLocationDnaSummary($listingType, $listingId);
        if (!empty($locationSummary)) {
            $content['location_summary'] = $locationSummary;
        }

        if (empty($content)) {
            return null;
        }

        return self::makeFragment(
            self::SOURCE_KEY,
            self::PRIORITY,
            $content,
            true,
            ['seller', 'landlord', 'buyer', 'tenant'],
            self::CACHE_TTL
        );
    }

    /**
     * Load FAQ answers from the ai_faq_answers table (primary) or the
     * listing_ai_faq EAV meta key (fallback handled by callers).
     *
     * @param  string $listingType
     * @param  int    $listingId
     * @return array  Key-value pairs of question_key => answer_text
     */
    private function loadFaqAnswers(string $listingType, int $listingId): array
    {
        // ADMISSION BOUNDARY (Fair Housing P0-B — reader side).
        //
        // This loader is a SECOND prompt-context builder: it feeds `faq_answers` to the
        // Agent AI V2 assistant without passing through
        // AskAiContextBuilderService::buildFaqAnswers(), so the gate there does not cover
        // it. It reads `ai_faq_answers` directly, and before this guard it forwarded
        // every stored row verbatim.
        //
        // Guarding the writer in AskAiFaqEnrichmentService::sync() is not sufficient on
        // its own. Rows created before that guard existed are still in the table, and a
        // retired question — one deleted from config/ai_faq_*.php — leaves rows behind by
        // definition. The boundary has to be enforced where the value is consumed, or a
        // key stops being asked and keeps being answered.
        //
        // Same SSOT as the other two gates (config/ai_faq_{seller,buyer,landlord}.php and
        // config/tenant_ai_faq.php), and deliberately not a second hand-maintained list:
        // three copies of "which questions are legitimate" would drift, and the copy that
        // drifted would be the one nobody was reading.
        //
        // FAIL CLOSED: an unknown listing type, or a config that cannot be loaded, yields
        // an empty index and therefore no FAQ content. The loader simply contributes
        // nothing to the fragment, which is a supported outcome — __invoke() already
        // returns null when every source is empty.
        $registered = AskAiFaqEnrichmentService::buildConfigIndex($listingType);

        if (empty($registered)) {
            return [];
        }

        // The allowlist is applied in the QUERY as well as in the loop below. Filtering
        // only in PHP would let stale unregistered rows consume the MAX_FAQ_ENTRIES budget
        // and crowd legitimate answers out of the fragment — the guard would then quietly
        // cost the assistant real content. The in-loop check is kept as defence in depth
        // so the boundary does not depend on one query clause staying correct.
        $rows = AiFaqAnswer::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->whereIn('question_key', array_keys($registered))
            ->orderBy('question_group')
            ->orderBy('id')
            ->limit(self::MAX_FAQ_ENTRIES)
            ->get(['question_key', 'answer_text']);

        $answers = [];
        foreach ($rows as $row) {
            $key = (string) ($row->question_key ?? '');

            if (! array_key_exists($key, $registered)) {
                continue;
            }

            if ($key !== '' && $row->answer_text) {
                $answers[$key] = self::truncateText($row->answer_text);
            }
        }

        return $answers;
    }

    /**
     * Load public-allowed facts from the most recent ready knowledge snapshot.
     *
     * Only facts with public_allowed=true and visibility='public_allowed' are
     * included. Private and compliance-sensitive facts are excluded.
     *
     * @param  string $listingType
     * @param  int    $listingId
     * @return array  Key-value pairs of canonical_key => value
     */
    private function loadSnapshotFacts(string $listingType, int $listingId): array
    {
        $snapshot = AskAiKnowledgeSnapshot::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('status', 'ready')
            ->orderByDesc('version')
            ->first();

        if (!$snapshot) {
            return [];
        }

        $facts = $snapshot->facts()
            ->where('public_allowed', true)
            ->where('visibility', 'public_allowed')
            ->orderBy('sort_order')
            ->limit(self::MAX_FACTS)
            ->get(['canonical_key', 'value', 'label']);

        $result = [];
        foreach ($facts as $fact) {
            if ($fact->canonical_key && $fact->value !== null) {
                $key = ltrim(str_replace(['listing.', 'faq_answers.'], '', $fact->canonical_key), '.');
                $result[$key] = $fact->value;
            }
        }

        return $result;
    }

    /**
     * Load public property intelligence from PropertyDnaProfile.
     *
     * Per audit Section 2.4 + 12.2: school_rating, walk_score, transit_score,
     * and bike_score are excluded (fair-housing risk per Section 12.2 "School
     * quality ratings: Forbidden in every scope").
     *
     * @param  string $listingType
     * @param  int    $listingId
     * @return array
     */
    private function loadPropertyIntelligence(string $listingType, int $listingId): array
    {
        $profile = PropertyDnaProfile::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->orderByDesc('computed_at')
            ->first();

        if (!$profile) {
            return [];
        }

        $hooks = $profile->ai_marketing_hooks;
        if (is_array($hooks)) {
            $hooks = implode('; ', array_filter(array_slice($hooks, 0, 5)));
        }

        $archetypes = $profile->ai_buyer_archetype_tags;
        if (is_array($archetypes)) {
            $archetypes = implode(', ', array_filter(array_slice($archetypes, 0, 5)));
        }

        $locCtx = $profile->location_intelligence_context;
        $locationNarrative = null;
        if (is_array($locCtx)) {
            $locationNarrative = self::truncateText($locCtx['narrative'] ?? $locCtx['summary'] ?? null);
        }

        return array_filter([
            'marketing_hooks'           => $hooks ?: null,
            'buyer_archetype_tags'      => $archetypes ?: null,
            'location_narrative'        => $locationNarrative,
            'overall_dna_completeness'  => $profile->overall_dna_completeness,
        ]);
    }

    /**
     * Load the public location DNA summary.
     *
     * Per audit Section 12.2 and the governance block: crime statistics,
     * safety ratings, and neighborhood demographics are NEVER included.
     * Only the general lifestyle and summary JSON is surfaced.
     *
     * @param  string $listingType
     * @param  int    $listingId
     * @return array
     */
    private function loadLocationDnaSummary(string $listingType, int $listingId): array
    {
        // The geocode pipeline persists the canonical status 'geocoded'
        // (LocationDnaGeocodeService). A prior 'success' filter never matched any
        // row, so Location DNA silently never loaded into Agent AI knowledge.
        $locationDna = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('geocode_status', 'geocoded')
            ->orderByDesc('generated_at')
            ->first();

        if (!$locationDna) {
            return [];
        }

        $summary   = $locationDna->summary_json ?? [];
        $lifestyle = $locationDna->lifestyle_json ?? [];

        if (empty($summary) && empty($lifestyle)) {
            return [];
        }

        $forbiddenKeys = [
            'crime', 'crime_rate', 'crime_index', 'safety_rating', 'safety_score',
            'demographics', 'race', 'ethnicity', 'income_demographics',
            'school_rating', 'school_score', 'school_quality',
        ];

        $cleanSummary = [];
        foreach ($summary as $key => $value) {
            if (!in_array(strtolower($key), $forbiddenKeys, true)) {
                $cleanSummary[$key] = is_string($value) ? self::truncateText($value) : $value;
            }
        }

        $cleanLifestyle = [];
        foreach ($lifestyle as $key => $value) {
            if (!in_array(strtolower($key), $forbiddenKeys, true)) {
                $cleanLifestyle[$key] = is_string($value) ? self::truncateText($value) : $value;
            }
        }

        $result = array_filter([
            'summary'   => !empty($cleanSummary) ? $cleanSummary : null,
            'lifestyle' => !empty($cleanLifestyle) ? $cleanLifestyle : null,
            'city'      => $locationDna->source_city,
            'state'     => $locationDna->source_state,
            'zip'       => $locationDna->source_zip,
        ]);

        return $result;
    }
}
