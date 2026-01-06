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

        try {
            DB::beginTransaction();
            
            $pab->accepted = "accepted";
            $pab->accepted_date = date('Y-m-d H:i:s');
            $pab->save();

            $pa->is_sold = true;
            $pa->sold_date = date('Y-m-d H:i:s');
            $pa->save();
            
            TenantAgentAuctionBid::where('tenant_agent_auction_id', $pa->id)
                ->where('id', '!=', $pab->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            $ua = new UserAgent();
            $ua->user_id = Auth::user()->id;
            $ua->agent_id = $pab->user_id;
            $ua->type = 'tenant';
            $ua->save();
            
            DB::commit();
            
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
            
            try {
                $agent = User::find($pab->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($pab, $pa, $summaryId, 'tenant_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid accepted notification', [
                    'bid_id' => $pab->id,
                    'agent_id' => $pab->user_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return redirect()->back()->with('success', 'Bid Accepted successfully!');
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
            
            TenantAgentAuctionBid::where('tenant_agent_auction_id', $pa->id)
                ->where('id', '!=', $originalBid->id)
                ->where('accepted', '!=', 'accepted')
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
            
            try {
                $notifyUserId = $isListingOwner ? $originalBid->user_id : $pa->user_id;
                $userToNotify = User::find($notifyUserId);
                if ($userToNotify) {
                    $userToNotify->notify(new BidAcceptedNotification($originalBid, $pa, $summaryId, 'tenant_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter bid accepted notification', [
                    'bid_id' => $originalBid->id,
                    'counter_id' => $counterBid->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return redirect()->back()->with('success', 'Counter Bid Accepted successfully!');
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
        
        if ($auction->user_id !== Auth::id()) {
            return redirect()->back()->with('error', 'Only the listing owner can counter bids.');
        }
        
        $latestCounter = TenantCounterBidding::where('tenant_agent_auction_bid_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        return view('hire_tenant_agent.counter_bid', [
            'auction' => $auction,
            'bid' => $bid,
            'latestCounter' => $latestCounter,
        ]);
    }

    public function view_counter_terms($bid_id)
    {
        $bid = TenantAgentAuctionBid::with(['meta', 'auction', 'auction.user'])->find($bid_id);
        
        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }
        
        // Only the agent who made the bid can view counter terms for their bid
        if ($bid->user_id !== Auth::id()) {
            abort(403, 'You can only view counter terms for your own bids.');
        }
        
        $auction = TenantAgentAuction::find($bid->tenant_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }
        
        // Get the latest counter terms from the Tenant
        $counterTerms = TenantCounterBidding::with('meta')
            ->where('tenant_agent_auction_bid_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        // Also get any TenantCounterTerm (different model for tenant-initiated counters)
        $tenantCounter = \App\Models\TenantCounterTerm::with('meta')
            ->where('tenant_agent_auction_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        return view('hire_tenant_agent.view_counter_terms', [
            'bid' => $bid,
            'auction' => $auction,
            'counterTerms' => $counterTerms,
            'tenantCounter' => $tenantCounter,
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
