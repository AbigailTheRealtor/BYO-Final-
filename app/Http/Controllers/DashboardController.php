<?php

namespace App\Http\Controllers;

use App\Models\AgentService;
use App\Models\AgentServiceAuctionBid;
use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerCriteriaAuctionBid;
use App\Models\City;
use App\Models\Country;
use App\Models\County;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordAuctionBid;
use App\Models\PropertyAuction;
use App\Models\PropertyAuctionBid;
use App\Models\PropertyType;
use App\Models\SellerAgentAuctionBid;
use App\Models\State;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCriteriaAuctionBid;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $page_data['title'] = 'Dashboard';
        return view('dashboard', $page_data);
    }

    public function settings(Request $request)
    {
        $page_data['title'] = 'Profile Settings';
        $page_data['user'] = $user = Auth::user();
        $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        $page_data['countries'] = Country::whereId('231')->get();
        $page_data['cities'] = City::where('state_id', '3930')->get();
        $page_data['states'] = State::whereId('3930')->get();
        $page_data['counties'] = County::all();
        $page_data['services'] = AgentService::orderBy('sort', 'asc')->get();
        // dd($user->toArray());
        return view('settings', $page_data);
    }

    public function getStates(Request $request)
    {
        $country_id = $request->input('country_id');
        $states = State::where('country_id', $country_id)->get();
        return response()->json(['success' => true, 'states' => $states]);
    }

    public function getCities(Request $request)
    {
        $state_id = $request->input('state_id');
        $cities = City::where('state_id', $state_id)->get();
        return response()->json(['success' => true, 'cities' => $cities]);
    }


    public function saveSettings(Request $request)
    {
        $user = User::findOrFail(Auth::user()->id);
        $user->saveMeta('username', $request->username);
        $user->saveMeta('name', $request->name);
        $user->saveMeta('password', $request->password);
        $user->saveMeta('confirm_password', $request->confirm_password);
        $user->saveMeta('email', $request->email);
        $user->saveMeta('phone', $request->phone);
        $user->saveMeta('brokerage', $request->brokerage);
        $user->saveMeta('license_no', $request->license_no);
        $user->saveMeta('intro_video', $request->intro_video);
        $user->saveMeta('promotional_material', $request->promotional_material);
        $user->saveMeta('business_card', $request->business_card);
        $user->saveMeta('website', $request->website);
        $user->saveMeta('review', $request->review);
        $user->saveMeta('facebook', $request->facebook);
        $user->saveMeta('youtube', $request->youtube);
        $user->saveMeta('twitter', $request->twitter);
        $user->saveMeta('instagram', $request->instagram);
        $user->saveMeta('linkedin', $request->linkedin);
        $user->saveMeta('listing_term', $request->listing_term);
        $user->saveMeta('hired_agent', $request->hired_agent);
        $user->saveMeta('apart_agent', $request->apart_agent);
        $user->saveMeta('credit_offered', $request->credit_offered);
        $user->saveMeta('services', json_encode($request->services));
        $user->saveMeta('marketing_plan', $request->marketing_plan);
        $user->saveMeta('description', $request->description);
        $user->saveMeta('bio', $request->bio);
        $user->saveMeta('search_preferences', json_encode($request->search_preferences));
        $user->saveMeta('first_name', $request->first_name);
        $user->saveMeta('last_name', $request->last_name);
        $user->saveMeta('language', $request->language);
        $user->saveMeta('country_id', $request->country_id);
        $user->saveMeta('state_id', $request->state_id);
        $user->saveMeta('city_id', $request->city_id);
        $user->saveMeta('county_id', $request->county_id);
        $user->saveMeta('address1', $request->address1);
        $user->saveMeta('address2', $request->address2);
        $user->saveMeta('town', $request->town);
        $user->saveMeta('zip', $request->zip);
        $user->saveMeta('myavatar', $request->myavatar);
        $user->saveMeta('avatar', $request->avatar);
        $user->saveMeta('cover_photo', $request->cover_photo);
        $user->saveMeta('mycover', $request->mycover);
        // $user->user_name = $request->username;
        $user->name = $request->name;
        if ($request->password != "" && $request->password == $request->confirm_password) {
            $user->password = Hash::make($request->password);
        }
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->description = $request->description;
        // if (Auth::user()->user_type == 'agent') {
        //     $user->website = $request->website;
        //     $user->brokerage = $request->brokerage;
        //     $user->license_no = $request->license_no;
        //     $user->intro_video = $request->intro_video;
        //     $user->credit_offered = $request->credit_offered;
        //     $user->services = json_encode($request->services);
        // }
        // $user->search_preferences = json_encode($request->search_preferences);
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        // $user->language = $request->language;
        // $user->country_id = $request->country_id;
        // $user->state_id = $request->state_id;
        // $user->city_id = $request->city_id;
        // $user->county_id = $request->county_id;
        // $user->address1 = $request->address1;
        // $user->address2 = $request->address2;
        // // $user->town = $request->town;
        // $user->zip = $request->zip;
        if ($request->myavatar != "") {
            $user->avatar = $request->myavatar;
        }
        if ($request->mycover != "") {
            $user->cover_photo = $request->mycover;
        }
        $user->save();

        // $user2 = User::whereId(Auth::user()->id)->first();
        if ($request->avatar != "") {
            $ext = $request->avatar->extension();
            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
                $uuid = (string) Str::uuid();
                $imageName = $uuid . '.' . $ext;
                $request->avatar->move(public_path('images/avatar'), $imageName);
                $user = User::whereId(Auth::user()->id)->first();
                $user->avatar = $imageName;
                $user->save();
            }
        }

        if ($request->cover_photo != "") {
            $ext = $request->cover_photo->extension();
            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
                $uuid = (string) Str::uuid();
                $imageName = $uuid . '.' . $ext;
                $request->cover_photo->move(public_path('images/cover'), $imageName);
                $user = User::whereId(Auth::user()->id)->first();
                $user->cover_photo = $imageName;
                $user->save();
            }
        }

        // $request->avatar
        // $request->cover_photo
        return redirect()->back()->with('success', 'Settings Saved Successfully!');
    }

    public function myBids($type = "seller-property")
    {
        $page_data['title'] = 'My Bids';
        $page_data['type'] = $type;
        $page_data['user'] = $user = auth()->user();
        if ($type == 'seller-property') {
            $page_data['bids'] = PropertyAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.seller_property', $page_data);
        } else if ($type == 'landlord-property') {
            $page_data['bids'] = $bid = LandlordAuctionBid::where('user_id', $user->id)->with('auction', function ($qry) {
                $qry->with('bids');
            })->get();
            // dd($bid->toArray());
            return view('my-bids.landlord_property', $page_data);
        } else if ($type == 'buyer-criteria') {
            $page_data['bids'] = $bid = BuyerCriteriaAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.buyer-criteria', $page_data);
        } else if ($type == 'tenant-criteria') {
            $page_data['bids'] = $bid = TenantCriteriaAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.tenant-criteria', $page_data);
        } else if ($type == 'agent-service') {
            $page_data['bids'] = $bid = AgentServiceAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.agent-service', $page_data);
        } else if ($type == 'buyer-agent') {
            $page_data['bids'] = $bid = BuyerAgentAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.buyer-agent', $page_data);
        } else if ($type == 'seller-agent') {
            $page_data['bids'] = $bid = SellerAgentAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.seller-agent', $page_data);
        } else if ($type == 'landlord-agent') {
            $page_data['bids'] = $bid = LandlordAgentAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.landlord-agent', $page_data);
        } else if ($type == 'tenant-agent') {
            $page_data['bids'] = $bid = TenantAgentAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.tenant-agent', $page_data);
        } else {
            abort(404);
        }
    }

    public function myAuctions(Request $request)
    {
        $page_data['title'] = 'My Auctions';
        $page_data['type'] = $type = $request->type ?? "2";

        $pendingAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'sold' => false]);
        $pendingApprovalAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'sold' => false]);
        $liveAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => false]);
        $soldAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => true, 'is_paid' => true]);
        $pendingPaymentAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => true, 'is_paid' => false]);

        if ($type == "0") {
            $auctions = $pendingAuctions->get();
        } else if ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } else if ($type == "2") {
            $auctions = $liveAuctions->get();
        } else if ($type == '3') {
            $auctions = $soldAuctions->get();
        } else if ($type == "4") {
            $auctions = $pendingPaymentAuctions->get();
        } else {
            $auctions = $liveAuctions->get();
        }

        $page_data['pendingCount'] = $pendingAuctions->count();
        $page_data['pendingApprovalCount'] = $pendingApprovalAuctions->count();
        $page_data['liveCount'] = $liveAuctions->count();
        $page_data['soldCount'] = $soldAuctions->count();
        $page_data['pendingPaymentCount'] = $pendingPaymentAuctions->count();

        $page_data['auctions'] = $auctions;

        // dd($page_data['count_my_auctions']);
        return view('my-auctions', $page_data);
    }

    public function myFriends()
    {
        $page_data['title'] = 'My Friends';
        return view('my-friends', $page_data);
    }

    public function qrSettings(Request $request)
    {
        $page_data['title'] = "QR Code Settings";
        return view('qr.settings', $page_data);
    }

    public function update_qr(Request $request)
    {
        $user = User::findOrFail(Auth::id());
        if ($user->saveMeta('qr', $request->uri)) {
            return redirect()->back()->with('success', "QR Code settings updated successfully");
        } else {
            return redirect()->back()->with('error', "Unable to update QR Code settings");
        }
    }
}
