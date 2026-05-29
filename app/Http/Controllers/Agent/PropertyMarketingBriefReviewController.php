<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\PropertyMarketingBriefService;
use Illuminate\Support\Facades\Auth;

/**
 * Agent-facing read-only Marketing Brief Review page (Phase T).
 *
 * Authorization:
 *   - Route is inside the agentAuth middleware group — unauthenticated users and
 *     non-agent users are rejected before this controller is reached.
 *   - Inside the controller, ownership is verified by confirming the authenticated
 *     agent's user ID matches listing->user_id on the resolved listing model.
 *
 * Why listing->user_id is the agent (not the seller/landlord client):
 *
 *   PropertyAuction (listing_type = 'seller'):
 *     Creation routes /add-listing and /property/listing/step/* sit at lines 722–737
 *     of routes/web.php. They are inside the agentAuth middleware group (opened at
 *     line 639). The adjacent comment at line 721 reads "// Seller's agent routes".
 *     PropertyAuctionController::store() sets $auction->user_id = Auth::user()->id,
 *     where Auth::user() is the authenticated agent.
 *
 *   LandlordAuction (listing_type = 'landlord'):
 *     Creation routes /landlord/auction/add and /landlord/auction/store sit at lines
 *     655–656 of routes/web.php. They are inside agentAuth + the agent. name group.
 *     LandlordAuctionController::store() sets $landlord_auction->user_id = Auth::user()->id,
 *     where Auth::user() is the authenticated agent.
 *
 *   HireAgentDirectController is not relevant here: its LISTING_MODELS maps 'seller'
 *   to SellerAgentAuction and 'landlord' to LandlordAgentAuction — different models
 *   from PropertyAuction/LandlordAuction — so that flow does not affect these records.
 *
 * Deliberately excluded authorization path:
 *   - AcceptedBidSummary.agent_user_id is NOT consulted here. AcceptedBidSummary
 *     uses listing_type values of 'seller_agent' / 'landlord_agent', which differ
 *     from the 'seller' / 'landlord' values stored in PropertyDnaProfile. There is
 *     no safe foreign-key path from a 'seller' PropertyDnaProfile to an
 *     AcceptedBidSummary record, so using it would require guessing.
 *
 * Governance:
 *   - No writes or mutations of any kind.
 *   - No AI, LLM, embedding, or external API calls.
 *   - No schema changes.
 *   - PropertyMarketingBriefService and PropertyMarketingContextService are not modified.
 *   - Output is not public, not client-facing, and not accessible without agentAuth.
 */
class PropertyMarketingBriefReviewController extends Controller
{
    public function __invoke(PropertyDnaProfile $profile, PropertyMarketingBriefService $briefService)
    {
        $agentId = Auth::id();

        if ($profile->listing_type === 'seller') {
            $listing = PropertyAuction::select('id', 'user_id')->find($profile->listing_id);
        } elseif ($profile->listing_type === 'landlord') {
            $listing = LandlordAuction::select('id', 'user_id')->find($profile->listing_id);
        } else {
            abort(403, 'This listing type cannot be safely mapped to an authorized agent.');
        }

        if (!$listing || (int) $listing->user_id !== (int) $agentId) {
            abort(403, 'You are not authorized to view this marketing brief.');
        }

        $brief = $briefService->build($profile);

        return response()
            ->view('agent.dna.marketing-brief-review', compact('profile', 'brief'))
            ->header('Cache-Control', 'no-store');
    }
}
