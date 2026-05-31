<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Dna\AiMarketingReportOwnerApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase XO — Owner-facing Marketing Report Approval controller.
 *
 * Provides a read-only review page plus Approve and Reject actions for the
 * property owner (seller or landlord) who commissioned the listing.
 *
 * Authorization:
 *   - Routes are inside the auth + verified + noAdmin middleware group.
 *     Unauthenticated, unverified, and admin users are rejected before
 *     this controller is reached.
 *   - Inside the controller, ownership is verified by resolving the listing
 *     via DB::table() and confirming Auth::id() matches listing.user_id.
 *
 * Ownership chain (DB::table() — not Eloquent, per PostgreSQL gate resolver):
 *   marketing_reports.profile_id → property_dna_profiles.id
 *   property_dna_profiles.listing_type + listing_id → polymorphic listing
 *   listing_type = 'seller'   → property_auctions,  user_id = property owner
 *   listing_type = 'landlord' → landlord_auctions,  user_id = property owner
 *
 * Governance:
 *   - show()    is read-only; no writes or mutations of any kind.
 *   - approve() delegates to AiMarketingReportOwnerApprovalService::approve().
 *   - reject()  delegates to AiMarketingReportOwnerApprovalService::reject().
 *   - No AI, LLM, embedding, or external API calls.
 *   - No publication, export, PDF, or email.
 *   - No schema changes.
 *   - AI services (Generator, Review, Orchestrator, Persistence, AgentRevision)
 *     are not referenced here.
 */
class AiMarketingReportOwnerApprovalController extends Controller
{
    public function __construct(
        private AiMarketingReportOwnerApprovalService $approvalService
    ) {}

    /**
     * Show the read-only approval page for the authenticated property owner.
     */
    public function show(string $report)
    {
        $record = DB::table('marketing_reports')->where('id', $report)->first();

        if (! $record) {
            abort(404);
        }

        [$profile, $listing] = $this->resolveOwnershipOrFail($record);

        Log::info('Owner: marketing_reports approval page accessed', [
            'owner_user_id' => Auth::id(),
            'report_id'     => $record->id,
        ]);

        $sections = is_string($record->sections)
            ? (json_decode($record->sections, true) ?? [])
            : [];

        $versions = DB::table('marketing_report_versions')
            ->where('marketing_report_id', $record->id)
            ->orderBy('section_key')
            ->orderByDesc('version_number')
            ->get();

        $audits = DB::table('marketing_report_audits')
            ->where('report_id', $record->id)
            ->orderByDesc('event_at')
            ->get();

        return response()
            ->view('owner.dna.marketing-report-approval', compact('record', 'sections', 'versions', 'audits'))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Approve the report (status: pending_review → seller_approved).
     */
    public function approve(string $report)
    {
        $record = DB::table('marketing_reports')->where('id', $report)->first();

        if (! $record) {
            abort(404);
        }

        $this->resolveOwnershipOrFail($record);

        try {
            $result = $this->approvalService->approve($report, (int) Auth::id());
        } catch (\Throwable $e) {
            Log::error('Owner: marketing_report approval failed', [
                'owner_user_id' => Auth::id(),
                'report_id'     => $report,
                'error'         => $e->getMessage(),
            ]);

            return redirect()
                ->route('owner.property-dna.marketing-reports.approval.show', ['report' => $report])
                ->with('error', 'Approval could not be saved due to an unexpected error. Please try again.');
        }

        if (! $result['ok']) {
            return redirect()
                ->route('owner.property-dna.marketing-reports.approval.show', ['report' => $report])
                ->with('error', $result['error'] ?? 'Approval could not be saved.');
        }

        return redirect()
            ->route('owner.property-dna.marketing-reports.approval.show', ['report' => $report])
            ->with('success', 'The marketing report has been approved.');
    }

    /**
     * Reject the report (status: pending_review → rejected).
     */
    public function reject(Request $request, string $report)
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = DB::table('marketing_reports')->where('id', $report)->first();

        if (! $record) {
            abort(404);
        }

        $this->resolveOwnershipOrFail($record);

        try {
            $result = $this->approvalService->reject(
                $report,
                (int) Auth::id(),
                $validated['rejection_reason'] ?? null
            );
        } catch (\Throwable $e) {
            Log::error('Owner: marketing_report rejection failed', [
                'owner_user_id' => Auth::id(),
                'report_id'     => $report,
                'error'         => $e->getMessage(),
            ]);

            return redirect()
                ->route('owner.property-dna.marketing-reports.approval.show', ['report' => $report])
                ->with('error', 'Rejection could not be saved due to an unexpected error. Please try again.');
        }

        if (! $result['ok']) {
            return redirect()
                ->route('owner.property-dna.marketing-reports.approval.show', ['report' => $report])
                ->with('error', $result['error'] ?? 'Rejection could not be saved.');
        }

        return redirect()
            ->route('owner.property-dna.marketing-reports.approval.show', ['report' => $report])
            ->with('success', 'The marketing report has been rejected.');
    }

    /**
     * Resolve the DNA profile and listing from the given report record,
     * then confirm the authenticated user owns the listing.
     *
     * Aborts with 403 if ownership cannot be confirmed.
     *
     * @param  object  $record  Row from marketing_reports.
     * @return array{0: object, 1: object}  [$profile, $listing]
     */
    private function resolveOwnershipOrFail(object $record): array
    {
        $profile = DB::table('property_dna_profiles')
            ->select('id', 'listing_type', 'listing_id')
            ->where('id', $record->profile_id)
            ->first();

        if (! $profile) {
            abort(403, 'Profile not found for this report.');
        }

        $ownerId = (int) Auth::id();

        if ($profile->listing_type === 'seller') {
            $listing = DB::table('property_auctions')
                ->select('id', 'user_id')
                ->where('id', $profile->listing_id)
                ->first();
        } elseif ($profile->listing_type === 'landlord') {
            $listing = DB::table('landlord_auctions')
                ->select('id', 'user_id')
                ->where('id', $profile->listing_id)
                ->first();
        } else {
            abort(403, 'This listing type cannot be safely mapped to an authorized property owner.');
        }

        if (! $listing || (int) $listing->user_id !== $ownerId) {
            abort(403, 'You are not authorized to review this marketing report.');
        }

        return [$profile, $listing];
    }
}
