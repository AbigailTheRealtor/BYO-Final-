<?php

namespace App\Http\Controllers;

use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerCriteriaAuctionBid;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BuyerCriteriaAuctionBidController extends Controller
{
    public function add_bid($id)
    {
        // dd($id);
        $page_data['auction'] = BuyerCriteriaAuction::find($id);
        $page_data['title'] = "Add Bid for Buyer's Criteria Auction - " . $page_data['auction']->address;
        $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        // dd($page_data);
        return view('buyer_criteria.add-bid', $page_data);
    }
    //
    public function save_bid($id, Request $request)
    {

        try {
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
            $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];

            DB::beginTransaction();
            $bid = new BuyerCriteriaAuctionBid();
            $bid->user_id = Auth::user()->id;
            $bid->buyer_criteria_auction_id = $id;
            $bid->save();

            // changes
            $bid->saveMeta("acceptable", $request->acceptable);
            $bid->saveMeta("property_listed", $request->property_listed);
            $bid->saveMeta("property_link", $request->property_link);
            $bid->saveMeta("address", $request->address);
            $bid->saveMeta("city", $request->city);
            $bid->saveMeta("county", $request->county);
            $bid->saveMeta("state", $request->state);
            $bid->saveMeta("special_sale", $request->special_sale);
            $bid->saveMeta("custom_special_sale_provision", $request->custom_special_sale_provision);
            $bid->saveMeta("contribute_term", $request->contribute_term);
            $bid->saveMeta("commercialseller_contract_yes", $request->commercialseller_contract_yes);
            $bid->saveMeta("custom_seller_contract_no", $request->custom_seller_contract_no);
            $bid->saveMeta("max_price", $request->price);
            $bid->saveMeta("escrow_amount", $request->escrow_amount2);
            $bid->saveMeta("closing_days", $request->closing_days2);
            $bid->saveMeta("contigencies_accepted_by_seller", $request->contigencies_accepted_by_seller);
            $bid->saveMeta("inspection", $request->inspection);
            $bid->saveMeta("appraisal", $request->appraisal);
            $bid->saveMeta("finance", $request->finance);
            $bid->saveMeta("saleContingency", $request->saleContingency);
            $bid->saveMeta("timeFrame", $request->timeFrame);
            $bid->saveMeta("multiTimeFrame", $request->multiTimeFrame);
            $bid->saveMeta("term_financings", $request->term_financings);
            $bid->saveMeta("otherFinancing", $request->otherFinancing);
            $bid->saveMeta("type_of_NFT_accepted", $request->type_of_NFT_accepted);
            $bid->saveMeta("percentage_in_NFT", $request->percentage_in_NFT);
            $bid->saveMeta("percentage_in_cash", $request->percentage_in_cash);
            $bid->saveMeta("cryptocurrency_type", $request->cryptocurrency_type);
            $bid->saveMeta("percentage_in_crypto", $request->percentage_in_crypto);
            $bid->saveMeta("down_payment_seller_financing", $request->down_payment_seller_financing);
            $bid->saveMeta("seller_financing_amount", $request->seller_financing_amount);
            $bid->saveMeta("interest_rate_seller_financing", $request->interest_rate_seller_financing);
            $bid->saveMeta("term_seller_financing", $request->term_seller_financing);
            $bid->saveMeta("monthly_payment_seller_financing", $request->monthly_payment_seller_financing);
            $bid->saveMeta("closing_costs", $request->closing_costs);
            $bid->saveMeta("ballonPenalty", $request->ballonPenalty);
            $bid->saveMeta("ballonPenaltyYes", $request->ballonPenaltyYes);
            $bid->saveMeta("balloonPay", $request->balloonPay);
            $bid->saveMeta("balloonPayment", $request->balloonPayment);
            $bid->saveMeta("balloonDue", $request->balloonDue);
            $bid->saveMeta("desired_offering_price", $request->desired_offering_price);
            $bid->saveMeta("lease_option_terms", $request->lease_option_terms);
            $bid->saveMeta("proposed_lease_duration", $request->proposed_lease_duration);
            $bid->saveMeta("monthly_payment_amount", $request->monthly_payment_amount);
            $bid->saveMeta("lease_option_conditions", $request->lease_option_conditions);
            $bid->saveMeta("price_changes_possibility", $request->price_changes_possibility);
            $bid->saveMeta("exchange_trade", $request->exchange_trade);
            $bid->saveMeta("sellerFeeOptionYes", $request->sellerFeeOptionYes);
            $bid->saveMeta("desired_offering_price_lease_purchase", $request->desired_offering_price_lease_purchase);
            $bid->saveMeta("lease_purchase_terms", $request->lease_purchase_terms);
            $bid->saveMeta("proposed_lease_duration_lease_purchase", $request->proposed_lease_duration_lease_purchase);
            $bid->saveMeta("monthly_payment_amount_lease_purchase", $request->monthly_payment_amount_lease_purchase);
            $bid->saveMeta("lease_purchase_conditions", $request->lease_purchase_conditions);
            $bid->saveMeta("price_changes_possibility_lease_purchase", $request->price_changes_possibility_lease_purchase);
            $bid->saveMeta("sellerFeePurchaseYes", $request->sellerFeePurchaseYes);
            $bid->saveMeta("assumable_interest", $request->assumable_interest);
            $bid->saveMeta("assumable_max_interest_rate", $request->assumable_max_interest_rate);
            $bid->saveMeta("assumable_max_monthly_payment", $request->assumable_max_monthly_payment);
            $bid->saveMeta("assumable_bridge_gap_cash", $request->assumable_bridge_gap_cash);
            $bid->saveMeta("otherTrade", $request->otherTrade);
            $bid->saveMeta("estimatedTrade", $request->estimatedTrade);
            $bid->saveMeta("specificTrade", $request->specificTrade);
            $bid->saveMeta("cashTrade", $request->cashTrade);
            $bid->saveMeta("valueTrade", $request->valueTrade);
            $bid->saveMeta("sellerOffer", $request->sellerOffer);
            $bid->saveMeta("sellerOfferYes", $request->sellerOfferYes);
            $bid->saveMeta("property_type", $request->property_type);
            $bid->saveMeta("property_items", $request->property_items);
            $bid->saveMeta("otherProperty", $request->otherProperty);
            $bid->saveMeta("prop_condition", $request->prop_condition);
            $bid->saveMeta("otherCondition", $request->otherCondition);
            $bid->saveMeta("bedrooms", $request->bedrooms);
            $bid->saveMeta("custom_bedrooms", $request->custom_bedrooms);
            $bid->saveMeta("bathrooms", $request->bathrooms);
            $bid->saveMeta("custom_bathrooms", $request->custom_bathrooms);
            $bid->saveMeta("unit_type", $request->unit_type);
            $bid->saveMeta("beds_unit", $request->beds_unit);
            $bid->saveMeta("baths_unit", $request->baths_unit);
            $bid->saveMeta("sqt_ft_heated", $request->sqt_ft_heated);
            $bid->saveMeta("number_of_units", $request->number_of_units);
            $bid->saveMeta("occupied", $request->occupied);
            $bid->saveMeta("custom_occupied", $request->custom_occupied);
            $bid->saveMeta("current_rent", $request->current_rent);
            $bid->saveMeta("expected_rent", $request->expected_rent);
            $bid->saveMeta("garage_spaces_unit", $request->garage_spaces_unit);
            $bid->saveMeta("unit_type_of_description", $request->unit_type_of_description);
            $bid->saveMeta("annual_gross_income", $request->annual_gross_income);
            $bid->saveMeta("total_monthly_rent", $request->total_monthly_rent);
            $bid->saveMeta("total_monthly_expenses", $request->total_monthly_expenses);
            $bid->saveMeta("annual_net_income", $request->annual_net_income);
            $bid->saveMeta("est_annual_market_income", $request->est_annual_market_income);
            $bid->saveMeta("annual_expenses", $request->annual_expenses);
            $bid->saveMeta("custom_leases_terms", $request->custom_leases_terms);
            $bid->saveMeta("terms_of_lease", $request->terms_of_lease);
            $bid->saveMeta("otherTermLease", $request->otherTermLease);
            $bid->saveMeta("tenant_pays", $request->tenant_pays);
            $bid->saveMeta("otherTenantPay", $request->otherTenantPay);
            $bid->saveMeta("financial_sources", $request->financial_sources);
            $bid->saveMeta("total_number_of_units", $request->total_number_of_units);
            $bid->saveMeta("heated_sqft", $request->heated_sqft);
            $bid->saveMeta("total_sqft", $request->total_sqft);
            $bid->saveMeta("heated_source", $request->heated_source);
            $bid->saveMeta("total_aceage", $request->total_aceage);
            $bid->saveMeta("lot_size", $request->lot_size);
            $bid->saveMeta("year_built", $request->year_built);
            $bid->saveMeta("legal_description", $request->legal_description);
            $bid->saveMeta("description", $request->description);
            $bid->saveMeta("agent_commission_offered", $request->agent_commission_offered);
            $bid->saveMeta("agent_commission", $request->agent_commission);
            $bid->saveMeta("agent_compensation", $request->agent_compensation);
            $bid->saveMeta("offer_expiry", $request->offer_expiry);
            $bid->saveMeta("legal_subdivison_name", $request->legal_subdivison_name);
            $bid->saveMeta("appliances", json_encode($request->appliances));
            $bid->saveMeta("otherAppliances", $request->otherAppliances);
            $bid->saveMeta("fireplace", $request->fireplace);
            $bid->saveMeta("has_furnishing", $request->has_furnishing);
            $bid->saveMeta("furnishings_include", $request->furnishings_include);
            $bid->saveMeta("has_additional_fees", $request->has_additional_fees);
            $bid->saveMeta("listed_furniture_price", $request->listed_furniture_price);
            $bid->saveMeta("interior_features", json_encode($request->interior_features));
            $bid->saveMeta("otherInterior", $request->otherInterior);
            $bid->saveMeta("additionalRooms", $request->additionalRooms);
            $bid->saveMeta("number_of_buildings", $request->number_of_buildings);
            $bid->saveMeta("total_floors", $request->total_floors);
            $bid->saveMeta("building_elevator", $request->building_elevator);
            $bid->saveMeta("floor_covering", json_encode($request->floor_covering));
            $bid->saveMeta("otherFloorCovering", $request->otherFloorCovering);
            $bid->saveMeta("front_exposure", $request->front_exposure);
            $bid->saveMeta("foundation", json_encode($request->foundation));
            $bid->saveMeta("otherFoundation", $request->otherFoundation);
            $bid->saveMeta("exterior_construction", json_encode($request->exterior_construction));
            $bid->saveMeta("otherConstruction", $request->otherConstruction);
            $bid->saveMeta("exterior_feature", json_encode($request->exterior_feature));
            $bid->saveMeta("otherExterior", $request->otherExterior);
            $bid->saveMeta("lot_features", json_encode($request->lot_features));
            $bid->saveMeta("otherLotFeature", $request->otherLotFeature);
            $bid->saveMeta("otherStructureOpt", $request->otherStructureOpt);
            $bid->saveMeta("community_features", json_encode($request->community_features));
            $bid->saveMeta("otherCommunityFeature", $request->otherCommunityFeature);
            $bid->saveMeta("security_features", json_encode($request->security_features));
            $bid->saveMeta("otherSecurity", $request->otherSecurity);
            $bid->saveMeta("waterfront_features", json_encode($request->waterfront_features));
            $bid->saveMeta("otherWaterFront", $request->otherWaterFront);
            $bid->saveMeta("water_access", json_encode($request->water_access));
            $bid->saveMeta("otherWaterAccess", $request->otherWaterAccess);
            $bid->saveMeta("water_extras", json_encode($request->water_extras));
            $bid->saveMeta("otherWaterExtras", $request->otherWaterExtras);
            $bid->saveMeta("has_pool", $request->has_pool);
            $bid->saveMeta("number_of_pool", $request->number_of_pool);
            $bid->saveMeta("pool_feature", json_encode($request->pool_feature));
            $bid->saveMeta("otherPoolFeature", $request->otherPoolFeature);
            $bid->saveMeta("water_view", json_encode($request->water_view));
            $bid->saveMeta("otherWaterView", $request->otherWaterView);
            $bid->saveMeta("green_features", json_encode($request->green_features));
            $bid->saveMeta("otherGreenFeature", $request->otherGreenFeature);
            $bid->saveMeta("water_source", json_encode($request->water_source));
            $bid->saveMeta("otherWaterSource", $request->otherWaterSource);
            $bid->saveMeta("sewer_type", json_encode($request->sewer_type));
            $bid->saveMeta("otherSewerType", $request->otherSewerType);
            $bid->saveMeta("building_heating", json_encode($request->building_heating));
            $bid->saveMeta("otherHeating", $request->otherHeating);
            $bid->saveMeta("building_cooling", json_encode($request->building_cooling));
            $bid->saveMeta("otherCooling", $request->otherCooling);
            $bid->saveMeta("electrical_features", json_encode($request->electrical_features));
            $bid->saveMeta("otherElectricalFeature", $request->otherElectricalFeature);
            $bid->saveMeta("utilities", json_encode($request->utilities));
            $bid->saveMeta("otherUtilities", $request->otherUtilities);
            $bid->saveMeta("zoning_code", $request->zoning_code);
            $bid->saveMeta("zoning_type", $request->zoning_type);
            $bid->saveMeta("custom_zoning", $request->custom_zoning);
            $bid->saveMeta("building_type", json_encode($request->building_type));
            $bid->saveMeta("land_type", $request->land_type);
            $bid->saveMeta("development_status", json_encode($request->development_status));
            $bid->saveMeta("otherDevelopment", $request->otherDevelopment);
            $bid->saveMeta("potential_use", json_encode($request->potential_use));
            $bid->saveMeta("otherPotentialUse", $request->otherPotentialUse);
            $bid->saveMeta("land_features", json_encode($request->land_features));
            $bid->saveMeta("otherLandFeature", $request->otherLandFeature);
            $bid->saveMeta("road_surface", json_encode($request->road_surface));
            $bid->saveMeta("otherRoadSurface", $request->otherRoadSurface);
            $bid->saveMeta("vegetation", json_encode($request->vegetation));
            $bid->saveMeta("otherVegetation", $request->otherVegetation);
            $bid->saveMeta("association_fee", $request->association_fee);
            $bid->saveMeta("association_fee_frequency", $request->association_fee_frequency);
            $bid->saveMeta("additionalFees", $request->additionalFees);
            $bid->saveMeta("taxes", $request->taxes);
            $bid->saveMeta("grossIncome", $request->grossIncome);
            $bid->saveMeta("annualInsurance", $request->annualInsurance);
            $bid->saveMeta("land_tax", $request->land_tax);
            $bid->saveMeta("utility_fees", $request->utility_fees);
            $bid->saveMeta("maintenance_fees", $request->maintenance_fees);
            $bid->saveMeta("otherMaintFees", $request->otherMaintFees);
            $bid->saveMeta("total_aceage", $request->total_aceage);
            $bid->saveMeta("annual_op_cost", $request->annual_op_cost);
            $bid->saveMeta("tenant_payment", $request->tenant_payment);
            $bid->saveMeta("otherTenantPayment", $request->otherTenantPayment);
            $bid->saveMeta("lease_length", $request->lease_length);
            $bid->saveMeta("operating_expenses", $request->operating_expenses);
            $bid->saveMeta("repair_maint", $request->repair_maint);
            $bid->saveMeta("annual_property_tax", $request->annual_property_tax);
            $bid->saveMeta("owner_cost", $request->owner_cost);
            $bid->saveMeta("otherOwnerCost", $request->otherOwnerCost);
            $bid->saveMeta("annual_hazard_insurance", $request->annual_hazard_insurance);
            $bid->saveMeta("otherInsurance", $request->otherInsurance);
            $bid->saveMeta("capitalization_rate", $request->capitalization_rate);
            $bid->saveMeta("annual_cap_rate", $request->annual_cap_rate);
            $bid->saveMeta("zoning_type", $request->zoning_type);
            $bid->saveMeta("otherZoningType", $request->otherZoningType);
            $bid->saveMeta("first_name", $request->first_name);
            $bid->saveMeta("last_name", $request->last_name);
            $bid->saveMeta("phone_number", $request->phone_number);
            $bid->saveMeta("email", $request->email);
            $bid->saveMeta("brokerage", $request->brokerage);
            $bid->saveMeta("license", $request->license);
            $bid->saveMeta("member_id", $request->member_id);
            $bid->saveMeta("three_d_tour", $request->three_d_tour);
            $bid->saveMeta('video_type', $request->video_type);
            $bid->saveMeta('youtube_video_link', $request->youtube_video_link);
            $bid->saveMeta('vimeo_video_link', $request->vimeo_video_link);

            if ($request->audio != "") {
                $extension = $request->audio->getClientOriginalExtension();
                $check = in_array($extension, $allowedAudios);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $audioName = $uuid . '.' . $extension;
                    $request->audio->move(public_path('auction/bid/audios'), $audioName);
                    $bid->saveMeta('audio', 'auction/bid/audios/' . $audioName);
                }
            }

            // Disclosure Upload
            $disclosures = [];
            if ($request->hasFile('disclosures')) {
                foreach ($request->file('disclosures') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $fileSize = $file->getSize();
                    $check = in_array($extension, $allowedFiles);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $fileName = $uuid . '.' . $extension;
                        $file->move(public_path('auction/files'), $fileName);
                        $disclosures[] = 'auction/files/' . $fileName;
                    }
                }
                $bid->saveMeta('disclosures', json_encode($disclosures));
            }

            if ($request->hasFile('floor_plan')) {
                $file = $request->floor_plan[0];
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    $bid->saveMeta('floor_plan', 'auction/files/' . $fileName);
                }
            }

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

                // dd($arrangedPhotoArr);

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
                $bid->saveMeta('photo', json_encode($photoLinks));
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
                        $video->move(public_path('bid/videos'), $videoName);
                        $video = 'bid/videos/' . $videoName;
                    }
                    $bid->saveMeta('video', $video);
                }
            }


            if ($request->card != "") {
                $extension = $request->card->getClientOriginalExtension();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $cardName = $uuid . '.' . $extension;
                    $request->card->move(public_path('auction/bid/cards'), $cardName);
                    $bid->saveMeta('card', 'auction/bid/cards/' . $cardName);
                }
            }


            if ($request->note != "") {
                $extension = $request->note->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $letterName = $uuid . '.' . $extension;
                    $request->note->move(public_path('auction/bid/letters'), $letterName);
                    $bid->saveMeta('note', 'auction/bid/letters/' . $letterName);
                }
            }
            $route = route('buyer.criteria.view', $id);
            DB::commit();
            return redirect()->to($route)->with('success', 'Bid Added Successfully');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add bid');
        }
    }

    public function acceptBCABid(Request $request)
    {
        $pab = BuyerCriteriaAuctionBid::whereId($request->bid_id)->first();
        $pab->is_accepted = true;
        $pab->accepted_date = date('Y-m-d H:i:s');

        $pa = BuyerCriteriaAuction::whereId($request->auction_id)->first();
        $pa->is_sold = true;
        $pa->sold_date = date('Y-m-d H:i:s');

        if ($pab->save() && $pa->save()) {
            return redirect()->back()->with('success', 'Bid Accepted successfully!');
        } else {
            return redirect()->back()->with('error', 'Some problem in bid acceptance!');
        }
    }
    public function renew($id)
    {
        $buyer_criteria = BuyerCriteriaAuction::whereId($id)->first();
        return view('buyer_criteria.renew', compact('buyer_criteria'));
    }
    public function renew_save(Request $request)
    {
        $buyer_criteriaAuction = BuyerCriteriaAuction::find($request->id);
        $buyer_criteriaAuction->saveMeta('listing_date', $request->listing_date);
        $buyer_criteriaAuction->saveMeta('expiration_date', $request->expiration_date);
        return redirect()->route('buyer.criteria.auctions');
    }
}
