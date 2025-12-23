<?php

namespace App\Http\Controllers;

use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterBidding;
use App\Models\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $pab->accepted = "accepted";
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = TenantAgentAuction::whereId($request->auction_id)->first();
        $pa->is_sold = true;
        $pa->sold_date = date('Y-m-d H:i:s');

        $ua = new UserAgent();
        $ua->user_id = Auth::user()->id;
        $ua->agent_id = $pab->user_id;
        $ua->type = 'tenant';
        $ua->save();

        if ($pab->save() && $pa->save()) {
            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }
    public function reject_bid(Request $request)
    {
        $pab = TenantAgentAuctionBid::whereId($request->bid_id)->first();
        $pab->accepted = "rejected";
        $pab->accepted_date = date('Y-m-d H:i:s');


        if ($pab->save()) {
            return redirect()->back()->with('success', 'Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
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


        $pab = TenantCounterBidding::whereId($request->counter_bid_id)->first();
        $pab->accepted = "accepted";
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = TenantAgentAuction::whereId($request->auction_id)->first();
        $pa->is_sold = true;
        $pa->sold_date = date('Y-m-d H:i:s');

        $ua = new UserAgent();
        $ua->user_id = Auth::user()->id;
        $ua->agent_id = $pab->user_id;
        $ua->type = 'tenant';
        $ua->save();

        if ($pab->save() && $pa->save()) {
            return redirect()->back()->with('success', 'Counter Bid Accepted successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }
    public function reject_counter_bid(Request $request)
    {

        $pab = TenantCounterBidding::whereId($request->counter_bid_id)->first();
        $pab->accepted = "rejected";
        $pab->accepted_date = date('Y-m-d H:i:s');


        if ($pab->save()) {
            return redirect()->back()->with('success', 'Counter Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
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
