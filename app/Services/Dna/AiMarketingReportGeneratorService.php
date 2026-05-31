<?php

namespace App\Services\Dna;

use App\Exceptions\Dna\MarketingReadinessException;
use App\Models\PropertyDnaProfile;
use App\Services\Ai\OpenAiClientService;
use Exception;

/**
 * AiMarketingReportGeneratorService — Phase XD AI Marketing Report Generator
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is the AI REPORT GENERATION ORCHESTRATOR for the Property DNA
 * pipeline. It wires together the approved Phase P, R, and U deterministic outputs,
 * assembles a structured prompt payload per the Phase XC specification, calls
 * OpenAiClientService, validates the returned Phase W report contract, and returns
 * the result in memory only.
 *
 * This service MUST NEVER:
 *   - Call any AI system, language model, or external API directly — all AI calls
 *     must go through OpenAiClientService::send().
 *   - Write to or read from any database table, queue, cache layer, session, or
 *     persistent store of any kind. No database writes, no cache, no session.
 *   - Repair, auto-fill, or synthesize any key or value missing from the AI response.
 *     On any contract violation, throw immediately — no silent fallback.
 *   - Introduce any route, controller, Blade view, Livewire component, JavaScript,
 *     migration, seeder, or database schema change.
 *   - Accept or pass any prohibited input key (demographic, race, religion, ethnicity,
 *     disability, family_status, income_tier, school_rating, credit_score,
 *     buyer_identity, tenant_identity) — OpenAiClientService enforces this gate.
 *   - Modify PropertyMarketingContextService, PropertyMarketingBriefService,
 *     PropertyMarketingReadinessService, or OpenAiClientService.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *   - Generate prompt template text — payload structure assembly only.
 *   - Apply protected-class inference of any kind.
 *   - Perform AI reasoning, embedding lookup, or ML logic.
 * ==================================================================================
 */
class AiMarketingReportGeneratorService
{
    /**
     * The five required section keys within the Phase W report `sections` object.
     */
    private const REQUIRED_SECTION_KEYS = [
        'property_feature_narrative',
        'transaction_terms_summary',
        'marketing_asset_statement',
        'missing_information_note',
        'listing_preparation_summary',
    ];

    /**
     * The seven required top-level keys of the Phase W report contract.
     */
    private const REQUIRED_TOP_LEVEL_KEYS = [
        'report_id',
        'generated_at',
        'listing_context',
        'readiness_snapshot',
        'sections',
        'generation_metadata',
        'attribution_verified',
    ];

    /**
     * The required fields that must be present within each section entry.
     */
    private const REQUIRED_SECTION_FIELDS = [
        'draft_text',
        'source_attribution',
    ];

    public function __construct(
        private readonly PropertyMarketingContextService $contextService,
        private readonly PropertyMarketingBriefService $briefService,
        private readonly PropertyMarketingReadinessService $readinessService,
        private readonly OpenAiClientService $openAiClientService,
    ) {}

    /**
     * Generate an AI Marketing Intelligence Report for the given PropertyDnaProfile.
     *
     * Orchestrates the full Phase XD pipeline:
     *   1. Evaluates the Phase U readiness gate — throws MarketingReadinessException
     *      if is_marketing_ready !== true. No prompt is assembled and no OpenAI call
     *      is made when the gate fails.
     *   2. Collects Phase P (context) and Phase R (brief) deterministic outputs.
     *   3. Assembles a structured payload per the Phase XC specification containing
     *      phase_p, phase_r, phase_u, required_contract, and prompt_version.
     *   4. Passes the payload to OpenAiClientService::send().
     *   5. Validates the returned report against the Phase W contract via
     *      validateReportContract() — throws on any contract violation.
     *   6. Verifies section attribution via verifyAttribution().
     *   7. Returns the four-key result array in memory only — nothing is written
     *      to the database, session, or any cache layer at any point.
     *
     * Return value:
     * [
     *     'report'               => array,  // the validated Phase W report object
     *     'readiness'            => array,  // the Phase U snapshot captured at the gate
     *     'attribution_verified' => bool,   // result of verifyAttribution()
     *     'generated_at'         => string, // UTC ISO 8601 timestamp
     * ]
     *
     * @param  PropertyDnaProfile $profile  A persisted, cast profile model instance.
     * @return array
     *
     * @throws MarketingReadinessException  If is_marketing_ready !== true.
     * @throws Exception                    If the OpenAI call fails or the report
     *                                      contract is violated.
     */
    public function generate(PropertyDnaProfile $profile): array
    {
        // --- Step 1: Readiness gate (Phase U) ---
        // Re-evaluated freshly on every call. A cached or session-persisted gate
        // result must never be used as a substitute (Phase W Section 2.4).
        $readiness = $this->readinessService->build($profile);

        if (($readiness['is_marketing_ready'] ?? false) !== true) {
            throw new MarketingReadinessException(
                sprintf(
                    'Profile does not meet AI report generation readiness requirements. '
                    . 'Missing groups: %s. All three required information groups '
                    . '(Property Attributes, Transaction Details, Quantitative Data) '
                    . 'must be present before a report can be generated.',
                    implode(', ', $readiness['missing_groups'] ?? [])
                ),
                $readiness
            );
        }

        // --- Step 2: Collect Phase P and Phase R deterministic outputs ---
        $phaseP = $this->contextService->build($profile);
        $phaseR = $this->briefService->build($profile);

        // --- Step 3: Assemble the Phase XC structured prompt payload ---
        // Payload structure only — no hardcoded prompt text. The prompt_version
        // is read from config so template versioning is controlled without code
        // changes (Phase XC Section 7).
        $payload = [
            'phase_p'           => $phaseP,
            'phase_r'           => $phaseR,
            'phase_u'           => $readiness,
            'required_contract' => 'phase_w',
            'prompt_version'    => (string) config('ai.prompt_version', ''),
        ];

        // --- Step 4: Call OpenAI through the approved wrapper ---
        // OpenAiClientService handles retry, audit metadata, prohibited-key
        // scanning, and response JSON validation. The 'data' key contains
        // the raw decoded report from the AI.
        $aiResult = $this->openAiClientService->send($payload);
        $report   = (array) ($aiResult['data'] ?? []);

        // --- Step 5: Validate the Phase W report contract ---
        // Throws on any violation — no repair, no auto-filling.
        $this->validateReportContract($report);

        // --- Step 6: Verify section attribution ---
        $attributionVerified = $this->verifyAttribution($report);

        // --- Step 7: Assemble and return the result in memory only ---
        $generatedAt = now()->utc()->toIso8601String();

        return [
            'report'               => $report,
            'readiness'            => $readiness,
            'attribution_verified' => $attributionVerified,
            'generated_at'         => $generatedAt,
        ];
    }

    /**
     * Validate that the AI-returned report array conforms to the Phase W contract.
     *
     * Checks performed (in order):
     *   1. All 7 required top-level keys are present.
     *   2. All 5 required section keys exist under `sections`.
     *   3. Each section contains at least `draft_text` and `source_attribution`.
     *   4. `attribution_verified` key exists at the top level.
     *
     * Throws a descriptive Exception immediately on the first violation found.
     * No repair logic, no auto-filling, no silent fallback of any kind.
     *
     * @param  array $report  The decoded report array returned by OpenAI.
     * @return void
     *
     * @throws Exception  If the report does not conform to the Phase W contract.
     */
    public function validateReportContract(array $report): void
    {
        // 1. All 7 required top-level keys must be present.
        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $report)) {
                throw new Exception(
                    sprintf(
                        'Phase W report contract violation: required top-level key "%s" '
                        . 'is absent from the AI response. No repair or auto-filling '
                        . 'will be attempted. Required top-level keys: %s.',
                        $key,
                        implode(', ', self::REQUIRED_TOP_LEVEL_KEYS)
                    )
                );
            }
        }

        // 2. The `sections` value must be an array containing all 5 required section keys.
        $sections = $report['sections'];

        if (!is_array($sections)) {
            throw new Exception(
                'Phase W report contract violation: "sections" must be an array but '
                . sprintf('received %s.', gettype($sections))
            );
        }

        foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
            if (!array_key_exists($sectionKey, $sections)) {
                throw new Exception(
                    sprintf(
                        'Phase W report contract violation: required section key "%s" '
                        . 'is absent from the "sections" object in the AI response. '
                        . 'All 5 section keys must be present regardless of input completeness. '
                        . 'Required section keys: %s.',
                        $sectionKey,
                        implode(', ', self::REQUIRED_SECTION_KEYS)
                    )
                );
            }
        }

        // 3. Each section must contain at least `draft_text` and `source_attribution`.
        foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
            $section = (array) $sections[$sectionKey];

            foreach (self::REQUIRED_SECTION_FIELDS as $field) {
                if (!array_key_exists($field, $section)) {
                    throw new Exception(
                        sprintf(
                            'Phase W report contract violation: required field "%s" '
                            . 'is absent from section "%s" in the AI response. '
                            . 'Every section must contain at least "draft_text" and '
                            . '"source_attribution".',
                            $field,
                            $sectionKey
                        )
                    );
                }
            }
        }

        // 4. `attribution_verified` must be present at the top level.
        // Already covered by the top-level key check above, but explicitly
        // re-confirmed here per Phase W Section 3.2 and the task specification.
        if (!array_key_exists('attribution_verified', $report)) {
            throw new Exception(
                'Phase W report contract violation: "attribution_verified" key is '
                . 'absent from the top-level report object. This key is required '
                . 'regardless of section content.'
            );
        }
    }

    /**
     * Verify that every section with non-empty draft_text has non-empty source_attribution.
     *
     * Iterates all 5 required section keys. For each section where `draft_text` is a
     * non-empty string, checks that `source_attribution` is a non-empty array.
     *
     * Returns true only when all such sections pass. Returns false when any section
     * with a non-empty draft_text has an absent or empty source_attribution.
     *
     * The report array is never modified. This method only reads and evaluates.
     *
     * @param  array $report  The validated Phase W report array.
     * @return bool           True when attribution is complete; false otherwise.
     */
    public function verifyAttribution(array $report): bool
    {
        $sections = (array) ($report['sections'] ?? []);

        foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
            if (!isset($sections[$sectionKey])) {
                continue;
            }

            $section    = (array) $sections[$sectionKey];
            $draftText  = $section['draft_text'] ?? '';
            $attribution = $section['source_attribution'] ?? [];

            if (is_string($draftText) && $draftText !== '') {
                if (!is_array($attribution) || count($attribution) === 0) {
                    return false;
                }
            }
        }

        return true;
    }
}
