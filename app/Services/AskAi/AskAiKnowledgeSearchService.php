<?php

namespace App\Services\AskAi;

use App\Models\AskAiAnswer;
use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\AskAiQuestion;

/**
 * AskAiKnowledgeSearchService — Phase 4: Database-First Answer Layer
 *
 * Searches the latest ready knowledge snapshot for a stored answer before
 * the OpenAI adapter is invoked. Returns a typed result with a source
 * metadata struct so callers can short-circuit when confidence is high.
 *
 * Search order (stops at first confident match):
 *   A. Exact question_text / sample_question / sample_question_2 match.
 *   B. Canonical key lookup via options['normalized_field_key'] when set.
 *   C. Normalized variant matching (lowercase, punctuation-stripped, synonym-mapped).
 *   D. not_found → caller falls through to OpenAI unchanged.
 *
 * Canonical key conventions in snapshot tables:
 *   ask_ai_facts.canonical_key     — bare key (e.g. 'bedrooms', 'asking_price')
 *   ask_ai_questions.canonical_key — full path (e.g. 'faq_answers.roof_age_and_condition',
 *                                    'listing.bedrooms')
 *   ask_ai_answers.canonical_key   — bare FAQ key (e.g. 'roof_age_and_condition') or
 *                                    full path; both forms are tried on every lookup.
 *
 * GOVERNANCE:
 *   - Restricted facts (restricted = true) always return outcome = 'restricted'.
 *   - Blank / null stored values return outcome = 'blank_information_not_provided'.
 *   - This service MUST NOT write to any table or call any external HTTP service.
 *   - All exceptions are caught internally; callers always receive a typed result.
 *
 * ==========================================================================================
 * VIEWER-SCOPE ENFORCEMENT (P0.1)
 * ==========================================================================================
 * This is the READ-TIME half of the fail-closed contract that P0 established on the write
 * side in SnapshotFactVisibility. Before P0.1 this service gated on `restricted` ALONE and
 * ignored `public_allowed`, `visibility` and the viewer scope it was already being handed in
 * $options — so 'owner_only' was a classification with no reader, and the database-first path
 * would serve an owner-only fact to any authenticated non-owner who reached
 * POST /api/ask-ai/ask (which authenticates but, unlike /ask-ai/listing-question, performs no
 * ownership check of its own).
 *
 * The three tiers are now enforced here, using the SAME scope the controller already resolves
 * via AskAiViewerAuthorizationService::resolveScope() and threads through
 * AskAiInternalRunnerService — no second authorization model:
 *
 *   restricted     → outcome 'restricted' for EVERY scope, owner included. Unchanged: this is
 *                    a compliance obligation, not an ownership question.
 *   owner_only     → served to SCOPE_OWNER; blocked for every other scope.
 *   public_allowed → served to every scope.
 *
 * FAIL CLOSED: an absent, empty or unrecognised viewer_scope resolves to SCOPE_PUBLIC, the
 * most restrictive tier. A caller that forgets to pass the scope gets the guest's answer, not
 * the owner's.
 *
 * WHY A BLOCKED NON-OWNER REUSES THE 'restricted' OUTCOME:
 *   The runner's Phase 4 already maps 'restricted' to status 'blocked' with a null answer and
 *   the refusal template. Returning an outcome the runner does not recognise would fall
 *   through to the OpenAI path instead — and the redacted context still carries some
 *   owner-only keys (the seller's minimums among them), so falling through is precisely the
 *   leak this guard exists to close. The result therefore reuses 'restricted' and records
 *   source.visibility_block = 'owner_only' so traces and tests can tell a compliance block
 *   from an ownership block without the runner needing a new branch.
 *
 * ROWS WRITTEN BEFORE P0 STILL CARRY THE OLD DEFAULT-OPEN FLAGS. This guard reads what is
 * stored, so a stale snapshot will still present its facts as public_allowed until it is
 * rebuilt. Rebuilding is deliberately NOT part of P0.1 — see
 * docs/ask-ai-agent-ai-v2-activation-prerequisites.md.
 * ==========================================================================================
 */
class AskAiKnowledgeSearchService
{
    public const INFORMATION_NOT_PROVIDED = 'Information not provided.';

    /**
     * Stored `visibility` value meaning "not public, but the listing owner may read it".
     * Mirrors SnapshotFactVisibility::OWNER_ONLY; duplicated as a literal rather than
     * imported so this read-time guard cannot be altered by an edit to the writer's enum.
     */
    private const VISIBILITY_PUBLIC_ALLOWED = 'public_allowed';

    private const SYNONYM_MAP = [
        'sq footage'  => 'square footage',
        'sq ft'       => 'square feet',
        'sqft'        => 'square feet',
        'sq. ft.'     => 'square feet',
        'how old is the' => 'age of the',
        'how old is'  => 'age of',
    ];

    private const FILLER_PHRASES = [
        'can you tell me',
        'could you tell me',
        'tell me about the',
        'tell me about',
        'what is the',
        'what are the',
        'what is a',
        'what is an',
        'what is',
        'what are',
        'is there a',
        'is there an',
        'does it have a',
        'does it have an',
        'does it have',
        'how many',
        'do they have',
        'please describe',
    ];

    /**
     * Search for a stored answer for the given question against the listing's
     * latest ready snapshot.
     *
     * @param  string $listingType  Canonical listing type ('seller', 'buyer', 'landlord', 'tenant').
     * @param  int    $listingId    Primary key of the listing record.
     * @param  string $question     Raw user question string.
     * @param  array  $options      Pipeline options; may contain 'normalized_field_key' and
     *                              'viewer_scope' (AskAiViewerAuthorizationService::SCOPE_*).
     *                              An absent or unrecognised viewer_scope fails closed to
     *                              SCOPE_PUBLIC.
     * @return array{
     *   outcome: 'database_hit'|'blank_information_not_provided'|'restricted'|'not_found',
     *   answer: string|null,
     *   source: array{
     *     answer_source: 'database'|null,
     *     snapshot_id: int|null,
     *     canonical_key: string|null,
     *     match_type: 'canonical_field'|'exact_question'|'alternate_question'|'normalized_variant'|null,
     *     snapshot_version: int|null,
     *   },
     * }
     */
    public function search(
        string $listingType,
        int $listingId,
        string $question,
        array $options = []
    ): array {
        try {
            $snapshot = AskAiKnowledgeSnapshot::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->where('status', 'ready')
                ->orderByDesc('version')
                ->first();

            if ($snapshot === null) {
                return $this->notFound();
            }

            $normalizedFieldKey = $options['normalized_field_key'] ?? null;

            // P0.1 — resolve the viewer scope ONCE, here, and thread it down every lookup
            // path. It is passed as an argument rather than stored on the instance because
            // this service is a container singleton shared across the request: per-request
            // state on it would be a cross-request authorization bug waiting to happen.
            $viewerScope = $this->resolveViewerScope($options);

            // Step A — Exact question_text / sample_question / sample_question_2 match.
            // Checked first so verbatim questions resolve without requiring a canonical key.
            $result = $this->searchByExactQuestion($snapshot, $question, $viewerScope);
            if ($result !== null) {
                return $result;
            }

            // Step B — Direct canonical key lookup (high confidence when normalizer resolved a key).
            if ($normalizedFieldKey !== null) {
                $result = $this->searchByCanonicalKey($snapshot, $normalizedFieldKey, $viewerScope);
                if ($result !== null) {
                    return $result;
                }
            }

            // Step C — Normalised variant match.
            $result = $this->searchByNormalizedVariant($snapshot, $question, $viewerScope);
            if ($result !== null) {
                return $result;
            }

            return $this->notFound();
        } catch (\Throwable) {
            return $this->notFound();
        }
    }

    // =========================================================================
    // Step A — Canonical key lookup
    // =========================================================================

    private function searchByCanonicalKey(
        AskAiKnowledgeSnapshot $snapshot,
        string $normalizedFieldKey,
        string $viewerScope
    ): ?array {
        if (str_starts_with($normalizedFieldKey, 'faq_answers.')) {
            return $this->lookupFaqAnswer($snapshot, $normalizedFieldKey, 'canonical_field', $viewerScope);
        }

        if (str_starts_with($normalizedFieldKey, 'listing.')) {
            return $this->lookupListingFact($snapshot, $normalizedFieldKey, 'canonical_field', $viewerScope);
        }

        return null;
    }

    // =========================================================================
    // Step B — Exact question text matching
    // =========================================================================

    private function searchByExactQuestion(
        AskAiKnowledgeSnapshot $snapshot,
        string $question,
        string $viewerScope
    ): ?array {
        $lower = mb_strtolower(trim($question));

        // Collect all rows that match on question_text or sample_question.
        $primaryMatches = AskAiQuestion::where('snapshot_id', $snapshot->id)
            ->where(function ($q) use ($lower) {
                $q->whereRaw('LOWER(question_text) = ?', [$lower])
                  ->orWhereRaw('LOWER(sample_question) = ?', [$lower]);
            })
            ->get();

        if ($primaryMatches->isNotEmpty()) {
            // Ambiguous: multiple distinct canonical keys match — fall through to avoid wrong answer.
            if ($primaryMatches->pluck('canonical_key')->unique()->count() > 1) {
                return null;
            }
            $row = $primaryMatches->first();
            return $this->resolveAnswerForQuestion($snapshot, $row, $row->canonical_key, 'exact_question', $viewerScope);
        }

        // Check sample_question_2 separately (alternate).
        $alternateMatches = AskAiQuestion::where('snapshot_id', $snapshot->id)
            ->whereRaw('LOWER(sample_question_2) = ?', [$lower])
            ->get();

        if ($alternateMatches->isNotEmpty()) {
            if ($alternateMatches->pluck('canonical_key')->unique()->count() > 1) {
                return null;
            }
            $row = $alternateMatches->first();
            return $this->resolveAnswerForQuestion($snapshot, $row, $row->canonical_key, 'alternate_question', $viewerScope);
        }

        return null;
    }

    // =========================================================================
    // Step C — Normalised variant matching
    // =========================================================================

    private function searchByNormalizedVariant(
        AskAiKnowledgeSnapshot $snapshot,
        string $question,
        string $viewerScope
    ): ?array {
        $normalized = $this->normalizeQuestion($question);
        if ($normalized === '') {
            return null;
        }

        $questions = AskAiQuestion::where('snapshot_id', $snapshot->id)->get();

        // Collect all rows whose any normalized form matches, preserving match type.
        $candidates = [];
        foreach ($questions as $q) {
            $qtNorm = $this->normalizeQuestion((string) ($q->question_text ?? ''));
            if ($qtNorm !== '' && $qtNorm === $normalized) {
                $candidates[] = ['row' => $q, 'matchType' => 'normalized_variant'];
                continue;
            }

            $sqNorm = $this->normalizeQuestion((string) ($q->sample_question ?? ''));
            if ($sqNorm !== '' && $sqNorm === $normalized) {
                $candidates[] = ['row' => $q, 'matchType' => 'normalized_variant'];
                continue;
            }

            $sq2Norm = $this->normalizeQuestion((string) ($q->sample_question_2 ?? ''));
            if ($sq2Norm !== '' && $sq2Norm === $normalized) {
                $candidates[] = ['row' => $q, 'matchType' => 'alternate_question'];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Ambiguous: multiple distinct canonical keys — fall through to OpenAI.
        $distinctKeys = array_unique(array_map(fn ($c) => $c['row']->canonical_key, $candidates));
        if (count($distinctKeys) > 1) {
            return null;
        }

        $first = $candidates[0];
        return $this->resolveAnswerForQuestion(
            $snapshot,
            $first['row'],
            $first['row']->canonical_key,
            $first['matchType'],
            $viewerScope
        );
    }

    // =========================================================================
    // Answer resolution helpers
    // =========================================================================

    /**
     * Given a matched AskAiQuestion row, find and return the corresponding
     * stored answer (FAQ answers or listing facts depending on canonical key prefix).
     */
    private function resolveAnswerForQuestion(
        AskAiKnowledgeSnapshot $snapshot,
        AskAiQuestion $question,
        string $canonicalKey,
        string $matchType,
        string $viewerScope
    ): ?array {
        if (str_starts_with($canonicalKey, 'faq_answers.')) {
            return $this->lookupFaqAnswer($snapshot, $canonicalKey, $matchType, $viewerScope);
        }

        if (str_starts_with($canonicalKey, 'listing.')) {
            return $this->lookupListingFact($snapshot, $canonicalKey, $matchType, $viewerScope);
        }

        // Bare key without prefix — try answers table (could be a bare FAQ key).
        $answer = AskAiAnswer::where('snapshot_id', $snapshot->id)
            ->where('canonical_key', $canonicalKey)
            ->first();

        if ($answer !== null) {
            // P0.1 — the bare-key path is a third way into the same rows and needs the same
            // gate as lookupFaqAnswer()/lookupListingFact(). Gating only the prefixed paths
            // would leave this one open to exactly the questions that reach it.
            if (! $this->answerVisibleToScope($answer, $viewerScope)) {
                return $this->ownerOnlyBlockedResult($snapshot, $canonicalKey, $matchType);
            }

            $text = $answer->answer_text ?? null;
            if ($text === null || trim($text) === '') {
                return $this->blankResult($snapshot, $canonicalKey, $matchType);
            }
            return $this->hitResult($snapshot, $canonicalKey, $text, $matchType);
        }

        // Try facts table with the bare key.
        $fact = AskAiFact::where('snapshot_id', $snapshot->id)
            ->where('canonical_key', $canonicalKey)
            ->first();

        if ($fact !== null) {
            if ($fact->restricted) {
                return $this->restrictedResult($snapshot, $canonicalKey, $matchType);
            }
            if (! $this->factVisibleToScope($fact, $viewerScope)) {
                return $this->ownerOnlyBlockedResult($snapshot, $canonicalKey, $matchType);
            }
            $value = $fact->value ?? null;
            if ($value === null || trim($value) === '') {
                return $this->blankResult($snapshot, $canonicalKey, $matchType);
            }
            return $this->hitResult($snapshot, $canonicalKey, $value, $matchType);
        }

        return null;
    }

    /**
     * Lookup a stored FAQ answer for the given faq_answers.* canonical key.
     *
     * The answers table may store the canonical_key as either the full path
     * ('faq_answers.roof_age_and_condition') or the bare key ('roof_age_and_condition')
     * depending on how the snapshot builder persisted it. Both forms are tried.
     */
    private function lookupFaqAnswer(
        AskAiKnowledgeSnapshot $snapshot,
        string $normalizedFieldKey,
        string $matchType,
        string $viewerScope
    ): ?array {
        $bareKey = str_starts_with($normalizedFieldKey, 'faq_answers.')
            ? substr($normalizedFieldKey, strlen('faq_answers.'))
            : $normalizedFieldKey;

        $answer = AskAiAnswer::where('snapshot_id', $snapshot->id)
            ->where(function ($q) use ($bareKey, $normalizedFieldKey) {
                $q->where('canonical_key', $bareKey)
                  ->orWhere('canonical_key', $normalizedFieldKey);
            })
            ->first();

        if ($answer !== null) {
            // P0.1 — owner-authored KB answers persist as 'owner_only' (see the four snapshot
            // builders). Blocked for every non-owner scope; the owner keeps their own answer.
            if (! $this->answerVisibleToScope($answer, $viewerScope)) {
                return $this->ownerOnlyBlockedResult($snapshot, $normalizedFieldKey, $matchType);
            }

            $text = $answer->answer_text ?? null;
            if ($text === null || trim($text) === '') {
                return $this->blankResult($snapshot, $normalizedFieldKey, $matchType);
            }
            return $this->hitResult($snapshot, $normalizedFieldKey, $text, $matchType);
        }

        // No answer row, but check whether the question is in the registry for this snapshot.
        // If the field is registered (question exists), the absence of an answer means blank.
        $questionExists = AskAiQuestion::where('snapshot_id', $snapshot->id)
            ->where(function ($q) use ($bareKey, $normalizedFieldKey) {
                $q->where('canonical_key', $bareKey)
                  ->orWhere('canonical_key', $normalizedFieldKey);
            })
            ->exists();

        if ($questionExists) {
            return $this->blankResult($snapshot, $normalizedFieldKey, $matchType);
        }

        return null;
    }

    /**
     * Lookup a stored listing fact for the given listing.* canonical key.
     *
     * Facts are stored with the bare key (no 'listing.' prefix).
     */
    private function lookupListingFact(
        AskAiKnowledgeSnapshot $snapshot,
        string $normalizedFieldKey,
        string $matchType,
        string $viewerScope
    ): ?array {
        $bareKey = str_starts_with($normalizedFieldKey, 'listing.')
            ? substr($normalizedFieldKey, strlen('listing.'))
            : $normalizedFieldKey;

        $fact = AskAiFact::where('snapshot_id', $snapshot->id)
            ->where('canonical_key', $bareKey)
            ->first();

        if ($fact !== null) {
            // Compliance first: restricted is blocked for every scope, the owner included.
            if ($fact->restricted) {
                return $this->restrictedResult($snapshot, $normalizedFieldKey, $matchType);
            }
            // P0.1 — then ownership: an owner_only fact (which includes every buyer/tenant
            // criteria fact and the seller's own minimums) reaches the owner and nobody else.
            if (! $this->factVisibleToScope($fact, $viewerScope)) {
                return $this->ownerOnlyBlockedResult($snapshot, $normalizedFieldKey, $matchType);
            }
            $value = $fact->value ?? null;
            if ($value === null || trim($value) === '') {
                return $this->blankResult($snapshot, $normalizedFieldKey, $matchType);
            }
            return $this->hitResult($snapshot, $normalizedFieldKey, $value, $matchType);
        }

        // Field not in facts. Check whether it is a known question (registered but null).
        $questionExists = AskAiQuestion::where('snapshot_id', $snapshot->id)
            ->where('canonical_key', $normalizedFieldKey)
            ->exists();

        if ($questionExists) {
            return $this->blankResult($snapshot, $normalizedFieldKey, $matchType);
        }

        return null;
    }

    // =========================================================================
    // Question normalizer
    // =========================================================================

    /**
     * Produce a canonical normalised form of a question for fuzzy matching.
     *
     * Steps:
     *   1. Lowercase + trim.
     *   2. Strip all punctuation (keep letters, digits, spaces).
     *   3. Apply synonym replacements.
     *   4. Strip common question-filler phrases.
     *   5. Collapse whitespace.
     *
     * The result is suitable for equality comparison only — it is intentionally
     * lossy (e.g. 'sq ft' and 'square feet' become identical). Two questions
     * that normalise to the same non-empty string are considered the same intent.
     */
    public function normalizeQuestion(string $question): string
    {
        if ($question === '') {
            return '';
        }

        $q = mb_strtolower(trim($question));

        // Strip punctuation; keep letters, digits, whitespace.
        $q = preg_replace('/[^a-z0-9\s]/u', ' ', $q) ?? '';

        // Apply synonym map (longest phrases first avoids partial replacements).
        foreach (self::SYNONYM_MAP as $from => $to) {
            $q = str_replace($from, $to, $q);
        }

        // Strip common filler phrases.
        foreach (self::FILLER_PHRASES as $filler) {
            $pattern = '/\b' . preg_quote($filler, '/') . '\b/u';
            $q = preg_replace($pattern, ' ', $q) ?? $q;
        }

        // Collapse whitespace.
        $q = preg_replace('/\s+/', ' ', $q) ?? '';

        return trim($q);
    }

    // =========================================================================
    // Typed result constructors
    // =========================================================================

    private function hitResult(AskAiKnowledgeSnapshot $snapshot, string $canonicalKey, string $answer, string $matchType): array
    {
        return [
            'outcome' => 'database_hit',
            'answer'  => $answer,
            'source'  => [
                'answer_source'    => 'database',
                'snapshot_id'      => $snapshot->id,
                'canonical_key'    => $canonicalKey,
                'match_type'       => $matchType,
                'snapshot_version' => $snapshot->version,
            ],
        ];
    }

    private function blankResult(AskAiKnowledgeSnapshot $snapshot, string $canonicalKey, string $matchType): array
    {
        return [
            'outcome' => 'blank_information_not_provided',
            'answer'  => self::INFORMATION_NOT_PROVIDED,
            'source'  => [
                'answer_source'    => 'database',
                'snapshot_id'      => $snapshot->id,
                'canonical_key'    => $canonicalKey,
                'match_type'       => $matchType,
                'snapshot_version' => $snapshot->version,
            ],
        ];
    }

    private function restrictedResult(AskAiKnowledgeSnapshot $snapshot, string $canonicalKey, string $matchType): array
    {
        return [
            'outcome' => 'restricted',
            'answer'  => null,
            'source'  => [
                'answer_source'    => 'database',
                'snapshot_id'      => $snapshot->id,
                'canonical_key'    => $canonicalKey,
                'match_type'       => $matchType,
                'snapshot_version' => $snapshot->version,
            ],
        ];
    }

    /**
     * An ownership block, reported through the runner's existing 'restricted' branch.
     *
     * source.visibility_block distinguishes it from a compliance block for traces, logs and
     * tests. The answer is null and no stored value is echoed back, so a blocked non-owner
     * learns nothing about the value — only that the assistant will not answer.
     */
    private function ownerOnlyBlockedResult(
        AskAiKnowledgeSnapshot $snapshot,
        string $canonicalKey,
        string $matchType
    ): array {
        $result = $this->restrictedResult($snapshot, $canonicalKey, $matchType);
        $result['source']['visibility_block'] = 'owner_only';

        return $result;
    }

    /**
     * Resolve the requester's scope from the pipeline options, failing closed.
     *
     * Anything absent, empty, non-string or unrecognised becomes SCOPE_PUBLIC — the most
     * restrictive tier — so a caller that omits the scope is treated as a guest rather than
     * silently granted the owner's view.
     */
    private function resolveViewerScope(array $options): string
    {
        $scope = $options['viewer_scope'] ?? null;

        if (! is_string($scope)) {
            return AskAiViewerAuthorizationService::SCOPE_PUBLIC;
        }

        $scope = strtolower(trim($scope));

        return in_array($scope, [
            AskAiViewerAuthorizationService::SCOPE_OWNER,
            AskAiViewerAuthorizationService::SCOPE_AUTHORIZED,
            AskAiViewerAuthorizationService::SCOPE_PUBLIC,
        ], true)
            ? $scope
            : AskAiViewerAuthorizationService::SCOPE_PUBLIC;
    }

    /**
     * True when a stored FACT may be served to this scope.
     *
     * The owner sees anything that is not compliance-restricted. Everyone else needs the
     * fact to be explicitly public: `public_allowed = true` AND `visibility` still saying
     * 'public_allowed', so a row whose two columns disagree is withheld rather than served.
     */
    private function factVisibleToScope(AskAiFact $fact, string $viewerScope): bool
    {
        if ($viewerScope === AskAiViewerAuthorizationService::SCOPE_OWNER) {
            return true;
        }

        return ((bool) $fact->public_allowed) === true
            && $this->normalizeVisibility($fact->visibility) === self::VISIBILITY_PUBLIC_ALLOWED;
    }

    /**
     * True when a stored ANSWER may be served to this scope.
     *
     * `ask_ai_answers` has no `public_allowed` column, so `visibility` is the whole record.
     * A null or unrecognised value reads as not-public.
     */
    private function answerVisibleToScope(AskAiAnswer $answer, string $viewerScope): bool
    {
        if ($viewerScope === AskAiViewerAuthorizationService::SCOPE_OWNER) {
            return true;
        }

        return $this->normalizeVisibility($answer->visibility) === self::VISIBILITY_PUBLIC_ALLOWED;
    }

    /**
     * Normalise a stored visibility value. Null, non-string and blank all become '' so they
     * can never equal 'public_allowed' — an unclassified row is not a public row.
     */
    private function normalizeVisibility(mixed $visibility): string
    {
        return is_string($visibility) ? strtolower(trim($visibility)) : '';
    }

    private function notFound(): array
    {
        return [
            'outcome' => 'not_found',
            'answer'  => null,
            'source'  => [
                'answer_source'    => null,
                'snapshot_id'      => null,
                'canonical_key'    => null,
                'match_type'       => null,
                'snapshot_version' => null,
            ],
        ];
    }
}
