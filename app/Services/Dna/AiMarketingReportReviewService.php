<?php

namespace App\Services\Dna;

/**
 * AiMarketingReportReviewService — Phase XF In-Memory AI Marketing Report Review
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service performs a backend-only, in-memory review of a generated Phase W
 * AI marketing report. It evaluates the report for contract compliance, attribution
 * completeness, section status validity, publication safety, and governance text
 * violations.
 *
 * This service MUST NEVER:
 *   - Call any AI system, language model, or external API of any kind.
 *   - Write to or read from any database table, queue, cache layer, session, or
 *     persistent store of any kind.
 *   - Modify AiMarketingReportGeneratorService, OpenAiClientService,
 *     PropertyMarketingContextService, PropertyMarketingBriefService, or
 *     PropertyMarketingReadinessService.
 *   - Introduce any route, controller, Blade view, Livewire component, JavaScript,
 *     migration, seeder, or database schema change.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *   - Perform legal interpretation of any governance text finding.
 *   - Apply protected-class inference of any kind.
 * ==================================================================================
 */
class AiMarketingReportReviewService
{
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
     * Expected `status` value per section key.
     * Four narrative/summary sections expect `pending_review`;
     * `missing_information_note` expects `internal_note`.
     */
    private const EXPECTED_SECTION_STATUSES = [
        'property_feature_narrative'  => 'pending_review',
        'transaction_terms_summary'   => 'pending_review',
        'marketing_asset_statement'   => 'pending_review',
        'missing_information_note'    => 'internal_note',
        'listing_preparation_summary' => 'pending_review',
    ];

    /**
     * Status values that indicate a report has been pre-approved or published,
     * which is prohibited at the review stage.
     */
    private const PROHIBITED_STATUSES = [
        'approved',
        'revised',
        'published',
        'externally_sent',
    ];

    /**
     * Prohibited phrases for the governance text scan.
     * This is a defensive, static scan only — no legal interpretation is made.
     */
    private const PROHIBITED_PHRASES = [
        'ideal buyer',
        'ideal family',
        'family-friendly',
        'good schools',
        'great schools',
        'top schools',
        'best schools',
        'school district',
        'race',
        'religion',
        'religious',
        'national origin',
        'ethnicity',
        'ethnic',
        'disability',
        'handicap',
        'familial status',
        'family status',
        'children preferred',
        'no children',
        'adults only',
        'mature community',
        'perfect for couples',
        'ideal for singles',
        'income',
        'credit score',
        'buyer identity',
        'tenant identity',
        'sexual orientation',
        'gender identity',
        'marital status',
        'source of income',
        'safe neighborhood',
        'integrated neighborhood',
        'changing neighborhood',
    ];

    /**
     * Review a generated Phase W AI marketing report for compliance and safety.
     *
     * Performs five checks in sequence (all checks always run regardless of earlier
     * failures so that the full issue list is returned in one call):
     *   1. Contract presence  — required top-level and section keys must exist.
     *   2. Attribution        — sections with non-empty draft_text need non-empty
     *                           source_attribution arrays.
     *   3. Section status     — each section's `status` must match its expected value.
     *   4. Publication safety — no section may carry a prohibited status value.
     *   5. Governance text    — draft_text values must not contain prohibited phrases.
     *
     * Return structure:
     * [
     *     'review_status' => 'approved_for_agent_review' | 'rejected',
     *     'passed'        => bool,
     *     'issues'        => [
     *         [
     *             'type'     => string,  // contract | attribution | status |
     *                                    //   publication_safety | governance_text
     *             'severity' => string,  // high | medium
     *             'message'  => string,
     *         ],
     *         ...
     *     ],
     *     'summary' => [
     *         'issue_count'            => int,
     *         'attribution_complete'   => bool,
     *         'section_statuses_valid' => bool,
     *         'publication_safe'       => bool,
     *     ],
     * ]
     *
     * @param  array $report  A decoded Phase W report array.
     * @return array
     */
    public function review(array $report): array
    {
        $issues = [];

        // --- Check 1: Contract presence ---
        $contractIssues = $this->checkContractPresence($report);
        $issues = array_merge($issues, $contractIssues);

        // Extract sections defensively; remaining checks work on whatever is present.
        $sections = is_array($report['sections'] ?? null) ? $report['sections'] : [];

        // --- Check 2: Attribution completeness ---
        $attributionIssues = $this->checkAttributionCompleteness($sections);
        $issues = array_merge($issues, $attributionIssues);

        // --- Check 3: Section status validity ---
        $statusIssues = $this->checkSectionStatuses($sections);
        $issues = array_merge($issues, $statusIssues);

        // --- Check 4: Publication safety ---
        $publicationIssues = $this->checkPublicationSafety($sections);
        $issues = array_merge($issues, $publicationIssues);

        // --- Check 5: Governance text scan ---
        $governanceIssues = $this->checkGovernanceText($sections);
        $issues = array_merge($issues, $governanceIssues);

        // --- Build summary flags ---
        $hasHighSeverity      = $this->hasIssueOfSeverity($issues, 'high');
        $attributionComplete  = !$this->hasIssueOfType($attributionIssues, 'attribution');
        $sectionStatusesValid = count($statusIssues) === 0;
        $publicationSafe      = count($publicationIssues) === 0;

        $passed       = !$hasHighSeverity;
        $reviewStatus = $passed ? 'approved_for_agent_review' : 'rejected';

        return [
            'review_status' => $reviewStatus,
            'passed'        => $passed,
            'issues'        => $issues,
            'summary'       => [
                'issue_count'            => count($issues),
                'attribution_complete'   => $attributionComplete,
                'section_statuses_valid' => $sectionStatusesValid,
                'publication_safe'       => $publicationSafe,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Private check methods
    // -------------------------------------------------------------------------

    /**
     * Check that all required top-level and section keys are present.
     *
     * Adds a `high`-severity `contract` issue for each missing key.
     *
     * @param  array $report
     * @return array  List of issue arrays.
     */
    private function checkContractPresence(array $report): array
    {
        $issues = [];

        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $report)) {
                $issues[] = [
                    'type'     => 'contract',
                    'severity' => 'high',
                    'message'  => sprintf(
                        'Required top-level key "%s" is missing from the report.',
                        $key
                    ),
                ];
            }
        }

        $sections = $report['sections'] ?? null;

        if (is_array($sections)) {
            foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
                if (!array_key_exists($sectionKey, $sections)) {
                    $issues[] = [
                        'type'     => 'contract',
                        'severity' => 'high',
                        'message'  => sprintf(
                            'Required section key "%s" is missing from the "sections" object.',
                            $sectionKey
                        ),
                    ];
                }
            }
        } else {
            // sections is absent or not an array — emit one issue per required section key
            // so that callers receive the full list of missing keys, not an aggregate message.
            foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
                $issues[] = [
                    'type'     => 'contract',
                    'severity' => 'high',
                    'message'  => sprintf(
                        'Required section key "%s" is missing from the "sections" object.',
                        $sectionKey
                    ),
                ];
            }
        }

        return $issues;
    }

    /**
     * Check that every section with non-empty draft_text has a non-empty source_attribution.
     *
     * Adds a `high`-severity `attribution` issue per violation.
     *
     * @param  array $sections  The decoded `sections` object from the report.
     * @return array
     */
    private function checkAttributionCompleteness(array $sections): array
    {
        $issues = [];

        foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
            if (!array_key_exists($sectionKey, $sections)) {
                continue;
            }

            $section     = is_array($sections[$sectionKey]) ? $sections[$sectionKey] : [];
            $draftText   = $section['draft_text'] ?? '';
            $attribution = $section['source_attribution'] ?? [];

            if (is_string($draftText) && $draftText !== '') {
                if (!is_array($attribution) || count($attribution) === 0) {
                    $issues[] = [
                        'type'     => 'attribution',
                        'severity' => 'high',
                        'message'  => sprintf(
                            'Section "%s" has non-empty draft_text but source_attribution is absent or empty.',
                            $sectionKey
                        ),
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Check that each section's `status` matches its expected value.
     *
     * Expected: `pending_review` for the four narrative/summary sections,
     * `internal_note` for `missing_information_note`.
     *
     * Adds a `medium`-severity `status` issue per violation.
     *
     * @param  array $sections
     * @return array
     */
    private function checkSectionStatuses(array $sections): array
    {
        $issues = [];

        foreach (self::EXPECTED_SECTION_STATUSES as $sectionKey => $expectedStatus) {
            if (!array_key_exists($sectionKey, $sections)) {
                continue;
            }

            $section       = is_array($sections[$sectionKey]) ? $sections[$sectionKey] : [];
            $actualStatus  = $section['status'] ?? null;

            if ($actualStatus !== $expectedStatus) {
                $issues[] = [
                    'type'     => 'status',
                    'severity' => 'medium',
                    'message'  => sprintf(
                        'Section "%s" has status "%s" but expected "%s".',
                        $sectionKey,
                        $actualStatus ?? '(absent)',
                        $expectedStatus
                    ),
                ];
            }
        }

        return $issues;
    }

    /**
     * Scan all section statuses for prohibited pre-approval/publication values.
     *
     * Adds a `high`-severity `publication_safety` issue per violation.
     *
     * @param  array $sections
     * @return array
     */
    private function checkPublicationSafety(array $sections): array
    {
        $issues = [];

        foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
            if (!array_key_exists($sectionKey, $sections)) {
                continue;
            }

            $section      = is_array($sections[$sectionKey]) ? $sections[$sectionKey] : [];
            $actualStatus = $section['status'] ?? null;

            if (in_array($actualStatus, self::PROHIBITED_STATUSES, true)) {
                $issues[] = [
                    'type'     => 'publication_safety',
                    'severity' => 'high',
                    'message'  => sprintf(
                        'Section "%s" carries prohibited status "%s". Reports must not carry pre-approval or publication statuses at the review stage.',
                        $sectionKey,
                        $actualStatus
                    ),
                ];
            }
        }

        return $issues;
    }

    /**
     * Scan all section draft_text values for prohibited governance phrases.
     *
     * This is a defensive, static scan only. No legal interpretation is applied.
     * Adds a `high`-severity `governance_text` issue per match.
     *
     * @param  array $sections
     * @return array
     */
    private function checkGovernanceText(array $sections): array
    {
        $issues = [];

        foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
            if (!array_key_exists($sectionKey, $sections)) {
                continue;
            }

            $section   = is_array($sections[$sectionKey]) ? $sections[$sectionKey] : [];
            $draftText = $section['draft_text'] ?? '';

            if (!is_string($draftText) || $draftText === '') {
                continue;
            }

            $lowerText = strtolower($draftText);

            foreach (self::PROHIBITED_PHRASES as $phrase) {
                if (str_contains($lowerText, $phrase)) {
                    $issues[] = [
                        'type'     => 'governance_text',
                        'severity' => 'high',
                        'message'  => sprintf(
                            'Section "%s" contains prohibited phrase "%s". Defensive governance scan — no legal interpretation.',
                            $sectionKey,
                            $phrase
                        ),
                    ];
                }
            }
        }

        return $issues;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Return true if any issue in the list has the given severity.
     *
     * @param  array  $issues
     * @param  string $severity
     * @return bool
     */
    private function hasIssueOfSeverity(array $issues, string $severity): bool
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === $severity) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return true if any issue in the list has the given type.
     *
     * @param  array  $issues
     * @param  string $type
     * @return bool
     */
    private function hasIssueOfType(array $issues, string $type): bool
    {
        foreach ($issues as $issue) {
            if (($issue['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }
}
