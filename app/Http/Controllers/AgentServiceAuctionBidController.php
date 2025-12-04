<?php

namespace App\Http\Controllers;

use App\Models\AgentService;
use App\Models\AgentServiceAuction;
use App\Models\AgentServiceAuctionBid;
use App\Models\City;
use App\Models\County;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentServiceAuctionBidController extends Controller
{

    public function add_bid($id)
    {

        $page_data['auction'] = AgentServiceAuction::find($id);
        $page_data['title'] = 'Add Bid to Agent Service Auction';
        return view('agent_service.add-bid', $page_data);
    }

    public function save_bid($id, Request $request)
    {
        // dd($request->all());
        try {
            DB::beginTransaction();
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
            $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];

            $bid = new AgentServiceAuctionBid();
            $bid->agent_service_auction_id = $id;
            $bid->user_id = Auth::user()->id;
            $bid->price = $request->offering_price;
            $bid->save();

            // Changes
            $bid->saveMeta('agent_fee', $request->agent_fee);
            $bid->saveMeta('fee_cancel_', $request->fee_cancel_);
            $bid->saveMeta('custom_fee_cancel', $request->custom_fee_cancel);
            $bid->saveMeta('type_of_client', $request->type_of_client);
            $bid->saveMeta('buyers_flat_fee_list', json_encode($request->buyers_flat_fee_list));
            $bid->saveMeta('seller_flat_fee_list', json_encode($request->seller_flat_fee_list));
            $bid->saveMeta('tenant_flat_fee_list', json_encode($request->tenant_flat_fee_list));
            $bid->saveMeta('landlord_flat_fee_list', json_encode($request->landlord_flat_fee_list));
            $bid->saveMeta('real_estate_flat_fee_list', json_encode($request->real_estate_flat_fee_list));
            $bid->saveMeta('has_non_negotiable_amenities', $request->has_non_negotiable_amenities);
            $bid->saveMeta('custom_real_estatet_fee_list', $request->custom_real_estatet_fee_list);
            $bid->saveMeta('agent_licensed', $request->agent_licensed);
            $bid->saveMeta('referralFeeOther', $request->referralFeeOther);
            $bid->saveMeta('referralFeeOpt', $request->referralFeeOpt);

            // Changes

            $bid->saveMeta('agent_first_name', $request->agent_first_name);
            $bid->saveMeta('agent_last_name', $request->agent_last_name);
            $bid->saveMeta('agent_phone', $request->agent_phone);
            $bid->saveMeta('agent_email', $request->agent_email);
            $bid->saveMeta('agent_brokerage', $request->agent_brokerage);
            $bid->saveMeta('agent_license_no', $request->agent_license_no);
            $bid->saveMeta('agent_mls_id', $request->agent_mls_id);
            $bid->saveMeta('offering_price', $request->offering_price);
            $bid->saveMeta('website_link', json_encode($request->website_link));
            $bid->saveMeta('reviews_link', json_encode($request->reviews_link));
            $bid->saveMeta('socialType', json_encode($request->socialType));
            $bid->saveMeta('social_link', json_encode($request->social_link));
            $bid->saveMeta('bio', $request->bio);
            $bid->saveMeta('why_hire_you', $request->why_hire_you);
            $bid->saveMeta('what_sets_you_apart', $request->what_sets_you_apart);
            $bid->saveMeta('marketing_plan', $request->marketing_plan);
            $bid->saveMeta('services', json_encode($request->services));
            $bid->saveMeta('other_services', $request->other_services);
            $bid->saveMeta('video_url', $request->video_url);


            if ($request->hasFile('agent_video')) {
                $file = $request->agent_video;
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    $fileName = 'auction/files/' . $fileName;
                    $bid->saveMeta('agent_video', $fileName);
                }
            }
            if ($request->hasFile('note')) {
                $notes = $request->file('note');
                foreach ($notes as $note) {
                    $extension1 = $note->getClientOriginalExtension();
                    $check = in_array($extension1, $allowedFiles);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $fileName = $uuid . '.' . $extension1;
                        $note->move(public_path('auction/files'), $fileName);
                        $fileName = 'auction/files/' . $fileName;
                        $bid->saveMeta('note', $fileName);
                    }
                }
            }


            if ($request->hasFile('card')) {
                $card = $request->card;
                $extension2 = $card->getClientOriginalExtension();
                $check = in_array($extension2, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $cardName = $uuid . '.' . $extension2;
                    $card->move(public_path('auction/bid/cards'), $cardName);
                    $cardName = 'auction/bid/cards/' . $cardName;
                    $bid->saveMeta('card', $cardName);
                }
            }
            DB::commit();
            $route = route('agent.service.auction.view', $id);
            return redirect()->to($route)->with('success', 'Bid added successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add bid');
        }
    }

    public function acceptASBid(Request $request)
    {

        $pab = AgentServiceAuctionBid::whereId($request->bid_id)->first();
        $pab->accepted = true;
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = AgentServiceAuction::whereId($request->auction_id)->first();
        $pa->is_sold = true;
        $pa->sold_date = date('Y-m-d H:i:s');

        if ($pab->save() && $pa->save()) {
            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }

    public function removeVideo($id)
    {
        $as = AgentServiceAuction::whereId($id)->first();
        $filePath = public_path('auction/videos/' . $as->video);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $as->video = '';
        $as->update();
        return redirect()->back()->with('success', 'Video deleted successfully!');
    }
}
