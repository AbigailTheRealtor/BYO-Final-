<?php

namespace App\Http\Controllers;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordCounterTerm;
use App\Models\UserAgent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use App\Notifications\LandlordAgentHiredNotification;
use App\Services\LandlordAcceptedBidSummaryService;

class LandlordAgentAuctionBidController extends Controller
{
    public function add_bid($id, Request $request)
    {
        $auction = LandlordAgentAuction::find($id);
        $title = "Add Bid to Hire a Listing Agent Listing";
        return view('hire_landlord_agent.add-bid', compact('title', 'auction'));
    }


    public function save_bid(Request $request)
    {
        // Backend guard: reject bid if listing is not Active
        $_listingGuard = LandlordAgentAuction::find($request->auction_id);
        if (!$_listingGuard) {
            return redirect()->back()->with('error', 'Listing not found.');
        }
        if (in_array($_listingGuard->status, ['Hired Agent'])) {
            return redirect()->back()->with('error', 'This listing is not currently accepting new bids.');
        }

        // dd($request->all());

        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
        $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
        $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];

        // try {
        //     DB::beginTransaction();
        $bid = new LandlordAgentAuctionBid();
        $bid->user_id = Auth::user()->id;
        $bid->landlord_agent_auction_id = $request->auction_id;
        $bid->save();
        $bid->saveMeta("auction_id", $request->auction_id);
        $bid->saveMeta("listing_terms", $request->listing_terms);
        $bid->saveMeta("custom_listing_terms", $request->custom_listing_terms);
        $bid->saveMeta("offering_price", $request->offering_price);
        $bid->saveMeta("agentCommission", $request->agentCommission);
        $bid->saveMeta("agentCommissionOther", $request->agentCommissionOther);
        $bid->saveMeta("commissionRetianOpt", $request->commissionRetianOpt);
        $bid->saveMeta("customRetainCommission", $request->customRetainCommission);
        $bid->saveMeta("agentCharges", $request->agentCharges);
        $bid->saveMeta("broker_compensation", $request->broker_compensation);
        $bid->saveMeta("compensation_percent", $request->compensation_percent);
        $bid->saveMeta("handle_compensation", $request->handle_compensation);
        $bid->saveMeta("compensation_amount", $request->compensation_amount);
        $bid->saveMeta("compensation_tenant_broker", $request->compensation_tenant_broker);
        $bid->saveMeta("payment_timing", $request->payment_timing);
        $bid->saveMeta("payment_timing_days", $request->payment_timing_days);
        $bid->saveMeta("early_termination", $request->early_termination);
        $bid->saveMeta("early_termination_amount", $request->early_termination_amount);
        $bid->saveMeta("protection_period", $request->protection_period);
        $bid->saveMeta("protection_period_days", $request->protection_period_days);
        $bid->saveMeta("compensation_new_lease_percent", $request->compensation_new_lease_percent);
        $bid->saveMeta("compensation_new_lease_amount", $request->compensation_new_lease_amount);
        $bid->saveMeta("compensation_new_lease", $request->compensation_new_lease);
        $bid->saveMeta("custom_agent_charges", $request->custom_agent_charges);
        $bid->saveMeta("services", json_encode($request->services));
        $bid->saveMeta("other_services", $request->other_services);
        $bid->saveMeta("bio", $request->bio);
        $bid->saveMeta("why_hire_you", $request->why_hire_you);
        $bid->saveMeta("what_sets_you_apart", $request->what_sets_you_apart);
        $bid->saveMeta("marketing_plan", $request->marketing_plan);
        $bid->saveMeta("website_link", json_encode($request->website_link));
        $bid->saveMeta("reviews_link", json_encode($request->reviews_link));
        $bid->saveMeta("socialType", json_encode($request->socialType));
        $bid->saveMeta("social_link", json_encode($request->social_link));
        $bid->saveMeta("licensed", $request->licensed);
        $bid->saveMeta("first_name", $request->first_name);
        $bid->saveMeta("last_name", $request->last_name);
        $bid->saveMeta("agent_phone", $request->agent_phone);
        $bid->saveMeta("agent_email", $request->agent_email);
        $bid->saveMeta("agent_brokerage", $request->agent_brokerage);
        $bid->saveMeta("agent_license_no", $request->agent_license_no);
        $bid->saveMeta("mls_id", $request->mls_id);
        $bid->saveMeta("bid_on_hirenow_terms", $request->bid_on_hirenow_terms);

        if ($request->hasFile('video_file')) {
            $file = $request->video_file;
            $extension = $file->getClientOriginalExtension();
            $check = in_array($extension, $allowedVideos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $fileName = $uuid . '.' . $extension;
                $file->move(public_path('auction/files'), $fileName);
                $bid->saveMeta('video_file', 'auction/files/' . $fileName);
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
                $check = in_array($extension, $allowedFiles);

                if ($check) {
                    $uuid = Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    // Assuming $bid is defined somewhere in your code
                    $bid->saveMeta('promo', 'auction/files/' . $fileName);
                }
            }
        }
        // DB::commit();

        $hireNowTerms = [
            'listing_term',
            'custom_listing_terms',
            'broker_compensation',
            'compensation_percent',
            'handle_compensation',
            'compensation_amount',
            'compensation_tenant_broker',
            'payment_timing',
            'payment_timing_days',
            'early_termination',
            'early_termination_amount',
            'protection_period',
            'protection_period_days',
            'compensation_new_lease_percent',
            'compensation_new_lease_amount',
            'compensation_new_lease'
        ];

        $this->checkHireNowTerms($request, $hireNowTerms, $request->auction_id, $bid->id);

        $route = route('landlord.agent.auction.view', $request->auction_id);
        return redirect()->to($route)->with('success', 'Bid added successfully.');
        // } catch (\Exception $e) {
        //throw $e;
        DB::rollBack();
        return $e->getMessage();
        return redirect()->back()->with('error', 'Unable to add bid on Landlord\'s Agent Auction.');
        // }
    }

    public function view($bid_id)
    {
        $bid = LandlordAgentAuctionBid::findOrFail($bid_id);
        $page_data['title'] = 'Landlord Agent Auction Bid';
        $page_data['bid'] = $bid;
        return view('hire_landlord_agent.view-bid', $page_data);
    }

    public function accept_bid(Request $request)
    {
        $bid = LandlordAgentAuctionBid::with('user', 'meta')->whereId($request->bid_id)->first();
        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $auction = LandlordAgentAuction::with('user', 'meta')->whereId($request->auction_id)->first();
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        if ($auction->user_id !== Auth::id()) {
            abort(403, 'Only the listing owner can accept bids.');
        }

        if ($bid->accepted === 'accepted' || $bid->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This bid has already been ' . $bid->accepted . '.');
        }

        try {
            DB::beginTransaction();

            $bid->accepted = "accepted";
            $bid->accepted_date = date('Y-m-d H:i:s');
            $bid->save();

            $auction->is_sold = true;
            $auction->sold_date = date('Y-m-d H:i:s');
            $auction->save();
            $auction->saveMeta('listing_status', 'Hired Agent');

            // Reject all other non-accepted bids on this listing
            LandlordAgentAuctionBid::where('landlord_agent_auction_id', $auction->id)
                ->where('id', '!=', $bid->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            $ua = new UserAgent();
            $ua->user_id = Auth::user()->id;
            $ua->agent_id = $bid->user_id;
            $ua->type = 'landlord';
            $ua->property_id = $auction->id;
            $ua->save();

            DB::commit();

            $summaryId = null;
            try {
                $summaryService = new LandlordAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($bid);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('LandlordAcceptedBidSummaryService failed', [
                    'bid_id' => $bid->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            // Notify agent — bid accepted
            try {
                $agent = User::find($bid->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($bid, $auction, $summaryId, 'landlord_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid accepted notification to agent (landlord)', [
                    'bid_id'   => $bid->id,
                    'agent_id' => $bid->user_id,
                    'error'    => $e->getMessage(),
                ]);
            }

            // Notify landlord (listing owner) — agent hired
            try {
                $landlord = User::find($auction->user_id);
                if ($landlord) {
                    $landlord->notify(new LandlordAgentHiredNotification($bid, $auction, $summaryId));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send agent hired notification to landlord', [
                    'bid_id'     => $bid->id,
                    'landlord_id'=> $auction->user_id,
                    'error'      => $e->getMessage(),
                ]);
            }

            if ($summaryId) {
                return redirect()->route('accepted-bid-summary.view', $summaryId)
                    ->with('success', 'Agent hired successfully! Your Accepted Bid Summary is ready to review and sign.');
            }

            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } catch (\Exception $th) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error accepting bid: ' . $th->getMessage());
        }
    }

    public function reject_bid(Request $request)
    {
        $bid = LandlordAgentAuctionBid::whereId($request->bid_id)->first();
        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $auction = LandlordAgentAuction::find($request->auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        if ($auction->user_id !== Auth::id()) {
            abort(403, 'Only the listing owner can reject bids.');
        }

        if ($bid->accepted === 'accepted' || $bid->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This bid has already been ' . $bid->accepted . '.');
        }

        $bid->accepted = "rejected";
        $bid->accepted_date = date('Y-m-d H:i:s');

        if ($bid->save()) {
            try {
                $agent = User::find($bid->user_id);
                if ($agent) {
                    $agent->notify(new BidRejectedNotification($bid, $auction));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid rejected notification (landlord)', [
                    'bid_id'   => $bid->id,
                    'agent_id' => $bid->user_id,
                    'error'    => $e->getMessage(),
                ]);
            }
            return redirect()->back()->with('success', 'Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem rejecting the bid.');
        }
    }


    public function counter_bid(Request $request)
    {
        $pab = LandlordAgentAuction::whereId($request->auction_id)->first();
        $bid_id = $request->bid_id;
        $parent_counter_id = $request->counter_bid_id ? $request->counter_bid_id : null;

        return view('hire_landlord_agent.counter-bid', compact('bid_id', 'pab', 'parent_counter_id'));
    }



    public function accept_counter_bid(Request $request)
    {
        $counterTerm = LandlordCounterTerm::whereId($request->counter_bid_id)->first();
        if (!$counterTerm) {
            return redirect()->back()->with('error', 'Counter bid not found.');
        }

        // landlord_counter_terms.landlord_agent_auction_id stores BID ID
        $originalBid = LandlordAgentAuctionBid::with('user', 'meta')->find($counterTerm->landlord_agent_auction_id);
        if (!$originalBid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }

        $pa = LandlordAgentAuction::with('user', 'meta')->find($originalBid->landlord_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        $isListingOwner    = $pa->user_id === Auth::id();
        $isOriginalBidOwner = $originalBid->user_id === Auth::id();

        if (!$isListingOwner && !$isOriginalBidOwner) {
            abort(403, 'You are not authorized to accept this counter bid.');
        }

        if ($counterTerm->status === 'accepted' || $counterTerm->status === 'rejected') {
            return redirect()->back()->with('error', 'This counter bid has already been ' . $counterTerm->status . '.');
        }

        try {
            DB::beginTransaction();

            $counterTerm->status = 'accepted';
            $counterTerm->accepted_date = date('Y-m-d H:i:s');
            $counterTerm->save();

            $originalBid->accepted = 'accepted';
            $originalBid->accepted_date = date('Y-m-d H:i:s');
            $originalBid->save();

            $pa->is_sold = true;
            $pa->sold_date = date('Y-m-d H:i:s');
            $pa->save();
            $pa->saveMeta('listing_status', 'Hired Agent');

            // Reject all other non-accepted bids
            LandlordAgentAuctionBid::where('landlord_agent_auction_id', $pa->id)
                ->where('id', '!=', $originalBid->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            $ua = new UserAgent();
            $ua->user_id = Auth::user()->id;
            $ua->agent_id = $originalBid->user_id;
            $ua->type = 'landlord';
            $ua->property_id = $pa->id;
            $ua->save();

            DB::commit();

            $summaryId = null;
            try {
                $summaryService = new LandlordAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($originalBid, $counterTerm);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('LandlordAcceptedBidSummaryService (counter) failed', [
                    'bid_id'     => $originalBid->id,
                    'counter_id' => $counterTerm->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Notify agent that counter bid was accepted
            try {
                $agent = User::find($originalBid->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($originalBid, $pa, $summaryId, 'landlord_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter accepted notification to agent (landlord)', [
                    'bid_id'     => $originalBid->id,
                    'counter_id' => $counterTerm->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Notify landlord (listing owner) that agent was hired
            try {
                $landlord = User::find($pa->user_id);
                if ($landlord) {
                    $landlord->notify(new LandlordAgentHiredNotification($originalBid, $pa, $summaryId));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send agent hired notification to landlord (counter)', [
                    'bid_id'      => $originalBid->id,
                    'landlord_id' => $pa->user_id,
                    'error'       => $e->getMessage(),
                ]);
            }

            if ($summaryId) {
                return redirect()->route('accepted-bid-summary.view', $summaryId)
                    ->with('success', 'Counter Bid Accepted! Your Accepted Bid Summary is ready to review and sign.');
            }

            return redirect()->back()->with('success', 'Counter Bid Accepted successfully!');
        } catch (\Exception $th) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error accepting counter bid: ' . $th->getMessage());
        }
    }

    public function reject_counter_bid(Request $request)
    {
        $counterTerm = LandlordCounterTerm::whereId($request->counter_bid_id)->first();
        if (!$counterTerm) {
            return redirect()->back()->with('error', 'Counter bid not found.');
        }

        $originalBid = LandlordAgentAuctionBid::find($counterTerm->landlord_agent_auction_id);
        if (!$originalBid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }

        $pa = LandlordAgentAuction::find($originalBid->landlord_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        $isListingOwner    = $pa->user_id === Auth::id();
        $isOriginalBidOwner = $originalBid->user_id === Auth::id();

        if (!$isListingOwner && !$isOriginalBidOwner) {
            abort(403, 'You are not authorized to reject this counter bid.');
        }

        if ($counterTerm->status === 'accepted' || $counterTerm->status === 'rejected') {
            return redirect()->back()->with('error', 'This counter bid has already been ' . $counterTerm->status . '.');
        }

        $counterTerm->status = 'rejected';
        $counterTerm->accepted_date = date('Y-m-d H:i:s');

        if ($counterTerm->save()) {
            return redirect()->back()->with('success', 'Counter Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem rejecting the counter bid.');
        }
    }


    public function addCounterBid($id, $bid_id)
    {
        $auction = LandlordAgentAuction::with('user', 'meta')->find($id);
        if (!$auction) {
            abort(404, 'Auction not found');
        }

        $bid = LandlordAgentAuctionBid::with('meta', 'user')->find($bid_id);
        if (!$bid) {
            abort(404, 'Bid not found');
        }

        if ($bid->landlord_agent_auction_id != $auction->id) {
            abort(404, 'Bid does not belong to this auction');
        }

        $userId = Auth::id();
        $isListingOwner = ($auction->user_id === $userId);
        $isBidOwner = ($bid->user_id === $userId);

        if (!$isListingOwner && !$isBidOwner) {
            return redirect()->back()->with('error', 'You are not authorized to counter this bid.');
        }

        $latestCounter = LandlordCounterTerm::where('landlord_agent_auction_id', $bid_id)
            ->orderBy('created_at', 'desc')
            ->first();

        $parentCounterId = $latestCounter ? $latestCounter->id : null;

        return view('hire_landlord_agent.counter-bid', [
            'pab' => $auction,
            'bid_id' => $bid_id,
            'parent_counter_id' => $parentCounterId,
        ]);
    }

    public function view_counter_terms($bid_id)
    {
        $bid = LandlordAgentAuctionBid::with(['user', 'meta', 'counterTerms', 'auction.user', 'auction.meta'])->findOrFail($bid_id);
        $auction = $bid->auction;

        if (!$auction) {
            abort(404, 'Auction not found.');
        }

        $authId = Auth::id();
        $isListingOwner = $auction->user_id === $authId;
        $isAgent = $bid->user_id === $authId;

        if (!$isListingOwner && !$isAgent) {
            abort(403, 'You are not authorized to view this counter.');
        }

        $viewerRole = $isAgent ? 'agent' : 'landlord';

        // All counters for this bid
        $allCounters = $bid->counterTerms()->latest()->get();

        // Landlord's counter = submitted by auction owner
        $landlordCounter = $allCounters->firstWhere('user_id', $auction->user_id);

        // Agent's counter = submitted by bid owner
        $agentCounter = $allCounters->firstWhere('user_id', $bid->user_id);

        return view('hire_landlord_agent.view_counter_terms', compact(
            'bid', 'auction', 'viewerRole', 'landlordCounter', 'agentCounter'
        ));
    }

    public function saveCounterBid(Request $request, $bid_id)
    {
        $auctionBid = LandlordAgentAuctionBid::with('meta')->find($bid_id);
        $bid = new LandlordAgentAuctionBid();

        $bid->user_id = Auth::user()->id;
        $bid->counter_id = $bid_id;
        $bid->landlord_agent_auction_id = $auctionBid->landlord_agent_auction_id;
        $bid->save();
        $bid->saveMeta("auction_id", $request->auction_id);
        $bid->saveMeta("listing_terms", $request->listing_terms);
        $bid->saveMeta("custom_listing_terms", $request->custom_listing_terms);
        $bid->saveMeta("offering_price", $request->offering_price);
        $bid->saveMeta("agentCommission", $request->agentCommission);
        $bid->saveMeta("agentCommissionOther", $request->agentCommissionOther);
        $bid->saveMeta("commissionRetianOpt", $request->commissionRetianOpt);
        $bid->saveMeta("customRetainCommission", $request->customRetainCommission);
        $bid->saveMeta("broker_compensation", $request->broker_compensation);
        $bid->saveMeta("compensation_percent", $request->compensation_percent);
        $bid->saveMeta("handle_compensation", $request->handle_compensation);
        $bid->saveMeta("compensation_amount", $request->compensation_amount);
        $bid->saveMeta("compensation_tenant_broker", $request->compensation_tenant_broker);
        $bid->saveMeta("payment_timing", $request->payment_timing);
        $bid->saveMeta("payment_timing_days", $request->payment_timing_days);
        $bid->saveMeta("early_termination", $request->early_termination);
        $bid->saveMeta("early_termination_amount", $request->early_termination_amount);
        $bid->saveMeta("protection_period", $request->protection_period);
        $bid->saveMeta("protection_period_days", $request->protection_period_days);
        $bid->saveMeta("compensation_new_lease_percent", $request->compensation_new_lease_percent);
        $bid->saveMeta("compensation_new_lease_amount", $request->compensation_new_lease_amount);
        $bid->saveMeta("compensation_new_lease", $request->compensation_new_lease);
        $bid->saveMeta("agentCharges", $request->agentCharges);
        $bid->saveMeta("custom_agent_charges", $request->custom_agent_charges);
        $bid->saveMeta("services", json_encode($request->services));
        $bid->saveMeta("other_services", $request->other_services);
        $bid->saveMeta("bio", $request->bio);
        $bid->saveMeta("why_hire_you", $request->why_hire_you);
        $bid->saveMeta("what_sets_you_apart", $request->what_sets_you_apart);
        $bid->saveMeta("marketing_plan", $request->marketing_plan);
        $bid->saveMeta("website_link", json_encode($request->website_link));
        $bid->saveMeta("reviews_link", json_encode($request->reviews_link));
        $bid->saveMeta("socialType", json_encode($request->socialType));
        $bid->saveMeta("social_link", json_encode($request->social_link));
        $bid->saveMeta("licensed", $request->licensed);
        $bid->saveMeta("first_name", $request->first_name);
        $bid->saveMeta("last_name", $request->last_name);
        $bid->saveMeta("agent_phone", $request->agent_phone);
        $bid->saveMeta("agent_email", $request->agent_email);
        $bid->saveMeta("agent_brokerage", $request->agent_brokerage);
        $bid->saveMeta("agent_license_no", $request->agent_license_no);
        $bid->saveMeta("mls_id", $request->mls_id);
        $bid->saveMeta("bid_on_hirenow_terms", $request->bid_on_hirenow_terms);


        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
        $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
        $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];

        if ($request->hasFile('video_file')) {
            $file = $request->video_file;
            $extension = $file->getClientOriginalExtension();
            $check = in_array($extension, $allowedVideos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $fileName = $uuid . '.' . $extension;
                $file->move(public_path('auction/files'), $fileName);
                $bid->saveMeta('video_file', 'auction/files/' . $fileName);
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
                $check = in_array($extension, $allowedFiles);

                if ($check) {
                    $uuid = Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    // Assuming $bid is defined somewhere in your code
                    $bid->saveMeta('promo', 'auction/files/' . $fileName);
                }
            }
        }

        $route = route('landlord.agent.auction.view', $auctionBid->landlord_agent_auction_id);
        return redirect()->to($route)->with('success', 'Counter Bid placed successfully!');
    }

    private function checkHireNowTerms($req, $terms, $auction_id, $bid_id)
    {
        $auction = LandlordAgentAuction::find($auction_id);
        $bid = LandlordAgentAuctionBid::find($bid_id);

        if (!$auction) {
            return false;
        }

        // Check terms
        foreach ($terms as $term) {
            if ($req->has($term) && $req->input($term) != $auction->$term) {
                return false; // Found a mismatch, return false immediately
            }
        }

        // Check services
        if (!empty($req->services) && is_array($req->services)) {
            foreach ($req->services as $service) {
                if (!in_array($service, json_decode($auction->services, true))) {
                    return false; // Found a mismatch, return false immediately
                }
            }
        }

        // If everything is valid, update auction
        DB::beginTransaction();
        $bid->accepted = 1;
        $bid->accepted_date = date('Y-m-d H:i:s');
        $bid->save();

        $auction->auction_ended = 1;
        $auction->is_sold = true;
        $auction->sold_date = date('Y-m-d H:i:s');
        $auction->save();

        $ua = new UserAgent();
        $ua->user_id = Auth::user()->id;
        $ua->agent_id = $bid->user_id;
        $ua->type = 'landlord';
        $ua->save();
        DB::commit();

        return true;
    }
}
