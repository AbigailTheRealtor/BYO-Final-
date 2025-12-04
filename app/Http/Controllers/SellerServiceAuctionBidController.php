<?php

namespace App\Http\Controllers;

use App\Models\SellerServiceAuction;
use App\Models\SellerServiceAuctionBid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SellerServiceAuctionBidController extends Controller
{
    public function saveSSBid(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'brokerage' => ['required'],
            'license_no' => ['required'],
            // 'mls_id' => ['required'],
            'price' => ['required'],
            // 'price_percent' => ['required'],
        ], [
            'name.required' => 'Name is required!',
            'brokerage.required' => 'Please enter brokerage!',
            'license_no.required' => 'Please enter license number!',
            // 'mls_id.required' => 'Please enter MLS ID!',
            'price.required' => 'Please enter price you are offering!',
            // 'price_percent.required' => 'Please enter price percent!',
        ]);

        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
        $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
        $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];

        $asab = new SellerServiceAuctionBid();
        $asab->seller_service_auction_id = $request->auction_id;
        $asab->user_id = Auth::user()->id;
        $asab->name = $request->name;
        $asab->phone = $request->phone;
        $asab->email = $request->email;
        $asab->brokerage = $request->brokerage;
        $asab->license_no = $request->license_no;
        $asab->price_in = $request->price_in;
        $asab->price = $request->price;
        $asab->additional_details = $request->additional_details;
        $asab->video_url = $request->video_url;

        $other = [];
        $other['website_link'] = $request->website_link;
        $other['reviews_link'] = $request->reviews_link;
        $other['socialType'] = $request->socialType;
        $other['social_link'] = $request->social_link;
        $asab->other = json_encode($other);

        if ($request->hasFile('card')) {
            $card = $request->card;
            $extension = $card->getClientOriginalExtension();
            $check = in_array($extension, $allowedPhotos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $cardName = $uuid . '.' . $extension;
                $card->move(public_path('auction/bid/cards'), $cardName);
                $asab->card = $cardName;
            }
        }

        if ($request->hasFile('video')) {
            $video = $request->video;
            $extension = $video->getClientOriginalExtension();
            $check = in_array($extension, $allowedVideos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $videoName = $uuid . '.' . $extension;
                $video->move(public_path('auction/videos'), $videoName);
                $asab->video = $videoName;
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
                $asab->audio = $audioName;
            }
        }
        if ($request->hasFile('note')) {
            $file = $request->note;
            $extension = $file->getClientOriginalExtension();
            $check = in_array($extension, $allowedFiles);
            if ($check) {
                $uuid = (string) Str::uuid();
                $fileName = $uuid . '.' . $extension;
                $file->move(public_path('auction/files'), $fileName);
                $asab->note = $fileName;
            }
        }

        if ($asab->save()) {
            return redirect()->back()->with('success', 'Bid Added Successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add auction bid!');
        }
    }


    public function acceptSSBid(Request $request)
    {
        $pab = SellerServiceAuctionBid::whereId($request->bid_id)->first();
        $pab->accepted = true;
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = SellerServiceAuction::whereId($request->auction_id)->first();
        $pa->is_sold = true;
        $pa->sold_date = date('Y-m-d H:i:s');

        if ($pab->save() && $pa->save()) {
            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }
}
