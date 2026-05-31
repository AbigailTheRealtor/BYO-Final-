<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Agent-facing read-only Marketing Report Review page (Phase XM).
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
 *   - No writes or mutations of any kind.
 *   - No AI, LLM, embedding, or external API calls.
 *   - No schema changes.
 *   - AI services (Generator, Review, Orchestrator, Persistence) are not referenced.
 *   - Output is not public, not client-facing, not published.
 *   - Uses DB::table() throughout (not Eloquent) per the PostgreSQL gate resolver pattern.
 */
class AiMarketingReportAgentController extends Controller
{
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
}
