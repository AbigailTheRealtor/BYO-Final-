<?php

namespace App\Services\Dna;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase XP — Admin Marketing Report Publication Service.
 *
 * Handles the publication write path for authorized admins transitioning a
 * seller_approved marketing report to published status.
 *
 * Schema notes (documentation only — no schema changes made here):
 *   - marketing_reports_status_check constraint allows:
 *       'pending_review', 'agent_approved', 'seller_approved',
 *       'published', 'rejected', 'held_attribution_failure'
 *     'archived' is NOT in the constraint. Archive transition is deferred.
 *   - marketing_report_audits_event_type_check allows:
 *       'generation', 'review', 'readiness_failure', 'attribution_failure'
 *     event_type = 'review' is used for this publication audit row.
 *
 * Governance:
 *   - No AI, LLM, embedding, or external API calls.
 *   - No version rows created, updated, or deleted.
 *   - No audit rows updated or deleted (append-only table).
 *   - No schema changes.
 *   - No export, PDF, or email.
 *   - Only transitions from status = 'seller_approved' are allowed.
 *   - All writes wrapped in DB::transaction().
 *   - Uses DB::table() throughout per the PostgreSQL gate resolver pattern.
 */
class AiMarketingReportPublicationService
{
    /**
     * Publish a marketing report on behalf of an authorized admin.
     *
     * Transitions marketing_reports.status from 'seller_approved' → 'published'.
     * Inserts an append-only audit row with event_type = 'review'.
     *
     * Archive transition is explicitly deferred: 'archived' is absent from the
     * marketing_reports_status_check DB constraint and must not be introduced
     * without a schema migration.
     *
     * @param  string  $reportId     UUID of the marketing_reports row.
     * @param  int     $adminUserId  Authenticated admin's user ID.
     * @return array{ok: bool, error: string|null}
     */
    public function publish(string $reportId, int $adminUserId): array
    {
        return DB::transaction(function () use ($reportId, $adminUserId) {
            $report = DB::table('marketing_reports')
                ->select('id', 'listing_id', 'profile_id', 'status')
                ->where('id', $reportId)
                ->lockForUpdate()
                ->first();

            if (! $report) {
                return ['ok' => false, 'error' => 'Marketing report not found.'];
            }

            if ($report->status !== 'seller_approved') {
                return [
                    'ok'    => false,
                    'error' => "Only reports in 'seller_approved' status can be published. Current status: {$report->status}.",
                ];
            }

            $now = now();

            DB::table('marketing_reports')
                ->where('id', $reportId)
                ->update([
                    'status'     => 'published',
                    'updated_at' => $now,
                ]);

            DB::table('marketing_report_audits')->insert([
                'event_type' => 'review',
                'report_id'  => $reportId,
                'listing_id' => $report->listing_id,
                'profile_id' => $report->profile_id,
                'actor_id'   => $adminUserId,
                'event_at'   => $now,
                'event_data' => json_encode([
                    'action'          => 'published',
                    'review_scope'    => 'all_sections',
                    'published_at'    => $now->toIso8601String(),
                    'published_by'    => $adminUserId,
                    'previous_status' => 'seller_approved',
                    'new_status'      => 'published',
                ]),
                'created_at' => $now,
            ]);

            Log::info('Admin: marketing_report published', [
                'admin_user_id' => $adminUserId,
                'report_id'     => $reportId,
            ]);

            return ['ok' => true, 'error' => null];
        });
    }
}
