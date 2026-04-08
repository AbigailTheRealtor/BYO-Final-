<?php

namespace App\Http\Controllers;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\BuyerCounterBidding;
use App\Models\BuyerCounterTerm;
use App\Models\User;
use App\Models\UserAgent;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use App\Notifications\BidSubmittedNotification;
use App\Notifications\BuyerAgentHiredNotification;
use App\Services\BuyerAcceptedBidSummaryService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BuyerAgentAuctionBidController extends Controller
{

    public function add_bid($id, Request $request)
    {
        $page_data['auction'] = $auction = BuyerAgentAuction::find($id);
        $page_data['title'] = "Add Bid - {$auction->title}";
        return view('buyer_agent_auction_add_bid', $page_data);
    }

    public function saveBABid(Request $request)
    {
        // Backend guard: reject bid if listing is not Active
        $_listingGuard = BuyerAgentAuction::find($request->auction_id);
        if (!$_listingGuard) {
            return redirect()->back()->with('error', 'Listing not found.');
        }
        if (in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])) {
            return redirect()->back()->with('error', 'This listing is not currently accepting new bids.');
        }

        try {
            DB::beginTransaction();
            $bid = new BuyerAgentAuctionBid();
            $bid->user_id = Auth::user()->id;
            $bid->buyer_agent_auction_id = $request->auction_id;
            $bid->save();
            $bid->saveMeta('maxBudget', $request->maxBudget);
            $bid->saveMeta('terms_of_contract', $request->terms_of_contract);
            $bid->saveMeta('custom_contract_terms', $request->custom_contract_terms);
            $bid->saveMeta('has_buyer_credit_at_closing', $request->has_buyer_credit_at_closing);
            $bid->saveMeta('buyer_credit_at_closing', $request->buyer_credit_at_closing);
            $bid->saveMeta('buyer_credit_at_closing', $request->buyer_credit_at_closing);
            $bid->saveMeta('has_charges', $request->has_charges);
            $bid->saveMeta('fee_being_charged', $request->fee_being_charged);
            $bid->saveMeta('fee_for', $request->fee_for);
            $bid->saveMeta('hasagentCancellationFee', $request->hasagentCancellationFee);
            $bid->saveMeta('agentCancellationFee', $request->agentCancellationFee);
            $bid->saveMeta('services', json_encode($request->services));
            $bid->saveMeta('other_services', $request->other_services);
            $bid->saveMeta('bio', $request->bio);
            $bid->saveMeta('why_hire_you', $request->why_hire_you);
            $bid->saveMeta('what_sets_you_apart', $request->what_sets_you_apart);
            $bid->saveMeta('marketing_plan', $request->marketing_plan);
            $bid->saveMeta('website_link', json_encode($request->website_link));
            $bid->saveMeta('reviews_link', json_encode($request->reviews_link));
            $bid->saveMeta('social_link', json_encode($request->social_link));
            $bid->saveMeta('agent_license_year', $request->agent_license_year);
            $bid->saveMeta('virtual_buyer_presentation_link', $request->virtual_buyer_presentation_link);
            $bid->saveMeta('first_name', $request->first_name);
            $bid->saveMeta('last_name', $request->last_name);
            $bid->saveMeta('phone', $request->phone);
            $bid->saveMeta('email', $request->email);
            $bid->saveMeta('brokerage', $request->brokerage);
            $bid->saveMeta('license_no', $request->license_no);
            $bid->saveMeta('mls_id', $request->mls_id);
            $bid->saveMeta('socialType', json_encode($request->socialType));
            $bid->saveMeta('video_url', $request->video_url);

            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps'];
            $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];

            if ($request->hasFile('virtual_buyer_presentation')) {
                $video = $request->virtual_buyer_presentation;
                $extension = $video->getClientOriginalExtension();
                if (in_array($extension, $allowedVideos)) {
                    $uuid = (string) Str::uuid();
                    $videoName = $uuid . '.' . $extension;
                    $video->move(public_path('auction/videos'), $videoName);
                    $bid->saveMeta('virtual_buyer_presentation', $videoName);
                }
            }
            if ($request->hasFile('audio')) {
                $audio = $request->audio;
                $extension = $audio->getClientOriginalExtension();
                if (in_array($extension, $allowedAudios)) {
                    $uuid = (string) Str::uuid();
                    $audioName = $uuid . '.' . $extension;
                    $audio->move(public_path('auction/audios'), $audioName);
                    $bid->saveMeta('audio', $audioName);
                }
            }
            if ($request->hasFile('note')) {
                $uploadedFileNames = [];
                $files = $request->file('note');
                foreach ($files as $file) {
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, $allowedFiles)) {
                        $uuid = (string) Str::uuid();
                        $fileName = $uuid . '.' . $extension;
                        $file->move(public_path('auction/files'), $fileName);
                        $uploadedFileNames[] = $fileName;
                    }
                }
                $bid->saveMeta('note', json_encode($uploadedFileNames));
            }
            if ($request->hasFile('card')) {
                $photo = $request->file('card');
                $extension = $photo->getClientOriginalExtension();
                if (in_array($extension, $allowedPhotos)) {
                    $uuid = (string) Str::uuid();
                    $photoName = $uuid . '.' . $extension;
                    $photo->move(public_path('auction/bid/cards'), $photoName);
                    $bid->saveMeta('card', 'auction/bid/cards/' . $photoName);
                }
            }

            // Increment 1 day by adding one bid
            $buyer_auction = BuyerAgentAuction::with('meta')->find($request->auction_id);
            $date = new DateTime($buyer_auction->get->expiration_date);
            $date->modify('+1 day');
            $date->setTime(0, 0, 0);
            BuyerAgentAuctionMeta::where('meta_key', 'expiration_date')
                ->where('buyer_agent_auction_id', $request->auction_id)
                ->update(['meta_value' => $date->format('Y-m-d H:i:s')]);

            DB::commit();

            // Notify the listing owner (buyer) that a new bid was submitted
            try {
                $listingOwner = User::find($buyer_auction->user_id);
                if ($listingOwner) {
                    $listingOwner->notify(new BidSubmittedNotification($bid, $buyer_auction, 'buyer_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid submitted notification for buyer listing', [
                    'bid_id' => $bid->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            return redirect()->to(route('buyer.view-auction', $request->auction_id))->with('success', 'Bid added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('saveBABid failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Unable to add bid.');
        }
    }

    public function counter_bid(Request $request)
    {
        $pab = BuyerAgentAuction::whereId($request->auction_id)->first();
        $bid_id = $request->bid_id;
        $parent_counter_id = $request->counter_bid_id ? $request->counter_bid_id : null;

        return view('counter-bid', compact('bid_id', 'pab', 'parent_counter_id'));
    }

    public function accept_bid(Request $request)
    {
        $bid = BuyerAgentAuctionBid::whereId($request->bid_id)->first();
        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $auction = BuyerAgentAuction::whereId($request->auction_id)->first();
        if (!$auction) {
            return redirect()->back()->with('error', 'Listing not found.');
        }

        // Authorization: only the listing owner (buyer) can accept a bid
        if ($auction->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Confirm this bid belongs to this auction
        if ($bid->buyer_agent_auction_id !== $auction->id) {
            return redirect()->back()->with('error', 'Bid does not belong to this listing.');
        }

        if ($bid->accepted === 'accepted') {
            return redirect()->back()->with('error', 'This bid has already been accepted.');
        }

        try {
            DB::beginTransaction();

            $bid->accepted = 'accepted';
            $bid->accepted_date = date('Y-m-d H:i:s');
            $bid->save();

            $auction->is_sold = true;
            $auction->sold_date = date('Y-m-d H:i:s');
            $auction->save();
            $auction->saveMeta('listing_status', 'Hired Agent');

            // Reject all other competing bids
            BuyerAgentAuctionBid::where('buyer_agent_auction_id', $auction->id)
                ->where('id', '!=', $bid->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            // Create UserAgent record — use stable party mapping (not Auth::id())
            $ua = new UserAgent();
            $ua->user_id = $auction->user_id;
            $ua->agent_id = $bid->user_id;
            $ua->type = 'buyer';
            $ua->save();

            DB::commit();

            // Generate accepted bid summary
            $summaryId = null;
            try {
                $summaryService = new BuyerAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($bid->load('meta'), null);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate buyer accepted bid summary', [
                    'bid_id' => $bid->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            // Notify the agent that their bid was accepted
            try {
                $agent = User::find($bid->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($bid, $auction, $summaryId, 'buyer_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid accepted notification for buyer listing', [
                    'bid_id' => $bid->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            // Notify the listing owner (buyer) that they have hired an agent
            try {
                $buyer = User::find($auction->user_id);
                if ($buyer) {
                    $buyer->notify(new BuyerAgentHiredNotification($bid, $auction, $summaryId, 'buyer_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send buyer hired notification', [
                    'bid_id'   => $bid->id,
                    'buyer_id' => $auction->user_id,
                    'error'    => $e->getMessage(),
                ]);
            }

            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('accept_bid failed for buyer listing', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }

    public function reject_bid(Request $request)
    {
        $bid = BuyerAgentAuctionBid::whereId($request->bid_id)->first();
        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $auction = BuyerAgentAuction::find($bid->buyer_agent_auction_id);

        // Authorization: only the listing owner (buyer) can reject a bid
        if (!$auction || $auction->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $bid->accepted = 'rejected';
        $bid->accepted_date = date('Y-m-d H:i:s');

        if ($bid->save()) {
            // Notify the agent that their bid was rejected
            try {
                $agent = User::find($bid->user_id);
                if ($agent) {
                    $agent->notify(new BidRejectedNotification($bid, $auction, 'buyer_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid rejected notification for buyer listing', [
                    'bid_id' => $bid->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            return redirect()->back()->with('success', 'Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid rejection!');
        }
    }

    public function accept_counter_bid(Request $request)
    {
        $counterBid = BuyerCounterBidding::whereId($request->counter_bid_id)->first();
        if (!$counterBid) {
            return redirect()->back()->with('error', 'Counter bid not found.');
        }

        // Find the original bid this counter belongs to
        $originalBid = BuyerAgentAuctionBid::find($counterBid->buyer_agent_auction_bid_id);
        if (!$originalBid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }

        $auction = BuyerAgentAuction::find($counterBid->buyer_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Listing not found.');
        }

        // Authorization: listing owner (buyer) OR the original bid owner (agent) can accept
        $isListingOwner   = $auction->user_id === Auth::id();
        $isOriginalBidder = $originalBid->user_id === Auth::id();

        if (!$isListingOwner && !$isOriginalBidder) {
            abort(403, 'You are not authorized to accept this counter bid.');
        }

        // Guard: already resolved
        if ($counterBid->accepted === 'accepted' || $counterBid->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This counter bid has already been ' . $counterBid->accepted . '.');
        }

        try {
            DB::beginTransaction();

            // Mark counter bid accepted
            $counterBid->accepted = 'accepted';
            $counterBid->accepted_date = date('Y-m-d H:i:s');
            $counterBid->save();

            // Mark the original bid accepted (Tenant parity)
            $originalBid->accepted = 'accepted';
            $originalBid->accepted_date = date('Y-m-d H:i:s');
            $originalBid->save();

            // Close the listing
            $auction->is_sold = true;
            $auction->sold_date = date('Y-m-d H:i:s');
            $auction->save();
            $auction->saveMeta('listing_status', 'Hired Agent');

            // Reject all other competing bids
            BuyerAgentAuctionBid::where('buyer_agent_auction_id', $auction->id)
                ->where('id', '!=', $originalBid->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            // Create UserAgent record — always use stable party mapping:
            // user_id = buyer (listing owner), agent_id = bidding agent.
            // Do NOT use Auth::id() here — the acceptor may be either party.
            $ua = new UserAgent();
            $ua->user_id = $auction->user_id;
            $ua->agent_id = $originalBid->user_id;
            $ua->type = 'buyer';
            $ua->save();

            DB::commit();

            // Generate accepted bid summary (counter version)
            $summaryId = null;
            try {
                $summaryService = new BuyerAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($originalBid->load('meta'), $counterBid->load('meta'));
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate buyer accepted bid summary after counter accept', [
                    'bid_id'     => $originalBid->id,
                    'counter_id' => $counterBid->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Notify the agent that the counter bid was accepted
            try {
                $agent = User::find($originalBid->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($originalBid, $auction, $summaryId, 'buyer_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter bid accepted notification for buyer listing', [
                    'bid_id'     => $originalBid->id,
                    'counter_id' => $counterBid->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Notify the listing owner (buyer) that they have hired an agent
            try {
                $buyer = User::find($auction->user_id);
                if ($buyer) {
                    $buyer->notify(new BuyerAgentHiredNotification($originalBid, $auction, $summaryId, 'buyer_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send buyer hired notification after counter accept', [
                    'bid_id'   => $originalBid->id,
                    'buyer_id' => $auction->user_id,
                    'error'    => $e->getMessage(),
                ]);
            }

            return redirect()->back()->with('success', 'Counter Bid Accepted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('accept_counter_bid failed for buyer listing', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Some problem in counter bid acceptance!');
        }
    }

    public function reject_counter_bid(Request $request)
    {
        $counterBid = BuyerCounterBidding::whereId($request->counter_bid_id)->first();
        if (!$counterBid) {
            return redirect()->back()->with('error', 'Counter bid not found.');
        }

        $originalBid = BuyerAgentAuctionBid::find($counterBid->buyer_agent_auction_bid_id);
        $auction = BuyerAgentAuction::find($counterBid->buyer_agent_auction_id);

        // Authorization: listing owner (buyer) OR the original bid owner (agent) can reject
        $isListingOwner   = $auction && $auction->user_id === Auth::id();
        $isOriginalBidder = $originalBid && $originalBid->user_id === Auth::id();

        if (!$isListingOwner && !$isOriginalBidder) {
            abort(403, 'You are not authorized to reject this counter bid.');
        }

        $counterBid->accepted = 'rejected';
        $counterBid->accepted_date = date('Y-m-d H:i:s');

        if ($counterBid->save()) {
            return redirect()->back()->with('success', 'Counter Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in counter bid rejection!');
        }
    }

    public function accept_buyer_counter_term(Request $request)
    {
        $buyerCounterTerm = BuyerCounterTerm::find($request->buyer_counter_term_id);
        if (!$buyerCounterTerm) {
            return redirect()->back()->with('error', 'Buyer counter term not found.');
        }

        // buyer_agent_auction_id now stores the specific bid ID (per-bid architecture).
        $originalBid = BuyerAgentAuctionBid::find($buyerCounterTerm->buyer_agent_auction_id);
        if (!$originalBid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }

        $auction = BuyerAgentAuction::find($originalBid->buyer_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Listing not found.');
        }

        // Authorization: only the bidding agent can accept the buyer's counter
        if ($originalBid->user_id !== Auth::id()) {
            abort(403, 'You are not authorized to accept this buyer counter term.');
        }

        try {
            DB::beginTransaction();

            // Mark buyer counter term accepted
            $buyerCounterTerm->accepted = 'accepted';
            $buyerCounterTerm->accepted_date = date('Y-m-d H:i:s');
            $buyerCounterTerm->save();

            // Mark the original bid accepted
            $originalBid->accepted = 'accepted';
            $originalBid->accepted_date = date('Y-m-d H:i:s');
            $originalBid->save();

            // Close the listing
            $auction->is_sold = true;
            $auction->sold_date = date('Y-m-d H:i:s');
            $auction->save();
            $auction->saveMeta('listing_status', 'Hired Agent');

            // Reject all other competing bids
            BuyerAgentAuctionBid::where('buyer_agent_auction_id', $auction->id)
                ->where('id', '!=', $originalBid->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            // Create UserAgent record
            $ua = new UserAgent();
            $ua->user_id  = $auction->user_id;
            $ua->agent_id = $originalBid->user_id;
            $ua->type     = 'buyer';
            $ua->save();

            DB::commit();

            // Generate accepted bid summary
            $summaryId = null;
            try {
                $summaryService = new BuyerAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($originalBid->load('meta'), null);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate buyer accepted bid summary after buyer counter accept', [
                    'bid_id' => $originalBid->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            // Notify the buyer that the agent accepted their counter
            try {
                $buyer = User::find($auction->user_id);
                if ($buyer) {
                    $buyer->notify(new BidAcceptedNotification($originalBid, $auction, $summaryId, 'buyer_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send buyer counter term accepted notification', [
                    'bid_id' => $originalBid->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            return redirect()->back()->with('success', 'Buyer counter terms accepted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('accept_buyer_counter_term failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Some problem accepting buyer counter terms.');
        }
    }

    public function reject_buyer_counter_term(Request $request)
    {
        $buyerCounterTerm = BuyerCounterTerm::find($request->buyer_counter_term_id);
        if (!$buyerCounterTerm) {
            return redirect()->back()->with('error', 'Buyer counter term not found.');
        }

        // buyer_agent_auction_id now stores the specific bid ID (per-bid architecture).
        $originalBid = BuyerAgentAuctionBid::find($buyerCounterTerm->buyer_agent_auction_id);
        if (!$originalBid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }

        if ($originalBid->user_id !== Auth::id()) {
            abort(403, 'You are not authorized to reject this buyer counter term.');
        }

        $buyerCounterTerm->accepted = 'rejected';
        $buyerCounterTerm->accepted_date = date('Y-m-d H:i:s');

        if ($buyerCounterTerm->save()) {
            return redirect()->back()->with('success', 'Buyer counter terms rejected.');
        } else {
            return redirect()->back()->with('error', 'Some problem rejecting buyer counter terms.');
        }
    }

    public function view_counter_terms($bid_id)
    {
        $bid = BuyerAgentAuctionBid::with(['meta', 'auction', 'user'])->find($bid_id);

        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $auction = BuyerAgentAuction::with(['user', 'meta'])->find($bid->buyer_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Listing not found.');
        }

        $userId = Auth::id();
        $isAgent = ($bid->user_id === $userId);
        $isBuyer = ($auction->user_id === $userId);

        if (!$isAgent && !$isBuyer) {
            abort(403, 'You do not have permission to view these counter terms.');
        }

        $agentCounter = BuyerCounterBidding::with('meta')
            ->where('buyer_agent_auction_bid_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();

        $buyerCounter = BuyerCounterTerm::with('meta')
            ->where('buyer_agent_auction_id', $bid->buyer_agent_auction_id)
            ->where('user_id', $auction->user_id)
            ->orderBy('created_at', 'desc')
            ->first();

        $viewerRole = $isAgent ? 'agent' : 'buyer';

        return view('hire_buyer_agent.view_counter_terms', [
            'bid'          => $bid,
            'auction'      => $auction,
            'agentCounter' => $agentCounter,
            'buyerCounter' => $buyerCounter,
            'viewerRole'   => $viewerRole,
            'isAgent'      => $isAgent,
            'isBuyer'      => $isBuyer,
        ]);
    }

}
