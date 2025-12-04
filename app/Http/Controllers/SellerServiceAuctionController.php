<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\County;
use App\Models\SellerService;
use App\Models\SellerServiceAuction;
use App\Models\SellerServiceAuctionBid;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SellerServiceAuctionController extends Controller
{
    public function add()
    {
        $page_data['title'] = 'Add Seller Service Auction';
        $page_data['cities'] = City::where('state_id','3930')->get();
        // $page_data['states'] = State::where('country_id','231')->where('id','3930')->get();
        $page_data['counties'] = County::all();
        $page_data['services'] = SellerService::orderBy('sort','asc')->get();
        return view('sellerServiceAuction.add', $page_data);
    }

    public function store(Request $request)
    {
        // dd('ok');
        $request->validate([
            'auction_type' => ['required'],
            'auction_length' => ['required'],
            'services' => ['required'],
            'description' => ['required'],
            'city_id' => ['required'],
            'county_id' => ['required'],
            'required_at' => ['required'],
            'price' => ['required'],
        ],[
            'auction_type.required' => 'Please select auction type',
            'auction_length.required' => 'Please select auction length',
            'services.required' => 'Please select a service',
            'description.required' => 'please enter description',
            'city_id.required' => 'please select city',
            'county_id.required' => 'please select county',
            'required_at.required' => 'please select a date and time',
            'price.required' => 'please enter minimum price in dollars',
        ]);
        // $allowedPhotos=['jpg','png','jpeg','gif','svg'];
        $allowedFiles=['jpg','png','jpeg','gif','svg','csv','txt','xlx','xls','pdf','doc','docs','docm','docx','dot','dotm','dotx','odt','rtf','wps','xml','xps'];//csv,txt,xlx,xls,pdf
        $allowedVideos=['mp4','mov','wmv','avi','mkv','mpeg-2'];
        $allowedAudios=['mp3','wav','voc','ogg','oga','cda','ogv'];
        $ssa = new SellerServiceAuction();
        $ssa->user_id           = Auth::user()->id;
        $ssa->auction_type      = $request->auction_type;
        $ssa->auction_length    = $request->auction_length;
        $ssa->services          = json_encode($request->services);
        $ssa->description       = $request->description;
        $ssa->city_id           = $request->city_id;
        $ssa->county_id         = $request->county_id;
        $ssa->required_at       = Carbon::parse($request->required_at)->format('Y-m-d H:i:s');
        $ssa->price             = $request->price;
        $ssa->seller_name       = $request->seller_name;
        $ssa->seller_phone      = $request->seller_phone;
        $ssa->seller_email      = $request->seller_email;
        $ssa->private_notes     = $request->private_notes;
        $ssa->meeting_info      = $request->meeting_info;
        $ssa->address           = $request->address;

        if($request->hasFile('video'))
        {
            $video = $request->video;
            $extension = $video->getClientOriginalExtension();
            $check=in_array($extension,$allowedVideos);
            if($check)
            {
                $uuid = (string) Str::uuid();
                $videoName = $uuid.'.'.$extension;
                $video->move(public_path('auction/videos'), $videoName);
                $ssa->video = $videoName;
            }
        }
        if($request->hasFile('audio'))
        {
            $audio = $request->audio;
            $extension = $audio->getClientOriginalExtension();
            $check=in_array($extension,$allowedAudios);
            if($check)
            {
                $uuid = (string) Str::uuid();
                $audioName = $uuid.'.'.$extension;
                $audio->move(public_path('auction/audios'), $audioName);
                $ssa->audio = $audioName;
            }
        }

        if($request->hasFile('note'))
        {
            $file = $request->note;
            $extension = $file->getClientOriginalExtension();
            $check=in_array($extension,$allowedFiles);
            if($check)
            {
                $uuid = (string) Str::uuid();
                $fileName = $uuid.'.'.$extension;
                $file->move(public_path('auction/files'), $fileName);
                $ssa->note = $fileName;
            }
        }

        if($ssa->save())
        {

            $to = route('seller.service.auction.view', $ssa->id);
            return redirect()->to($to)->with('success','Seller Service Auction added successfully!');
        } else {
            return redirect()->back()->with('error','Something went wrong!');
        }
    }

    public function edit($id)
    {
        $page_data['auction'] = SellerServiceAuction::find($id);
        $page_data['id'] = $id;
        $page_data['title'] = 'Edit Seller Service Auction';
        $page_data['cities'] = City::where('state_id','3930')->get();
        // $page_data['states'] = State::where('country_id','231')->where('id','3930')->get();
        $page_data['counties'] = County::all();
        $page_data['services'] = SellerService::orderBy('sort','asc')->get();
        return view('sellerServiceAuction.edit', $page_data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'auction_type' => ['required'],
            'auction_length' => ['required'],
            'services' => ['required'],
            'description' => ['required'],
            'city_id' => ['required'],
            'county_id' => ['required'],
            'required_at' => ['required'],
            'price' => ['required'],
        ],[
            'auction_type.required' => 'Please select auction type',
            'auction_length.required' => 'Please select auction length',
            'services.required' => 'Please select a service',
            'description.required' => 'please enter description',
            'city_id.required' => 'please select city',
            'county_id.required' => 'please select county',
            'required_at.required' => 'please select a date and time',
            'price.required' => 'please enter minimum price in dollars',
        ]);
        // $allowedPhotos=['jpg','png','jpeg','gif','svg'];
        $allowedFiles=['jpg','png','jpeg','gif','svg','csv','txt','xlx','xls','pdf','doc','docs','docm','docx','dot','dotm','dotx','odt','rtf','wps','xml','xps'];//csv,txt,xlx,xls,pdf
        $allowedVideos=['mp4','mov','wmv','avi','mkv','mpeg-2'];
        $allowedAudios=['mp3','wav','voc','ogg','oga','cda','ogv'];
        $ssa = SellerServiceAuction::find($request->id);
        $ssa->user_id           = Auth::user()->id;
        $ssa->auction_type      = $request->auction_type;
        $ssa->auction_length    = $request->auction_length;
        $ssa->services          = json_encode($request->services);
        $ssa->description       = $request->description;
        $ssa->city_id           = $request->city_id;
        $ssa->county_id         = $request->county_id;
        $ssa->required_at       = Carbon::parse($request->required_at)->format('Y-m-d H:i:s');
        $ssa->price             = $request->price;
        $ssa->seller_name       = $request->seller_name;
        $ssa->seller_phone      = $request->seller_phone;
        $ssa->seller_email      = $request->seller_email;
        $ssa->private_notes     = $request->private_notes;
        $ssa->meeting_info      = $request->meeting_info;
        $ssa->address           = $request->address;

        if($request->hasFile('video'))
        {
            $video = $request->video;
            $extension = $video->getClientOriginalExtension();
            $check=in_array($extension,$allowedVideos);
            if($check)
            {
                $uuid = (string) Str::uuid();
                $videoName = $uuid.'.'.$extension;
                $video->move(public_path('auction/videos'), $videoName);
                $ssa->video = $videoName;
            }
        }
        if($request->hasFile('audio'))
        {
            $audio = $request->audio;
            $extension = $audio->getClientOriginalExtension();
            $check=in_array($extension,$allowedAudios);
            if($check)
            {
                $uuid = (string) Str::uuid();
                $audioName = $uuid.'.'.$extension;
                $audio->move(public_path('auction/audios'), $audioName);
                $ssa->audio = $audioName;
            }
        }

        if($request->hasFile('note'))
        {
            $file = $request->note;
            $extension = $file->getClientOriginalExtension();
            $check=in_array($extension,$allowedFiles);
            if($check)
            {
                $uuid = (string) Str::uuid();
                $fileName = $uuid.'.'.$extension;
                $file->move(public_path('auction/files'), $fileName);
                $ssa->note = $fileName;
            }
        }

        if($ssa->update())
        {
            return redirect()->back()->with('success','Seller Service Auction updated successfully!');
        } else {
            return redirect()->back()->with('error','Something went wrong!');
        }
    }

    public function view($id)
    {
        $page_data['auction'] = $auction = SellerServiceAuction::whereId($id)->first();
        $page_data['title'] = 'View Seller Service Auction';
        $page_data['id'] = $id;
        return view('sellerServiceAuction.view', $page_data);
    }

    public function list(Request $request)
    {
        $page_data['title'] = 'Seller Service Auctions';
        $page_data['type'] = $type = $request->type ?? "2";
        $pendingApprovalAuctions = SellerServiceAuction::where(['user_id' => Auth::user()->id, 'is_approved'=>false, 'is_sold' => false]);
        $liveAuctions = SellerServiceAuction::where(['user_id' => Auth::user()->id, 'is_approved'=>true, 'is_sold' => false]);
        $soldAuctions = SellerServiceAuction::where(['user_id' => Auth::user()->id, 'is_approved'=>true, 'is_sold' => true]);

        if($type=="1") {
            $auctions = $pendingApprovalAuctions->get();
        } else if($type=="2") {
            $auctions = $liveAuctions->get();
        } else if($type=='3') {
            $auctions = $soldAuctions->get();
        } else {
            $auctions = $liveAuctions->get();
        }

        $page_data['pendingApprovalCount'] = $pendingApprovalAuctions->count();
        $page_data['liveCount'] = $liveAuctions->count();
        $page_data['soldCount'] = $soldAuctions->count();

        $page_data['auctions'] = $auctions;
        return view('sellerServiceAuction.list', $page_data);
    }

    public function search(Request $request)
    {
        $page_data['title'] = 'Search Listings';
        $page_data['count'] = SellerServiceAuction::where('is_sold', false)->where('is_approved',1)->count();
        // dd($page_data['count']);
        $page_data['pAuctions'] = SellerServiceAuction::where('is_sold', false)->where('is_approved',1)->paginate(12);
        return view('sellerServiceAuction.search',$page_data);
    }
}
