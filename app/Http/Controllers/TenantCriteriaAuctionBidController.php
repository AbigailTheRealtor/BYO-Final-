<?php

namespace App\Http\Controllers;

use App\Models\TenantCriteriaAuction;
use App\Models\TenantCriteriaAuctionBid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantCriteriaAuctionBidController extends Controller
{
    public function add_bid($id)
    {
        $page_data['auction'] = TenantCriteriaAuction::find($id);
        $page_data['title'] = "Add Bid for Tenant's Criteria Auction";
        return view('tenant_criteria.add-bid', $page_data);
    }

    public function save_bid($id, Request $request)
    {
        // dd($request->all());

        try {

            DB::beginTransaction();
            $bid = new TenantCriteriaAuctionBid();
            $bid->user_id = Auth::user()->id;
            $bid->tenant_criteria_auction_id = $id;
            $bid->save();
            $bid->saveMeta('property_listed', $request->property_listed);
            $bid->saveMeta('property_link', $request->property_link);
            $bid->saveMeta('address', $request->address);
            $bid->saveMeta('city', $request->city);
            $bid->saveMeta('county', $request->county);
            $bid->saveMeta('state', $request->state);

            $bid->saveMeta('landlordOfferCommission', $request->landlordOfferCommission);
            $bid->saveMeta('commissionAmmountOffered', $request->commissionAmmountOffered);
            $bid->saveMeta('landlordPaysAmount', $request->landlordPaysAmount);

            $bid->saveMeta('offerExpires', $request->offerExpires);

            $bid->saveMeta("listing_date",$request->listing_date);
            $bid->saveMeta("expiration_date",$request->expiration_date);
            $bid->saveMeta("listing_service_type",$request->listing_service_type);
            $bid->saveMeta("representation",$request->representation);
            $bid->saveMeta("auction_type",$request->auction_type);
            $bid->saveMeta("auction_length",$request->auction_length);
            $bid->saveMeta("property_type",$request->property_type);
            $bid->saveMeta("property_items",json_encode($request->property_items));
            $bid->saveMeta("leasePropOption",$request->leasePropOption);
            $bid->saveMeta("singleRoom",json_encode($request->singleRoom));
            $bid->saveMeta("propConditions",$request->propConditions);
            $bid->saveMeta("propOther",$request->propOther);

            $bid->saveMeta("rentNow",$request->rentNow);
            $bid->saveMeta("rentNowSqft",$request->rentNowSqft);
            $bid->saveMeta("startingPrice",$request->startingPrice);
            $bid->saveMeta("reservePrice",$request->reservePrice);

            $bid->saveMeta("price",$request->price);
            $bid->saveMeta("list_price_per_sq",$request->list_price_per_sq);
            $bid->saveMeta("leaseDate",$request->leaseDate);
            $bid->saveMeta("leaseTime",json_encode($request->leaseTime));
            $bid->saveMeta("other_lease_duration",$request->other_lease_duration);
            $bid->saveMeta("leaseTerms",json_encode($request->leaseTerms));
            $bid->saveMeta("other_lease_terms", $request->other_lease_terms);
            $bid->saveMeta("start_date",$request->start_date); 
            $bid->saveMeta("end_date",$request->end_date); 
            $bid->saveMeta("buildingFeatures",json_encode($request->buildingFeatures)); 
            $bid->saveMeta("buildingFeaturesOther",$request->buildingFeaturesOther); 
            $bid->saveMeta("frequency",json_encode($request->frequency)); 
            $bid->saveMeta("tenant_pays",json_encode($request->tenant_pays)); 
            $bid->saveMeta("tenantPaysOther", $request->tenantPaysOther); 
            $bid->saveMeta("ownerPays",json_encode($request->ownerPays)); 
            $bid->saveMeta("landlordPaysOther", $request->landlordPaysOther); 
            $bid->saveMeta("rent",json_encode($request->rent)); 
            $bid->saveMeta("rentOther",$request->rentOther); 
            $bid->saveMeta("required_at_move_in",$request->required_at_move_in);
            $bid->saveMeta("leaseTermOther",$request->leaseTermOther); 
            $bid->saveMeta("firstMonthDeposit",$request->firstMonthDeposit); 
            $bid->saveMeta("lastMonthDeposit",$request->lastMonthDeposit); 
            $bid->saveMeta("securityDeposit",$request->securityDeposit); 
            $bid->saveMeta("firstMonthSecond",$request->firstMonthSecond); 
            $bid->saveMeta("lastMonthSecond",$request->lastMonthSecond); 
            $bid->saveMeta("securityDepositSecond",$request->securityDepositSecond); 
            $bid->saveMeta("petDepositSecond",$request->petDepositSecond);
            $bid->saveMeta("exitCleaningFeeSecond",$request->exitCleaningFeeSecond);
            $bid->saveMeta("applicationFeeSecond",$request->applicationFeeSecond);
            $bid->saveMeta("applicationLinkSecond",$request->applicationLinkSecond);
            $bid->saveMeta("firstMonthThird",$request->firstMonthThird);
            $bid->saveMeta("lastMonthThird",$request->lastMonthThird);
            $bid->saveMeta("securityDepositThird",$request->securityDepositThird);
            $bid->saveMeta("petDepositThird",$request->petDepositThird);
            $bid->saveMeta("exitCleaningFeeThird",$request->exitCleaningFeeThird);
            $bid->saveMeta("applicationFeeThird",$request->applicationFeeThird);
            $bid->saveMeta("applicationLinkThird",$request->applicationLinkThird);
            $bid->saveMeta("firstMonthFour",$request->firstMonthFour);
            $bid->saveMeta("lastMonthFour",$request->lastMonthFour);
            $bid->saveMeta("securityDepositFour",$request->securityDepositFour);
            $bid->saveMeta("applicationFeeFour",$request->applicationFeeFour);
            $bid->saveMeta("exitCleaningFeeFour",$request->exitCleaningFeeFour);
            $bid->saveMeta("applicationLinkFour",$request->applicationLinkFour);
            $bid->saveMeta("vacationTaxFour",$request->vacationTaxFour);
            $bid->saveMeta("firstMonthFive",$request->firstMonthFive);
            $bid->saveMeta("securityDepositFive",$request->securityDepositFive); 
            $bid->saveMeta("exitCleaningFeeFive",$request->exitCleaningFeeFive);
            $bid->saveMeta("applicationFeeFive",$request->applicationFeeFive);
            $bid->saveMeta("applicationLinkFive",$request->applicationLinkFive);
            $bid->saveMeta("vacationTaxFive",$request->vacationTaxFive);
            $bid->saveMeta("firstMonthSix",$request->firstMonthSix);
            $bid->saveMeta("securityDepositSix",$request->securityDepositSix);
            $bid->saveMeta("exitCleaningFeeSix",$request->exitCleaningFeeSix);
            $bid->saveMeta("applicationFeeSix",$request->applicationFeeSix);
            $bid->saveMeta("applicationLinkSix",$request->applicationLinkSix);
            $bid->saveMeta("firstMonthSeven",$request->firstMonthSeven);
            $bid->saveMeta("securityDepositSeven",$request->securityDepositSeven);
            $bid->saveMeta("exitCleaningFeeSeven",$request->exitCleaningFeeSeven);
            $bid->saveMeta("applicationFeeSeven",$request->applicationFeeSeven);
            $bid->saveMeta("applicationLinkSeven",$request->applicationLinkSeven);
            $bid->saveMeta("timeFrame",$request->timeFrame);
            $bid->saveMeta("timeFrameMultiple",$request->timeFrameMultiple);
            $bid->saveMeta("specialMoveOption",$request->specialMoveOption);
            $bid->saveMeta("specialMove",$request->specialMove);
            $bid->saveMeta("petsOpt",$request->petsOpt);
            $bid->saveMeta("petsNumber",$request->petsNumber);
            $bid->saveMeta("petsType",$request->petsType);
            $bid->saveMeta("petsWeight",$request->petsWeight);
            $bid->saveMeta("petsFee",$request->petsFee);
            $bid->saveMeta("petsAmount",$request->petsAmount);
            $bid->saveMeta("petsFund",$request->petsFund);
            $bid->saveMeta("offer_allowed_occupants",$request->offer_allowed_occupants);
            $bid->saveMeta("custom_occupants",$request->custom_occupants);
            $bid->saveMeta("creditScore",$request->creditScore);
            $bid->saveMeta("offer_min_net_income",$request->offer_min_net_income);
            $bid->saveMeta("eviction",$request->eviction);
            $bid->saveMeta("offer_prior_felony",$request->offer_prior_felony);
            $bid->saveMeta("bedroom",$request->bedroom);
            $bid->saveMeta("other_bedrooms",$request->other_bedrooms);
            $bid->saveMeta("bathrooms",$request->bathrooms);
            $bid->saveMeta("other_bathrooms",$request->other_bathrooms);
            $bid->saveMeta("heated_sqft",$request->heated_sqft);
            $bid->saveMeta("net_leasable_sqft",$request->net_leasable_sqft);
            $bid->saveMeta("sqft_total",$request->sqft_total);
            $bid->saveMeta("heated_source",$request->heated_source);
            $bid->saveMeta("otherSqft",$request->otherSqft);
            $bid->saveMeta("total_acreage",$request->total_acreage);
            $bid->saveMeta("yearBuilt",$request->yearBuilt);
            $bid->saveMeta("lotSize",$request->lotSize);
            $bid->saveMeta("legarName",$request->legarName);
            $bid->saveMeta("taxId",$request->taxId);
            $bid->saveMeta("zoneCode",$request->zoneCode);
            $bid->saveMeta("zoning",$request->zoning);
            $bid->saveMeta("tax_year",$request->tax_year);
            $bid->saveMeta("taxes_annual",$request->taxes_annual);
            $bid->saveMeta("legal_description",$request->legal_description);
            $bid->saveMeta("no_of_parcels",$request->no_of_parcels);
            $bid->saveMeta("additional_parcels",$request->additional_parcels);
            $bid->saveMeta("additional_tax_id",$request->additional_tax_id);
            $bid->saveMeta("furnishings",$request->furnishings);
            $bid->saveMeta("appliances",json_encode($request->appliances));
            $bid->saveMeta("appliancesOther",$request->appliancesOther);
            $bid->saveMeta("firePlace",$request->firePlace);
            $bid->saveMeta("amenities",json_encode($request->amenities));
            $bid->saveMeta("otherAmenities",$request->otherAmenities);
            $bid->saveMeta("features",json_encode($request->features));
            $bid->saveMeta("interiorFeatures",json_encode($request->interiorFeatures));
            $bid->saveMeta("interiorFeatureOther",$request->interiorFeatureOther);
            $bid->saveMeta("additional_rooms",json_encode($request->additional_rooms));
            $bid->saveMeta("roomOther",$request->roomOther);
            $bid->saveMeta("laundry",json_encode($request->laundry));
            $bid->saveMeta("laundryOther",$request->laundryOther);
            $bid->saveMeta("propFloors",$request->propFloors);
            $bid->saveMeta("floorNumber",$request->floorNumber);
            $bid->saveMeta("totalFloors",$request->totalFloors);
            $bid->saveMeta("building_elevator",$request->building_elevator);
            $bid->saveMeta("floor_covering",json_encode($request->floor_covering));
            $bid->saveMeta("floorConvringOther",$request->floorConvringOther); 
            $bid->saveMeta("roomDimensions",json_encode($request->roomDimensions));
            $bid->saveMeta("room_type",json_encode($request->room_type));
            $bid->saveMeta("room_level",json_encode($request->room_level));
            $bid->saveMeta("bedroomCloset",json_encode($request->bedroomCloset));
            $bid->saveMeta("roomPrimary",json_encode($request->roomPrimary));
            $bid->saveMeta("room_feature",json_encode($request->room_feature));
            $bid->saveMeta("roomFeatueOther",$request->roomFeatueOther);
            $bid->saveMeta("waterAccessOpt",json_encode($request->waterAccessOpt));
            $bid->saveMeta("water_access",json_encode($request->water_access));
            $bid->saveMeta("has_water_view",$request->has_water_view);
            $bid->saveMeta("water_view",json_encode($request->water_view));
            $bid->saveMeta("has_water_extra",$request->has_water_extra);
            $bid->saveMeta("water_extras",json_encode($request->water_extras));
            $bid->saveMeta("has_dock", $request->has_dock);
            $bid->saveMeta("dock",json_encode($request->dock));
            $bid->saveMeta("dockDescription",json_encode($request->dockDescription));
            $bid->saveMeta("dockLiftCapacity", $request->dockLiftCapacity);
            $bid->saveMeta("dockYearBuilt", $request->dockYearBuilt);
            $bid->saveMeta("dockDimension", $request->dockDimension);
            $bid->saveMeta("dockMaintenanceFee", $request->dockMaintenanceFee);
            $bid->saveMeta("dockMaintenanceFeeFrequency", $request->dockMaintenanceFeeFrequency);

            $bid->saveMeta("has_water_fontage",$request->has_water_fontage);
            $bid->saveMeta("waterFrontageView",json_encode($request->waterFrontageView));
            $bid->saveMeta("utilities",json_encode($request->utilities));
            $bid->saveMeta("otherUtilities",$request->otherUtilities);
            $bid->saveMeta("water",json_encode($request->water));
            $bid->saveMeta("otherWater",$request->otherWater);
            $bid->saveMeta("sewer",json_encode($request->sewer));
            $bid->saveMeta("airConditioning",json_encode($request->airConditioning));
            $bid->saveMeta("otherAirCondition",$request->otherAirCondition);
            $bid->saveMeta("heatingFuel",json_encode($request->heatingFuel));
            $bid->saveMeta("otherFuel",$request->otherFuel);
            $bid->saveMeta("carportOther",$request->carportOther);
            $bid->saveMeta("garage",$request->garage);
            $bid->saveMeta("garageOther",$request->garageOther);
            $bid->saveMeta("poolOpt",$request->poolOpt);
            $bid->saveMeta("pool",$request->pool);
            $bid->saveMeta("viewOption",json_encode($request->viewOption));
            $bid->saveMeta("view",json_encode($request->view));
            $bid->saveMeta("viewOther",$request->viewOther);
            $bid->saveMeta("otherParking",$request->otherParking);
            $bid->saveMeta("front_exposure",$request->front_exposure);
            $bid->saveMeta("foundation",json_encode($request->foundation));
            $bid->saveMeta("foundationOther",$request->foundationOther);
            $bid->saveMeta("exterior_construction",json_encode($request->exterior_construction));
            $bid->saveMeta("exteriorOther",$request->exteriorOther);
            $bid->saveMeta("exterior_feature",json_encode($request->exterior_feature));
            $bid->saveMeta("exteriorFeatureOther",$request->exteriorFeatureOther);
            $bid->saveMeta("other_structures",json_encode($request->other_structures));
            $bid->saveMeta("structuresOther",$request->structuresOther);
            $bid->saveMeta("roadFrontageOther",$request->roadFrontageOther);
            $bid->saveMeta("road_surface_type",json_encode($request->road_surface_type));
            $bid->saveMeta("roadSurfaceOther",$request->roadSurfaceOther);
            $bid->saveMeta("roof",json_encode($request->roof));
            $bid->saveMeta("roofCementOther",$request->roofCementOther);
            $bid->saveMeta("adjoining_property",json_encode($request->adjoining_property));
            $bid->saveMeta("otherFeatures",$request->otherFeatures);
            $bid->saveMeta("has_condo_enviornment",$request->has_condo_enviornment);
            $bid->saveMeta("condo_fee",$request->condo_fee);
            $bid->saveMeta("association_name",$request->association_name);
            $bid->saveMeta("association_phone",$request->association_phone);
            $bid->saveMeta("association_email",$request->association_email);
            $bid->saveMeta("association_website",$request->association_website); 
            $bid->saveMeta("communityFeatureOther",$request->communityFeatureOther);
            $bid->saveMeta("has_hoa",$request->has_hoa);
            $bid->saveMeta("assocRequired",$request->assocRequired);
            $bid->saveMeta("oldHouse",$request->oldHouse);
            $bid->saveMeta("hoa_fee_requirenment",$request->hoa_fee_requirenment);
            $bid->saveMeta("feeReq",$request->feeReq);
            $bid->saveMeta("paySchedule",$request->paySchedule);
            $bid->saveMeta("association_approval_fee",$request->association_approval_fee);
            $bid->saveMeta("parking_fee_for_tenants",$request->parking_fee_for_tenants);
            $bid->saveMeta("association_security_deposit",$request->association_security_deposit);
            $bid->saveMeta("other_association_fee",$request->other_association_fee);
            $bid->saveMeta("community_feature",json_encode($request->community_feature));
            $bid->saveMeta("communityOther", $request->communityOther);
            $bid->saveMeta("association_amenitie",json_encode($request->association_amenitie));
            $bid->saveMeta("description",$request->description);
            $bid->saveMeta("disclaimer",$request->disclaimer);
            $bid->saveMeta("driving_directions",$request->driving_directions);
            $bid->saveMeta("tenant_agent_compensation",$request->tenant_agent_compensation);
            $bid->saveMeta("compensationYes",$request->compensationYes);
            $bid->saveMeta("first_name",$request->first_name);
            $bid->saveMeta("last_name",$request->last_name);
            $bid->saveMeta("agent_phone",$request->agent_phone);
            $bid->saveMeta("agent_email",$request->agent_email);
            $bid->saveMeta("agent_brokerage",$request->agent_brokerage);
            $bid->saveMeta("agent_license_no",$request->agent_license_no);
            $bid->saveMeta("agent_mls_id",$request->agent_mls_id);
            $bid->saveMeta("realEstate",$request->realEstate);
            $bid->saveMeta("three_d_tour",$request->three_d_tour);
            $bid->saveMeta('video_type', $request->video_type);
            $bid->saveMeta('youtube_video_link', $request->youtube_video_link);
            $bid->saveMeta('vimeo_video_link', $request->vimeo_video_link);
            $route = route('tenant.criteria.auction.view', $id);

            // "picPropUpload" => Illuminate\Http\UploadedFile {#1675 ▶}
            // "videoUpload" => Illuminate\Http\UploadedFile {#1674 ▶}
            // "planPropUpload" => Illuminate\Http\UploadedFile {#1676 ▶}
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];

            $allowedVideos = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v'];

            $visible_upload_file = [];
            // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf



            // photos and video uploads


            // Picture
            if ($request->hasFile('picPropUpload')) {
                $picPropUpload = $request->file('picPropUpload');
                $originalName = $picPropUpload->getClientOriginalName();
                $extension = $picPropUpload->getClientOriginalExtension();
                $imageSize = $picPropUpload->getSize();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $imageName = $uuid . '.' . $extension;
                    $picPropUpload->move(public_path('auction/images'), $imageName);
                    $picPropUpload = 'auction/images/' . $imageName;
                }
                $bid->saveMeta('picPropUpload', $picPropUpload);
            }
            // Picture

            // Video
            if ($request->hasFile('videoUpload')) {
                $videoUpload = $request->file('videoUpload');
                $originalName = $videoUpload->getClientOriginalName();
                $extension = $videoUpload->getClientOriginalExtension();
                $videoSize = $videoUpload->getSize();
                $check = in_array($extension, $allowedVideos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $videoName = $uuid . '.' . $extension;
                    $videoUpload->move(public_path('auction/videos'), $videoName);
                    $videoUpload = 'auction/videos/' . $videoName;
                }
                $bid->saveMeta('videoUpload', $videoUpload);
            }
            // Video


            //Floor Plan
            if ($request->hasFile('planPropUpload')) {
                $planPropUpload = $request->file('planPropUpload');
                $originalName = $planPropUpload->getClientOriginalName();
                $extension = $planPropUpload->getClientOriginalExtension();
                $imageSize = $planPropUpload->getSize();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $imageName = $uuid . '.' . $extension;
                    $planPropUpload->move(public_path('auction/images'), $imageName);
                    $planPropUpload = 'auction/images/' . $imageName;
                }
                $bid->saveMeta('planPropUpload', $planPropUpload);
            }

            //Floor Plan

            // Business Card

            DB::commit();
            return redirect()->to($route)->with('success', 'Bid Added Successfully');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add bid');
        }
    }

    public function accept_bid(Request $request)
    {
        $pab = TenantCriteriaAuctionBid::whereId($request->bid_id)->first();
        $pab->is_accepted = true;
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = TenantCriteriaAuction::whereId($request->auction_id)->first();
        $pa->is_sold = true;
        $pa->sold_date = date('Y-m-d H:i:s');

        if ($pab->save() && $pa->save()) {
            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }
}
