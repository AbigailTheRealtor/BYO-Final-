<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\TenantCriteriaAuction;
use App\Models\TenantCriteriaAuctionBid;
use App\Services\LocationDna\BoundaryLookupService;
use App\Services\LocationDna\FloodZoneLookupService;

class TenantCriteriaAuctionController extends Controller
{
    public function index()
    {
        $page_data['title'] = 'Add Tenant\'s Criteria';
        return view('tenant_criteria.add', $page_data);
    }

    public function store(Request $request)
    {

        // dd($request->all());

        try {

            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lengths = explode(' ', $request->auction_length);
                $auction_length_days = current($auction_lengths);
            } else {
                $auction_length_days = '-1';
            }

            DB::beginTransaction();
            $auction = new TenantCriteriaAuction();
            $auction->user_id = Auth::user()->id;

            // update code
            $auction->save();
            $listing_date = Carbon::parse($request->listing_date);
            $expiration_date = Carbon::parse($request->expiration_date);
            $auction->listing_date = $listing_date;
            $auction->expiration_date = $expiration_date;
            $auction->saveMeta("cities",json_encode($request->cities));
            $auction->saveMeta("counties",json_encode($request->counties));
            $auction->saveMeta("state",json_encode($request->state));
            $auction->saveMeta("listing_date",$request->listing_date);
            $auction->saveMeta("expiration_date",$request->expiration_date);
            $auction->saveMeta("listingType",$request->listingType);
            $auction->saveMeta("representation",$request->representation);
            $auction->saveMeta("titleListing",$request->titleListing);
            $auction->saveMeta("property_type",$request->property_type);
            $auction->saveMeta("property_items",json_encode($request->property_items));
            $auction->saveMeta("leaseProp",$request->leaseProp);
            $auction->saveMeta("leasePropOther",$request->leasePropOther);
            $auction->saveMeta("prop_condition",json_encode($request->prop_condition));
            $auction->saveMeta("propsOther",$request->propsOther);
            $auction->saveMeta("monthly_price",$request->monthly_price);
            $auction->saveMeta("leaseLength",json_encode($request->leaseLength));
            $auction->saveMeta("leaseOther",$request->leaseOther);
            $auction->saveMeta("idealDate",$request->idealDate);
            $auction->saveMeta("bedrooms",$request->bedrooms);
            $auction->saveMeta("custom_bedrooms",$request->custom_bedrooms);
            $auction->saveMeta("bathrooms",$request->bathrooms);
            $auction->saveMeta("custom_bathrooms",$request->custom_bathrooms);
            $auction->saveMeta("minimum_sqft_needed",$request->minimum_sqft_needed);
            $auction->saveMeta("garageParkingOpt",$request->garageParkingOpt);
            $auction->saveMeta("garageOther",$request->garageOther);
            $auction->saveMeta("carport",$request->carport);
            $auction->saveMeta("carport_opt",$request->carport_opt);
            $auction->saveMeta("custom_carport",$request->custom_carport);
            $auction->saveMeta("garage",$request->garage);
            $auction->saveMeta("garage_opt",$request->garage_opt);
            $auction->saveMeta("custom_garage",$request->custom_garage);
            $auction->saveMeta("parking_feature_garage", json_encode($request->parking_feature_garage));
            $auction->saveMeta("parkingGarageOther",$request->parkingGarageOther);
            $auction->saveMeta("has_water_view",$request->has_water_view);
            $auction->saveMeta("water_view",json_encode($request->water_view));
            $auction->saveMeta("has_water_extra",$request->has_water_extra);
            $auction->saveMeta("water_extras",json_encode($request->water_extras));
            $auction->saveMeta("waterFrontageOpt",$request->waterFrontageOpt);
            $auction->saveMeta("waterFrontage",json_encode($request->waterFrontage));
            $auction->saveMeta("waterAccessOpt",$request->waterAccessOpt);
            $auction->saveMeta("water_access",json_encode($request->water_access));
            $auction->saveMeta("has_dock",$request->has_dock);
            $auction->saveMeta("dock",json_encode($request->dock));
            $auction->saveMeta("dockDescription",$request->dockDescription);
            $auction->saveMeta("viewOpt",$request->viewOpt);
            $auction->saveMeta("viewReference",json_encode($request->viewReference));
            $auction->saveMeta("viewReferenceOther",$request->viewReferenceOther);
            $auction->saveMeta("total_acreage",$request->total_acreage);
            $auction->saveMeta("Furnishings",json_encode($request->Furnishings));
            $auction->saveMeta("pool",$request->pool);
            $auction->saveMeta("poolNeededOpt",$request->poolNeededOpt);
            $auction->saveMeta("has_pets",$request->has_pets);
            $auction->saveMeta("totalPets",$request->totalPets);
            $auction->saveMeta("petType",$request->petType);
            $auction->saveMeta("petBreed",$request->petBreed);
            $auction->saveMeta("petWeight",$request->petWeight);
            $auction->saveMeta("represented",$request->represented);
            $auction->saveMeta("agentCommissionRequested",$request->agentCommissionRequested);
            $auction->saveMeta("agentCompensationAmountReq",$request->agentCompensationAmountReq);
            $auction->saveMeta("agentCompensationAmountReqOther",$request->agentCompensationAmountReqOther);
            $auction->saveMeta("tenantPaysTheAgent",$request->tenantPaysTheAgent);
            $auction->saveMeta("tenantPaysAmount",$request->tenantPaysAmount);
            $auction->saveMeta("tenantPaysAmountOther",$request->tenantPaysAmountOther);
            $auction->saveMeta("is_tenant_eligible",$request->is_tenant_eligible);
            $auction->saveMeta("description_buyer_specific",$request->description_buyer_specific);
            $auction->saveMeta("any_non_negotiable_factors",$request->any_non_negotiable_factors);
            $auction->saveMeta("negotiable",json_encode($request->negotiable));
            $auction->saveMeta("negotiableOther",$request->negotiableOther);
            $auction->saveMeta("renterTerms",$request->renterTerms);
            $auction->saveMeta("how_many_occupying",$request->how_many_occupying);
            $auction->saveMeta("tenant_credit_score",$request->tenant_credit_score);
            $auction->saveMeta("monthly_household_income",$request->monthly_household_income);
            $auction->saveMeta("convicted",$request->convicted);
            $auction->saveMeta("custom_convicted",$request->custom_convicted);
            $auction->saveMeta("evicted",$request->evicted);
            $auction->saveMeta("custom_evicted",$request->custom_evicted);
            $auction->saveMeta("lease_violations",$request->lease_violations);
            $auction->saveMeta("lease_violation_why",$request->lease_violation_why);
            $auction->saveMeta("compensateOpt",$request->compensateOpt);
            $auction->saveMeta("compensateOtherOpt",$request->compensateOtherOpt);
            $auction->saveMeta("compensateOther",$request->compensateOther);
            $auction->saveMeta("agent_first_name",$request->agent_first_name);
            $auction->saveMeta("agent_last_name",$request->agent_last_name);
            $auction->saveMeta("agent_phone",$request->agent_phone);
            $auction->saveMeta("agent_email",$request->agent_email);
            $auction->saveMeta("agent_brokerage",$request->agent_brokerage);
            $auction->saveMeta("agent_license_no",$request->agent_license_no);
            $auction->saveMeta("agent_mls_id",$request->agent_mls_id);
            $auction->saveMeta("realStateAgent",$request->realStateAgent);

            $ldnaTenantStore = $request->input('location_dna_preferences');
            if ($ldnaTenantStore !== null && $ldnaTenantStore !== '') {
                json_decode($ldnaTenantStore);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $auction->saveMeta('location_dna_preferences', $ldnaTenantStore);
                }
            }

            // AI FAQ / Chatbot Knowledge Base — stored privately, never shown publicly
            if ($request->has('ai_faq')) {
                $request->validate([
                    'ai_faq'   => ['nullable', 'array'],
                    'ai_faq.*' => ['nullable', 'string'],
                ]);
                $allowedKeys = array_column(config('tenant_ai_faq.questions'), 'key');
                $rawFaq = array_intersect_key((array) $request->input('ai_faq'), array_flip($allowedKeys));
                $faqData = array_map(fn($v) => ($v === '' || $v === null) ? null : (string) $v, $rawFaq);
                $auction->listing_ai_faq = $faqData;
                $auction->save();
            }

            // update code


            // 19 June 2023 for Residential and Income

            // photos and video uploads

            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];

            $allowedVideos = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v'];

            $visible_upload_file = [];
            // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf



            // photos and video uploads


            // Picture
            if ($request->hasFile('visible_property_picture')) {
                $visible_property_picture = $request->file('visible_property_picture');
                $originalName = $visible_property_picture->getClientOriginalName();
                $extension = $visible_property_picture->getClientOriginalExtension();
                $imageSize = $visible_property_picture->getSize();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $imageName = $uuid . '.' . $extension;
                    $visible_property_picture->move(public_path('auction/images'), $imageName);
                    $visible_property_picture = 'auction/images/' . $imageName;
                }
                $auction->saveMeta('property_picture', $visible_property_picture);
            }
            // Picture

            // Video
            if ($request->hasFile('video')) {
                $video = $request->file('video');
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

            // Video


            //Floor Plan
            if ($request->hasFile('visible_note')) {
                $file = $request->visible_note;
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    $auction->saveMeta('note', 'auction/files/' . $fileName);
                }
            }

            //Floor Plan

            // Business Card
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
            // Business Card

            // 19 June 2023 for Residential and Income

            DB::commit();
            \App\Services\ReferralLinkService::persistListingReferral($auction);
            return redirect()->route('tenant.criteria.auction.view', $auction->id)->with('success', 'Auction added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add auction');
        }
    }

    public function addCounterBid(Request $request, $bid_id)
    {
        $auction_bid = TenantCriteriaAuctionBid::find($bid_id);
        $page_data['auction'] = $auction = TenantCriteriaAuction::find($auction_bid->tenant_criteria_auction_id);
        $page_data['title'] = "Add Counter Bid for Tenant Criteria" . ' ' . '-' . ' ' . $auction->get->titleListing;
        $page_data['bid'] = $auction_bid;
        return view('tenant_criteria.add-counter_bid', $page_data);
    }

    public function saveCounterBid(Request $request, $bid_id)
    {
        $auctionBid = TenantCriteriaAuctionBid::with('meta')->where('tenant_criteria_auction_id', $bid_id)->first();
        $bid = new TenantCriteriaAuctionBid();
        $bid->user_id = Auth::user()->id;
        $bid->counter_id = $auctionBid->id;
        $bid->tenant_criteria_auction_id = $auctionBid->tenant_criteria_auction_id;
        $bid->save();
        $bid->saveMeta('address', $request->address);
        $bid->saveMeta('city', $request->city);
        $bid->saveMeta('county', $request->county);
        $bid->saveMeta('state', $request->state);
        $bid->saveMeta('landlordOfferCommission', $request->landlordOfferCommission);
        $bid->saveMeta('commissionAmmountOffered', $request->commissionAmmountOffered);
        $bid->saveMeta('landlordPaysAmount', $request->landlordPaysAmount);
        $bid->saveMeta("price",$request->price);
        $bid->saveMeta("leaseDate",$request->leaseDate);
        $bid->saveMeta("leaseTime",json_encode($request->leaseTime));
        $bid->saveMeta("other_lease_duration",$request->other_lease_duration);
        $bid->saveMeta('offerExpires', $request->offerExpires);
        $bid->saveMeta("first_name",$request->first_name);
        $bid->saveMeta("last_name",$request->last_name);
        $bid->saveMeta("agent_phone",$request->agent_phone);
        $bid->saveMeta("agent_email",$request->agent_email);
        $bid->saveMeta("agent_brokerage",$request->agent_brokerage);
        $bid->saveMeta("agent_license_no",$request->agent_license_no);
        $bid->saveMeta("agent_mls_id",$request->agent_mls_id);

        return redirect()->route('tenant.criteria.auction.view', $auctionBid->tenant_criteria_auction_id)->with("success", "Counter Bid placed successfully!");
    }

    public function bidsVisibility($id, $vis){
        $auction = TenantCriteriaAuction::where('id', $id)->first();
        if($vis == 'show'){
            $auction->display_bids = 1;
            $auction->save();
            return redirect()->back()->with('success', 'Bids list is now visible for all users');
        }else{
            $auction->display_bids = 0;
            $auction->save();
            return redirect()->back()->with('success', 'Bids list is now hidden for other users');
        }
    }

    public function edit($id, Request $request)
    {
        $page_data['auction'] = TenantCriteriaAuction::findOrFail($id);
        $page_data['title'] = 'Edit Tenant\'s Criteria';
        $ldnaRaw = $page_data['auction']->info('location_dna_preferences');
        $page_data['existingLocationDna'] = $ldnaRaw ? (json_decode($ldnaRaw, true) ?? []) : [];
        return view('tenant_criteria.edit', $page_data);
    }

    public function update($id, Request $request)
    {

        // try {

        if (str_contains(strtolower($request->auction_length), 'day')) {
            $auction_lengths = explode(' ', $request->auction_length);
            $auction_length_days = current($auction_lengths);
        } else {
            $auction_length_days = '-1';
        }

        // DB::beginTransaction();
        $auction = TenantCriteriaAuction::findOrFail($id);
        $auction->user_id = Auth::user()->id;
        $auction->auction_type = $request->auction_type;
        $auction->auction_length = $auction_length_days;
        $auction->update();
        $listing_date = Carbon::parse($request->listing_date);
        $expiration_date = Carbon::parse($request->expiration_date);
        $auction->listing_date = $listing_date;
        $auction->expiration_date = $expiration_date;
        $auction->saveMeta("cities",json_encode($request->cities));
        $auction->saveMeta("counties",json_encode($request->counties));
        $auction->saveMeta("state",json_encode($request->state));
        $auction->saveMeta("listing_date",$request->listing_date);
        $auction->saveMeta("expiration_date",$request->expiration_date);
        $auction->saveMeta("listingType",$request->listingType);
        $auction->saveMeta("representation",$request->representation);
        $auction->saveMeta("titleListing",$request->titleListing);
        $auction->saveMeta("property_type",$request->property_type);
        $auction->saveMeta("property_items",json_encode($request->property_items));
        $auction->saveMeta("leaseProp",$request->leaseProp);
        $auction->saveMeta("leasePropOther",$request->leasePropOther);
        $auction->saveMeta("prop_condition",json_encode($request->prop_condition));
        $auction->saveMeta("propsOther",$request->propsOther);
        $auction->saveMeta("monthly_price",$request->monthly_price);
        $auction->saveMeta("leaseLength",json_encode($request->leaseLength));
        $auction->saveMeta("leaseOther",$request->leaseOther);
        $auction->saveMeta("idealDate",$request->idealDate);
        $auction->saveMeta("bedrooms",$request->bedrooms);
        $auction->saveMeta("custom_bedrooms",$request->custom_bedrooms);
        $auction->saveMeta("bathrooms",$request->bathrooms);
        $auction->saveMeta("custom_bathrooms",$request->custom_bathrooms);
        $auction->saveMeta("minimum_sqft_needed",$request->minimum_sqft_needed);
        $auction->saveMeta("garageParkingOpt",$request->garageParkingOpt);
        $auction->saveMeta("garageOther",$request->garageOther);
        $auction->saveMeta("carport",$request->carport);
        $auction->saveMeta("carport_opt",$request->carport_opt);
        $auction->saveMeta("custom_carport",$request->custom_carport);
        $auction->saveMeta("garage",$request->garage);
        $auction->saveMeta("garage_opt",$request->garage_opt);
        $auction->saveMeta("custom_garage",$request->custom_garage);
        $auction->saveMeta("has_water_view",$request->has_water_view);
        $auction->saveMeta("water_view",json_encode($request->water_view));
        $auction->saveMeta("has_water_extra",$request->has_water_extra);
        $auction->saveMeta("water_extras",json_encode($request->water_extras));
        $auction->saveMeta("waterFrontageOpt",$request->waterFrontageOpt);
        $auction->saveMeta("waterFrontage",json_encode($request->waterFrontage));
        $auction->saveMeta("waterAccessOpt",$request->waterAccessOpt);
        $auction->saveMeta("water_access",json_encode($request->water_access));
        $auction->saveMeta("has_dock",$request->has_dock);
        $auction->saveMeta("dock",json_encode($request->dock));
        $auction->saveMeta("dockDescription",$request->dockDescription);
        $auction->saveMeta("viewOpt",$request->viewOpt);
        $auction->saveMeta("viewReference",json_encode($request->viewReference));
        $auction->saveMeta("viewReferenceOther",$request->viewReferenceOther);
        $auction->saveMeta("total_acreage",$request->total_acreage);
        $auction->saveMeta("Furnishings",json_encode($request->Furnishings));
        $auction->saveMeta("pool",$request->pool);
        $auction->saveMeta("poolNeededOpt",$request->poolNeededOpt);
        $auction->saveMeta("has_pets",$request->has_pets);
        $auction->saveMeta("totalPets",$request->totalPets);
        $auction->saveMeta("petType",$request->petType);
        $auction->saveMeta("petBreed",$request->petBreed);
        $auction->saveMeta("petWeight",$request->petWeight);
        $auction->saveMeta("represented",$request->represented);
        $auction->saveMeta("agentCommissionRequested",$request->agentCommissionRequested);
        $auction->saveMeta("agentCompensationAmountReq",$request->agentCompensationAmountReq);
        $auction->saveMeta("agentCompensationAmountReqOther",$request->agentCompensationAmountReqOther);
        $auction->saveMeta("tenantPaysTheAgent",$request->tenantPaysTheAgent);
        $auction->saveMeta("tenantPaysAmount",$request->tenantPaysAmount);
        $auction->saveMeta("tenantPaysAmountOther",$request->tenantPaysAmountOther);
        $auction->saveMeta("is_tenant_eligible",$request->is_tenant_eligible);
        $auction->saveMeta("description_buyer_specific",$request->description_buyer_specific);
        $auction->saveMeta("any_non_negotiable_factors",$request->any_non_negotiable_factors);
        $auction->saveMeta("negotiable",json_encode($request->negotiable));
        $auction->saveMeta("negotiableOther",$request->negotiableOther);
        $auction->saveMeta("renterTerms",$request->renterTerms);
        $auction->saveMeta("how_many_occupying",$request->how_many_occupying);
        $auction->saveMeta("tenant_credit_score",$request->tenant_credit_score);
        $auction->saveMeta("monthly_household_income",$request->monthly_household_income);
        $auction->saveMeta("convicted",$request->convicted);
        $auction->saveMeta("custom_convicted",$request->custom_convicted);
        $auction->saveMeta("evicted",$request->evicted);
        $auction->saveMeta("custom_evicted",$request->custom_evicted);
        $auction->saveMeta("lease_violations",$request->lease_violations);
        $auction->saveMeta("lease_violation_why",$request->lease_violation_why);
        $auction->saveMeta("compensateOpt",$request->compensateOpt);
        $auction->saveMeta("compensateOtherOpt",$request->compensateOtherOpt);
        $auction->saveMeta("compensateOther",$request->compensateOther);
        $auction->saveMeta("agent_first_name",$request->agent_first_name);
        $auction->saveMeta("agent_last_name",$request->agent_last_name);
        $auction->saveMeta("agent_phone",$request->agent_phone);
        $auction->saveMeta("agent_email",$request->agent_email);
        $auction->saveMeta("agent_brokerage",$request->agent_brokerage);
        $auction->saveMeta("agent_license_no",$request->agent_license_no);
        $auction->saveMeta("agent_mls_id",$request->agent_mls_id);
        $auction->saveMeta("realStateAgent",$request->realStateAgent);

        $ldnaTenantUpdate = $request->input('location_dna_preferences');
        if ($ldnaTenantUpdate !== null && $ldnaTenantUpdate !== '') {
            json_decode($ldnaTenantUpdate);
            if (json_last_error() === JSON_ERROR_NONE) {
                $auction->saveMeta('location_dna_preferences', $ldnaTenantUpdate);
            }
        }

        // AI FAQ / Chatbot Knowledge Base — stored privately, never shown publicly
        if ($request->has('ai_faq')) {
            $request->validate([
                'ai_faq'   => ['nullable', 'array'],
                'ai_faq.*' => ['nullable', 'string'],
            ]);
            $allowedKeys = array_column(config('tenant_ai_faq.questions'), 'key');
            $rawFaq = array_intersect_key((array) $request->input('ai_faq'), array_flip($allowedKeys));
            $faqData = array_map(fn($v) => ($v === '' || $v === null) ? null : (string) $v, $rawFaq);
            $auction->listing_ai_faq = $faqData;
            $auction->save();
        }

        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];

        $allowedVideos = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v'];

        $visible_upload_file = [];
        // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
        $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf



        // photos and video uploads


        // Picture
        if ($request->hasFile('visible_property_picture')) {
            $visible_property_picture = $request->file('visible_property_picture');
            $originalName = $visible_property_picture->getClientOriginalName();
            $extension = $visible_property_picture->getClientOriginalExtension();
            $imageSize = $visible_property_picture->getSize();
            $check = in_array($extension, $allowedPhotos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $imageName = $uuid . '.' . $extension;
                $visible_property_picture->move(public_path('auction/images'), $imageName);
                $visible_property_picture = 'auction/images/' . $imageName;
            }
            $auction->saveMeta('property_picture', $visible_property_picture);
        }
        // Picture

        // Video
        if ($request->hasFile('visible_property_video')) {
            $visible_property_video = $request->file('visible_property_video');
            $originalName = $visible_property_video->getClientOriginalName();
            $extension = $visible_property_video->getClientOriginalExtension();
            $videoSize = $visible_property_video->getSize();
            $check = in_array($extension, $allowedVideos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $videoName = $uuid . '.' . $extension;
                $visible_property_video->move(public_path('auction/videos'), $videoName);
                $visible_property_video = 'auction/videos/' . $videoName;
            }
            $auction->saveMeta('property_video', $visible_property_video);
        }

        // Video


        //Floor Plan
        if ($request->hasFile('visible_note')) {
            $file = $request->visible_note;
            $extension = $file->getClientOriginalExtension();
            $check = in_array($extension, $allowedFiles);
            if ($check) {
                $uuid = (string) Str::uuid();
                $fileName = $uuid . '.' . $extension;
                $file->move(public_path('auction/files'), $fileName);
                $auction->saveMeta('note', 'auction/files/' . $fileName);
            }
        }

        //Floor Plan

        // Business Card
        if ($request->hasFile('business_card')) {
            $visible_property_picture = $request->file('business_card');
            $originalName = $visible_property_picture->getClientOriginalName();
            $extension = $visible_property_picture->getClientOriginalExtension();
            $imageSize = $visible_property_picture->getSize();
            $check = in_array($extension, $allowedPhotos);
            if ($check) {
                $uuid = (string) Str::uuid();
                $imageName = $uuid . '.' . $extension;
                $visible_property_picture->move(public_path('auction/images'), $imageName);
                $visible_property_picture = 'auction/images/' . $imageName;
            }
            $auction->saveMeta('business_card', $visible_property_picture);
        }
        // Business Card

        // DB::commit();
        return redirect()->route('tenant.criteria.auction.view', $auction->id)->with('success', 'Auction updated successfully');
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     return $e->getMessage();
        //     return redirect()->back()->with('error', 'Unable to update auction');
        // }
    }

    public function list(Request $request)
    {
        $page_data['title'] = 'Tenant Criteria Auctions';
        $page_data['type'] = $type = $request->type ?? "2";
        $pendingAuctions = TenantCriteriaAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
        $pendingApprovalAuctions = TenantCriteriaAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
        $liveAuctions = TenantCriteriaAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false]);
        $soldAuctions = TenantCriteriaAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true]); //, 'is_paid' => true
        $pendingPaymentAuctions = TenantCriteriaAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true, 'is_paid' => false]);
        if ($type == "0") {
            // $auctions = $pendingAuctions->get();
        } else if ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } else if ($type == "2") {
            $auctions = $liveAuctions->get();
        } else if ($type == '3') {
            $auctions = $soldAuctions->get();
        } else if ($type == "4") {
            // $auctions = $pendingPaymentAuctions->get();
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
        return view('tenant_criteria.list', $page_data);
    }


    public function admin_list(Request $request)
    {
        $page_data['title'] = "Tenant's Criteria";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = TenantCriteriaAuction::where('is_approved', true)->get();
        } else if ($type == 2) {
            $page_data['auctions'] = TenantCriteriaAuction::where('is_sold', true)->get();
        } else {
            $page_data['auctions'] = TenantCriteriaAuction::where('is_approved', false)->get();
        }
        return view('admin.tenantCriteriaAuctions', $page_data);
    }


    public function approve($id)
    {
        $auction = TenantCriteriaAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    public function view($id, Request $request, BoundaryLookupService $boundaryLookupService, FloodZoneLookupService $floodZoneLookupService)
    {
        $page_data['auction'] = TenantCriteriaAuction::whereId($id)->first();
        $page_data['title'] = 'Tenant Criteria';
        $page_data['id'] = $id;
        $page_data['bids'] = TenantCriteriaAuctionBid::with('meta')->where('tenant_criteria_auction_id', $id)->whereNull('counter_id')->get();
        $auction = $page_data['auction'];
        $ldnaRaw = $auction->info('location_dna_preferences');
        $page_data['locationDnaPreferences'] = $ldnaRaw ? (json_decode($ldnaRaw, true) ?? null) : null;
        $page_data['legacyLocation'] = [
            'cities'   => json_decode($auction->info('cities')   ?: '[]', true) ?? [],
            'counties' => json_decode($auction->info('counties') ?: '[]', true) ?? [],
            'states'   => json_decode($auction->info('state')    ?: '[]', true) ?? [],
            'zip_codes' => [],
        ];
        $page_data['boundaryData'] = $boundaryLookupService->resolve(
            $page_data['locationDnaPreferences'],
            $page_data['legacyLocation']
        );
        $page_data['floodZoneData'] = $floodZoneLookupService->resolve(
            $page_data['boundaryData'],
            $page_data['locationDnaPreferences'] ?? []
        );
        return view('tenant_criteria.view', $page_data);
    }

    public function search(Request $request)
    {
        $page_data['title'] = 'Search Listings';
        $auctions = TenantCriteriaAuction::selectRaw("*, (SELECT meta_value FROM tenant_criteria_auction_metas WHERE tenant_criteria_auction_metas.tenant_criteria_auction_id = tenant_criteria_auctions.id AND meta_key = 'max_price') as price, (SELECT meta_value FROM tenant_criteria_auction_metas WHERE tenant_criteria_auction_metas.tenant_criteria_auction_id = tenant_criteria_auctions.id AND meta_key = 'property_type') as address")->where('is_sold', false)->where('is_approved', true);

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
            } else if ($sort == 2) {
                $sort_by = 'address';
                $sort_type = 'ASC';
            } else if ($sort == 3) {
                $sort_by = 'created_at';
                $sort_type = 'DESC';
            } else if ($sort == 4) {
                $sort_by = 'created_at';
                $sort_type = 'ASC';
            } else if ($sort == 5) {
                $sort_by = 'price';
                $sort_type = 'DESC';
            } else if ($sort == 6) {
                $sort_by = 'price';
                $sort_type = 'ASC';
            }
            $auctions->orderBy($sort_by, $sort_type);
        } else {
            $auctions->orderBy(DB::raw('RAND()'));
        }

        $auctions_c = $auctions;

        $page_data['count'] = $auctions_c->count();
        $page_data['pAuctions'] = $auctions->paginate(12);
        return view('tenant_criteria.search', $page_data);
    }

    public function renew($id)
    {
        $tenant_criteria = TenantCriteriaAuction::whereId($id)->first();
        return view('tenant_criteria.renew', compact('tenant_criteria'));
    }
    public function renew_save(Request $request)
    {
        $tenantCriteriaAuction = TenantCriteriaAuction::find($request->id);
        $tenantCriteriaAuction->saveMeta('listing_date', $request->listing_date);
        $tenantCriteriaAuction->saveMeta('expiration_date', $request->expiration_date);
        return redirect('/tenant/criteria/auctions');
    }
}
