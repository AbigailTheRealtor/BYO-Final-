<?php

namespace App\Http\Controllers;

use App\Models\LandlordAuction;
use App\Models\LandlordAuctionBid;
use App\Models\PropertyType;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Mail\Agent\PropertyAdd;
use Illuminate\Support\Facades\Mail;
use App\Jobs\Agent\PropertyAdd as SendMailToAgentAndSeller;

class LandlordAuctionController extends Controller
{
    public function index()
    {
        $page_data['title'] = 'Auction for Landlords';
        $page_data['property_types'] = PropertyType::orderBy('sort', 'asc')->get();
        return view('landlord_auction.add', $page_data);
    }

    public function store(Request $request)
    {
        // dd($request->all());

        try {
            DB::beginTransaction();
            $landlord_auction = new LandlordAuction();
            $landlord_auction->user_id = Auth::user()->id;
            $landlord_auction->address = $request->address;
            $landlord_auction->city = $request->city;
            $landlord_auction->state = $request->state;
            $landlord_auction->county = $request->county;
            $landlord_auction->listing_date = $request->listing_date;
            $landlord_auction->expiration_date = $request->expiration_date;
            $landlord_auction->save();
            $landlord_auction->saveMeta("unit_number", $request->unit_number);
            $landlord_auction->saveMeta('address', $request->address);
            $landlord_auction->saveMeta("property_lat", $request->property_lat);
            $landlord_auction->saveMeta("property_lng", $request->property_lng);
            $landlord_auction->saveMeta("google_place_id", $request->google_place_id);
            $landlord_auction->saveMeta("formatted_address", $request->formatted_address);
            $landlord_auction->saveMeta("city", $request->city);
            $landlord_auction->saveMeta("county", $request->county);
            $landlord_auction->saveMeta("state", $request->state);
            $landlord_auction->saveMeta("listing_date", $request->listing_date);
            $landlord_auction->saveMeta("expiration_date", $request->expiration_date);
            $landlord_auction->saveMeta("listing_service_type", $request->listing_service_type);
            $landlord_auction->saveMeta("representation", $request->representation);
            $landlord_auction->saveMeta("auction_type", $request->auction_type);
            $landlord_auction->saveMeta("auction_length", $request->auction_length);
            $landlord_auction->saveMeta("property_type", $request->property_type);
            $landlord_auction->saveMeta("property_items", json_encode($request->property_items));
            $landlord_auction->saveMeta("leasePropOption", $request->leasePropOption);
            $landlord_auction->saveMeta("singleRoom", json_encode($request->singleRoom));
            $landlord_auction->saveMeta("propConditions", $request->propConditions);
            $landlord_auction->saveMeta("propOther", $request->propOther);

            $landlord_auction->saveMeta("rentNow", $request->rentNow);
            $landlord_auction->saveMeta("rentNowSqft", $request->rentNowSqft);
            $landlord_auction->saveMeta("startingPrice", $request->startingPrice);
            $landlord_auction->saveMeta("reservePrice", $request->reservePrice);

            $landlord_auction->saveMeta("price", $request->price);
            $landlord_auction->saveMeta("list_price_per_sq", $request->list_price_per_sq);
            $landlord_auction->saveMeta("leaseDate", $request->leaseDate);
            $landlord_auction->saveMeta("leaseTime", json_encode($request->leaseTime));
            $landlord_auction->saveMeta("other_lease_duration", $request->other_lease_duration);
            $landlord_auction->saveMeta("leaseTerms", json_encode($request->leaseTerms));
            $landlord_auction->saveMeta("other_lease_terms", $request->other_lease_terms);
            $landlord_auction->saveMeta("start_date", $request->start_date);
            $landlord_auction->saveMeta("end_date", $request->end_date);
            $landlord_auction->saveMeta("buildingFeatures", json_encode($request->buildingFeatures));
            $landlord_auction->saveMeta("buildingFeaturesOther", $request->buildingFeaturesOther);
            $landlord_auction->saveMeta("frequency", json_encode($request->frequency));
            $landlord_auction->saveMeta("season_runs_from", $request->season_runs_from);
            $landlord_auction->saveMeta("season_runs_to", $request->season_runs_to);
            $landlord_auction->saveMeta("roomPrimaryOther", $request->roomPrimaryOther);
            $landlord_auction->saveMeta("tenant_pays", json_encode($request->tenant_pays));
            $landlord_auction->saveMeta("tenantPaysOther", $request->tenantPaysOther);
            $landlord_auction->saveMeta("ownerPays", json_encode($request->ownerPays));
            $landlord_auction->saveMeta("landlordPaysOther", $request->landlordPaysOther);
            $landlord_auction->saveMeta("rent", json_encode($request->rent));
            $landlord_auction->saveMeta("rentOther", $request->rentOther);
            $landlord_auction->saveMeta("required_at_move_in", $request->required_at_move_in);
            $landlord_auction->saveMeta("leaseTermOther", $request->leaseTermOther);
            $landlord_auction->saveMeta("required_move_in", $request->required_move_in);
            $landlord_auction->saveMeta("required_move_in_amount", $request->required_move_in_amount);
            $landlord_auction->saveMeta("firstMonthDeposit", $request->firstMonthDeposit);
            $landlord_auction->saveMeta("lastMonthDeposit", $request->lastMonthDeposit);
            $landlord_auction->saveMeta("securityDeposit", $request->securityDeposit);
            $landlord_auction->saveMeta("firstMonthSecond", $request->firstMonthSecond);
            $landlord_auction->saveMeta("lastMonthSecond", $request->lastMonthSecond);
            $landlord_auction->saveMeta("securityDepositSecond", $request->securityDepositSecond);
            $landlord_auction->saveMeta("petDepositSecond", $request->petDepositSecond);
            $landlord_auction->saveMeta("exitCleaningFeeSecond", $request->exitCleaningFeeSecond);
            $landlord_auction->saveMeta("applicationFeeSecond", $request->applicationFeeSecond);
            $landlord_auction->saveMeta("applicationLinkSecond", $request->applicationLinkSecond);
            $landlord_auction->saveMeta("firstMonthThird", $request->firstMonthThird);
            $landlord_auction->saveMeta("lastMonthThird", $request->lastMonthThird);
            $landlord_auction->saveMeta("securityDepositThird", $request->securityDepositThird);
            $landlord_auction->saveMeta("petDepositThird", $request->petDepositThird);
            $landlord_auction->saveMeta("exitCleaningFeeThird", $request->exitCleaningFeeThird);
            $landlord_auction->saveMeta("applicationFeeThird", $request->applicationFeeThird);
            $landlord_auction->saveMeta("applicationLinkThird", $request->applicationLinkThird);
            $landlord_auction->saveMeta("firstMonthFour", $request->firstMonthFour);
            $landlord_auction->saveMeta("lastMonthFour", $request->lastMonthFour);
            $landlord_auction->saveMeta("securityDepositFour", $request->securityDepositFour);
            $landlord_auction->saveMeta("applicationFeeFour", $request->applicationFeeFour);
            $landlord_auction->saveMeta("exitCleaningFeeFour", $request->exitCleaningFeeFour);
            $landlord_auction->saveMeta("applicationLinkFour", $request->applicationLinkFour);
            $landlord_auction->saveMeta("vacationTaxFour", $request->vacationTaxFour);
            $landlord_auction->saveMeta("firstMonthFive", $request->firstMonthFive);
            $landlord_auction->saveMeta("securityDepositFive", $request->securityDepositFive);
            $landlord_auction->saveMeta("exitCleaningFeeFive", $request->exitCleaningFeeFive);
            $landlord_auction->saveMeta("applicationFeeFive", $request->applicationFeeFive);
            $landlord_auction->saveMeta("applicationLinkFive", $request->applicationLinkFive);
            $landlord_auction->saveMeta("vacationTaxFive", $request->vacationTaxFive);
            $landlord_auction->saveMeta("firstMonthSix", $request->firstMonthSix);
            $landlord_auction->saveMeta("securityDepositSix", $request->securityDepositSix);
            $landlord_auction->saveMeta("exitCleaningFeeSix", $request->exitCleaningFeeSix);
            $landlord_auction->saveMeta("applicationFeeSix", $request->applicationFeeSix);
            $landlord_auction->saveMeta("applicationLinkSix", $request->applicationLinkSix);
            $landlord_auction->saveMeta("firstMonthSeven", $request->firstMonthSeven);
            $landlord_auction->saveMeta("securityDepositSeven", $request->securityDepositSeven);
            $landlord_auction->saveMeta("exitCleaningFeeSeven", $request->exitCleaningFeeSeven);
            $landlord_auction->saveMeta("applicationFeeSeven", $request->applicationFeeSeven);
            $landlord_auction->saveMeta("applicationLinkSeven", $request->applicationLinkSeven);
            $landlord_auction->saveMeta("timeFrame", $request->timeFrame);
            $landlord_auction->saveMeta("timeFrameMultiple", $request->timeFrameMultiple);
            $landlord_auction->saveMeta("specialMoveOption", $request->specialMoveOption);
            $landlord_auction->saveMeta("specialMove", $request->specialMove);
            $landlord_auction->saveMeta("petsOpt", $request->petsOpt);
            $landlord_auction->saveMeta("petsNumber", $request->petsNumber);
            $landlord_auction->saveMeta("petsType", $request->petsType);
            $landlord_auction->saveMeta("petsWeight", $request->petsWeight);
            $landlord_auction->saveMeta("petsFee", $request->petsFee);
            $landlord_auction->saveMeta("petsAmount", $request->petsAmount);
            $landlord_auction->saveMeta("petsFund", $request->petsFund);
            $landlord_auction->saveMeta("offer_allowed_occupants", $request->offer_allowed_occupants);
            $landlord_auction->saveMeta("custom_occupants", $request->custom_occupants);
            $landlord_auction->saveMeta("creditScore", $request->creditScore);
            $landlord_auction->saveMeta("offer_min_net_income", $request->offer_min_net_income);
            $landlord_auction->saveMeta("eviction", $request->eviction);
            $landlord_auction->saveMeta("offer_prior_felony", $request->offer_prior_felony);
            $landlord_auction->saveMeta("bedroom", $request->bedroom);
            $landlord_auction->saveMeta("other_bedrooms", $request->other_bedrooms);
            $landlord_auction->saveMeta("bathrooms", $request->bathrooms);
            $landlord_auction->saveMeta("other_bathrooms", $request->other_bathrooms);
            $landlord_auction->saveMeta("heated_sqft", $request->heated_sqft);
            $landlord_auction->saveMeta("net_leasable_sqft", $request->net_leasable_sqft);
            $landlord_auction->saveMeta("sqft_total", $request->sqft_total);
            $landlord_auction->saveMeta("heated_source", $request->heated_source);
            $landlord_auction->saveMeta("otherSqft", $request->otherSqft);
            $landlord_auction->saveMeta("total_acreage", $request->total_acreage);
            $landlord_auction->saveMeta("yearBuilt", $request->yearBuilt);
            $landlord_auction->saveMeta("lotSize", $request->lotSize);
            $landlord_auction->saveMeta("legarName", $request->legarName);
            $landlord_auction->saveMeta("taxId", $request->taxId);
            $landlord_auction->saveMeta("zoneCode", $request->zoneCode);
            $landlord_auction->saveMeta("zoning", $request->zoning);
            $landlord_auction->saveMeta("tax_year", $request->tax_year);
            $landlord_auction->saveMeta("taxes_annual", $request->taxes_annual);
            $landlord_auction->saveMeta("legal_description", $request->legal_description);
            $landlord_auction->saveMeta("no_of_parcels", $request->no_of_parcels);
            $landlord_auction->saveMeta("additional_parcels", $request->additional_parcels);
            $landlord_auction->saveMeta("additional_tax_id", $request->additional_tax_id);
            $landlord_auction->saveMeta("furnishings", $request->furnishings);
            $landlord_auction->saveMeta("appliances", json_encode($request->appliances));
            $landlord_auction->saveMeta("appliancesOther", $request->appliancesOther);
            $landlord_auction->saveMeta("firePlace", $request->firePlace);
            $landlord_auction->saveMeta("amenities", json_encode($request->amenities));
            $landlord_auction->saveMeta("otherAmenities", $request->otherAmenities);
            $landlord_auction->saveMeta("features", json_encode($request->features));
            $landlord_auction->saveMeta("interiorFeatures", json_encode($request->interiorFeatures));
            $landlord_auction->saveMeta("interiorFeatureOther", $request->interiorFeatureOther);
            $landlord_auction->saveMeta("additional_rooms", json_encode($request->additional_rooms));
            $landlord_auction->saveMeta("roomOther", $request->roomOther);
            $landlord_auction->saveMeta("laundry", json_encode($request->laundry));
            $landlord_auction->saveMeta("laundryOther", $request->laundryOther);
            $landlord_auction->saveMeta("propFloors", $request->propFloors);
            $landlord_auction->saveMeta("floorNumber", $request->floorNumber);
            $landlord_auction->saveMeta("totalFloors", $request->totalFloors);
            $landlord_auction->saveMeta("building_elevator", $request->building_elevator);
            $landlord_auction->saveMeta("floor_covering", json_encode($request->floor_covering));
            $landlord_auction->saveMeta("floorConvringOther", $request->floorConvringOther);
            $landlord_auction->saveMeta("roomDimensions", json_encode($request->roomDimensions));
            $landlord_auction->saveMeta("room_type", json_encode($request->room_type));
            $landlord_auction->saveMeta("room_details_data", json_encode($request->room_details_data));
            $landlord_auction->saveMeta("room_level", json_encode($request->room_level));
            $landlord_auction->saveMeta("bedroomCloset", json_encode($request->bedroomCloset));
            $landlord_auction->saveMeta("roomPrimary", json_encode($request->roomPrimary));
            $landlord_auction->saveMeta("room_feature", json_encode($request->room_feature));
            $landlord_auction->saveMeta("roomFeatueOther", $request->roomFeatueOther);
            $landlord_auction->saveMeta("waterAccessOpt", json_encode($request->waterAccessOpt));
            $landlord_auction->saveMeta("water_access", json_encode($request->water_access));
            $landlord_auction->saveMeta("has_water_view", $request->has_water_view);
            $landlord_auction->saveMeta("water_view", json_encode($request->water_view));
            $landlord_auction->saveMeta("has_water_extra", $request->has_water_extra);
            $landlord_auction->saveMeta("water_extras", json_encode($request->water_extras));
            $landlord_auction->saveMeta("has_dock", $request->has_dock);
            $landlord_auction->saveMeta("dock", json_encode($request->dock));
            $landlord_auction->saveMeta("dockDescription", json_encode($request->dockDescription));
            $landlord_auction->saveMeta("dockLiftCapacity", $request->dockLiftCapacity);
            $landlord_auction->saveMeta("dockYearBuilt", $request->dockYearBuilt);
            $landlord_auction->saveMeta("dockDimension", $request->dockDimension);
            $landlord_auction->saveMeta("dockMaintenanceFee", $request->dockMaintenanceFee);
            $landlord_auction->saveMeta("dockMaintenanceFeeFrequency", $request->dockMaintenanceFeeFrequency);

            $landlord_auction->saveMeta("has_water_fontage", $request->has_water_fontage);
            $landlord_auction->saveMeta("waterFrontageView", json_encode($request->waterFrontageView));
            $landlord_auction->saveMeta("utilities", json_encode($request->utilities));
            $landlord_auction->saveMeta("otherUtilities", $request->otherUtilities);
            $landlord_auction->saveMeta("water", json_encode($request->water));
            $landlord_auction->saveMeta("otherWater", $request->otherWater);
            $landlord_auction->saveMeta("sewer", json_encode($request->sewer));
            $landlord_auction->saveMeta('otherSewer', $request->otherSewer);
            $landlord_auction->saveMeta("airConditioning", json_encode($request->airConditioning));
            $landlord_auction->saveMeta("otherAirCondition", $request->otherAirCondition);
            $landlord_auction->saveMeta("heatingFuel", json_encode($request->heatingFuel));
            $landlord_auction->saveMeta("otherFuel", $request->otherFuel);
            $landlord_auction->saveMeta('carport', $request->carport);
            $landlord_auction->saveMeta("carportOther", $request->carportOther);
            $landlord_auction->saveMeta("garage", $request->garage);
            $landlord_auction->saveMeta("garageOther", $request->garageOther);
            $landlord_auction->saveMeta("poolOpt", $request->poolOpt);
            $landlord_auction->saveMeta("pool", $request->pool);
            $landlord_auction->saveMeta("viewOption", json_encode($request->viewOption));
            $landlord_auction->saveMeta("view", json_encode($request->view));
            $landlord_auction->saveMeta("viewOther", $request->viewOther);
            $landlord_auction->saveMeta("otherParking", $request->otherParking);
            $landlord_auction->saveMeta("front_exposure", $request->front_exposure);
            $landlord_auction->saveMeta("foundation", json_encode($request->foundation));
            $landlord_auction->saveMeta("foundationOther", $request->foundationOther);
            $landlord_auction->saveMeta("exterior_construction", json_encode($request->exterior_construction));
            $landlord_auction->saveMeta("exteriorOther", $request->exteriorOther);
            $landlord_auction->saveMeta("exterior_feature", json_encode($request->exterior_feature));
            $landlord_auction->saveMeta("exteriorFeatureOther", $request->exteriorFeatureOther);
            $landlord_auction->saveMeta("other_structures", json_encode($request->other_structures));
            $landlord_auction->saveMeta("structuresOther", $request->structuresOther);
            $landlord_auction->saveMeta("roadFrontageOther", $request->roadFrontageOther);
            $landlord_auction->saveMeta("road_surface_type", json_encode($request->road_surface_type));
            $landlord_auction->saveMeta("roadSurfaceOther", $request->roadSurfaceOther);
            $landlord_auction->saveMeta("roof", json_encode($request->roof));
            $landlord_auction->saveMeta("roofCementOther", $request->roofCementOther);
            $landlord_auction->saveMeta("adjoining_property", json_encode($request->adjoining_property));
            $landlord_auction->saveMeta("otherFeatures", $request->otherFeatures);
            $landlord_auction->saveMeta("has_condo_enviornment", $request->has_condo_enviornment);
            $landlord_auction->saveMeta("condo_fee", $request->condo_fee);
            $landlord_auction->saveMeta("association_name", $request->association_name);
            $landlord_auction->saveMeta("association_phone", $request->association_phone);
            $landlord_auction->saveMeta("association_email", $request->association_email);
            $landlord_auction->saveMeta("association_website", $request->association_website);
            $landlord_auction->saveMeta("communityFeatureOther", $request->communityFeatureOther);
            $landlord_auction->saveMeta("has_hoa", $request->has_hoa);
            $landlord_auction->saveMeta("assocRequired", $request->assocRequired);
            $landlord_auction->saveMeta("oldHouse", $request->oldHouse);
            $landlord_auction->saveMeta("hoa_fee_requirenment", $request->hoa_fee_requirenment);
            $landlord_auction->saveMeta("feeReq", $request->feeReq);
            $landlord_auction->saveMeta("paySchedule", $request->paySchedule);
            $landlord_auction->saveMeta("association_approval_fee", $request->association_approval_fee);
            $landlord_auction->saveMeta("parking_fee_for_tenants", $request->parking_fee_for_tenants);
            $landlord_auction->saveMeta("association_security_deposit", $request->association_security_deposit);
            $landlord_auction->saveMeta("other_association_fee", $request->other_association_fee);
            $landlord_auction->saveMeta("community_feature", json_encode($request->community_feature));
            $landlord_auction->saveMeta("communityOther", $request->communityOther);
            $landlord_auction->saveMeta("association_amenitie", json_encode($request->association_amenitie));
            $landlord_auction->saveMeta("description", $request->description);
            $landlord_auction->saveMeta("disclaimer", $request->disclaimer);
            $landlord_auction->saveMeta("driving_directions", $request->driving_directions);
            $landlord_auction->saveMeta("tenant_agent_compensation", $request->tenant_agent_compensation);
            $landlord_auction->saveMeta("compensationYes", $request->compensationYes);
            $landlord_auction->saveMeta("compensation_structure", $request->compensation_structure);
            $landlord_auction->saveMeta("compensation_structure_yes", $request->compensation_structure_yes);
            $landlord_auction->saveMeta("first_name", $request->first_name);
            $landlord_auction->saveMeta("last_name", $request->last_name);
            $landlord_auction->saveMeta("agent_phone", $request->agent_phone);
            $landlord_auction->saveMeta("agent_email", $request->agent_email);
            $landlord_auction->saveMeta("agent_brokerage", $request->agent_brokerage);
            $landlord_auction->saveMeta("agent_license_no", $request->agent_license_no);
            $landlord_auction->saveMeta("agent_mls_id", $request->agent_mls_id);
            $landlord_auction->saveMeta("realEstate", $request->realEstate);
            $landlord_auction->saveMeta("three_d_tour", $request->three_d_tour);
            $landlord_auction->saveMeta('video_type', $request->video_type);
            $landlord_auction->saveMeta('youtube_video_link', $request->youtube_video_link);
            $landlord_auction->saveMeta('vimeo_video_link', $request->vimeo_video_link);
            $landlord_auction->saveMeta("listing_ai_faq", json_encode($request->listing_ai_faq ?? []));

            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];

            $allowedVideos = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v'];

            $visible_upload_file = [];
            // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
            // if ($request->hasFile('photo')) {
            //     $photo = $request->file('photo');
            //     $originalName = $photo->getClientOriginalName();
            //     $extension = $photo->getClientOriginalExtension();
            //     $imageSize = $photo->getSize();
            //     $check = in_array($extension, $allowedPhotos);
            //     if ($check) {
            //         $uuid = (string) Str::uuid();
            //         $imageName = $uuid . '.' . $extension;
            //         $photo->move(public_path('auction/images'), $imageName);
            //         $photo = 'auction/images/' . $imageName;
            //     }
            //     $landlord_auction->saveMeta('photo', $photo);
            // }

            // if ($request->hasFile('photo')) {
            //     $photos = $request->file('photo'); // Get the array of uploaded files
            //     $photoLinks = []; // Array to hold photo links
            //     foreach ($photos as $photo)  {
            //         $extension = $photo->getClientOriginalExtension();
            //         $check = in_array($extension, $allowedFiles);

            //         if ($check) {
            //             $uuid = (string) Str::uuid();
            //             $photoName = $uuid . '.' . $extension;
            //             $photo->move(public_path('auction/images'), $photoName);
            //             $photoLinks[] = 'auction/images/' . $photoName; // Store each link
            //         }
            //     }
            //     // dd($photoLinks);

            //     // Save all links as JSON or a comma-separated string
            //     $landlord_auction->saveMeta('photo', json_encode($photoLinks));
            // }

            if ($request->hasFile('photo') && $request->has('photoNames')) {
                $photos = $request->file('photo'); // Get the array of uploaded files
                $photosNames = $request->photoNames;

                $arrangedPhotoArr = [];

                // Loop through each name to arrange the photos accordingly
                foreach ($photosNames as $photoName) {
                    // Filter to find the matching photo
                    $filteredPhotos = array_filter($photos, function ($photo) use ($photoName) {
                        return $photo->getClientOriginalName() === $photoName; // Get original name correctly
                    });

                    // If any photos are found, get the first one
                    if (!empty($filteredPhotos)) {
                        // Directly get the first matched file
                        $arrangedPhotoArr[] = reset($filteredPhotos); // This extracts the first matched file from the filtered array
                    }
                }

                $photoLinks = []; // Array to hold photo links
                foreach ($arrangedPhotoArr as $photo) {
                    $extension = $photo->getClientOriginalExtension();
                    $check = in_array($extension, $allowedFiles);

                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $photoName = $uuid . '.' . $extension;
                        $photo->move(public_path('auction/images'), $photoName);
                        $photoLinks[] = 'auction/images/' . $photoName; // Store each link
                    }
                }
                // dd($photoLinks);

                // Save all links as JSON or a comma-separated string
                $landlord_auction->saveMeta('photo', json_encode($photoLinks));
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
                    $landlord_auction->saveMeta('video', $video);
                }
            }

            if ($request->hasFile('disclosures')) {
                foreach ($request->file('disclosures') as $upload_file) {
                    $originalName = $upload_file->getClientOriginalName();
                    $extensionClosure = $upload_file->getClientOriginalExtension();
                    $imageSize = $upload_file->getSize();
                    // $size = number_format($imageSize / 1048576,2);
                    $check = in_array($extensionClosure, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $file_name = $uuid . '.' . $extensionClosure;
                        $upload_file->move(public_path('auction/images'), $file_name);
                        $disclosures[] = 'auction/images/' . $file_name;
                    }
                }
                $landlord_auction->saveMeta('disclosures', json_encode($disclosures));
            }

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $upload_file) {
                    $originalName = $upload_file->getClientOriginalName();
                    $extensionDoc = $upload_file->getClientOriginalExtension();
                    $imageSize = $upload_file->getSize();
                    // $size = number_format($imageSize / 1048576,2);
                    $check = in_array($extensionDoc, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $file_name = $uuid . '.' . $extensionDoc;
                        $upload_file->move(public_path('auction/images'), $file_name);
                        $documents[] = 'auction/images/' . $file_name;
                    }
                }
                $landlord_auction->saveMeta('documents', json_encode($documents));
            }

            //Floor Plan
            // Photos

            $visible_photos = [];
            if ($request->hasFile('visible_photos')) {

                foreach ($request->file('visible_photos') as $photo) {
                    $originalName = $photo->getClientOriginalName();
                    $extension = $photo->getClientOriginalExtension();
                    $imageSize = $photo->getSize();
                    // $size = number_format($imageSize / 1048576,2);
                    $check = in_array($extension, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $imageName = $uuid . '.' . $extension;
                        $photo->move(public_path('auction/images'), $imageName);
                        $visible_photos[] = 'auction/images/' . $imageName;
                    }
                }
                $landlord_auction->saveMeta('photos', json_encode($visible_photos));
            }
            // Photos

            //Floor Plan
            if ($request->hasFile('visible_note')) {
                $file = $request->visible_note;
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    $landlord_auction->saveMeta('note', 'auction/files/' . $fileName);
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
                $landlord_auction->saveMeta('business_card', $visible_property_picture);
            }
            // Business Card

            // 19 June 2023 for Residential and Income
            // 21 June 2023 for Residential

            $landlord_auction->saveMeta('video_url', $request->video_url);


            // dd('good');

            // // send email notification
            // $users = User::where('user_type', 'seller')->orWhere('user_type', 'buyer')->get();
            // foreach ($users as $key => $user) {

            //     dispatch(new SendMailToAgentAndSeller($user));
            // }

            DB::commit();
            return redirect()->route('agent.landlord.auction', $landlord_auction->id)->with('success', "Auction for landlord has been added successfully");
            // return redirect()->back()->with('success', "Auction for landlord has been added successfully");
        } catch (Exception $e) {
            // dd($e->getMessage());
            //throw $th;
            DB::rollBack();
            // dd($e);
            // return $e->getMessage();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function list(Request $request)
    {
        $page_data['tite'] = "Auctions for Landlords";

        $page_data['type'] = $type = $request->type ?? "2";
        if (Auth::user()->user_type == 'agent') {
            $pendingAuctions = LandlordAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
            $pendingApprovalAuctions = LandlordAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
            $liveAuctions = LandlordAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false]);
            $soldAuctions = LandlordAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true]); //, 'is_paid' => true
            $pendingPaymentAuctions = LandlordAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true, 'is_paid' => false]);
        } else {
            $pendingAuctions = LandlordAuction::where(['buyer_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
            $pendingApprovalAuctions = LandlordAuction::where(['buyer_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
            $liveAuctions = LandlordAuction::where(['buyer_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false]);
            $soldAuctions = LandlordAuction::where(['buyer_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true]); //, 'is_paid' => true
            $pendingPaymentAuctions = LandlordAuction::where(['buyer_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true, 'is_paid' => false]);
        }
        if ($type == "0") {
            // $auctions = $pendingAuctions->get();
        } elseif ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } elseif ($type == "2") {
            $auctions = $liveAuctions->get();
        } elseif ($type == '3') {
            $auctions = $soldAuctions->get();
        } elseif ($type == "4") {
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

        return view('landlord_auction.list', $page_data);
    }

    public function view($id, Request $request)
    {
        // $data = LandlordAuction::with('meta')->find($id);
        // dd($data->get);
        $page_data['auction'] = $auction = LandlordAuction::find($id);
        $page_data['title'] = @$auction->address;
        $page_data['bids'] = $bids = LandlordAuctionBid::with('meta')->where('landlord_auction_id', $id)->whereNull('counter_id')->get();
        if ($auction) {
            return view('landlord_auction.view', $page_data);
        }
    }

    public function viewBid($bid_id)
    {
        $bid = LandlordAuctionBid::findOrFail($bid_id);
        $page_data['title'] = 'Landlord Auction Bid';
        $page_data['bid'] = $bid;
        return view('landlord_auction.view-bid', $page_data);
    }

    public function endAuction($id)
    {
        $auction = landlordAuction::findOrFail($id);
        $auction->update(['auction_ended' => true]);
        return response()->json(['message' => 'Auction ended successfully']);
    }

    public function bidsVisibility($id, $vis)
    {
        $auction = landlordAuction::where('id', $id)->first();
        // WF-6: owner-only — block authenticated IDOR on the bid-visibility toggle.
        abort_unless(auth()->check() && $auction && (int) $auction->user_id === (int) auth()->id(), 403);
        if ($vis == 'show') {
            $auction->display_bids = 1;
            $auction->save();
            return redirect()->back()->with('success', 'Bids list is now visible');
        } else {
            $auction->display_bids = 0;
            $auction->save();
            return redirect()->back()->with('success', 'Bids list is now hidden');
        }
    }

    public function edit($id, Request $request)
    {
        $page_data['auction'] = $auction = LandlordAuction::find($id);
        $page_data['title'] = "Edit Auction for Landlord";
        $page_data['property_types'] = PropertyType::orderBy('sort', 'asc')->get();
        return view('landlord_auction.edit', $page_data);
    }

    public function update($id, Request $request)
    {
        // dd($request->post());
        try {
            DB::beginTransaction();
            $landlord_auction = LandlordAuction::find($id);
            $landlord_auction->user_id = Auth::user()->id;
            $landlord_auction->address = $request->address;
            $landlord_auction->city = $request->city;
            $landlord_auction->state = $request->state;
            $landlord_auction->county = $request->county;
            $landlord_auction->listing_date = $request->listing_date;
            $landlord_auction->expiration_date = $request->expiration_date;
            $landlord_auction->save();
            $landlord_auction->saveMeta("unit_number", $request->unit_number);
            $landlord_auction->saveMeta('address', $request->address);
            $landlord_auction->saveMeta("property_lat", $request->property_lat);
            $landlord_auction->saveMeta("property_lng", $request->property_lng);
            $landlord_auction->saveMeta("google_place_id", $request->google_place_id);
            $landlord_auction->saveMeta("formatted_address", $request->formatted_address);
            $landlord_auction->saveMeta("city", $request->city);
            $landlord_auction->saveMeta("county", $request->county);
            $landlord_auction->saveMeta("state", $request->state);
            $landlord_auction->saveMeta("listing_date", $request->listing_date);
            $landlord_auction->saveMeta("expiration_date", $request->expiration_date);
            $landlord_auction->saveMeta("listing_service_type", $request->listing_service_type);
            $landlord_auction->saveMeta("representation", $request->representation);
            $landlord_auction->saveMeta("auction_type", $request->auction_type);
            $landlord_auction->saveMeta("auction_length", $request->auction_length);
            $landlord_auction->saveMeta("property_type", $request->property_type);
            $landlord_auction->saveMeta("property_items", json_encode($request->property_items));
            $landlord_auction->saveMeta("leasePropOption", $request->leasePropOption);
            $landlord_auction->saveMeta("singleRoom", json_encode($request->singleRoom));
            $landlord_auction->saveMeta("propConditions", $request->propConditions);
            $landlord_auction->saveMeta("propOther", $request->propOther);

            $landlord_auction->saveMeta("rentNow", $request->rentNow);
            $landlord_auction->saveMeta("rentNowSqft", $request->rentNowSqft);
            $landlord_auction->saveMeta("startingPrice", $request->startingPrice);
            $landlord_auction->saveMeta("reservePrice", $request->reservePrice);

            $landlord_auction->saveMeta("price", $request->price);
            $landlord_auction->saveMeta("list_price_per_sq", $request->list_price_per_sq);
            $landlord_auction->saveMeta("leaseDate", $request->leaseDate);
            $landlord_auction->saveMeta("leaseTime", json_encode($request->leaseTime));
            $landlord_auction->saveMeta("other_lease_duration", $request->other_lease_duration);
            $landlord_auction->saveMeta("leaseTerms", json_encode($request->leaseTerms));
            $landlord_auction->saveMeta("other_lease_terms", $request->other_lease_terms);
            $landlord_auction->saveMeta("start_date", $request->start_date);
            $landlord_auction->saveMeta("end_date", $request->end_date);
            $landlord_auction->saveMeta("buildingFeatures", json_encode($request->buildingFeatures));
            $landlord_auction->saveMeta("buildingFeaturesOther", $request->buildingFeaturesOther);
            $landlord_auction->saveMeta("frequency", json_encode($request->frequency));
            $landlord_auction->saveMeta("season_runs_from", $request->season_runs_from);
            $landlord_auction->saveMeta("season_runs_to", $request->season_runs_to);
            $landlord_auction->saveMeta("roomPrimaryOther", $request->roomPrimaryOther);
            $landlord_auction->saveMeta("tenant_pays", json_encode($request->tenant_pays));
            $landlord_auction->saveMeta("tenantPaysOther", $request->tenantPaysOther);
            $landlord_auction->saveMeta("ownerPays", json_encode($request->ownerPays));
            $landlord_auction->saveMeta("landlordPaysOther", $request->landlordPaysOther);
            $landlord_auction->saveMeta("rent", json_encode($request->rent));
            $landlord_auction->saveMeta("rentOther", $request->rentOther);
            $landlord_auction->saveMeta("required_at_move_in", $request->required_at_move_in);
            $landlord_auction->saveMeta("leaseTermOther", $request->leaseTermOther);
            $landlord_auction->saveMeta("required_move_in", $request->required_move_in);
            $landlord_auction->saveMeta("required_move_in_amount", $request->required_move_in_amount);
            $landlord_auction->saveMeta("firstMonthDeposit", $request->firstMonthDeposit);
            $landlord_auction->saveMeta("lastMonthDeposit", $request->lastMonthDeposit);
            $landlord_auction->saveMeta("securityDeposit", $request->securityDeposit);
            $landlord_auction->saveMeta("firstMonthSecond", $request->firstMonthSecond);
            $landlord_auction->saveMeta("lastMonthSecond", $request->lastMonthSecond);
            $landlord_auction->saveMeta("securityDepositSecond", $request->securityDepositSecond);
            $landlord_auction->saveMeta("petDepositSecond", $request->petDepositSecond);
            $landlord_auction->saveMeta("exitCleaningFeeSecond", $request->exitCleaningFeeSecond);
            $landlord_auction->saveMeta("applicationFeeSecond", $request->applicationFeeSecond);
            $landlord_auction->saveMeta("applicationLinkSecond", $request->applicationLinkSecond);
            $landlord_auction->saveMeta("firstMonthThird", $request->firstMonthThird);
            $landlord_auction->saveMeta("lastMonthThird", $request->lastMonthThird);
            $landlord_auction->saveMeta("securityDepositThird", $request->securityDepositThird);
            $landlord_auction->saveMeta("petDepositThird", $request->petDepositThird);
            $landlord_auction->saveMeta("exitCleaningFeeThird", $request->exitCleaningFeeThird);
            $landlord_auction->saveMeta("applicationFeeThird", $request->applicationFeeThird);
            $landlord_auction->saveMeta("applicationLinkThird", $request->applicationLinkThird);
            $landlord_auction->saveMeta("firstMonthFour", $request->firstMonthFour);
            $landlord_auction->saveMeta("lastMonthFour", $request->lastMonthFour);
            $landlord_auction->saveMeta("securityDepositFour", $request->securityDepositFour);
            $landlord_auction->saveMeta("applicationFeeFour", $request->applicationFeeFour);
            $landlord_auction->saveMeta("exitCleaningFeeFour", $request->exitCleaningFeeFour);
            $landlord_auction->saveMeta("applicationLinkFour", $request->applicationLinkFour);
            $landlord_auction->saveMeta("vacationTaxFour", $request->vacationTaxFour);
            $landlord_auction->saveMeta("firstMonthFive", $request->firstMonthFive);
            $landlord_auction->saveMeta("securityDepositFive", $request->securityDepositFive);
            $landlord_auction->saveMeta("exitCleaningFeeFive", $request->exitCleaningFeeFive);
            $landlord_auction->saveMeta("applicationFeeFive", $request->applicationFeeFive);
            $landlord_auction->saveMeta("applicationLinkFive", $request->applicationLinkFive);
            $landlord_auction->saveMeta("vacationTaxFive", $request->vacationTaxFive);
            $landlord_auction->saveMeta("firstMonthSix", $request->firstMonthSix);
            $landlord_auction->saveMeta("securityDepositSix", $request->securityDepositSix);
            $landlord_auction->saveMeta("exitCleaningFeeSix", $request->exitCleaningFeeSix);
            $landlord_auction->saveMeta("applicationFeeSix", $request->applicationFeeSix);
            $landlord_auction->saveMeta("applicationLinkSix", $request->applicationLinkSix);
            $landlord_auction->saveMeta("firstMonthSeven", $request->firstMonthSeven);
            $landlord_auction->saveMeta("securityDepositSeven", $request->securityDepositSeven);
            $landlord_auction->saveMeta("exitCleaningFeeSeven", $request->exitCleaningFeeSeven);
            $landlord_auction->saveMeta("applicationFeeSeven", $request->applicationFeeSeven);
            $landlord_auction->saveMeta("applicationLinkSeven", $request->applicationLinkSeven);
            $landlord_auction->saveMeta("timeFrame", $request->timeFrame);
            $landlord_auction->saveMeta("timeFrameMultiple", $request->timeFrameMultiple);
            $landlord_auction->saveMeta("specialMoveOption", $request->specialMoveOption);
            $landlord_auction->saveMeta("specialMove", $request->specialMove);
            $landlord_auction->saveMeta("petsOpt", $request->petsOpt);
            $landlord_auction->saveMeta("petsNumber", $request->petsNumber);
            $landlord_auction->saveMeta("petsType", $request->petsType);
            $landlord_auction->saveMeta("petsWeight", $request->petsWeight);
            $landlord_auction->saveMeta("petsFee", $request->petsFee);
            $landlord_auction->saveMeta("petsAmount", $request->petsAmount);
            $landlord_auction->saveMeta("petsFund", $request->petsFund);
            $landlord_auction->saveMeta("offer_allowed_occupants", $request->offer_allowed_occupants);
            $landlord_auction->saveMeta("custom_occupants", $request->custom_occupants);
            $landlord_auction->saveMeta("creditScore", $request->creditScore);
            $landlord_auction->saveMeta("offer_min_net_income", $request->offer_min_net_income);
            $landlord_auction->saveMeta("eviction", $request->eviction);
            $landlord_auction->saveMeta("offer_prior_felony", $request->offer_prior_felony);
            $landlord_auction->saveMeta("bedroom", $request->bedroom);
            $landlord_auction->saveMeta("other_bedrooms", $request->other_bedrooms);
            $landlord_auction->saveMeta("bathrooms", $request->bathrooms);
            $landlord_auction->saveMeta("other_bathrooms", $request->other_bathrooms);
            $landlord_auction->saveMeta("heated_sqft", $request->heated_sqft);
            $landlord_auction->saveMeta("net_leasable_sqft", $request->net_leasable_sqft);
            $landlord_auction->saveMeta("sqft_total", $request->sqft_total);
            $landlord_auction->saveMeta("heated_source", $request->heated_source);
            $landlord_auction->saveMeta("otherSqft", $request->otherSqft);
            $landlord_auction->saveMeta("total_acreage", $request->total_acreage);
            $landlord_auction->saveMeta("yearBuilt", $request->yearBuilt);
            $landlord_auction->saveMeta("lotSize", $request->lotSize);
            $landlord_auction->saveMeta("legarName", $request->legarName);
            $landlord_auction->saveMeta("taxId", $request->taxId);
            $landlord_auction->saveMeta("zoneCode", $request->zoneCode);
            $landlord_auction->saveMeta("zoning", $request->zoning);
            $landlord_auction->saveMeta("tax_year", $request->tax_year);
            $landlord_auction->saveMeta("taxes_annual", $request->taxes_annual);
            $landlord_auction->saveMeta("legal_description", $request->legal_description);
            $landlord_auction->saveMeta("no_of_parcels", $request->no_of_parcels);
            $landlord_auction->saveMeta("additional_parcels", $request->additional_parcels);
            $landlord_auction->saveMeta("additional_tax_id", $request->additional_tax_id);
            $landlord_auction->saveMeta("furnishings", $request->furnishings);
            $landlord_auction->saveMeta("appliances", json_encode($request->appliances));
            $landlord_auction->saveMeta("appliancesOther", $request->appliancesOther);
            $landlord_auction->saveMeta("firePlace", $request->firePlace);
            $landlord_auction->saveMeta("amenities", json_encode($request->amenities));
            $landlord_auction->saveMeta("otherAmenities", $request->otherAmenities);
            $landlord_auction->saveMeta("features", json_encode($request->features));
            $landlord_auction->saveMeta("interiorFeatures", json_encode($request->interiorFeatures));
            $landlord_auction->saveMeta("interiorFeatureOther", $request->interiorFeatureOther);
            $landlord_auction->saveMeta("additional_rooms", json_encode($request->additional_rooms));
            $landlord_auction->saveMeta("roomOther", $request->roomOther);
            $landlord_auction->saveMeta("laundry", json_encode($request->laundry));
            $landlord_auction->saveMeta("laundryOther", $request->laundryOther);
            $landlord_auction->saveMeta("propFloors", $request->propFloors);
            $landlord_auction->saveMeta("floorNumber", $request->floorNumber);
            $landlord_auction->saveMeta("totalFloors", $request->totalFloors);
            $landlord_auction->saveMeta("building_elevator", $request->building_elevator);
            $landlord_auction->saveMeta("floor_covering", json_encode($request->floor_covering));
            $landlord_auction->saveMeta("floorConvringOther", $request->floorConvringOther);
            $landlord_auction->saveMeta("roomDimensions", json_encode($request->roomDimensions));
            $landlord_auction->saveMeta("room_type", json_encode($request->room_type));
            $landlord_auction->saveMeta("room_details_data", json_encode($request->room_details_data));
            $landlord_auction->saveMeta("room_level", json_encode($request->room_level));
            $landlord_auction->saveMeta("bedroomCloset", json_encode($request->bedroomCloset));
            $landlord_auction->saveMeta("roomPrimary", json_encode($request->roomPrimary));
            $landlord_auction->saveMeta("room_feature", json_encode($request->room_feature));
            $landlord_auction->saveMeta("roomFeatueOther", $request->roomFeatueOther);
            $landlord_auction->saveMeta("waterAccessOpt", json_encode($request->waterAccessOpt));
            $landlord_auction->saveMeta("water_access", json_encode($request->water_access));
            $landlord_auction->saveMeta("has_water_view", $request->has_water_view);
            $landlord_auction->saveMeta("water_view", json_encode($request->water_view));
            $landlord_auction->saveMeta("has_water_extra", $request->has_water_extra);
            $landlord_auction->saveMeta("water_extras", json_encode($request->water_extras));
            $landlord_auction->saveMeta("has_dock", $request->has_dock);
            $landlord_auction->saveMeta("dock", json_encode($request->dock));
            $landlord_auction->saveMeta("dockDescription", json_encode($request->dockDescription));
            $landlord_auction->saveMeta("dockLiftCapacity", $request->dockLiftCapacity);
            $landlord_auction->saveMeta("dockYearBuilt", $request->dockYearBuilt);
            $landlord_auction->saveMeta("dockDimension", $request->dockDimension);
            $landlord_auction->saveMeta("dockMaintenanceFee", $request->dockMaintenanceFee);
            $landlord_auction->saveMeta("dockMaintenanceFeeFrequency", $request->dockMaintenanceFeeFrequency);

            $landlord_auction->saveMeta("has_water_fontage", $request->has_water_fontage);
            $landlord_auction->saveMeta("waterFrontageView", json_encode($request->waterFrontageView));
            $landlord_auction->saveMeta("utilities", json_encode($request->utilities));
            $landlord_auction->saveMeta("otherUtilities", $request->otherUtilities);
            $landlord_auction->saveMeta("water", json_encode($request->water));
            $landlord_auction->saveMeta("otherWater", $request->otherWater);
            $landlord_auction->saveMeta("sewer", json_encode($request->sewer));
            $landlord_auction->saveMeta('otherSewer', $request->otherSewer);
            $landlord_auction->saveMeta("airConditioning", json_encode($request->airConditioning));
            $landlord_auction->saveMeta("otherAirCondition", $request->otherAirCondition);
            $landlord_auction->saveMeta("heatingFuel", json_encode($request->heatingFuel));
            $landlord_auction->saveMeta("otherFuel", $request->otherFuel);
            $landlord_auction->saveMeta('carport', $request->carport);
            $landlord_auction->saveMeta("carportOther", $request->carportOther);
            $landlord_auction->saveMeta("garage", $request->garage);
            $landlord_auction->saveMeta("garageOther", $request->garageOther);
            $landlord_auction->saveMeta("poolOpt", $request->poolOpt);
            $landlord_auction->saveMeta("pool", $request->pool);
            $landlord_auction->saveMeta("viewOption", json_encode($request->viewOption));
            $landlord_auction->saveMeta("view", json_encode($request->view));
            $landlord_auction->saveMeta("viewOther", $request->viewOther);
            $landlord_auction->saveMeta("otherParking", $request->otherParking);
            $landlord_auction->saveMeta("front_exposure", $request->front_exposure);
            $landlord_auction->saveMeta("foundation", json_encode($request->foundation));
            $landlord_auction->saveMeta("foundationOther", $request->foundationOther);
            $landlord_auction->saveMeta("exterior_construction", json_encode($request->exterior_construction));
            $landlord_auction->saveMeta("exteriorOther", $request->exteriorOther);
            $landlord_auction->saveMeta("exterior_feature", json_encode($request->exterior_feature));
            $landlord_auction->saveMeta("exteriorFeatureOther", $request->exteriorFeatureOther);
            $landlord_auction->saveMeta("other_structures", json_encode($request->other_structures));
            $landlord_auction->saveMeta("structuresOther", $request->structuresOther);
            $landlord_auction->saveMeta("roadFrontageOther", $request->roadFrontageOther);
            $landlord_auction->saveMeta("road_surface_type", json_encode($request->road_surface_type));
            $landlord_auction->saveMeta("roadSurfaceOther", $request->roadSurfaceOther);
            $landlord_auction->saveMeta("roof", json_encode($request->roof));
            $landlord_auction->saveMeta("roofCementOther", $request->roofCementOther);
            $landlord_auction->saveMeta("adjoining_property", json_encode($request->adjoining_property));
            $landlord_auction->saveMeta("otherFeatures", $request->otherFeatures);
            $landlord_auction->saveMeta("has_condo_enviornment", $request->has_condo_enviornment);
            $landlord_auction->saveMeta("condo_fee", $request->condo_fee);
            $landlord_auction->saveMeta("association_name", $request->association_name);
            $landlord_auction->saveMeta("association_phone", $request->association_phone);
            $landlord_auction->saveMeta("association_email", $request->association_email);
            $landlord_auction->saveMeta("association_website", $request->association_website);
            $landlord_auction->saveMeta("communityFeatureOther", $request->communityFeatureOther);
            $landlord_auction->saveMeta("has_hoa", $request->has_hoa);
            $landlord_auction->saveMeta("assocRequired", $request->assocRequired);
            $landlord_auction->saveMeta("oldHouse", $request->oldHouse);
            $landlord_auction->saveMeta("hoa_fee_requirenment", $request->hoa_fee_requirenment);
            $landlord_auction->saveMeta("feeReq", $request->feeReq);
            $landlord_auction->saveMeta("paySchedule", $request->paySchedule);
            $landlord_auction->saveMeta("association_approval_fee", $request->association_approval_fee);
            $landlord_auction->saveMeta("parking_fee_for_tenants", $request->parking_fee_for_tenants);
            $landlord_auction->saveMeta("association_security_deposit", $request->association_security_deposit);
            $landlord_auction->saveMeta("other_association_fee", $request->other_association_fee);
            $landlord_auction->saveMeta("community_feature", json_encode($request->community_feature));
            $landlord_auction->saveMeta("communityOther", $request->communityOther);
            $landlord_auction->saveMeta("association_amenitie", json_encode($request->association_amenitie));
            $landlord_auction->saveMeta("description", $request->description);
            $landlord_auction->saveMeta("disclaimer", $request->disclaimer);
            $landlord_auction->saveMeta("driving_directions", $request->driving_directions);
            $landlord_auction->saveMeta("tenant_agent_compensation", $request->tenant_agent_compensation);
            $landlord_auction->saveMeta("compensationYes", $request->compensationYes);
            $landlord_auction->saveMeta("compensation_structure", $request->compensation_structure);
            $landlord_auction->saveMeta("compensation_structure_yes", $request->compensation_structure_yes);
            $landlord_auction->saveMeta("first_name", $request->first_name);
            $landlord_auction->saveMeta("last_name", $request->last_name);
            $landlord_auction->saveMeta("agent_phone", $request->agent_phone);
            $landlord_auction->saveMeta("agent_email", $request->agent_email);
            $landlord_auction->saveMeta("agent_brokerage", $request->agent_brokerage);
            $landlord_auction->saveMeta("agent_license_no", $request->agent_license_no);
            $landlord_auction->saveMeta("agent_mls_id", $request->agent_mls_id);
            $landlord_auction->saveMeta("realEstate", $request->realEstate);
            $landlord_auction->saveMeta("three_d_tour", $request->three_d_tour);
            $landlord_auction->saveMeta('video_type', $request->video_type);
            $landlord_auction->saveMeta('youtube_video_link', $request->youtube_video_link);
            $landlord_auction->saveMeta('vimeo_video_link', $request->vimeo_video_link);
            $landlord_auction->saveMeta("listing_ai_faq", json_encode($request->listing_ai_faq ?? []));

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
                $landlord_auction->saveMeta('property_picture', $visible_property_picture);
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
                $landlord_auction->saveMeta('property_video', $visible_property_video);
            }

            // Video


            //Floor Plan
            if ($request->hasFile('visible_upload_file')) {


                foreach ($request->file('visible_upload_file') as $upload_file) {
                    $originalName = $upload_file->getClientOriginalName();
                    $extension = $upload_file->getClientOriginalExtension();
                    $imageSize = $upload_file->getSize();
                    // $size = number_format($imageSize / 1048576,2);
                    $check = in_array($extension, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $file_name = $uuid . '.' . $extension;
                        $upload_file->move(public_path('auction/images'), $file_name);
                        $visible_upload_file[] = 'auction/images/' . $file_name;
                    }
                }
                $landlord_auction->saveMeta('visible_upload_file', json_encode($visible_upload_file));
            }

            //Floor Plan
            // Photos

            $visible_photos = [];
            if ($request->hasFile('visible_photos')) {

                foreach ($request->file('visible_photos') as $photo) {
                    $originalName = $photo->getClientOriginalName();
                    $extension = $photo->getClientOriginalExtension();
                    $imageSize = $photo->getSize();
                    // $size = number_format($imageSize / 1048576,2);
                    $check = in_array($extension, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $imageName = $uuid . '.' . $extension;
                        $photo->move(public_path('auction/images'), $imageName);
                        $visible_photos[] = 'auction/images/' . $imageName;
                    }
                }

                $landlord_auction->saveMeta('photos', json_encode($visible_photos));
            }
            // Photos

            if ($request->hasFile('photo') && $request->has('photoNames')) {
                $photos = $request->file('photo'); // Get the array of uploaded files
                $photosNames = $request->photoNames;

                $arrangedPhotoArr = [];

                // Loop through each name to arrange the photos accordingly
                foreach ($photosNames as $photoName) {
                    // Filter to find the matching photo
                    $filteredPhotos = array_filter($photos, function ($photo) use ($photoName) {
                        return $photo->getClientOriginalName() === $photoName; // Get original name correctly
                    });

                    // If any photos are found, get the first one
                    if (!empty($filteredPhotos)) {
                        // Directly get the first matched file
                        $arrangedPhotoArr[] = reset($filteredPhotos); // This extracts the first matched file from the filtered array
                    }
                }

                $photoLinks = []; // Array to hold photo links
                foreach ($arrangedPhotoArr as $photo) {
                    $extension = $photo->getClientOriginalExtension();
                    $check = in_array($extension, $allowedFiles);

                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $photoName = $uuid . '.' . $extension;
                        $photo->move(public_path('auction/images'), $photoName);
                        $photoLinks[] = 'auction/images/' . $photoName; // Store each link
                    }
                }
                // dd($photoLinks);

                // Save all links as JSON or a comma-separated string
                $landlord_auction->saveMeta('photo', json_encode($photoLinks));
            }

            //Floor Plan
            if ($request->hasFile('visible_note')) {
                $file = $request->visible_note;
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    $landlord_auction->saveMeta('note', 'auction/files/' . $fileName);
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
                $landlord_auction->saveMeta('business_card', $visible_property_picture);
            }
            // Business Card
            // 19 June 2023 for Residential and Income

            $landlord_auction->saveMeta('video_url', $request->video_url);

            DB::commit();
            return redirect()->route('agent.landlord.auction', $id)->with('success', "Auction for landlord has been updated successfully");
        } catch (Exception $e) {
            //throw $th;
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function add_bid(Request $request, $id)
    {
        $page_data['auction'] = $auction = LandlordAuction::find($id);
        $page_data['title'] = "Add Bid on Auction for Landlord - {$auction->address}";
        return view('landlord_auction.add-bid', $page_data);
    }

    public function save_bid($id, Request $request)
    {
        $page_data['auction'] = $auction = LandlordAuction::find($id);
        try {
            DB::beginTransaction();
            $auction_bid = new LandlordAuctionBid();
            $auction_bid->user_id = Auth::user()->id;
            $auction_bid->landlord_auction_id = $auction->id;
            $auction_bid->save();
            $auction_bid->saveMeta('offered_price', $request->offered_price);
            $auction_bid->saveMeta('lease_terms', json_encode($request->lease_terms));
            $auction_bid->saveMeta('price', $request->price);
            $auction_bid->saveMeta('start_date', $request->start_date);
            $auction_bid->saveMeta('days_until_start_date', $request->days_until_start_date);
            $auction_bid->saveMeta('end_date', $request->end_date);
            $auction_bid->saveMeta('securityDeposit', $request->securityDeposit);
            $auction_bid->saveMeta('offered_price', $request->offered_price);
            $auction_bid->saveMeta('setPrice', $request->setPrice);
            $auction_bid->saveMeta('autobidPrice', $request->autobidPrice);
            $auction_bid->saveMeta('highestOffer', $request->highestOffer);
            $auction_bid->saveMeta('bestTerms', $request->bestTerms);
            $auction_bid->saveMeta('occupants', $request->occupants);
            $auction_bid->saveMeta('petOpt', $request->petOpt);
            $auction_bid->saveMeta('pets', $request->pets);
            $auction_bid->saveMeta('petTypes', $request->petTypes);
            $auction_bid->saveMeta('petWeight', $request->petWeight);
            $auction_bid->saveMeta('scoreRating', $request->scoreRating);
            $auction_bid->saveMeta('monthlyIncome', $request->monthlyIncome);
            $auction_bid->saveMeta('evictions', $request->evictions);
            $auction_bid->saveMeta('convicted', $request->convicted);
            $auction_bid->saveMeta('violations', $request->violations);
            $auction_bid->saveMeta('outstanding', $request->outstanding);

            $auction_bid->saveMeta('tenant_represented', $request->tenant_represented);
            $auction_bid->saveMeta('agent_accept_compensation', $request->agent_accept_compensation);
            $auction_bid->saveMeta('tenant_requests_commission', $request->tenant_requests_commission);
            $auction_bid->saveMeta('tenant_requests_commission_amount', $request->tenant_requests_commission_amount);
            $auction_bid->saveMeta('tenant_requests_commission_amount_other', $request->tenant_requests_commission_amount_other);

            $auction_bid->saveMeta('compensation_acceptable', $request->compensation_acceptable);

            $auction_bid->saveMeta('offer_expiry', $request->offer_expiry);

            $auction_bid->saveMeta('escalation_clause', $request->escalation_clause);
            $auction_bid->saveMeta('autobid_price', $request->autobid_price);
            $auction_bid->saveMeta('autobid_days_start_date', $request->autobid_days_start_date);
            $auction_bid->saveMeta('autobid_lease_length', $request->autobid_lease_length);

            $auction_bid->saveMeta('additionalInfo', $request->additionalInfo);

            $auction_bid->saveMeta('first_name', $request->first_name);
            $auction_bid->saveMeta('last_name', $request->last_name);
            $auction_bid->saveMeta('agent_phone', $request->agent_phone);
            $auction_bid->saveMeta('agent_email', $request->agent_email);
            $auction_bid->saveMeta('agent_brokerage', $request->agent_brokerage);
            $auction_bid->saveMeta('agent_license_no', $request->agent_license_no);
            $auction_bid->saveMeta('agent_mls_id', $request->agent_mls_id);
            $auction_bid->saveMeta('video_url', $request->video_url);
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $photos = [];
            // echo $request->hasFile('photos');
            if ($request->hasFile('photos')) {

                foreach ($request->file('photos') as $photo) {
                    $extension = $photo->getClientOriginalExtension();
                    $check = in_array($extension, $allowedPhotos);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $imageName = $uuid . '.' . $extension;
                        $photo->move(public_path('auction/images'), $imageName);
                        $photos[] = 'auction/images/' . $imageName;
                    }
                }
            }
            $auction_bid->saveMeta('photos', json_encode($photos));
            DB::commit();
            return redirect()->to(route('agent.landlord.auction', $auction->id))->with("success", "Bid placed successfully!");
        } catch (Exception $e) {
            //throw $th;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->to(route('agent.landlord.auction', $auction->id))->with('error', $e->getMessage());
        }
    }

    public function addCounterBid(Request $request, $bid_id)
    {
        // dd($bid_id);
        $auction_bid = LandlordAuctionBid::find($bid_id);
        $page_data['auction'] = $auction = LandlordAuction::find($auction_bid->landlord_auction_id);
        $page_data['title'] = "Add Counter Bid for Seller's Landlord - {$auction->address}";
        $page_data['bid'] = $auction_bid;
        return view('landlord_auction.add-counter-bid', $page_data);
    }

    public function saveCounterBid(Request $request, $bid_id)
    {
        $auctionBid = LandlordAuctionBid::with('meta')->find($bid_id);
        $bid = new LandlordAuctionBid();

        $bid->user_id = Auth::user()->id;
        $bid->counter_id = $bid_id;
        $bid->landlord_auction_id = $auctionBid->landlord_auction_id;
        $bid->save();
        $bid->saveMeta('offered_price', $request->offered_price);
        $bid->saveMeta('lease_terms', json_encode($request->lease_terms));
        $bid->saveMeta('price', $request->price);
        $bid->saveMeta('start_date', $request->start_date);
        $bid->saveMeta('days_until_start_date', $request->days_until_start_date);
        $bid->saveMeta('end_date', $request->end_date);
        $bid->saveMeta('securityDeposit', $request->securityDeposit);
        $bid->saveMeta('offered_price', $request->offered_price);
        $bid->saveMeta('setPrice', $request->setPrice);
        $bid->saveMeta('autobidPrice', $request->autobidPrice);
        $bid->saveMeta('highestOffer', $request->highestOffer);
        $bid->saveMeta('bestTerms', $request->bestTerms);
        $bid->saveMeta('occupants', $request->occupants);
        $bid->saveMeta('petOpt', $request->petOpt);
        $bid->saveMeta('pets', $request->pets);
        $bid->saveMeta('petTypes', $request->petTypes);
        $bid->saveMeta('petWeight', $request->petWeight);
        $bid->saveMeta('scoreRating', $request->scoreRating);
        $bid->saveMeta('monthlyIncome', $request->monthlyIncome);
        $bid->saveMeta('evictions', $request->evictions);
        $bid->saveMeta('convicted', $request->convicted);
        $bid->saveMeta('violations', $request->violations);
        $bid->saveMeta('outstanding', $request->outstanding);
        $bid->saveMeta('tenant_represented', $request->tenant_represented);
        $bid->saveMeta('agent_accept_compensation', $request->agent_accept_compensation);
        $bid->saveMeta('tenant_requests_commission', $request->tenant_requests_commission);
        $bid->saveMeta('tenant_requests_commission_amount', $request->tenant_requests_commission_amount);
        $bid->saveMeta('tenant_requests_commission_amount_other', $request->tenant_requests_commission_amount_other);
        $bid->saveMeta('offer_expiry', $request->offer_expiry);
        $bid->saveMeta('escalation_clause', $request->escalation_clause);
        $bid->saveMeta('autobid_price', $request->autobid_price);
        $bid->saveMeta('autobid_days_start_date', $request->autobid_days_start_date);
        $bid->saveMeta('autobid_lease_length', $request->autobid_lease_length);
        $bid->saveMeta('additionalInfo', $request->additionalInfo);
        $bid->saveMeta('first_name', $request->first_name);
        $bid->saveMeta('last_name', $request->last_name);
        $bid->saveMeta('agent_phone', $request->agent_phone);
        $bid->saveMeta('agent_email', $request->agent_email);
        $bid->saveMeta('agent_brokerage', $request->agent_brokerage);
        $bid->saveMeta('agent_license_no', $request->agent_license_no);
        $bid->saveMeta('agent_mls_id', $request->agent_mls_id);

        return redirect()->route('agent.landlord.auction', $auctionBid->landlord_auction_id)->with("success", "Counter Bid placed successfully!");
    }

    public function accept_bid($id, Request $request)
    {
        try {
            DB::beginTransaction();
            $bid = LandlordAuctionBid::find($id);
            $bid->accepted = 1;
            $bid->accepted_date = date('Y-m-d H:i:s');
            $bid->save();
            $auction = LandlordAuction::find($request->auction_id);
            $auction->is_sold = true;
            $auction->sold_date = date('Y-m-d H:i:s');
            $auction->save();
            DB::commit();
            return redirect()->back()->with('success', "Bid accepted successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject_bid($id, Request $request)
    {
        $bid = LandlordAuctionBid::whereId($id)->first();
        $bid->accepted = 2;
        $bid->save();
        return redirect()->back()->with('success', 'Bid Rejected successfully!');
    }

    public function admin_list(Request $request)
    {
        $page_data['title'] = "Auctions for Landlords";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = LandlordAuction::where('is_approved', true)->get();
        } elseif ($type == 2) {
            $page_data['auctions'] = LandlordAuction::where('is_sold', true)->get();
        } else {
            $page_data['auctions'] = LandlordAuction::where('is_approved', false)->get();
        }
        return view('admin.landlord-auctions', $page_data);
    }

    public function approve($id, Request $request)
    {
        $auction = LandlordAuction::find($id);
        $auction->is_approved = true;
        if ($auction->save()) {
            return redirect()->back()->with('success', 'Auction approved successfully.');
        } else {
            return redirect()->back()->with('error', 'Unable to approve auction.');
        }
    }

    public function search_listing(Request $request)
    {
        $page_data['title'] = "Auctions for Landlords";
        $auctions = LandlordAuction::query();

        $auctions->selectRaw("*, (SELECT meta_value FROM landlord_auction_metas WHERE landlord_auction_metas.landlord_auction_id = landlord_auctions.id AND meta_key = 'monthly_lease_price') as price")->where('is_sold', false)->where('is_approved', true);

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
            } else {
                $sort_by = 'id';
                $sort_type = 'ASC';
            }
            $auctions->orderBy($sort_by, $sort_type);
        } else {
            // $auctions->orderBy(DB::raw('RAND()'));
        }

        $page_data['count'] = $auctions->clone()->count();
        $page_data['auctions'] = $auctions->paginate(12);
        return view('landlord_auction.search', $page_data);
    }
    public function renew($id)
    {
        $landlord_auction = LandlordAuction::whereId($id)->first();
        return view('landlord_auction.renew', compact('landlord_auction'));
    }
    public function renew_save(Request $request)
    {
        $landlord_auction = LandlordAuction::find($request->id);
        $landlord_auction->listing_date = $request->listing_date;
        $landlord_auction->expiration_date = $request->expiration_date;
        $landlord_auction->update();
        return redirect('/landlord/auctions');
    }
}
