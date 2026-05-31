<?php

namespace App\Services\Dna;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase XO — Seller/Landlord Marketing Report Owner Approval Service.
 *
 * Handles the approval/rejection write path for property owners (sellers and
 * landlords) reviewing an AI marketing report before publication.
 *
 * Ownership chain (resolved via DB::table() — not Eloquent):
 *   marketing_reports.profile_id
 *     → property_dna_profiles.id
 *         → property_dna_profiles.listing_type / listing_id  (polymorphic)
 *             → property_auctions.user_id      (listing_type = 'seller')
 *             → landlord_auctions.user_id      (listing_type = 'landlord')
 *   Auth::id() is matched against listing.user_id on the resolved table.
 *   Both seller and landlord users authenticate through the shared users table
 *   and the same Auth::id() path — no separate consumer auth mechanism exists.
 *
 * Governance:
 *   - No AI, LLM, embedding, or external API calls.
 *   - No version rows created, updated, or deleted.
 *   - No audit rows updated or deleted (append-only table).
 *   - No schema changes.
 *   - No publication, export, PDF, or email.
 *   - Only transitions from status = 'pending_review' are allowed.
 *   - All writes wrapped in DB::transaction().
 *   - Uses DB::table() throughout per the PostgreSQL gate resolver pattern.
 */
class AiMarketingReportOwnerApprovalService
{
    /**
     * Approve a marketing report on behalf of the owning seller or landlord.
     *
     * Transitions marketing_reports.status from 'pending_review' → 'seller_approved'.
     * Inserts an append-only audit row with event_type = 'review'.
     *
     * @param  string  $reportId     UUID of the marketing_reports row.
     * @param  int     $ownerUserId  Authenticated owner's user ID.
     * @return array{ok: bool, error: string|null}
     */
    public function approve(string $reportId, int $ownerUserId): array
    {
        return DB::transaction(function () use ($reportId, $ownerUserId) {
            $report = DB::table('marketing_reports')
                ->select('id', 'listing_id', 'profile_id', 'status')
                ->where('id', $reportId)
                ->lockForUpdate()
                ->first();

            if (! $report) {
                return ['ok' => false, 'error' => 'Marketing report not found.'];
            }

            if ($report->status !== 'pending_review') {
                return [
                    'ok'    => false,
                    'error' => "Only reports in 'pending_review' status can be approved. Current status: {$report->status}.",
                ];
            }

            $now = now();

            DB::table('marketing_reports')
                ->where('id', $reportId)
                ->update([
                    'status'     => 'seller_approved',
                    'updated_at' => $now,
                ]);

            DB::table('marketing_report_audits')->insert([
                'event_type' => 'review',
                'report_id'  => $reportId,
                'listing_id' => $report->listing_id,
                'profile_id' => $report->profile_id,
                'actor_id'   => $ownerUserId,
                'event_at'   => $now,
                'event_data' => json_encode([
                    'action'       => 'seller_landlord_approved',
                    'review_scope' => 'owner_approval',
                    'approved_at'  => $now->toIso8601String(),
                    'approved_by'  => $ownerUserId,
                ]),
                'created_at' => $now,
            ]);

            Log::info('Owner: marketing_report approved', [
                'owner_user_id' => $ownerUserId,
                'report_id'     => $reportId,
            ]);

            return ['ok' => true, 'error' => null];
        });
    }

    /**
     * Reject a marketing report on behalf of the owning seller or landlord.
     *
     * Transitions marketing_reports.status from 'pending_review' → 'rejected'.
     * Inserts an append-only audit row with event_type = 'review'.
     *
     * @param  string       $reportId     UUID of the marketing_reports row.
     * @param  int          $ownerUserId  Authenticated owner's user ID.
     * @param  string|null  $reason       Optional rejection reason provided by the owner.
     * @return array{ok: bool, error: string|null}
     */
    public function reject(string $reportId, int $ownerUserId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($reportId, $ownerUserId, $reason) {
            $report = DB::table('marketing_reports')
                ->select('id', 'listing_id', 'profile_id', 'status')
                ->where('id', $reportId)
                ->lockForUpdate()
                ->first();

            if (! $report) {
                return ['ok' => false, 'error' => 'Marketing report not found.'];
            }

            if ($report->status !== 'pending_review') {
                return [
                    'ok'    => false,
                    'error' => "Only reports in 'pending_review' status can be rejected. Current status: {$report->status}.",
                ];
            }

            $now = now();

            DB::table('marketing_reports')
                ->where('id', $reportId)
                ->update([
                    'status'     => 'rejected',
                    'updated_at' => $now,
                ]);

            $eventData = [
                'action'       => 'seller_landlord_rejected',
                'review_scope' => 'owner_approval',
                'rejected_at'  => $now->toIso8601String(),
                'rejected_by'  => $ownerUserId,
                'reason'       => (! is_null($reason) && $reason !== '') ? $reason : null,
            ];

            DB::table('marketing_report_audits')->insert([
                'event_type' => 'review',
                'report_id'  => $reportId,
                'listing_id' => $report->listing_id,
                'profile_id' => $report->profile_id,
                'actor_id'   => $ownerUserId,
                'event_at'   => $now,
                'event_data' => json_encode($eventData),
                'created_at' => $now,
            ]);

            Log::info('Owner: marketing_report rejected', [
                'owner_user_id' => $ownerUserId,
                'report_id'     => $reportId,
                'has_reason'    => ! is_null($reason) && $reason !== '',
            ]);

            return ['ok' => true, 'error' => null];
        });
    }
}
