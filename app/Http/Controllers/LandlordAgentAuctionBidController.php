<?php

namespace App\Http\Controllers;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\landlordCounterBidding;
use App\Models\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use App\Notifications\CounterBidAcceptedNotification;
use App\Notifications\CounterBidRejectedNotification;
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
        if (in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])) {
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
        try {
            DB::beginTransaction();
            $bid = LandlordAgentAuctionBid::with('user', 'meta')->whereId($request->bid_id)->first();
            $bid->accepted = "accepted";
            $bid->accepted_date = date('Y-m-d H:i:s');
            $bid->save();

            $auction = LandlordAgentAuction::with('user', 'meta')->whereId($request->auction_id)->first();
            $auction->is_sold = true;
            $auction->sold_date = date('Y-m-d H:i:s');
            $auction->save();
            $auction->saveMeta('listing_status', 'Hired Agent');

            $ua = new UserAgent();
            $ua->user_id = Auth::user()->id;
            $ua->agent_id = $bid->user_id;
            $ua->type = 'landlord';
            $ua->property_id = $auction->id;
            $ua->save();

            DB::commit();

            // Generate Accepted Bid Summary
            $summary = null;
            try {
                $summaryService = new LandlordAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($bid);
            } catch (\Exception $e) {
                \Log::error('LandlordAcceptedBidSummaryService failed', ['error' => $e->getMessage()]);
            }

            $summaryId = $summary ? $summary->id : null;

            // Notify the bidder (agent) that their bid was accepted
            try { $bid->user->notify(new BidAcceptedNotification($bid, $auction)); } catch (\Exception $e) {}

            // Notify the landlord (listing owner) that an agent has been hired
            try { $auction->user->notify(new LandlordAgentHiredNotification($bid, $auction, $summaryId)); } catch (\Exception $e) {}

            if ($summary) {
                return redirect()->route('accepted-bid-summary.view', $summary->id)
                    ->with('success', 'Bid accepted successfully! Your Accepted Bid Summary is ready.');
            }

            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } catch (\Exception $th) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }

    public function reject_bid(Request $request)
    {
        $bid = LandlordAgentAuctionBid::whereId($request->bid_id)->first();
        $bid->accepted = "rejected";
        $bid->save();

        $auction = LandlordAgentAuction::find($request->auction_id); // We need the auction for the notification

        // Notify the bidder that their bid was rejected
        $bid->user->notify(new BidRejectedNotification($bid, $auction));

        return redirect()->back()->with('success', 'Bid Rejected successfully!');
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
        try {
            DB::beginTransaction();

            $pab = landlordCounterBidding::whereId($request->counter_bid_id)->first();
            $pab->accepted = "accepted";
            $pab->accepted_date = date('Y-m-d H:i:s');
            $pab->save();

            $pa = LandlordAgentAuction::with('user', 'meta')->whereId($request->auction_id)->first();
            $pa->is_sold = true;
            $pa->sold_date = date('Y-m-d H:i:s');
            $pa->save();
            $pa->saveMeta('listing_status', 'Hired Agent');

            $bid = LandlordAgentAuctionBid::with('user', 'meta')->whereId($pab->landlord_agent_auction_bid_id)->first();

            $ua = new UserAgent();
            $ua->user_id = $pa->user_id;
            $ua->agent_id = $bid->user_id;
            $ua->type = 'landlord';
            $ua->property_id = $pa->id;
            $ua->save();

            DB::commit();

            // Generate Accepted Bid Summary (counter bid terms)
            $summary = null;
            try {
                $summaryService = new LandlordAcceptedBidSummaryService();
                $counterModel = \App\Models\LandlordCounterBidding::find($pab->id);
                $summary = $summaryService->generateSummary($bid, $counterModel);
            } catch (\Exception $e) {
                \Log::error('LandlordAcceptedBidSummaryService (counter) failed', ['error' => $e->getMessage()]);
            }

            $summaryId = $summary ? $summary->id : null;

            // Notify the counter bidder (agent) that their counter bid was accepted
            try { $pab->user->notify(new CounterBidAcceptedNotification($pab, $pa)); } catch (\Exception $e) {}

            // Notify the landlord (listing owner) that an agent has been hired
            try { $pa->user->notify(new LandlordAgentHiredNotification($bid, $pa, $summaryId)); } catch (\Exception $e) {}

            if ($summary) {
                return redirect()->route('accepted-bid-summary.view', $summary->id)
                    ->with('success', 'Counter Bid accepted! Your Accepted Bid Summary is ready.');
            }

            return redirect()->back()->with('success', 'Counter Bid Accepted successfully!');
        } catch (\Exception $th) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }
    public function reject_counter_bid(Request $request)
    {
        $pab = landlordCounterBidding::whereId($request->counter_bid_id)->first();
        $pab->accepted = "rejected";
        $pab->accepted_date = date('Y-m-d H:i:s');
        $auction = LandlordAgentAuction::find($request->auction_id);

        // Send notification to counter bidder
        $pab->user->notify(new CounterBidRejectedNotification($pab, $auction));

        if ($pab->save()) {
            return redirect()->back()->with('success', 'Counter Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
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
