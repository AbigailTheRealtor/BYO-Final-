<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Dna\AiMarketingReportAgentRevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Agent-facing Marketing Report Review page (Phase XM) + Revision write path (Phase XN).
 *
 * Authorization:
 *   - Route is inside the agentAuth middleware group — unauthenticated users and
 *     non-agent users are rejected before this controller is reached.
 *   - Inside the controller, ownership is verified by confirming the authenticated
 *     agent's user ID matches listing->user_id on the resolved listing record.
 *
 * Ownership chain:
 *   marketing_reports.profile_id → property_dna_profiles.id (FK)
 *   property_dna_profiles.listing_type + listing_id → polymorphic listing
 *   listing_type = 'seller'   → property_auctions,   user_id = agent
 *   listing_type = 'landlord' → landlord_auctions,   user_id = agent
 *
 * Deliberately excluded authorization path:
 *   - AcceptedBidSummary.agent_user_id is NOT consulted here.
 *     AcceptedBidSummary uses listing_type values of 'seller_agent' / 'landlord_agent',
 *     which differ from the 'seller' / 'landlord' values stored in property_dna_profiles.
 *     There is no safe foreign-key path, so using it would require guessing.
 *
 * Governance:
 *   - show() is read-only; no writes or mutations of any kind.
 *   - updateSection() performs only a controlled section-text revision (Phase XN).
 *   - No AI, LLM, embedding, or external API calls.
 *   - No schema changes.
 *   - AI services (Generator, Review, Orchestrator, Persistence) are not referenced.
 *   - Output is not public, not client-facing, not published.
 *   - Uses DB::table() throughout (not Eloquent) per the PostgreSQL gate resolver pattern.
 */
class AiMarketingReportAgentController extends Controller
{
    /** Section keys an agent may submit revisions for. */
    private const EDITABLE_SECTIONS = [
        'property_feature_narrative',
        'transaction_terms_summary',
        'marketing_asset_statement',
        'listing_preparation_summary',
    ];

    public function __construct(
        private AiMarketingReportAgentRevisionService $revisionService
    ) {}

    public function show(string $report)
    {
        $record = DB::table('marketing_reports')->where('id', $report)->first();

        if (! $record) {
            abort(404);
        }

        $profile = DB::table('property_dna_profiles')
            ->select('id', 'listing_type', 'listing_id')
            ->where('id', $record->profile_id)
            ->first();

        if (! $profile) {
            abort(403, 'Profile not found for this report.');
        }

        $agentId = Auth::id();

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
            abort(403, 'This listing type cannot be safely mapped to an authorized agent.');
        }

        if (! $listing || (int) $listing->user_id !== (int) $agentId) {
            abort(403, 'You are not authorized to view this marketing report.');
        }

        Log::info('Agent: marketing_reports record accessed', [
            'agent_user_id' => $agentId,
            'report_id'     => $record->id,
        ]);

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
            ->view('agent.dna.marketing-report-show', compact('record', 'versions', 'audits'))
            ->header('Cache-Control', 'no-store');
    }

    public function updateSection(Request $request, string $report, string $section)
    {
        $validated = $request->validate([
            'draft_text' => ['required', 'string', 'max:10000'],
        ]);

        if (! in_array($section, self::EDITABLE_SECTIONS, true)) {
            return redirect()
                ->route('agent.property-dna.marketing-reports.show', ['report' => $report])
                ->with('error', $section === 'missing_information_note'
                    ? 'missing_information_note is read-only and cannot be revised.'
                    : "Section '{$section}' is not a valid editable section.");
        }

        $record = DB::table('marketing_reports')->where('id', $report)->first();

        if (! $record) {
            abort(404);
        }

        $profile = DB::table('property_dna_profiles')
            ->select('id', 'listing_type', 'listing_id')
            ->where('id', $record->profile_id)
            ->first();

        if (! $profile) {
            abort(403, 'Profile not found for this report.');
        }

        $agentId = Auth::id();

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
            abort(403, 'This listing type cannot be safely mapped to an authorized agent.');
        }

        if (! $listing || (int) $listing->user_id !== (int) $agentId) {
            abort(403, 'You are not authorized to revise this marketing report.');
        }

        try {
            $result = $this->revisionService->revise($report, $section, $validated['draft_text'], (int) $agentId);
        } catch (\Throwable $e) {
            Log::error('Agent: marketing_report revision failed', [
                'agent_user_id' => $agentId,
                'report_id'     => $report,
                'section_key'   => $section,
                'error'         => $e->getMessage(),
            ]);

            return redirect()
                ->route('agent.property-dna.marketing-reports.show', ['report' => $report])
                ->with('error', 'Revision could not be saved due to an unexpected error. Please try again.');
        }

        if (! $result['ok']) {
            return redirect()
                ->route('agent.property-dna.marketing-reports.show', ['report' => $report])
                ->with('error', $result['error'] ?? 'Revision could not be saved.');
        }

        return redirect()
            ->route('agent.property-dna.marketing-reports.show', ['report' => $report])
            ->with('success', 'Revision saved for section "' . $section . '" (version ' . $result['version_id'] . ').');
    }
}
