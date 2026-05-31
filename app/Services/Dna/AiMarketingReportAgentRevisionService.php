<?php

namespace App\Services\Dna;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase XN — Agent Marketing Report Revision Service.
 *
 * Handles the controlled write path that allows an authorized listing agent
 * to revise individual section text in a marketing report.
 *
 * JSON update strategy — decode / mutate / re-encode (not jsonb_set):
 *   The service reads the existing `sections` JSONB column as a PHP array,
 *   reads the current draft_text for the target section (preserved as
 *   `original_ai_text` in the audit), mutates only that section's draft_text,
 *   and writes the full re-encoded JSON back. All other sections are carried
 *   through untouched because only `$sections[$sectionKey]['draft_text']` is
 *   modified before re-encoding. The row is locked with lockForUpdate() inside
 *   the transaction to prevent concurrent updates from losing each other's data.
 *
 * Governance:
 *   - No AI, LLM, embedding, or external API calls.
 *   - No status transitions on marketing_reports.
 *   - No modifications to existing version or audit rows.
 *   - No schema changes.
 *   - Uses DB::table() throughout (not Eloquent) per the PostgreSQL gate resolver pattern.
 *   - All writes occur inside a single DB::transaction().
 */
class AiMarketingReportAgentRevisionService
{
    /**
     * The four section keys an agent is permitted to revise.
     * missing_information_note is explicitly excluded — it is read-only.
     */
    private const EDITABLE_SECTIONS = [
        'property_feature_narrative',
        'transaction_terms_summary',
        'marketing_asset_statement',
        'listing_preparation_summary',
    ];

    /**
     * Revise a single section of a marketing report.
     *
     * @param  string  $reportId      UUID of the marketing_reports row.
     * @param  string  $sectionKey    Must be one of the four editable section keys.
     * @param  string  $draftText     Revised text submitted by the agent.
     * @param  int     $agentUserId   Authenticated agent's user ID.
     * @return array{ok: bool, version_id: int|null, error: string|null}
     */
    public function revise(string $reportId, string $sectionKey, string $draftText, int $agentUserId): array
    {
        if (! in_array($sectionKey, self::EDITABLE_SECTIONS, true)) {
            if ($sectionKey === 'missing_information_note') {
                return ['ok' => false, 'version_id' => null, 'error' => 'missing_information_note is read-only and cannot be revised.'];
            }

            return ['ok' => false, 'version_id' => null, 'error' => "Section '{$sectionKey}' is not a valid editable section."];
        }

        return DB::transaction(function () use ($reportId, $sectionKey, $draftText, $agentUserId) {
            $report = DB::table('marketing_reports')
                ->select('id', 'listing_id', 'profile_id', 'status', 'sections')
                ->where('id', $reportId)
                ->lockForUpdate()
                ->first();

            if (! $report) {
                return ['ok' => false, 'version_id' => null, 'error' => 'Marketing report not found.'];
            }

            // Decode the existing sections JSON so we can:
            //   (a) extract original_ai_text before the update, and
            //   (b) mutate only the target section's draft_text before re-encoding.
            // All other sections remain byte-for-byte identical in the output.
            $sections = json_decode($report->sections, true);
            if (! is_array($sections)) {
                $sections = [];
            }

            $existingSection = $sections[$sectionKey] ?? [];
            $originalAiText  = is_array($existingSection)
                ? ($existingSection['draft_text'] ?? '')
                : (string) $existingSection;

            // Mutate only the target section's draft_text.
            if (is_array($existingSection)) {
                $sections[$sectionKey]['draft_text'] = $draftText;
            } else {
                $sections[$sectionKey] = ['draft_text' => $draftText];
            }

            $nextVersionNumber = (int) DB::table('marketing_report_versions')
                ->where('marketing_report_id', $reportId)
                ->where('section_key', $sectionKey)
                ->max('version_number') + 1;

            $createdBy = "agent:{$agentUserId}";
            $now       = now();

            $versionId = DB::table('marketing_report_versions')->insertGetId([
                'marketing_report_id' => $reportId,
                'section_key'         => $sectionKey,
                'version_number'      => $nextVersionNumber,
                'draft_text'          => $draftText,
                'source_attribution'  => '[]',
                'status'              => 'revised',
                'created_by'          => $createdBy,
                'created_at'          => $now,
            ]);

            // Update sections using decode/mutate/re-encode rather than a
            // database-specific JSON function. Only $sectionKey was changed above;
            // all remaining sections are untouched in the re-encoded payload.
            DB::table('marketing_reports')
                ->where('id', $reportId)
                ->update([
                    'sections'   => json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => $now,
                ]);

            DB::table('marketing_report_audits')->insert([
                'event_type' => 'review',
                'report_id'  => $reportId,
                'listing_id' => $report->listing_id,
                'profile_id' => $report->profile_id,
                'actor_id'   => $agentUserId,
                'event_at'   => $now,
                'event_data' => json_encode([
                    'action'            => 'approved_with_revisions',
                    'section_key'       => $sectionKey,
                    'revisions_made'    => true,
                    'original_ai_text'  => $originalAiText,
                    'approved_text'     => $draftText,
                    'review_version_id' => $versionId,
                ]),
                'created_at' => $now,
            ]);

            Log::info('Agent: marketing_report section revised', [
                'agent_user_id'  => $agentUserId,
                'report_id'      => $reportId,
                'section_key'    => $sectionKey,
                'version_number' => $nextVersionNumber,
                'version_id'     => $versionId,
            ]);

            return ['ok' => true, 'version_id' => $versionId, 'error' => null];
        });
    }
}
