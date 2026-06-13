<?php

namespace App\Http\Controllers;

use App\Models\AcceptedBidSummary;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterBidding;
use App\Models\User;
use App\Models\UserAgent;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use App\Notifications\CounterBidSubmittedNotification;
use App\Notifications\TenantAgentHiredNotification;
use App\Services\AcceptedBidSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantAgentAuctionBidController extends Controller
{
    public function add_bid($id, Request $request)
    {
        $page_data['auction'] = $auction = TenantAgentAuction::find($id);


        $page_data['title'] = "{$auction->title}";
        return view('hire_tenant_agent.add-bid', $page_data);
    }

    public function save_bid(Request $request)
    {
        // dd($request->all());

        // Backend guard: reject bid if listing is not Active
        $_listingGuard = TenantAgentAuction::find($request->auction_id);
        if (!$_listingGuard) {
            return redirect()->back()->with('error', 'Listing not found.');
        }
        if (in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])) {
            return redirect()->back()->with('error', 'This listing is not currently accepting new bids.');
        }

        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
        $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
        $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];
        try {
            DB::beginTransaction();
            $bid = new TenantAgentAuctionBid();
            $bid->user_id = Auth::user()->id;
            $bid->tenant_agent_auction_id = $request->auction_id;
            $bid->save();
            $bid->saveMeta('name', $request->first_name . '  ' . $request->last_name);
            $bid->saveMeta('phone', $request->phone);
            $bid->saveMeta('email', $request->email);
            $bid->saveMeta('finder_fee', $request->finder_fee);
            $bid->saveMeta('custom_finder_fee', $request->custom_finder_fee);
            $bid->saveMeta('custom_finder_lease', $request->custom_finder_lease);
            $bid->saveMeta('cancel_agreement', $request->cancel_agreement);
            $bid->saveMeta('custom_cancels', $request->custom_cancels);
            $bid->saveMeta('custom_cancel_lease', $request->custom_cancel_lease);
            $bid->saveMeta('video_url', $request->video_url);
            $bid->saveMeta('agent_license', $request->agent_license);
            $bid->saveMeta('mls_id', $request->mls_id);
            $bid->saveMeta('brokerage', $request->brokerage);
            $bid->saveMeta('license_no', $request->license_no);
            $bid->saveMeta('agent_fee', $request->agent_fee);
            $bid->saveMeta('reviews_link1', $request->reviews_link1);
            $bid->saveMeta('reviews_link2', $request->reviews_link2);
            $bid->saveMeta('website_link', json_encode($request->website_link));
            $bid->saveMeta('reviews_link', json_encode($request->reviews_link));
            $bid->saveMeta('socialType', json_encode($request->socialType));
            $bid->saveMeta('social_link', json_encode($request->social_link));
            $bid->saveMeta('bio', $request->bio);
            $bid->saveMeta('listing_terms', $request->listing_terms);
            $bid->saveMeta('custom_listing_terms', $request->custom_listing_terms);
            $bid->saveMeta('services', json_encode($request->services));
            $bid->saveMeta('other_services', $request->other_services);
            $bid->saveMeta('why_hire_you', $request->why_hire_you);
            $bid->saveMeta('what_sets_you_apart', $request->what_sets_you_apart);
            $bid->saveMeta('marketing_plan', $request->marketing_plan);

            if ($request->hasFile('video')) {
                $file = $request->video;
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedVideos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/images'), $fileName);
                    $bid->saveMeta('video', 'auction/images/' . $fileName);
                }
            }
            if ($request->hasFile('card')) {
                $file = $request->card;
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/images'), $fileName);
                    $bid->saveMeta('card', 'auction/images/' . $fileName);
                }
            }
            if ($request->hasFile('promo')) {
                $files = $request->file('promo');
                foreach ($files as $file) {
                    $extension = $file->getClientOriginalExtension();
                    $check = in_array($extension, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $fileName = $uuid . '.' . $extension;
                        $file->storeAs('auction/images', $fileName, 'public');
                        $bid->saveMeta('promo', 'auction/images/' . $fileName);
                    }
                }
            }

            DB::commit();
            $route = route('tenant.agent.view.auction.view', $request->auction_id);
            return redirect()->to($route)->with('success', 'Bid added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add bid.');
        }
    }

    public function accept_bid(Request $request)
    {
        $pab = TenantAgentAuctionBid::whereId($request->bid_id)->first();
        if (!$pab) {
            return redirect()->back()->with('error', 'Bid not found.');
        }
        
        $pa = TenantAgentAuction::find($pab->tenant_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }
        
        if ($request->has('auction_id') && (int)$request->auction_id !== (int)$pa->id) {
            abort(403, 'Bid does not belong to this auction.');
        }
        
        if ($pa->user_id !== Auth::id()) {
            abort(403, 'Only the listing owner can accept bids.');
        }
        
        if ($pab->accepted === 'accepted' || $pab->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This bid has already been ' . $pab->accepted . '.');
        }

        // Expiry guard: prevent accept/reject on expired listings via direct POST
        $expiryDate = $pa->get->expiration_date ?? null;
        if ($expiryDate && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($expiryDate))) {
            return redirect()->back()->with('error', 'This listing is expired and can no longer accept or reject bids.');
        }

        try {
            DB::beginTransaction();
            
            $pab->accepted = "accepted";
            $pab->accepted_date = date('Y-m-d H:i:s');
            $pab->save();

            $pa->is_sold = true;
            $pa->sold_date = date('Y-m-d H:i:s');
            $pa->save();
            $pa->saveMeta('listing_status', 'Hired Agent');
            
            TenantAgentAuctionBid::where('tenant_agent_auction_id', $pa->id)
                ->where('id', '!=', $pab->id)
                ->where(function ($q) {
                    $q->whereNull('accepted')->orWhere('accepted', '!=', 'accepted');
                })
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            $ua = new UserAgent();
            $ua->user_id = Auth::user()->id;
            $ua->agent_id = $pab->user_id;
            $ua->type = 'tenant';
            $ua->save();
            
            DB::commit();

            // Record recommendation attribution for bid_accepted.
            try {
                $recCtx = \App\Services\BidAnalyticsService::getRecContext('tenant_agent', (int) $pab->id);
                \App\Services\BidAnalyticsService::recordRecommendationInteraction(
                    'bid_accepted', 'tenant',
                    $recCtx['from_recommendation'], $recCtx['surface'],
                    'tenant_agent', (int) $pab->id,
                    null, Auth::id()
                );
            } catch (\Throwable $e) {
                // Analytics failure must not disrupt bid acceptance
            }
            
            $summaryId = null;
            try {
                $summaryService = new AcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($pab, null);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate accepted bid summary after bid acceptance', [
                    'bid_id' => $pab->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Notify the Agent that their bid was accepted
            try {
                $agent = User::find($pab->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($pab, $pa, $summaryId, 'tenant_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid accepted notification to agent', [
                    'bid_id'   => $pab->id,
                    'agent_id' => $pab->user_id,
                    'error'    => $e->getMessage(),
                ]);
            }

            // Notify the Tenant (listing owner) that the agent was hired
            try {
                $tenant = User::find($pa->user_id);
                if ($tenant) {
                    $tenant->notify(new TenantAgentHiredNotification($pab, $pa, $summaryId, 'tenant_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send agent hired notification to tenant', [
                    'bid_id'    => $pab->id,
                    'tenant_id' => $pa->user_id,
                    'error'     => $e->getMessage(),
                ]);
            }

            // Redirect Tenant straight to the Accepted Bid Summary
            if ($summaryId) {
                return redirect()->route('accepted-bid-summary.view', $summaryId)
                    ->with('success', 'Agent hired successfully! Your Accepted Bid Summary is ready to review and sign.');
            }

            // Fallback: redirect via bid lookup (graceful if summary generation failed)
            return redirect()->route('accepted-bid-summary.by-bid', $pab->id)
                ->with('success', 'Agent hired successfully! Your Accepted Bid Summary is ready.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error accepting bid: ' . $e->getMessage());
        }
    }
    
    public function reject_bid(Request $request)
    {
        $pab = TenantAgentAuctionBid::whereId($request->bid_id)->first();
        if (!$pab) {
            return redirect()->back()->with('error', 'Bid not found.');
        }
        
        $pa = TenantAgentAuction::find($pab->tenant_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }
        
        if ($request->has('auction_id') && (int)$request->auction_id !== (int)$pa->id) {
            abort(403, 'Bid does not belong to this auction.');
        }
        
        if ($pa->user_id !== Auth::id()) {
            abort(403, 'Only the listing owner can reject bids.');
        }
        
        if ($pab->accepted === 'accepted' || $pab->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This bid has already been ' . $pab->accepted . '.');
        }

        // Expiry guard: prevent accept/reject on expired listings via direct POST
        $expiryDate = $pa->get->expiration_date ?? null;
        if ($expiryDate && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($expiryDate))) {
            return redirect()->back()->with('error', 'This listing is expired and can no longer accept or reject bids.');
        }
        
        $pab->accepted = "rejected";
        $pab->accepted_date = date('Y-m-d H:i:s');

        if ($pab->save()) {
            // Send notification to agent that their bid was rejected
            try {
                $agent = User::find($pab->user_id);
                if ($agent) {
                    $agent->notify(new BidRejectedNotification($pab, $pa));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid rejected notification', [
                    'bid_id' => $pab->id,
                    'agent_id' => $pab->user_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return redirect()->back()->with('success', 'Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid rejection!');
        }
    }
    public function counter_bid(Request $request)
    {

        $pab = TenantAgentAuction::whereId($request->auction_id)->first();
        $bid_id = $request->bid_id;
        $parent_counter_id = $request->counter_bid_id ? $request->counter_bid_id : null;

        return view('hire_tenant_agent.counter-bid', compact('bid_id', 'pab', 'parent_counter_id'));
    }

    public function accept_counter_bid(Request $request)
    {
        $counterBid = TenantCounterBidding::whereId($request->counter_bid_id)->first();
        if (!$counterBid) {
            return redirect()->back()->with('error', 'Counter bid not found.');
        }
        
        $originalBid = TenantAgentAuctionBid::find($counterBid->tenant_agent_auction_bid_id);
        if (!$originalBid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }
        
        $pa = TenantAgentAuction::find($originalBid->tenant_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }
        
        if ($request->has('auction_id') && (int)$request->auction_id !== (int)$pa->id) {
            abort(403, 'Counter bid does not belong to this auction.');
        }
        
        $isListingOwner = $pa->user_id === Auth::id();
        $isOriginalBidOwner = $originalBid->user_id === Auth::id();
        
        if (!$isListingOwner && !$isOriginalBidOwner) {
            abort(403, 'You are not authorized to accept this counter bid.');
        }
        
        if ($counterBid->accepted === 'accepted' || $counterBid->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This counter bid has already been ' . $counterBid->accepted . '.');
        }

        try {
            DB::beginTransaction();
            
            $counterBid->accepted = "accepted";
            $counterBid->accepted_date = date('Y-m-d H:i:s');
            $counterBid->save();
            
            $originalBid->accepted = "accepted";
            $originalBid->accepted_date = date('Y-m-d H:i:s');
            $originalBid->save();

            $pa->is_sold = true;
            $pa->sold_date = date('Y-m-d H:i:s');
            $pa->save();
            $pa->saveMeta('listing_status', 'Hired Agent');
            
            TenantAgentAuctionBid::where('tenant_agent_auction_id', $pa->id)
                ->where('id', '!=', $originalBid->id)
                ->where(function ($q) {
                    $q->whereNull('accepted')->orWhere('accepted', '!=', 'accepted');
                })
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            $ua = new UserAgent();
            $ua->user_id = Auth::user()->id;
            $ua->agent_id = $originalBid->user_id;
            $ua->type = 'tenant';
            $ua->save();
            
            DB::commit();
            
            $summaryId = null;
            try {
                $summaryService = new AcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($originalBid, $counterBid);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate accepted bid summary after counter bid acceptance', [
                    'bid_id' => $originalBid->id,
                    'counter_id' => $counterBid->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Notify the Agent that the counter bid was accepted
            try {
                $agent = User::find($originalBid->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($originalBid, $pa, $summaryId, 'tenant_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter bid accepted notification to agent', [
                    'bid_id'    => $originalBid->id,
                    'counter_id'=> $counterBid->id,
                    'error'     => $e->getMessage(),
                ]);
            }

            // Notify the Tenant (listing owner) that the agent was hired via counter
            try {
                $tenant = User::find($pa->user_id);
                if ($tenant) {
                    $tenant->notify(new TenantAgentHiredNotification($originalBid, $pa, $summaryId, 'tenant_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send agent hired notification to tenant after counter accept', [
                    'bid_id'    => $originalBid->id,
                    'counter_id'=> $counterBid->id,
                    'tenant_id' => $pa->user_id,
                    'error'     => $e->getMessage(),
                ]);
            }

            // Redirect the accepting party straight to the Accepted Bid Summary
            if ($summaryId) {
                return redirect()->route('accepted-bid-summary.view', $summaryId)
                    ->with('success', 'Counter Bid Accepted! Your Accepted Bid Summary is ready to review and sign.');
            }

            return redirect()->route('accepted-bid-summary.by-bid', $originalBid->id)
                ->with('success', 'Counter Bid Accepted! Your Accepted Bid Summary is ready.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error accepting counter bid: ' . $e->getMessage());
        }
    }
    
    public function reject_counter_bid(Request $request)
    {
        $counterBid = TenantCounterBidding::whereId($request->counter_bid_id)->first();
        if (!$counterBid) {
            return redirect()->back()->with('error', 'Counter bid not found.');
        }
        
        $originalBid = TenantAgentAuctionBid::find($counterBid->tenant_agent_auction_bid_id);
        if (!$originalBid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }
        
        $pa = TenantAgentAuction::find($originalBid->tenant_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }
        
        $isListingOwner = $pa->user_id === Auth::id();
        $isOriginalBidOwner = $originalBid->user_id === Auth::id();
        
        if (!$isListingOwner && !$isOriginalBidOwner) {
            abort(403, 'You are not authorized to reject this counter bid.');
        }
        
        if ($counterBid->accepted === 'accepted' || $counterBid->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This counter bid has already been ' . $counterBid->accepted . '.');
        }
        
        $counterBid->accepted = "rejected";
        $counterBid->accepted_date = date('Y-m-d H:i:s');

        if ($counterBid->save()) {
            return redirect()->back()->with('success', 'Counter Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in counter bid rejection!');
        }
    }

    public function add_counter_bid($id, $bid_id)
    {
        $auction = TenantAgentAuction::with('user', 'meta', 'bids', 'bids.user')->find($id);
        if (!$auction) {
            abort(404, 'Auction not found');
        }
        
        $bid = TenantAgentAuctionBid::with('meta', 'user', 'counterBids')->find($bid_id);
        if (!$bid) {
            abort(404, 'Bid not found');
        }
        
        $userId = Auth::id();
        $isListingOwner = ($auction->user_id === $userId);
        $isBidOwner = ($bid->user_id === $userId);
        
        if (!$isListingOwner && !$isBidOwner) {
            return redirect()->back()->with('error', 'You are not authorized to counter this bid.');
        }
        
        // Get the latest counter from either party
        $latestAgentCounter = TenantCounterBidding::where('tenant_agent_auction_bid_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $latestTenantCounter = \App\Models\TenantCounterTerm::where('tenant_agent_auction_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        // Determine counterparty role for view logic
        $counterpartyRole = $isBidOwner ? 'agent' : 'tenant';
        
        // Get the parent counter ID for chaining (if any)
        $parentCounterId = null;
        if ($latestTenantCounter) {
            $parentCounterId = $latestTenantCounter->id;
        } elseif ($latestAgentCounter) {
            $parentCounterId = $latestAgentCounter->id;
        }
        
        return view('hire_tenant_agent.counter-bid', [
            'pab' => $auction,
            'bid_id' => $bid_id,
            'parent_counter_id' => $parentCounterId,
            'auction' => $auction,
            'latestTenantCounter' => $latestTenantCounter,
            'counterpartyRole' => $counterpartyRole,
            'isListingOwner' => $isListingOwner,
            'isBidOwner' => $isBidOwner,
        ]);
    }

    public function view_counter_terms($bid_id)
    {
        $bid = TenantAgentAuctionBid::with(['meta', 'auction', 'auction.user', 'user'])->find($bid_id);
        
        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }
        
        $auction = TenantAgentAuction::with(['user', 'meta'])->find($bid->tenant_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }
        
        $userId = Auth::id();
        $isAgent = ($bid->user_id === $userId);
        $isTenant = ($auction->user_id === $userId);
        
        // Only bid owner (agent) or listing owner (tenant) can view counter terms
        if (!$isAgent && !$isTenant) {
            abort(403, 'You do not have permission to view these counter terms.');
        }
        
        // Get agent's counter to tenant (TenantCounterBidding)
        $agentCounter = TenantCounterBidding::with('meta')
            ->where('tenant_agent_auction_bid_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        // Get tenant's counter to agent (TenantCounterTerm)
        $tenantCounter = \App\Models\TenantCounterTerm::with('meta')
            ->where('tenant_agent_auction_id', $bid->tenant_agent_auction_id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        // Determine viewer role
        $viewerRole = $isAgent ? 'agent' : 'tenant';
        
        return view('hire_tenant_agent.view_counter_terms', [
            'bid'            => $bid,
            'auction'        => $auction,
            'agentCounter'   => $agentCounter,
            'tenantCounter'  => $tenantCounter,
            'viewerRole'     => $viewerRole,
            'isAgent'        => $isAgent,
            'isTenant'       => $isTenant,
            'isOfferListing' => $auction->info('workflow_type') === 'offer_listing',
        ]);
    }

    public function withdraw_bid(Request $request)
    {
        $bid = TenantAgentAuctionBid::find($request->bid_id);
        
        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }
        
        if ($bid->user_id !== Auth::id()) {
            return redirect()->back()->with('error', 'You can only withdraw your own bid.');
        }
        
        if ($bid->accepted === 'accepted') {
            return redirect()->back()->with('error', 'Cannot withdraw an accepted bid.');
        }
        
        if ($bid->accepted === 'rejected') {
            return redirect()->back()->with('error', 'Cannot withdraw a rejected bid.');
        }
        
        $auction = TenantAgentAuction::find($bid->tenant_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }
        
        if ($auction->is_sold) {
            return redirect()->back()->with('error', 'Cannot withdraw a bid on a sold listing.');
        }
        
        $endDate = strtotime($auction->end_date . ' ' . ($auction->end_time ?? '23:59:59'));
        if (time() > $endDate) {
            return redirect()->back()->with('error', 'Cannot withdraw a bid after the auction has ended.');
        }
        
        TenantCounterBidding::where('tenant_agent_auction_bid_id', $bid->id)->delete();
        $bid->meta()->delete();
        $bid->delete();
        
        return redirect()->back()->with('success', 'Your bid has been withdrawn successfully.');
    }
}
