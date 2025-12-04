<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\City;
use App\Models\User;
use App\Models\State;
use App\Models\County;
use App\Models\Bedroom;
use App\Models\Bathroom;
use App\Models\Financing;
use App\Models\UserAgent;
use Illuminate\Support\Str;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use App\Mail\PreferredAgentsMail;
use App\Models\SellerAgentAuction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use App\Models\SellerAgentAuctionBid;
use App\Models\SellerAgentAuctionMeta;
use DateTime;

class SellerAgentAuctionController extends Controller
{
    public function sellerAgentHireAuction(Request $request)
    {
        $page_data['title'] = 'Auction to Hire Seller\'s Agent';
        $page_data['cities'] = City::where('state_id', '3930')->get();
        $page_data['states'] = State::where('country_id', '231')->where('id', '3930')->get();
        $page_data['counties'] = County::all();
        $page_data['bedrooms'] = Bedroom::all();
        $page_data['bathrooms'] = Bathroom::all();
        $page_data['financings'] = Financing::orderBy('sort', 'ASC')->get();
        $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        return view('hire_seller_agent.add', $page_data);
    }

    public function sellerAgentHireAuctionSave(Request $request)
    {

        // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        // $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];
        try {
            DB::beginTransaction();
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lenths = explode(' ', $request->auction_length);
                $auction_lenth_days = current($auction_lenths);
            } else {
                $auction_lenth_days = '-1';
            }
            // 13 July 2023
            $auction = new SellerAgentAuction();
            $auction->user_id = Auth::user()->id;
            $auction->address = $request->address;
            $auction->auction_type = $request->auction_type;
            $auction->auction_length = $auction_lenth_days;
            $auction->save(); //$request->auction_length;

            $listing_date = Carbon::parse($request->listing_date);
            $expiration_date = Carbon::parse($request->expiration_date);
            $auction->saveMeta('working_with_agent', $request->working_with_agent);
            $auction->saveMeta('address', $request->address);
            $auction->saveMeta('custom_lease', $request->custom_lease);
            // changes
            $auction->saveMeta('city', $request->city);
            $auction->saveMeta('county', $request->county);
            $auction->saveMeta('state', $request->state);
            $auction->saveMeta('custom_appliances', $request->custom_appliances);
            $auction->saveMeta('faucet', $request->faucet);
            $auction->saveMeta('totalSqft', $request->totalSqft);
            $auction->saveMeta('commercial_seller_contract_no', $request->commercial_seller_contract_no);
            $auction->saveMeta('custom_special_sale', $request->custom_special_sale);
            $auction->saveMeta('commercialseller_contract_yes', $request->commercialseller_contract_yes);
            $auction->saveMeta('custom_seller_income_yes', $request->custom_seller_income_yes);
            $auction->saveMeta('custom_seller_income_no', $request->custom_seller_income_no);
            $auction->saveMeta('prop_condition', $request->prop_condition);
            $auction->saveMeta('custom_cryptocurrency', $request->custom_cryptocurrency);
            $auction->saveMeta('custom_assumable', $request->custom_assumable);
            $auction->saveMeta('custom_seller_financing', $request->custom_seller_financing);
            $auction->saveMeta('custom_exchange_trade', $request->custom_exchange_trade);
            $auction->saveMeta('custom_seller_contract_no', $request->custom_seller_contract_no);
            $auction->saveMeta('custom_seller_contract_yes', $request->custom_seller_contract_yes);
            $auction->saveMeta('custom_seller_specific_price', $request->custom_seller_specific_price);
            $auction->saveMeta('view', json_encode($request->view));
            $auction->saveMeta('custom_rent', $request->custom_rent);
            $auction->saveMeta('other_property_condition', $request->other_property_condition);
            $auction->saveMeta('other_heated_income', $request->other_heated_income);
            $auction->saveMeta('other_heated', $request->other_heated);
            $auction->saveMeta('propertyLoc', $request->propertyLoc);
            $auction->saveMeta('sqft', $request->sqft);
            $auction->saveMeta('poolOptions', $request->poolOptions);
            $auction->saveMeta('pool', $request->pool);
            $auction->saveMeta('garageOptions', $request->garageOptions);
            $auction->saveMeta('garage', $request->garage);
            $auction->saveMeta('unitOther', $request->unitOther);
            $auction->saveMeta('heated_source', $request->heated_source);
            $auction->saveMeta('vacant', $request->vacant);
            $auction->saveMeta('sqft', $request->sqft);
            $auction->saveMeta('garageOther', $request->garageOther);
            $auction->saveMeta('carport', $request->carport);
            $auction->saveMeta('carportOptions', $request->carportOptions);
            $auction->saveMeta('carportOther', $request->carportOther);
            $auction->saveMeta('other_heated', $request->other_heated);
            $auction->saveMeta('commissionSplit', $request->commissionSplit);
            $auction->saveMeta('commissionSplitOther', $request->commissionSplitOther);
            $auction->saveMeta('custom_rent', $request->custom_rent);
            $auction->saveMeta('custom_appliances', $request->custom_appliances);
            $auction->saveMeta('view', json_encode($request->view));
            $auction->saveMeta('propertyLoc', $request->propertyLoc);
            $auction->saveMeta('custom_seller_specific_price', $request->custom_seller_specific_price);
            $auction->saveMeta('otherSellerPrice', $request->otherSellerPrice);
            $auction->saveMeta('balloon', $request->balloon);
            $auction->saveMeta('trade', json_encode($request->trade));
            $auction->saveMeta('leaseOptions', json_encode($request->leaseOptions));
            $auction->saveMeta('sellerFinancing', json_encode($request->sellerFinancing));
            $auction->saveMeta('assumable', json_encode($request->assumable));
            $auction->saveMeta('nft', json_encode($request->nft));
            $auction->saveMeta('cryptocurrency', json_encode($request->cryptocurrency));
            $auction->saveMeta('prepayment', $request->prepayment);
            $auction->saveMeta('prepaymentOther', json_encode($request->prepaymentOther));
            $auction->saveMeta('balloonpyment', json_encode($request->balloonpyment));
            $auction->saveMeta('garageCommercial', $request->garageCommercial);
            $auction->saveMeta('rentGarage', json_encode($request->rentGarage));
            $auction->saveMeta('rentGarageOther', $request->garage);
            $auction->saveMeta('currentRent', $request->currentRent);
            $auction->saveMeta('garage_parking', $request->garage_parking);
            $auction->saveMeta('other_garage', $request->other_garage);
            $auction->saveMeta('otherView', $request->otherView);
            $auction->saveMeta('otherAmenities', $request->otherAmenities);
            $auction->saveMeta('listing_date', $listing_date);
            $auction->saveMeta('expiration_date', $expiration_date);
            $auction->saveMeta('auction_type', $request->auction_type);
            $auction->saveMeta('auction_length', $request->auction_length);
            $auction->saveMeta('property_type', $request->property_type);
            $auction->saveMeta('property_items', json_encode($request->property_items));
            $auction->saveMeta('special_sale', $request->special_sale);
            $auction->saveMeta('financing_info', $request->financing_info);
            $auction->saveMeta('finder_fee', $request->finder_fee);
            $auction->saveMeta('another_contract', $request->another_contract);
            $auction->saveMeta('custom_prop_condition', $request->custom_prop_condition);
            $auction->saveMeta('bedrooms', $request->bedrooms);
            $auction->saveMeta('other_bedrooms', $request->other_bedrooms);
            $auction->saveMeta('bathrooms', $request->bathrooms);
            $auction->saveMeta('other_bathrooms', $request->other_bathrooms);
            $auction->saveMeta('heated_square_footage', $request->heated_square_footage);
            $auction->saveMeta('heated_square', $request->heated_square);
            $auction->saveMeta('occupant_type', $request->occupant_type);
            $auction->saveMeta('seller_specific_price', $request->seller_specific_price);
            $auction->saveMeta('expectation', $request->expectation);
            $auction->saveMeta('type_of_financing', $request->type_of_financing);
            $auction->saveMeta('selling_timeframe', $request->selling_timeframe);
            $auction->saveMeta('custom_timeframe', $request->custom_timeframe);
            $auction->saveMeta('listing_term', $request->listing_term);
            $auction->saveMeta('custom_listing_terms', $request->custom_listing_terms);
            $auction->saveMeta('offered_commission', $request->offered_commission);
            $auction->saveMeta('custom_offered_commission', $request->custom_offered_commission);
            $auction->saveMeta('contribute_term', $request->contribute_term);
            $auction->saveMeta('custom_contribute_terms', $request->custom_contribute_terms);
            $auction->saveMeta('important_aspect', $request->important_aspect);
            $auction->saveMeta('important_info', $request->important_info);
            $auction->saveMeta('unit_types', $request->unit_types);
            $auction->saveMeta('unit_beds', $request->unit_beds);
            $auction->saveMeta('unit_baths', $request->unit_baths);
            $auction->saveMeta('sqft_heated_unit', $request->sqft_heated_unit);
            $auction->saveMeta('number_of_units', $request->number_of_units);
            $auction->saveMeta('number_occupied', $request->number_occupied);
            $auction->saveMeta('expected_rent', $request->expected_rent);
            $auction->saveMeta('rent_include', $request->rent_include);
            $auction->saveMeta('commercial_servic', $request->commercial_servic);
            $auction->saveMeta('preferred_agent', $request->preferred_agent);
            $auction->saveMeta('first_name', $request->first_name);
            $auction->saveMeta('last_name', $request->last_name);
            $auction->saveMeta('brokerage', $request->brokerage);
            $auction->saveMeta('phone', $request->phone);
            $auction->saveMeta('email', $request->email);
            $auction->saveMeta('purchase_of_business', $request->purchase_of_business);
            $auction->saveMeta('total_acreage', $request->total_acreage);
            // Array Store

            $auction->saveMeta('amenities', $request->amenities ? json_encode($request->amenities) : null);
            $auction->saveMeta('appliances', $request->appliances ? json_encode($request->appliances) : null);
            $auction->saveMeta('financings', $request->financings ? json_encode($request->financings) : null);
            $auction->saveMeta('services', $request->services ? json_encode($request->services) : null);
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedVideos = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v'];

            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps'];
            $visible_upload_file = [];
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf

            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $originalName = $photo->getClientOriginalName();
                $extension = $photo->getClientOriginalExtension();
                $imageSize = $photo->getSize();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $imageName = $uuid . '.' . $extension;
                    $photo->move(public_path('auction/images'), $imageName);
                    $photo = 'auction/images/' . $imageName;
                }
                $auction->saveMeta('photo', $photo);
            }
            if ($request->hasFile('video')) {
                $video = $request->file('video');
                if ($video) {
                    $originalName = $video->getClientOriginalName();
                    $extension = $video->getClientOriginalExtension();
                    $videoSize = $video->getSize();
                    $check = in_array($extension, $allowedVideos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $videoName = $uuid . '.' . $extension;
                        $video->move(public_path('auction/videos'), $videoName);
                        $video = 'auction/videos/' . $videoName;
                    }
                    $auction->saveMeta('video', $video);
                }
            }
            // adding Prefered Agent with 3 star mark
            // $prefered_agent=$request->prefered_agent;
            // $perefered_agent_details = [];
            // $perefered_agent_details_meta = [];
            // $seller_name=Auth::user()->name;
            // foreach ($prefered_agent as $item) {
            //     $individualElements = explode(',', $item);
            //     foreach ($individualElements as $element) {
            //         $perefered_agent_details = [];
            //         $user_prefered = User::where('id', $element)->first();
            //         if ($user_prefered) {
            //             $perefered_agent_details[] = [
            //                 'id' => $user_prefered->id,
            //                 'name' => $user_prefered->name,
            //                 'user_name' => $user_prefered->user_name,
            //                 'email'=>$user_prefered->email,
            //             ];
            //             $perefered_agent_details_meta[] = [
            //                 'id' => $user_prefered->id,
            //                 'name' => $user_prefered->name,
            //                 'user_name' => $user_prefered->user_name,
            //                 'email'=>$user_prefered->email,
            //             ];

            //             Mail::to($user_prefered->email)->send(new PreferredAgentsMail($perefered_agent_details, $seller_name));
            //         }
            //     }
            // }
            // $perefered_agent_details=$perefered_agent_details_meta;
            // $auction->saveMeta('prefered_agent_details', json_encode($perefered_agent_details));
            // adding Prefered Agent with 3 star mark


            // Pictures and Video Upload
            // 13 July 2023
            // nisar changing end
            DB::commit();
            return redirect()->back()->with('success', 'Seller\'s Agent Auction added successfully.');
        } catch (\Exception $e) {
            throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add Seller\'s Agent Auction.');
        }
    }

    public function hireSellerAgentHireAuctions(Request $request)
    {
        $page_data['title'] = 'Hire Seller\'s Agent Auctions';
        $page_data['type'] = $type = $request->type ?? "2";
        $pendingApprovalAuctions = SellerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
        $liveAuctions = SellerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false]);
        $soldAuctions = SellerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true]);

        if ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } elseif ($type == "2") {
            $auctions = $liveAuctions->get();
        } elseif ($type == '3') {
            $auctions = $soldAuctions->get();
        } else {
            $auctions = $liveAuctions->get();
        }

        $page_data['pendingApprovalCount'] = $pendingApprovalAuctions->count();
        $page_data['liveCount'] = $liveAuctions->count();
        $page_data['soldCount'] = $soldAuctions->count();

        $page_data['auctions'] = $auctions;

        return view('hire_seller_agent.list', $page_data);
    }

    public function editSellerAgentHireAuction($id)
    {
        $page_data['auction'] = $auction = SellerAgentAuction::whereId($id)->first();
        $page_data['title'] = 'Edit Seller\'s Agent Auction ' . $auction->address;
        $page_data['cities'] = City::where('state_id', '3930')->get();
        $page_data['states'] = State::where('country_id', '231')->where('id', '3930')->get();
        $page_data['counties'] = County::all();
        $page_data['bedrooms'] = Bedroom::all();
        $page_data['bathrooms'] = Bathroom::all();
        $page_data['financings'] = Financing::orderBy('sort', 'ASC')->get();
        $page_data['id'] = $id;
        return view('hire_seller_agent.edit', $page_data);
    }

    public function updateSellerAgentHireAuction(Request $request)
    {
        // dd($request->post());
        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
        // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        // $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];
        try {


            DB::beginTransaction();
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lenths = explode(' ', $request->auction_length);
                $auction_lenth_days = current($auction_lenths);
            } else {
                $auction_lenth_days = '-1';
            }

            $auction = SellerAgentAuction::find($request->id);
            $auction->user_id = Auth::user()->id;
            $auction->address = $request->address;
            $auction->auction_type = $request->auction_type;
            $auction->auction_length = $auction_lenth_days; //$request->auction_length;
            $auction->save();
            $auction->saveMeta('address', $request->address);
            $auction->saveMeta('auction_type', $request->auction_type);
            $auction->saveMeta('auction_length', $request->auction_length);
            $auction->saveMeta('auction_lenth_days', $auction_lenth_days);
            $auction->saveMeta('working_with_agent', $request->working_with_agent);
            $auction->saveMeta('state', $request->state);
            $auction->saveMeta('city', $request->city);
            $auction->saveMeta('county', $request->county);
            $auction->saveMeta('bedrooms', $request->bedrooms);
            $auction->saveMeta('custom_bedrooms', $request->custom_bedrooms);
            $auction->saveMeta('bathrooms', $request->bathrooms);
            $auction->saveMeta('custom_bathrooms', $request->custom_bathrooms);
            $auction->saveMeta('prop_conditions', json_encode($request->prop_conditions));
            $auction->saveMeta('special_sale', $request->special_sale);
            $auction->saveMeta('property_type', $request->property_type);
            $auction->saveMeta('property_items', $request->property_items);
            $auction->saveMeta('selling_timeframe', $request->selling_timeframe);
            $auction->saveMeta('listing_term', $request->listing_term);
            $auction->saveMeta('custom_listing_terms', $request->custom_listing_terms);
            $auction->saveMeta('offered_commission', $request->offered_commission);
            $auction->saveMeta('custom_offered_commission', $request->custom_offered_commission);
            $auction->saveMeta('services', json_encode($request->services));
            $auction->saveMeta('custom_services', $request->custom_services);
            // $auction->saveMeta('preffered_agent', $request->preffered_agent);
            $auction->saveMeta('preferred_agent', $request->preferred_agent);
            $auction->saveMeta('first_name', $request->first_name);
            $auction->saveMeta('last_name', $request->last_name);
            $auction->saveMeta('brokerage', $request->brokerage);
            $auction->saveMeta('phone', $request->phone);
            $auction->saveMeta('email', $request->email);
            $auction->saveMeta('need_cma', $request->need_cma);
            $auction->saveMeta('cma_q1', $request->cma_q1);
            $auction->saveMeta('cma_q2', $request->cma_q2);
            $auction->saveMeta('cma_q3', $request->cma_q3);
            $auction->saveMeta('cma_q4', $request->cma_q4);
            $auction->saveMeta('cma_q5', $request->cma_q5);
            $auction->saveMeta('cma_q6', $request->cma_q6);
            $auction->saveMeta('sqft', $request->sqft);
            $auction->saveMeta('ideal_price', $request->ideal_price);
            $auction->saveMeta('custom_ideal_price', $request->custom_ideal_price);
            $auction->saveMeta('financings', json_encode($request->financings));
            $auction->saveMeta('description', $request->description);
            $auction->saveMeta('important_info', $request->important_info);
            $auction->saveMeta('description_ideal_agent', $request->description_ideal_agent);
            $auction->saveMeta('video_url', $request->video_url);

            if ($request->hasFile('photos')) {
                $photos = $request->photos;
                $photosNames = array();
                foreach ($photos as $photo) {
                    $extension = $photo->getClientOriginalExtension();
                    $check = in_array($extension, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $photoName = $uuid . '.' . $extension;
                        $photo->move(public_path('auction/images'), $photoName);
                        $photosNames[] = '/auction/images/' . $photoName;
                    }
                    $auction->saveMeta('photos', json_encode($photosNames));
                }
            }
            DB::commit();
            return redirect()->back()->with('success', 'Seller\'s Agent Auction updated successfully.');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to update Seller\'s Agent Auction.');
        }
    }

    /**
     * View Seller's agent auction details.
     **/
    public function viewDetail($id, Request $request)
    {
        $data = SellerAgentAuction::with('meta')->find($id);
        // dd($data->get);

        // Check the values of $start and $end for debugging

        $auction = SellerAgentAuction::find($id);

        $page_data['title'] = $auction->address;
        $counties = County::all();
        $page_data['id'] = $id;
        return view('hire_seller_agent.view', compact('auction', 'data', 'counties'));
    }


    public function add_bid($id, Request $request)
    {

        $page_data['auction'] = $auction = SellerAgentAuction::find($id);
        $page_data['title'] = "Add Bidd to Hiring Seller's Agent - " . $auction->address;
        return view('hire_seller_agent.add-bid', $page_data);
    }

    /**
     * Save Seller's agent bid.
     * */
    public function saveSABid(Request $request)
    {


        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
        $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
        $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];

        try {
            DB::beginTransaction();
            $bid = new SellerAgentAuctionBid();
            $bid->user_id = Auth::user()->id;
            $bid->seller_agent_auction_id = $request->auction_id;
            $bid->price = $request->total_comission;
            $bid->save();
            $bid->saveMeta('auction_id', $request->auction_id);
            $bid->saveMeta('first_name', $request->first_name);
            $bid->saveMeta('last_name', $request->last_name);
            $bid->saveMeta('agent_phone', $request->agent_phone);
            $bid->saveMeta('agent_email', $request->agent_email);
            $bid->saveMeta('agent_brokerage', $request->agent_brokerage);
            $bid->saveMeta('agent_license_no', $request->agent_license_no);
            $bid->saveMeta('mls_id', $request->mls_id);
            $bid->saveMeta('offering_price', $request->offering_price);
            $bid->saveMeta('website_link', json_encode($request->website_link));
            $bid->saveMeta('reviews_link', json_encode($request->json_encode));
            $bid->saveMeta('socialType', json_encode($request->socialType));
            $bid->saveMeta('social_link', json_encode($request->social_link));
            $bid->saveMeta('bio', $request->bio);
            $bid->saveMeta('buyerCommission', $request->buyerCommission);
            $bid->saveMeta('license_year', $request->license_year);
            $bid->saveMeta('listing_terms', $request->listing_terms);
            $bid->saveMeta('total_comission', $request->total_comission);
            $bid->saveMeta('has_buyer_credit', $request->has_buyer_credit);
            $bid->saveMeta('buyer_concession', $request->buyer_concession);
            $bid->saveMeta('has_charge_fee', $request->has_charge_fee);
            $bid->saveMeta('charge_fee', $request->charge_fee);
            $bid->saveMeta('charges', $request->charges);
            $bid->saveMeta('custom_terms', $request->custom_terms);
            $bid->saveMeta('why_hire_you', $request->why_hire_you);
            $bid->saveMeta('what_sets_you_apart', $request->what_sets_you_apart);
            $bid->saveMeta('marketing_plan', $request->marketing_plan);
            $bid->saveMeta('video_url', $request->video_url);
            $bid->saveMeta('services', json_encode($request->services));
            $bid->saveMeta('other_services', $request->other_services);
            $bid->saveMeta('virtual_buyer_presentation_link', $request->virtual_buyer_presentation_link);

            if ($request->hasFile('virtual_buyer_presentation')) {
                $file = $request->virtual_buyer_presentation;
                $extension = strtolower($file->getClientOriginalExtension());
                $check = in_array($extension, $allowedVideos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    $bid->saveMeta('virtual_buyer_presentation', 'auction/files/' . $fileName);
                }
            }

            if ($request->hasFile('card')) {
                $file = $request->card;
                $extension = strtolower($file->getClientOriginalExtension());
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/images'), $fileName);
                    $bid->saveMeta('card', 'auction/images/' . $fileName);
                }
            }

            // if ($request->hasFile('note')) {
            //     $file1 = $request->note;
            //     $extension1 = $file1->getClientOriginalExtension();
            //     $check = in_array($extension1, $allowedFiles);
            //     if ($check) {
            //         $uuid = (string) Str::uuid();
            //         $fileName = $uuid . '.' . $extension1;
            //         $file1->move(public_path('auction/files'), $fileName);
            //         $bid->saveMeta('note', 'auction/files/' . $fileName);
            //     }
            // }
            if ($request->hasFile('note')) {
                $uploadedFiles = []; // Initialize an array to store file details

                foreach ($request->file('note') as $image) {
                    $extension1 = strtolower($image->getClientOriginalExtension());
                    $check = in_array($extension1, $allowedFiles);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $fileName = $uuid . '.' . $extension1;
                        $image->move(public_path('auction/files'), $fileName);

                        // Store file details in an array
                        $fileDetails = [
                            'file_name' => $fileName,
                            'file_path' =>  $fileName
                        ];

                        $uploadedFiles[] = $fileDetails; // Add file details to the array
                    }
                }
                // Assuming $bid->saveMeta() is a method to save metadata
                // You can adjust this part to store the uploadedFiles array in the way you need it.
                $bid->saveMeta('note', json_encode($uploadedFiles));
            }

            // Increasment 1 day by adding one bid
            $bid_count = SellerAgentAuctionBid::where('seller_agent_auction_id', $request->auction_id)->count();
            $seller_auction = SellerAgentAuction::with('meta')->find($request->auction_id);
            $date = new DateTime($seller_auction->get->expiration_date); // Your initial date
            $date->modify('+1 day'); // Adding 1 day
            $date->setTime(0, 0, 0); // Setting the time to 00:00:00
            $increase_day = $date->format('Y-m-d H:i:s');;
            SellerAgentAuctionMeta::where('meta_key', 'expiration_date')
                ->where('seller_agent_auction_id', $request->auction_id) // Adjust this condition based on your requirement
                ->update(['meta_value' => $increase_day]); // Replace $increase_day with the new value
            // Increasment 1 day by adding one bid

            DB::commit();
            $route = route('seller.agent.auction.detail', $request->auction_id);
            return redirect()->to($route)->with('success', 'Bid added successfully.');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add bid on Seller\'s Agent Auction.');
        }
    }

    /**
     * Seller's Agent auctions for admin dashboard.
     **/
    public function sellerAgentAuctions(Request $request)
    {
        $page_data['title'] = "Hire Seller's Agent";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = SellerAgentAuction::where('is_approved', true)->get();
        } elseif ($type == 2) {
            $page_data['auctions'] = SellerAgentAuction::where('is_sold', true)->get();
        } else {
            $page_data['auctions'] = SellerAgentAuction::where('is_approved', false)->get();
        }
        return view('admin.sellerAgentAuctions', $page_data);
    }

    /**
     * Seller's Agent auction approve for admin dashboard.
     **/
    public function approveSellerAgentAuction($id)
    {
        $auction = SellerAgentAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    /**
     * Seller can accept Seller's Agent bid on his/her auction.
     **/
    public function acceptSABid(Request $request)
    {
        $pa = SellerAgentAuction::whereId($request->auction_id)->first();
        $pab = SellerAgentAuctionBid::whereId($request->bid_id)->first();
        $dataa = SellerAgentAuction::with('meta')->find($request->auction_id);
        $reQbidPrice = (@$dataa->get->expectation != 'Other') ? str_replace('k', '000', @$dataa->get->expectation) : '';
        $getlowPrice = explode('-', $reQbidPrice);
        $lowPrice = $getlowPrice[0];
        $bidPrice = $lowPrice ?: (@$dataa->get->custom_seller_specific_price ?: @$dataa->get->otherSellerPrice);
        if ($request->counterPrice >= $bidPrice) {
            $pa->update(['auction_price' => $request->counterPrice]);
            $pab->accepted = true;
            $pab->accepted_date = date('Y-m-d H:i:s');
            $pa->is_sold = true;
            $pab->is_sold = true;
            $pa->sold_date = date('Y-m-d H:i:s');
            $ua = new UserAgent();
            $ua->user_id = Auth::user()->id;
            $ua->agent_id = $pab->user_id;
            $ua->type = 'seller';
            $ua->save();
            if ($pab->save() && $pa->save()) {
                return redirect()->back()->with('success', 'Bid Accepted successfully!');
            } else {
                return redirect()->back()->with('error', 'Some problem in bid acceptance!');
            }
        } else {
            return redirect()->back()->with('error', 'Your Offered Price Should matched with Demonded price!');
        }
    }

    public function myAgents()
    {
        $page_data['mySellerAgents'] = 'My Agents';
        return view('mySellerAgents', $page_data);
    }


    public function searchListing(Request $request)
    {
        $page_data['title'] = 'Search Listings';
        $auctions = SellerAgentAuction::query();

        $auctions->selectRaw('*, (SELECT meta_value FROM seller_agent_auction_metas WHERE seller_agent_auction_metas.seller_agent_auction_id = seller_agent_auctions.id AND meta_key = "ideal_price") as price')->where('is_approved', 1);

        if ($request->title != "") {
            $auctions->where('address', 'like', '%' . $request->title . '%');
        }

        if ($request->bedrooms != "") {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'bedrooms')->where('meta_value', $request->bedrooms);
            });
        }

        if ($request->bathrooms != "") {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'bathrooms')->where('meta_value', $request->bathrooms);
            });
        }

        if ($request->property_type != "") {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'property_type')->where('meta_value', $request->property_type);
            });
        }

        if ($request->sort) {
            $sort = $request->sort;
            if ($sort == 1) {
                $sort_by = 'address';
                $sort_type = 'DESC';
            } elseif ($sort == 2) {
                $sort_by = 'address';
                $sort_type = 'ASC';
            } elseif ($sort == 3) {
                $sort_by = 'created_at';
                $sort_type = 'DESC';
            } elseif ($sort == 4) {
                $sort_by = 'created_at';
                $sort_type = 'ASC';
            } elseif ($sort == 5) {
                $sort_by = 'price';
                $sort_type = 'DESC';
            } elseif ($sort == 6) {
                $sort_by = 'price';
                $sort_type = 'ASC';
            }
            $auctions->orderBy($sort_by, $sort_type);
        } else {
            // $auctions->orderBy(DB::raw('RAND()'));
        }
        $auctions_c = $auctions;

        // dd($auctions->toSql());

        $page_data['count'] = $auctions_c->count();

        // dd($page_data['count']);
        $page_data['pAuctions'] = $auctions->paginate(12);
        return view('hire_seller_agent.search', $page_data);
    }
    // Prefered agents Select 3 Agents
    public function prefered_agents(Request $request)
    {
        $prefered_state = $request->input('prefered_state');
        $prefered_address = $request->input('prefered_address');
        $prefered_city = $request->input('prefered_city');
        $prefered_agents = User::where('user_type', 'agent')->get();
        $star_agent = [];

        if (empty($star_agent) && !empty($prefered_address)) {
            foreach ($prefered_agents as  $prefered_agent) {
                if ($prefered_agent->info('office_address') == $prefered_address) {
                    $star_agent[] = $prefered_agent;
                }
            }
        }
        if (empty($star_agent) && !empty($prefered_city)) {
            // Do something when the $star_agent array is empty
            foreach ($prefered_agents as  $prefered_agent) {
                if ($prefered_agent->info('city') == $prefered_city) {
                    $star_agent[] = $prefered_agent;
                }
            }
        }
        if (empty($star_agent) && !empty($prefered_state)) {
            // Do something when the $star_agent array is empty
            foreach ($prefered_agents as  $prefered_agent) {
                if ($prefered_agent->info('state') == $prefered_state) {
                    $star_agent[] = $prefered_agent;
                }
            }
        }
        $html = (string)view('partial_view.prefered_star_agent', compact('star_agent'));
        return response()->json([
            'html' => $html,
            'status' => '200',
        ]);
    }
}
