<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * AiMarketingReportPersistenceService — Phase XJ AI Marketing Report Persistence
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service accepts a completed in-memory orchestration result produced by
 * AiMarketingReportOrchestratorService and writes it to the three persistence
 * tables: marketing_reports, marketing_report_versions, and marketing_report_audits.
 *
 * This service MUST NEVER:
 *   - Call any AI system, language model, or external API of any kind.
 *   - Introduce any route, controller, Blade view, Livewire component, JavaScript,
 *     migration, seeder, or database schema change.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *   - Perform publication, approval, or editorial workflows.
 *   - Modify AiMarketingReportGeneratorService, AiMarketingReportReviewService,
 *     or AiMarketingReportOrchestratorService.
 *   - UPDATE or DELETE any row in marketing_report_audits — the table has a
 *     PostgreSQL append-only trigger that will raise an exception on any such attempt.
 * ==================================================================================
 */
class AiMarketingReportPersistenceService
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
     * Persist a completed orchestration result to the three marketing report tables.
     *
     * Pre-write gate conditions (all checked before any DB transaction is opened):
     *   (a) orchestration_status === 'ready_for_agent_review'
     *   (b) review.passed === true
     *   (c) generation.report is a non-empty array
     *   (d) generation.report.attribution_verified === true
     *   (e) All five required section keys are present in generation.report.sections
     *
     * If any gate condition fails, a descriptive exception is thrown and no write
     * occurs. A duplicate report_id check is also performed inside the transaction
     * before any insert is attempted.
     *
     * All three inserts (one marketing_reports row, five marketing_report_versions
     * rows, one marketing_report_audits row) are wrapped in a single DB::transaction().
     *
     * Return structure on success:
     * [
     *     'marketing_report_id' => string,  // UUID of the inserted marketing_reports row
     *     'versions_created'    => 5,
     *     'audit_created'       => true,
     *     'status'              => 'persisted',
     * ]
     *
     * @param  PropertyDnaProfile $profile             A persisted, cast profile model instance.
     * @param  array              $orchestrationResult The completed result from AiMarketingReportOrchestratorService::run().
     * @return array
     *
     * @throws Exception  If any gate condition fails, a duplicate report_id is detected,
     *                    or the DB transaction fails.
     */
    public function persist(PropertyDnaProfile $profile, array $orchestrationResult): array
    {
        // -------------------------------------------------------------------------
        // Gate conditions — all evaluated before any DB transaction is opened.
        // Any failure throws immediately; nothing is written.
        // -------------------------------------------------------------------------

        // (a) orchestration_status must be 'ready_for_agent_review'.
        if (($orchestrationResult['orchestration_status'] ?? null) !== 'ready_for_agent_review') {
            throw new Exception(
                sprintf(
                    'AiMarketingReportPersistenceService gate failure: orchestration_status is "%s" '
                    . 'but must be "ready_for_agent_review" before persisting. '
                    . 'Only orchestration results that have passed all review checks may be written.',
                    $orchestrationResult['orchestration_status'] ?? '(absent)'
                )
            );
        }

        // (b) review.passed must be true.
        if (($orchestrationResult['review']['passed'] ?? null) !== true) {
            throw new Exception(
                'AiMarketingReportPersistenceService gate failure: review.passed is not true. '
                . 'The report must pass the in-memory review before it can be persisted.'
            );
        }

        // (c) generation.report must be a non-empty array.
        $report = $orchestrationResult['generation']['report'] ?? null;

        if (!is_array($report) || count($report) === 0) {
            throw new Exception(
                'AiMarketingReportPersistenceService gate failure: generation.report is absent or empty. '
                . 'A valid, non-empty Phase W report array is required.'
            );
        }

        // (d) generation.report.attribution_verified must be true.
        if (($report['attribution_verified'] ?? null) !== true) {
            throw new Exception(
                'AiMarketingReportPersistenceService gate failure: generation.report.attribution_verified is not true. '
                . 'All sections with non-empty draft_text must have verified source attribution before persisting.'
            );
        }

        // (e) All five required section keys must be present in generation.report.sections.
        $sections = $report['sections'] ?? null;

        if (!is_array($sections)) {
            throw new Exception(
                'AiMarketingReportPersistenceService gate failure: generation.report.sections is absent or not an array. '
                . 'All five section keys must be present: '
                . implode(', ', self::REQUIRED_SECTION_KEYS) . '.'
            );
        }

        foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
            if (!array_key_exists($sectionKey, $sections)) {
                throw new Exception(
                    sprintf(
                        'AiMarketingReportPersistenceService gate failure: required section key "%s" '
                        . 'is absent from generation.report.sections. '
                        . 'All five section keys must be present before persisting. '
                        . 'Required: %s.',
                        $sectionKey,
                        implode(', ', self::REQUIRED_SECTION_KEYS)
                    )
                );
            }
        }

        // -------------------------------------------------------------------------
        // Extract all values needed for the three inserts before opening the
        // transaction so that any missing-key access error surfaces cleanly.
        // -------------------------------------------------------------------------

        $reportId         = $report['report_id'];
        $listingId        = $report['listing_context']['listing_id'];
        $generatedAt      = $report['generated_at'];
        $generationMeta   = $report['generation_metadata'] ?? [];
        $readinessSnapshot = $report['readiness_snapshot'] ?? [];
        $attributionVerified = $report['attribution_verified'];

        $aiModel                = $generationMeta['ai_model'] ?? '';
        $promptTemplateVersion  = $generationMeta['prompt_template_version'] ?? '';
        $phaseRBriefVersion     = $generationMeta['phase_r_brief_version'] ?? '';
        $phaseUReadinessVersion = $generationMeta['phase_u_readiness_version'] ?? '';

        $generationReadiness = $orchestrationResult['generation']['readiness'] ?? [];

        // -------------------------------------------------------------------------
        // DB transaction — contains the duplicate guard + all three inserts.
        // -------------------------------------------------------------------------

        $result = DB::transaction(function () use (
            $reportId,
            $listingId,
            $profile,
            $generatedAt,
            $aiModel,
            $promptTemplateVersion,
            $phaseRBriefVersion,
            $phaseUReadinessVersion,
            $readinessSnapshot,
            $sections,
            $attributionVerified,
            $generationMeta,
            $generationReadiness,
            $report
        ) {
            // --- Duplicate report_id guard ---
            // Check before any insert; do not rely on the primary-key violation alone.
            $exists = DB::table('marketing_reports')->where('id', $reportId)->exists();

            if ($exists) {
                throw new Exception(
                    sprintf(
                        'AiMarketingReportPersistenceService duplicate guard: a marketing_reports row '
                        . 'with id "%s" already exists. Persisting the same report_id twice is not '
                        . 'permitted. No rows have been written.',
                        $reportId
                    )
                );
            }

            // --- Insert marketing_reports row ---
            // readiness_snapshot and sections are jsonb columns. DB::table()
            // (query builder) does NOT auto-serialize PHP arrays — PostgreSQL
            // would receive the literal string "Array" and reject it with
            // "invalid input syntax for type json". json_encode() is therefore
            // required here. This is intentional and correct for DB facade usage.
            DB::table('marketing_reports')->insert([
                'id'                        => $reportId,
                'listing_id'                => $listingId,
                'profile_id'                => $profile->id,
                'generated_at'              => $generatedAt,
                'ai_model'                  => $aiModel,
                'prompt_template_version'   => $promptTemplateVersion,
                'phase_r_brief_version'     => $phaseRBriefVersion,
                'phase_u_readiness_version' => $phaseUReadinessVersion,
                'report_contract_version'   => 'phase-w-v1',
                'readiness_snapshot'        => json_encode($readinessSnapshot),
                'sections'                  => json_encode($sections),
                'attribution_verified'      => $attributionVerified,
                'status'                    => 'pending_review',
            ]);

            // --- Insert five marketing_report_versions rows ---
            $versionsCreated = 0;

            foreach (self::REQUIRED_SECTION_KEYS as $sectionKey) {
                $section           = is_array($sections[$sectionKey]) ? $sections[$sectionKey] : [];
                $draftText         = $section['draft_text'] ?? '';
                $sourceAttribution = $section['source_attribution'] ?? [];
                $sectionStatus     = $section['status'] ?? 'pending_review';

                // source_attribution is a jsonb column. json_encode() is required
                // because DB::table() (query builder) does not auto-serialize arrays.
                DB::table('marketing_report_versions')->insert([
                    'marketing_report_id' => $reportId,
                    'section_key'         => $sectionKey,
                    'version_number'      => 1,
                    'draft_text'          => is_string($draftText) ? $draftText : '',
                    'source_attribution'  => json_encode(is_array($sourceAttribution) ? $sourceAttribution : []),
                    'status'              => $sectionStatus,
                    'created_by'          => 'ai_generated',
                ]);

                $versionsCreated++;
            }

            // --- Insert marketing_report_audits generation row ---
            // This table is append-only (PostgreSQL trigger prohibits UPDATE/DELETE).
            // Do not attempt to UPDATE or DELETE this row under any circumstances.
            // event_data is a jsonb column. json_encode() is required because
            // DB::table() (query builder) does not auto-serialize PHP arrays.
            DB::table('marketing_report_audits')->insert([
                'event_type' => 'generation',
                'report_id'  => $reportId,
                'listing_id' => $listingId,
                'profile_id' => $profile->id,
                'actor_id'   => null,
                'event_at'   => $generatedAt,
                'event_data' => json_encode([
                    'ai_model'                   => $aiModel,
                    'prompt_template_version'    => $promptTemplateVersion,
                    'report_contract_version'    => 'phase-w-v1',
                    'phase_r_brief_snapshot'     => $generationMeta['phase_r_brief_snapshot'] ?? null,
                    'phase_u_readiness_snapshot' => $generationReadiness,
                    'is_marketing_ready_at_call' => $generationReadiness['is_marketing_ready'] ?? false,
                    'attribution_verified'        => $report['attribution_verified'],
                ]),
            ]);

            return [
                'marketing_report_id' => $reportId,
                'versions_created'    => $versionsCreated,
                'audit_created'       => true,
                'status'              => 'persisted',
            ];
        });

        return $result;
    }
}
