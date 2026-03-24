<?php

namespace App\Http\Controllers;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\BuyerCounterBidding;
use App\Models\UserAgent;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        // dd($request->all());
        // dd($request);
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
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
            $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];

            if ($request->hasFile('virtual_buyer_presentation')) {
                $video = $request->virtual_buyer_presentation;
                $extension = $video->getClientOriginalExtension();
                $check = in_array($extension, $allowedVideos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $videoName = $uuid . '.' . $extension;
                    $video->move(public_path('auction/videos'), $videoName);
                    // $saab->video = $videoName;
                    $bid->saveMeta('virtual_buyer_presentation', $videoName);
                }
            }
            if ($request->hasFile('audio')) {
                $audio = $request->audio;
                $extension = $audio->getClientOriginalExtension();
                $check = in_array($extension, $allowedAudios);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $audioName = $uuid . '.' . $extension;
                    $audio->move(public_path('auction/audios'), $audioName);
                    // $saab->audio = $audioName;
                    $bid->saveMeta('audio', $audioName);
                }
            }
            if ($request->hasFile('note')) {
                $files = $request->file('note');
                foreach ($files as $file) {
                    $extension = $file->getClientOriginalExtension();
                    $check = in_array($extension, $allowedFiles);

                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $fileName = $uuid . '.' . $extension;
                        $file->move(public_path('auction/files'), $fileName);
                        // Store each file name in the array
                        $uploadedFileNames[] = $fileName;
                    }
                }
                $bid->saveMeta('note', json_encode($uploadedFileNames));
            }

            if ($request->hasFile('card')) {
                $photo = $request->file('card');
                $extension = $photo->getClientOriginalExtension();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $photoName = $uuid . '.' . $extension;
                    $photo->move(public_path('auction/bid/cards'), $photoName);
                    // $other['card'] = 'auction/bid/cards/' . $photoName;
                    $bid->saveMeta('card', 'auction/bid/cards/' . $photoName);
                }
            }
            // Increasment 1 day by adding one bid
            $bid_count = BuyerAgentAuctionBid::where('buyer_agent_auction_id', $request->auction_id)->count();
            $buyer_auction = BuyerAgentAuction::with('meta')->find($request->auction_id);
            $date = new DateTime($buyer_auction->get->expiration_date); // Your initial date
            $date->modify('+1 day'); // Adding 1 day
            $date->setTime(0, 0, 0); // Setting the time to 00:00:00

            $increase_day = $date->format('Y-m-d H:i:s');
            BuyerAgentAuctionMeta::where('meta_key', 'expiration_date')
                ->where('buyer_agent_auction_id', $request->auction_id) // Adjust this condition based on your requirement
                ->update(['meta_value' => $increase_day]); // Replace $increase_day with the new value
            // Increasment 1 day by adding one bid


            DB::commit();
            $route = route('buyer.view-auction', $request->auction_id);
            return redirect()->to($route)->with('success', 'Bid added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
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

        $pab = BuyerAgentAuctionBid::whereId($request->bid_id)->first();
        $pab->accepted = "accepted";
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = BuyerAgentAuction::whereId($request->auction_id)->first();
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
        $pab = BuyerAgentAuctionBid::whereId($request->bid_id)->first();
        $pab->accepted = "rejected";
        $pab->accepted_date = date('Y-m-d H:i:s');


        if ($pab->save()) {
            return redirect()->back()->with('success', 'Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }

    public function accept_counter_bid(Request $request)
    {


        $pab = BuyerCounterBidding::whereId($request->counter_bid_id)->first();
        $pab->accepted = "accepted";
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = BuyerAgentAuction::whereId($request->auction_id)->first();
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

        $pab = BuyerCounterBidding::whereId($request->counter_bid_id)->first();
        $pab->accepted = "rejected";
        $pab->accepted_date = date('Y-m-d H:i:s');


        if ($pab->save()) {
            return redirect()->back()->with('success', 'Counter Bid Rejected successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }

}
