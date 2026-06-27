<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\City;
use App\Models\User;
use App\Models\State;
use App\Models\County;
use App\Models\Bedroom;
use App\Models\Bathroom;
use App\Models\Appliance;
use App\Models\Financing;
use App\Models\FeeInclude;
use App\Models\WaterExtra;
use App\Models\HeatingFuel;
use Illuminate\Support\Str;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use App\Models\WaterViewType;
use App\Mail\NotificationEmail;
use App\Models\PropertyAuction;
use Illuminate\Support\Facades\DB;
use App\Models\AirConditioningType;
use App\Models\PropertyAuctionFuel;
use App\Models\PropertyAuctionTerm;
use App\Models\PropertyAuctionMedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\PropertyAuctionAcType;
use App\Models\PropertyAuctionAppliance;
use App\Models\PropertyAuctionFinancing;
use App\Models\PropertyAuctionFeeInclude;
use App\Models\PropertyAuctionWaterExtra;
use App\Models\PropertyAuctionPropertyType;
use App\Events\SellerPropertyAuctionCreated;
use App\Events\SellerPropertyAuctionUpdated;
use App\Models\PropertyAuctionBid;
use App\Models\PropertyAuctionWaterViewType;
use Illuminate\Http\Response;

class PropertyAuctionController extends Controller
{
    public function addListing(Request $request)
    {
        $page_data['title'] = 'Add Property Listing';
        $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        return view('seller_property.add', $page_data);
    }
    public function store(Request $request)
    {

        try {
            DB::beginTransaction();

            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lengths = explode(' ', $request->auction_length);
                $auction_length_days = current($auction_lengths);
            } else {
                $auction_length_days = '-1';
            }
            $auction = new PropertyAuction();
            $auction->user_id = Auth::user()->id;
            $auction->address = $request->address;
            $auction->auction_type = $request->auction_type;
            $auction->auction_length = $auction_length_days;
            $auction->description = $request->description;
            $keywords = str_replace(' ', ',', $request->keywords);
            $auction->keywords = $keywords;
            $auction->autobid_price = $request->autobid_price;
            $auction->autobid_price2 = $request->autobid_price2;
            $auction->autobid_price3 = $request->autobid_price3;
            $auction->escrow_amount = $request->escrow_amount;
            $auction->escrow_amount2 = $request->escrow_amount2;
            $auction->inspection_period = $request->inspection_period;
            $auction->inspection_period2 = $request->inspection_period2;
            $auction->closing_days2 = $request->closing_days2;
            $auction->closing_days = $request->closing_days;
            $listing_date = Carbon::parse($request->listing_date);
            $expiration_date = Carbon::parse($request->expiration_date);
            $auction->listing_date = $listing_date;
            $auction->expiration_date = $expiration_date;
            $auction->save();
            $auction->saveMeta("unit_number", $request->unit_number);
            $auction->saveMeta("address", $request->address);
            $auction->saveMeta("property_lat", $request->property_lat);
            $auction->saveMeta("property_lng", $request->property_lng);
            $auction->saveMeta("google_place_id", $request->google_place_id);
            $auction->saveMeta("formatted_address", $request->formatted_address);
            $auction->saveMeta("city", $request->city);
            $auction->saveMeta("county", $request->county);
            $auction->saveMeta("state", $request->state);
            $auction->saveMeta("listing_date", $request->listing_date);
            $auction->saveMeta("expiration_date", $request->expiration_date);
            $auction->saveMeta("service_type", $request->service_type);
            $auction->saveMeta("representation", $request->representation);
            $auction->saveMeta("special_sale", $request->special_sale);
            $auction->saveMeta("custom_special_sale_provision", $request->custom_special_sale_provision);
            $auction->saveMeta("contribute_term", $request->contribute_term);
            $auction->saveMeta("commercialseller_contract_yes", $request->commercialseller_contract_yes);
            $auction->saveMeta("custom_seller_contract_no", $request->custom_seller_contract_no);
            $auction->saveMeta("auction_type", $request->auction_type);
            $auction->saveMeta("auction_length", $request->auction_length);
            $auction->saveMeta("price", $request->price);
            $auction->saveMeta("buy_now_price", $request->buy_now_price);
            $auction->saveMeta("buy_now_price_per_sqfeet", $request->buy_now_price_per_sqfeet);
            $auction->saveMeta("starting_price", $request->starting_price);
            $auction->saveMeta("reserve_price", $request->reserve_price);
            $auction->saveMeta("price_per_sqfeet", $request->price_per_sqfeet);
            $auction->saveMeta("escrow_amount", $request->escrow_amount);
            $auction->saveMeta("closing_days", $request->closing_days);
            $auction->saveMeta("contigencies_accepted_by_seller", json_encode($request->contigencies_accepted_by_seller));
            $auction->saveMeta("inspection", $request->inspection);
            $auction->saveMeta("appraisal", $request->appraisal);
            $auction->saveMeta("finance", $request->finance);
            $auction->saveMeta("saleContingency", $request->saleContingency);
            $auction->saveMeta("acceptable", $request->acceptable);
            $auction->saveMeta("acceptable_days", $request->acceptable_days);

            $auction->saveMeta("inspection_auction", $request->inspection_auction);
            $auction->saveMeta("appraisal_auction", $request->appraisal_auction);
            $auction->saveMeta("finance_auction", $request->finance_auction);
            $auction->saveMeta("saleContingency_auction", $request->saleContingency_auction);
            $auction->saveMeta("acceptable_auction", $request->acceptable_auction);
            $auction->saveMeta("acceptable_days_auction", $request->acceptable_days_auction);

            $auction->saveMeta("term_financings", json_encode($request->term_financings));
            $auction->saveMeta("otherFinancing", $request->otherFinancing);
            $auction->saveMeta("type_of_NFT_accepted", $request->type_of_NFT_accepted);
            $auction->saveMeta("percentage_in_NFT", $request->percentage_in_NFT);
            $auction->saveMeta("percentage_in_cash", $request->percentage_in_cash);
            $auction->saveMeta("cryptocurrency_type", $request->cryptocurrency_type);
            $auction->saveMeta("percentage_in_crypto", $request->percentage_in_crypto);
            $auction->saveMeta("purchase_price_seller_financing", $request->purchase_price_seller_financing);
            $auction->saveMeta("down_payment_seller_financing", $request->down_payment_seller_financing);
            $auction->saveMeta("seller_financing_amount", $request->seller_financing_amount);
            $auction->saveMeta("interest_rate_seller_financing", $request->interest_rate_seller_financing);
            $auction->saveMeta("term_seller_financing", $request->term_seller_financing);
            $auction->saveMeta("monthly_payment_seller_financing", $request->monthly_payment_seller_financing);
            $auction->saveMeta("closing_costs", $request->closing_costs);
            $auction->saveMeta("ballonPenalty", $request->ballonPenalty);
            $auction->saveMeta("ballonPenaltyYes", $request->ballonPenaltyYes);
            $auction->saveMeta("balloonPay", $request->balloonPay);
            $auction->saveMeta("balloonPayment", $request->balloonPayment);
            $auction->saveMeta("balloonDue", $request->balloonDue);
            $auction->saveMeta("desired_offering_price", $request->desired_offering_price);
            $auction->saveMeta("lease_option_terms", $request->lease_option_terms);
            $auction->saveMeta("proposed_lease_duration", $request->proposed_lease_duration);
            $auction->saveMeta("monthly_payment_amount", $request->monthly_payment_amount);
            $auction->saveMeta("lease_option_conditions", $request->lease_option_conditions);
            $auction->saveMeta("price_changes_possibility", $request->price_changes_possibility);
            $auction->saveMeta("exchange_trade", $request->exchange_trade);
            $auction->saveMeta("sellerFeeOption", $request->sellerFeeOption);
            $auction->saveMeta("sellerFeeOptionYes", $request->sellerFeeOptionYes);
            $auction->saveMeta("desired_offering_price_lease_purchase", $request->desired_offering_price_lease_purchase);
            $auction->saveMeta("lease_purchase_terms", $request->lease_purchase_terms);
            $auction->saveMeta("proposed_lease_duration_lease_purchase", $request->proposed_lease_duration_lease_purchase);
            $auction->saveMeta("monthly_payment_amount_lease_purchase", $request->monthly_payment_amount_lease_purchase);
            $auction->saveMeta("lease_purchase_conditions", $request->lease_purchase_conditions);
            $auction->saveMeta("price_changes_possibility_lease_purchase", $request->price_changes_possibility_lease_purchase);
            $auction->saveMeta("sellerFeePurchase", $request->sellerFeePurchase);
            $auction->saveMeta("sellerFeePurchaseYes", $request->sellerFeePurchaseYes);
            $auction->saveMeta("assumable_terms_offered", $request->assumable_terms_offered);
            $auction->saveMeta("restrictions_or_qualifications", $request->restrictions_or_qualifications);
            $auction->saveMeta("assumable_interest", $request->assumable_interest);
            $auction->saveMeta("assumable_monthly_payment", $request->assumable_monthly_payment);
            $auction->saveMeta("assumable_balance_loan", $request->assumable_balance_loan);
            $auction->saveMeta("outstandingBalanceYes", $request->outstandingBalanceYes);
            $auction->saveMeta("loan_balance_down_payment", $request->loan_balance_down_payment);
            $auction->saveMeta("otherTrade", $request->otherTrade);
            $auction->saveMeta("estimatedTrade", $request->estimatedTrade);
            $auction->saveMeta("specificTrade", $request->specificTrade);
            $auction->saveMeta("cashTrade", $request->cashTrade);
            $auction->saveMeta("valueTrade", $request->valueTrade);
            $auction->saveMeta("sellerOffer", $request->sellerOffer);
            $auction->saveMeta("sellerOfferYes", $request->sellerOfferYes);
            $auction->saveMeta("escrow_amount2", $request->escrow_amount2);
            $auction->saveMeta("closing_days2", $request->closing_days2);
            $auction->saveMeta("timeFrame", $request->timeFrame);
            $auction->saveMeta("multiTimeFrame", $request->multiTimeFrame);
            $auction->saveMeta("property_type", $request->property_type);
            $auction->saveMeta("propertyStyles", $request->propertyStyles);
            $auction->saveMeta("property_items", $request->property_items);
            $auction->saveMeta("otherProperty", $request->otherProperty);
            $auction->saveMeta("prop_condition", $request->prop_condition);
            $auction->saveMeta("otherCondition", $request->otherCondition);
            $auction->saveMeta("bedrooms", $request->bedrooms);
            $auction->saveMeta("custom_bedrooms", $request->custom_bedrooms);
            $auction->saveMeta("bathrooms", $request->bathrooms);
            $auction->saveMeta("custom_bathrooms", $request->custom_bathrooms);
            $auction->saveMeta("bathroomsCom", $request->bathroomsCom);
            $auction->saveMeta("custom_bathrooms_com", $request->custom_bathrooms_com);
            $auction->saveMeta("unit_type_data", json_encode($request->unit_type_data));
            $auction->saveMeta("unit_type", $request->unit_type);
            $auction->saveMeta("beds_unit", $request->beds_unit);
            $auction->saveMeta("baths_unit", $request->baths_unit);
            $auction->saveMeta("sqt_ft_heated", $request->sqt_ft_heated);
            $auction->saveMeta("number_of_units", $request->number_of_units);
            $auction->saveMeta("occupied", $request->occupied);
            $auction->saveMeta("custom_occupied", $request->custom_occupied);
            $auction->saveMeta("current_rent", $request->current_rent);
            $auction->saveMeta("expected_rent", $request->expected_rent);
            $auction->saveMeta("garage_spaces_unit", $request->garage_spaces_unit);
            $auction->saveMeta("unit_type_of_description", $request->unit_type_of_description);
            $auction->saveMeta("annual_gross_income", $request->annual_gross_income);
            $auction->saveMeta("total_monthly_rent", $request->total_monthly_rent);
            $auction->saveMeta("total_monthly_expenses", $request->total_monthly_expenses);
            $auction->saveMeta("annual_net_income", $request->annual_net_income);
            $auction->saveMeta("est_annual_market_income", $request->est_annual_market_income);
            $auction->saveMeta("annual_expenses", $request->annual_expenses);

            $auction->saveMeta("annual_ttl_schedule_income", $request->annual_ttl_schedule_income);
            $auction->saveMeta("annual_income_type", $request->annual_income_type);
            $auction->saveMeta("saleInclude", json_encode($request->saleInclude));
            $auction->saveMeta("otherSale", $request->otherSale);
            $auction->saveMeta("number_of_tenants", $request->number_of_tenants);
            $auction->saveMeta("class_of_space", $request->class_of_space);
            $auction->saveMeta("sale_include", $request->sale_include);
            $auction->saveMeta("number_of_hotel", $request->number_of_hotel);
            $auction->saveMeta("number_of_conference", $request->number_of_conference);
            $auction->saveMeta("number_of_restrooms", $request->number_of_restrooms);
            $auction->saveMeta("number_of_bays_high", $request->number_of_bays_high);
            $auction->saveMeta("number_of_bays_level", $request->number_of_bays_level);
            $auction->saveMeta("number_of_offices", $request->number_of_offices);
            $auction->saveMeta("has_condo_enviornment", $request->has_condo_enviornment);
            $auction->saveMeta("condo_fee_terms", $request->condo_fee_terms);
            $auction->saveMeta("association_manager_contact_name", $request->association_manager_contact_name);
            $auction->saveMeta("association_manager_contact_email", $request->association_manager_contact_email);
            $auction->saveMeta("association_manager_contact_number", $request->association_manager_contact_number);
            $auction->saveMeta("association_manager_contact_website", $request->association_manager_contact_website);
            $auction->saveMeta("community_features", json_encode($request->community_features));
            $auction->saveMeta("community_features_other", $request->community_features_other);

            $auction->saveMeta("custom_leases_length", $request->custom_leases_length);
            $auction->saveMeta("length_of_lease", json_encode($request->length_of_lease));
            $auction->saveMeta("terms_of_lease", $request->terms_of_lease);
            $auction->saveMeta("otherTermLease", $request->otherTermLease);
            $auction->saveMeta("tenant_pays", $request->tenant_pays);
            $auction->saveMeta("otherTenantPay", $request->otherTenantPay);
            $auction->saveMeta("financial_sources", $request->financial_sources);
            $auction->saveMeta("total_number_of_units", $request->total_number_of_units);
            $auction->saveMeta("heated_sqft", $request->heated_sqft);
            $auction->saveMeta("heated_sqft_com", $request->heated_sqft_com);
            $auction->saveMeta("accessibilityFeatures", json_encode($request->accessibilityFeatures));
            $auction->saveMeta("laundryFeatures", json_encode($request->laundryFeatures));
            $auction->saveMeta("total_sqft", $request->total_sqft);
            $auction->saveMeta("total_sqft_com", $request->total_sqft_com);
            $auction->saveMeta("heated_source", $request->heated_source);
            $auction->saveMeta("heated_source_com", $request->heated_source_com);
            $auction->saveMeta("total_aceage", $request->total_aceage);
            $auction->saveMeta("total_aceage_com", $request->total_aceage_com);
            $auction->saveMeta("total_aceage_vac", $request->total_aceage_vac);
            $auction->saveMeta("lot_size", $request->lot_size);
            $auction->saveMeta("lot_size_com", $request->lot_size_com);
            $auction->saveMeta("lot_size_vac", $request->lot_size_vac);
            $auction->saveMeta("year_built", $request->year_built);
            $auction->saveMeta("year_built_com", $request->year_built_com);
            $auction->saveMeta("legal_description", $request->legal_description);
            $auction->saveMeta("legal_description_com", $request->legal_description_com);
            $auction->saveMeta("legal_description_vac", $request->legal_description_vac);
            $auction->saveMeta("legal_subdivison_name", $request->legal_subdivison_name);
            $auction->saveMeta("legal_subdivison_name_com", $request->legal_subdivison_name_com);
            $auction->saveMeta("legal_subdivison_name_vac", $request->legal_subdivison_name_vac);
            $auction->saveMeta("appliances", json_encode($request->appliances));
            $auction->saveMeta("otherAppliances", $request->otherAppliances);
            $auction->saveMeta("otherAppliancesCom", $request->otherAppliancesCom);
            $auction->saveMeta("fireplace", $request->fireplace);
            $auction->saveMeta("has_furnishing", $request->has_furnishing);
            $auction->saveMeta("furnishings_include", $request->furnishings_include);
            $auction->saveMeta("has_additional_fees", $request->has_additional_fees);
            $auction->saveMeta("listed_furniture_price", $request->listed_furniture_price);
            $auction->saveMeta("has_furnishing_com", $request->has_furnishing_com);
            $auction->saveMeta("furnishings_include_com", $request->furnishings_include_com);
            $auction->saveMeta("has_additional_fees_com", $request->has_additional_fees_com);
            $auction->saveMeta("listed_furniture_price_com", $request->listed_furniture_price_com);
            $auction->saveMeta("interior_features", json_encode($request->interior_features));
            $auction->saveMeta("otherInterior", $request->otherInterior);
            $auction->saveMeta("additionalRooms", json_encode($request->additionalRooms));
            $auction->saveMeta("number_of_buildings", $request->number_of_buildings);
            $auction->saveMeta("total_number", $request->total_number);
            $auction->saveMeta("number_of_buildings_com", $request->number_of_buildings_com);
            $auction->saveMeta("floors_in_unit", $request->floors_in_unit);
            $auction->saveMeta("floors_in_unit_com", $request->floors_in_unit_com);
            $auction->saveMeta("total_floors", $request->total_floors);
            $auction->saveMeta("total_floors_com", $request->total_floors_com);
            $auction->saveMeta("building_elevator", $request->building_elevator);
            $auction->saveMeta("building_elevator_com", $request->building_elevator_com);
            $auction->saveMeta("floor_covering", json_encode($request->floor_covering));
            $auction->saveMeta("otherFloorCovering", $request->otherFloorCovering);
            $auction->saveMeta("otherFloorCoveringCom", $request->otherFloorCoveringCom);
            $auction->saveMeta("front_exposure", $request->front_exposure);
            $auction->saveMeta("front_exposure_com", $request->front_exposure_com);
            $auction->saveMeta("front_exposure_vac", $request->front_exposure_vac);
            $auction->saveMeta("foundation", json_encode($request->foundation));
            $auction->saveMeta("otherFoundation", $request->otherFoundation);
            $auction->saveMeta("otherFoundationCom", $request->otherFoundationCom);
            $auction->saveMeta("exterior_construction", json_encode($request->exterior_construction));
            $auction->saveMeta("otherExterior", $request->otherExterior);
            $auction->saveMeta("otherExteriorCom", $request->otherExteriorCom);
            $auction->saveMeta("otherConstruction", $request->otherConstruction);
            $auction->saveMeta("otherConstructionCom", $request->otherConstructionCom);
            $auction->saveMeta("exterior_feature", json_encode($request->exterior_feature));
            $auction->saveMeta("lot_features", json_encode($request->lot_features));
            $auction->saveMeta("otherLotFeature", $request->otherLotFeature);
            $auction->saveMeta("otherLotFeatureCom", $request->otherLotFeatureCom);
            $auction->saveMeta("otherLotFeatureVac", $request->otherLotFeatureVac);
            $auction->saveMeta("otherStructureOpt", $request->otherStructureOpt);
            $auction->saveMeta("otherStruct", json_encode($request->otherStruct));
            $auction->saveMeta("otherStructure", $request->otherStructure);
            $auction->saveMeta("unitStructure", json_encode($request->unitStructure));
            $auction->saveMeta("unitStructureOther", $request->unitStructureOther);
            $auction->saveMeta("sqftStructure", $request->sqftStructure);
            $auction->saveMeta("totalSqft", $request->totalSqft);
            $auction->saveMeta("roof", json_encode($request->roof));
            $auction->saveMeta("otherRoof", $request->otherRoof);
            $auction->saveMeta("otherRoofCom", $request->otherRoofCom);
            $auction->saveMeta("road_surface_type", json_encode($request->road_surface_type));
            $auction->saveMeta("otherSurface", $request->otherSurface);
            $auction->saveMeta("otherSurfaceCom", $request->otherSurfaceCom);
            $auction->saveMeta("otherSurfaceVac", $request->otherSurfaceVac);
            $auction->saveMeta("garage", $request->garage);
            $auction->saveMeta("garage_spaces", $request->garage_spaces);
            $auction->saveMeta("carport", $request->carport);
            $auction->saveMeta("carport_spaces", $request->carport_spaces);
            $auction->saveMeta("pool", $request->pool);
            $auction->saveMeta("poolOpt", $request->poolOpt);
            $auction->saveMeta("ptes_Allowed", $request->ptes_Allowed);
            $auction->saveMeta("ptes_Allowed_vac", $request->ptes_Allowed_vac);
            $auction->saveMeta("acceptablePet", $request->acceptablePet);
            $auction->saveMeta("has_rental_restrictions", $request->has_rental_restrictions);
            $auction->saveMeta("total_pets_allowed", $request->total_pets_allowed);
            $auction->saveMeta("custom_pets_allowed", $request->custom_pets_allowed);
            $auction->saveMeta("max_pet_weight", $request->max_pet_weight);
            $auction->saveMeta("pet_restrictions", $request->pet_restrictions);
            $auction->saveMeta("tax_id", $request->tax_id);
            $auction->saveMeta("tax_year", $request->tax_year);
            $auction->saveMeta("taxes_annual_amount", $request->taxes_annual_amount);
            $auction->saveMeta("has_homestead", $request->has_homestead);
            $auction->saveMeta("additionalParcels", $request->additionalParcels);
            $auction->saveMeta("additionalParcelsCom", $request->additionalParcelsCom);
            $auction->saveMeta("additionalParcelsVac", $request->additionalParcelsVac);
            $auction->saveMeta("total_number_of_parcels", $request->total_number_of_parcels);
            $auction->saveMeta("total_number_of_parcels_com", $request->total_number_of_parcels_com);
            $auction->saveMeta("total_number_of_parcels_vac", $request->total_number_of_parcels_vac);
            $auction->saveMeta("additional_tax_id", $request->additional_tax_id);
            $auction->saveMeta("additional_tax_id_com", $request->additional_tax_id_com);
            $auction->saveMeta("additional_tax_id_vac", $request->additional_tax_id_vac);
            $auction->saveMeta("zoning", $request->zoning);
            $auction->saveMeta("zoning_com", $request->zoning_com);
            $auction->saveMeta("zoning_vac", $request->zoning);
            $auction->saveMeta("is_in_flood_zone", $request->is_in_flood_zone);
            $auction->saveMeta("flood_zone_code", $request->flood_zone_code);
            $auction->saveMeta("flood_zone_code_com", $request->flood_zone_code_com);
            $auction->saveMeta("flood_zone_code_vac", $request->flood_zone_code_vac);
            $auction->saveMeta("front_footage", $request->front_footage);
            $auction->saveMeta("utilities", json_encode($request->utilities));
            $auction->saveMeta("otherUtilitise", $request->otherUtilitise);
            $auction->saveMeta("otherUtilitiseCom", $request->otherUtilitiseCom);
            $auction->saveMeta("otherUtilitiseVac", $request->otherUtilitiseVac);
            $auction->saveMeta("water", json_encode($request->water));
            $auction->saveMeta("otherWater", $request->otherWater);
            $auction->saveMeta("otherWaterCom", $request->otherWaterCom);
            $auction->saveMeta("otherWaterVac", $request->otherWaterVac);
            $auction->saveMeta("sewer", json_encode($request->sewer));
            $auction->saveMeta("otherSewer", $request->otherSewer);
            $auction->saveMeta("otherSewerCom", $request->otherSewerCom);
            $auction->saveMeta("otherSewerVac", $request->otherSewerVac);
            $auction->saveMeta("air_conditioning", json_encode($request->air_conditioning));
            $auction->saveMeta("otherAirCondition", $request->otherAirCondition);
            $auction->saveMeta("air_conditioning_com", json_encode($request->air_conditioning_com));
            $auction->saveMeta("otherAirConditionCom", $request->otherAirConditionCom);
            $auction->saveMeta("heating_and_fuel", json_encode($request->heating_and_fuel));
            $auction->saveMeta("otherHeatingFuel", $request->otherHeatingFuel);
            $auction->saveMeta("otherHeatingFuelCom", $request->otherHeatingFuelCom);
            $auction->saveMeta("room_type", json_encode($request->room_type));
            $auction->saveMeta("room_details_data", json_encode($request->room_details_data));
            $auction->saveMeta("approximate_room_dimensions", $request->approximate_room_dimensions);
            $auction->saveMeta("room_feature", json_encode($request->room_feature));
            $auction->saveMeta("custom_room_features", $request->custom_room_features);
            $auction->saveMeta("has_water_access", $request->has_water_access);
            $auction->saveMeta("has_water_access_com", $request->has_water_access_com);
            $auction->saveMeta("has_water_access_vac", $request->has_water_access_vac);
            $auction->saveMeta("water_access", json_encode($request->water_access));
            $auction->saveMeta("has_water_view", $request->has_water_view);
            $auction->saveMeta("has_water_view_com", $request->has_water_view_com);
            $auction->saveMeta("has_water_view_vac", $request->has_water_view_vac);
            $auction->saveMeta("water_view", json_encode($request->water_view));
            $auction->saveMeta("has_water_extra", $request->has_water_extra);
            $auction->saveMeta("has_water_extra_com", $request->has_water_extra_com);
            $auction->saveMeta("has_water_extra_vac", $request->has_water_extra_vac);
            $auction->saveMeta("water_extras", json_encode($request->water_extras));
            $auction->saveMeta("has_dock", $request->has_dock);
            $auction->saveMeta("has_dock_com", $request->has_dock_com);
            $auction->saveMeta("has_dock_vac", $request->has_dock_vac);
            $auction->saveMeta("dock", json_encode($request->dock));
            $auction->saveMeta("custom_dock", $request->custom_dock);
            $auction->saveMeta("dock_lift_capacity", $request->dock_lift_capacity);
            $auction->saveMeta("dock_year_built", $request->dock_year_built);
            $auction->saveMeta("dock_dimension", $request->dock_dimension);
            $auction->saveMeta("dock_maintenance_fee", $request->dock_maintenance_fee);

            $auction->saveMeta("green_features", $request->green_features);
            $auction->saveMeta("building_verification", $request->building_verification);
            $auction->saveMeta("building_verification_other", $request->building_verification_other);
            $auction->saveMeta("green_status", $request->green_status);
            $auction->saveMeta("green_year", $request->green_year);
            $auction->saveMeta("green_version", $request->green_version);
            $auction->saveMeta("green_body", $request->green_body);
            $auction->saveMeta("green_metric", $request->green_metric);
            $auction->saveMeta("green_rating", $request->green_rating);
            $auction->saveMeta("green_source", $request->green_source);
            $auction->saveMeta("green_source_other", $request->green_source_other);
            $auction->saveMeta("green_url", $request->green_url);
            $auction->saveMeta("green_sustainability", json_encode($request->green_sustainability));
            $auction->saveMeta("green_generation", json_encode($request->green_generation));
            $auction->saveMeta("green_water_features", json_encode($request->green_water_features));
            $auction->saveMeta("green_energy_features", json_encode($request->green_energy_features));
            $auction->saveMeta("green_landscaping", json_encode($request->green_landscaping));
            $auction->saveMeta("green_solar", json_encode($request->green_solar));
            $auction->saveMeta("green_disaster", json_encode($request->green_disaster));
            $auction->saveMeta("green_air", json_encode($request->green_air));

            $auction->saveMeta("licenses", $request->licenses);
            $auction->saveMeta("custom_licenses", $request->custom_licenses);
            $auction->saveMeta("other_structures", json_encode($request->other_structures));
            $auction->saveMeta("custom_other_structures", $request->custom_other_structures);

            $auction->saveMeta("has_water_fontage", $request->has_water_fontage);
            $auction->saveMeta("has_water_fontage_com", $request->has_water_fontage_com);
            $auction->saveMeta("has_water_fontage_vac", $request->has_water_fontage_vac);
            $auction->saveMeta("water_frontage", json_encode($request->water_frontage));
            $auction->saveMeta("viewOpt", $request->viewOpt);
            $auction->saveMeta("view", json_encode($request->view));
            $auction->saveMeta("otherView", $request->otherView);
            $auction->saveMeta("ownership", $request->ownership);
            $auction->saveMeta("ownership_com", $request->ownership_com);
            $auction->saveMeta("ownership_vac", $request->ownership_vac);
            $auction->saveMeta("otherOwnership", $request->otherOwnership);
            $auction->saveMeta("occupant_type", $request->occupant_type);
            $auction->saveMeta("occupant_type_com", $request->occupant_type_com);
            $auction->saveMeta("exiting_lease_or_tenant", $request->exiting_lease_or_tenant);
            $auction->saveMeta("exiting_lease_or_tenant_com", $request->exiting_lease_or_tenant_com);
            $auction->saveMeta("end_of_lease_date", $request->end_of_lease_date);
            $auction->saveMeta("end_of_lease_date_com", $request->end_of_lease_date_com);
            $auction->saveMeta("monthToMonth", $request->monthToMonth);
            $auction->saveMeta("monthToMonth_com", $request->monthToMonth_com);
            $auction->saveMeta("monthly_rental_ammount", $request->monthly_rental_ammount);
            $auction->saveMeta("monthly_rental_ammount_com", $request->monthly_rental_ammount_com);
            $auction->saveMeta("days_notice_to_terminate", $request->days_notice_to_terminate);
            $auction->saveMeta("days_notice_to_terminate_com", $request->days_notice_to_terminate_com);
            $auction->saveMeta("operating_expenses", $request->operating_expenses);
            $auction->saveMeta("net_operating_income", $request->net_operating_income);
            $auction->saveMeta("net_operating_income_type", $request->net_operating_income_type);
            $auction->saveMeta("has_leasing", $request->has_leasing);
            $auction->saveMeta("has_lease_restriction", $request->has_lease_restriction);
            $auction->saveMeta("association_approval_required", $request->association_approval_required);
            $auction->saveMeta("minimum_lease_period", $request->minimum_lease_period);
            $auction->saveMeta("minimum_lease_per_year", $request->minimum_lease_per_year);
            $auction->saveMeta("years_of_ownership", $request->years_of_ownership);
            $auction->saveMeta("number_of_ownership_prior_lease", $request->number_of_ownership_prior_lease);
            $auction->saveMeta("has_hoa", $request->has_hoa);
            $auction->saveMeta("has_hoa_vac", $request->has_hoa_vac);
            $auction->saveMeta("community_feature", json_encode($request->community_feature));
            $auction->saveMeta("association_amenitie", json_encode($request->association_amenitie));
            $auction->saveMeta("otherAssocAmenities", $request->otherAssocAmenities);
            $auction->saveMeta("otherAssocAmenities_vac", $request->otherAssocAmenities_vac);
            $auction->saveMeta("fee_include", json_encode($request->fee_include));
            $auction->saveMeta("otherFeeInclude", $request->otherFeeInclude);
            $auction->saveMeta("otherFeeInclude_vac", $request->otherFeeInclude_vac);
            $auction->saveMeta("amenities_with_additional_fees", $request->amenities_with_additional_fees);
            $auction->saveMeta("amenities_with_additional_fees_vac", $request->amenities_with_additional_fees_vac);
            $auction->saveMeta("comm_assoc_water_features", $request->comm_assoc_water_features);
            $auction->saveMeta("has_cdd", $request->has_cdd);
            $auction->saveMeta("annual_cdd_fee", $request->annual_cdd_fee);
            $auction->saveMeta("has_cdd_vac", $request->has_cdd_vac);
            $auction->saveMeta("annual_cdd_fee_vac", $request->annual_cdd_fee_vac);
            $auction->saveMeta("has_land_lease", $request->has_land_lease);
            $auction->saveMeta("land_lease_fee", $request->land_lease_fee);
            $auction->saveMeta("has_land_lease_vac", $request->has_land_lease_vac);
            $auction->saveMeta("land_lease_fee_vac", $request->land_lease_fee_vac);
            $auction->saveMeta("hoaFeeRequirements", $request->hoaFeeRequirements);
            $auction->saveMeta("hoaFeeRequirements_vac", $request->hoaFeeRequirements_vac);
            $auction->saveMeta("hoaFeeAmount", $request->hoaFeeAmount);
            $auction->saveMeta("hoaFeeAmount_vac", $request->hoaFeeAmount_vac);

            $auction->saveMeta("paymentSchedules", $request->paymentSchedules);
            $auction->saveMeta("paymentSchedules_vac", $request->paymentSchedules_vac);
            $auction->saveMeta("condoFeeAmount", $request->condoFeeAmount);
            $auction->saveMeta("condoFeeAmount_com", $request->condoFeeAmount_com);
            $auction->saveMeta("condoFee_vac", $request->condoFee_vac);
            $auction->saveMeta("condoFeeAmount_vac", $request->condoFeeAmount_vac);
            $auction->saveMeta("condoPay", $request->condoPay);
            $auction->saveMeta("condoPay_vac", $request->condoPay_vac);
            $auction->saveMeta("masterAssoc", $request->masterAssoc);
            $auction->saveMeta("masterAssociationFeeAmount", $request->masterAssociationFeeAmount);
            $auction->saveMeta("masterAssociationFeeAmount_vac", $request->masterAssociationFeeAmount_vac);
            $auction->saveMeta("assocSchedule", $request->assocSchedule);
            $auction->saveMeta("assocSchedule_vac", $request->assocSchedule_vac);
            $auction->saveMeta("masterAssociationName", $request->masterAssociationName);
            $auction->saveMeta("masterAssociationName_vac", $request->masterAssociationName_vac);
            $auction->saveMeta("masterAssociationContactPhone", $request->masterAssociationContactPhone);
            $auction->saveMeta("masterAssociationContactPhone_vac", $request->masterAssociationContactPhone_vac);
            $auction->saveMeta("additionalFees", $request->additionalFees);
            $auction->saveMeta("additionalFees_vac", $request->additionalFees_vac);
            $auction->saveMeta("additionalFeeReason", $request->additionalFeeReason);
            $auction->saveMeta("otherFeeAmount", $request->otherFeeAmount);
            $auction->saveMeta("otherFeeAmount_vac", $request->otherFeeAmount_vac);
            $auction->saveMeta("otherFee", $request->otherFee);
            $auction->saveMeta("associationManagerContactName", $request->associationManagerContactName);
            $auction->saveMeta("associationManagerContactName_vac", $request->associationManagerContactName_vac);
            $auction->saveMeta("associationManagerContactEmail", $request->associationManagerContactEmail);
            $auction->saveMeta("associationManagerContactEmail_vac", $request->associationManagerContactEmail_vac);
            $auction->saveMeta("associationManagerContactPhone", $request->associationManagerContactPhone);
            $auction->saveMeta("associationManagerContactPhone_vac", $request->associationManagerContactPhone_vac);
            $auction->saveMeta("associationManagerContactWebsite", $request->associationManagerContactWebsite);
            $auction->saveMeta("associationManagerContactWebsite_vac", $request->associationManagerContactWebsite_vac);
            $auction->saveMeta("olderPersons", $request->olderPersons);
            $auction->saveMeta("olderPersons_vac", $request->olderPersons_vac);
            $auction->saveMeta("description", $request->description);
            $auction->saveMeta("descriptionCom", $request->descriptionCom);
            $auction->saveMeta("descriptionVac", $request->descriptionVac);
            $auction->saveMeta("keywords", $request->keywords);
            $auction->saveMeta("disclamer", $request->disclamer);
            $auction->saveMeta("disclamer_com", $request->disclamer_com);
            $auction->saveMeta("disclamer_vac", $request->disclamer_vac);
            $auction->saveMeta("driving_directions", $request->driving_directions);
            $auction->saveMeta("driving_directions_com", $request->driving_directions_com);
            $auction->saveMeta("driving_directions_vac", $request->driving_directions_vac);
            $auction->saveMeta("looking_other_property", $request->looking_other_property);
            $auction->saveMeta("compensation_amount", $request->compensation_amount);
            $auction->saveMeta("compensation_amount_com", $request->compensation_amount_com);
            $auction->saveMeta("compensation_amount_vac", $request->compensation_amount_vac);
            $auction->saveMeta("listing_link", $request->listing_link);
            $auction->saveMeta("title_company_name", $request->title_company_name);
            $auction->saveMeta("title_company_name_com", $request->title_company_name_com);
            $auction->saveMeta("title_company_name_vac", $request->title_company_name_vac);
            $auction->saveMeta("title_company_address", $request->title_company_address);
            $auction->saveMeta("title_company_address_com", $request->title_company_address_com);
            $auction->saveMeta("title_company_address_vac", $request->title_company_address_vac);
            $auction->saveMeta("title_company_phone", $request->title_company_phone);
            $auction->saveMeta("title_company_phone_com", $request->title_company_phone_com);
            $auction->saveMeta("title_company_phone_vac", $request->title_company_phone_vac);
            $auction->saveMeta("title_company_email", $request->title_company_email);
            $auction->saveMeta("title_company_email_com", $request->title_company_email_com);
            $auction->saveMeta("title_company_email_vac", $request->title_company_email_vac);
            $auction->saveMeta("agent_first_name", $request->agent_first_name);
            $auction->saveMeta("agent_first_name_com", $request->agent_first_name_com);
            $auction->saveMeta("agent_first_name_vac", $request->agent_first_name_vac);
            $auction->saveMeta("agent_last_name", $request->agent_last_name);
            $auction->saveMeta("agent_last_name_com", $request->agent_last_name_com);
            $auction->saveMeta("agent_last_name_vac", $request->agent_last_name_vac);
            $auction->saveMeta("agent_phone", $request->agent_phone);
            $auction->saveMeta("agent_phone_com", $request->agent_phone_com);
            $auction->saveMeta("agent_phone_vac", $request->agent_phone_vac);
            $auction->saveMeta("agent_email", $request->agent_email);
            $auction->saveMeta("agent_email_com", $request->agent_email_com);
            $auction->saveMeta("agent_email_vac", $request->agent_email_vac);
            $auction->saveMeta("agent_brokerage", $request->agent_brokerage);
            $auction->saveMeta("agent_brokerage_com", $request->agent_brokerage_com);
            $auction->saveMeta("agent_brokerage_vac", $request->agent_brokerage_vac);
            $auction->saveMeta("agent_license_no", $request->agent_license_no);
            $auction->saveMeta("agent_license_no_com", $request->agent_license_no_com);
            $auction->saveMeta("agent_license_no_vac", $request->agent_license_no_vac);
            $auction->saveMeta("agent_mls_id", $request->agent_mls_id);
            $auction->saveMeta("agent_mls_id_com", $request->agent_mls_id_com);
            $auction->saveMeta("agent_mls_id_vac", $request->agent_mls_id_vac);
            $auction->saveMeta("realEstateAgent", $request->realEstateAgent);
            $auction->saveMeta("three_d_tour", $request->three_d_tour);
            $auction->saveMeta('video_type', $request->video_type);
            $auction->saveMeta('youtube_video_link', $request->youtube_video_link);
            $auction->saveMeta('vimeo_video_link', $request->vimeo_video_link);



            // Pictures and Video Upload
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedVideos = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v'];

            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps'];
            $visible_upload_file = [];
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf

            if ($request->hasFile('floor_plan')) {
                $file = $request->floor_plan;
                foreach ($file as $singleFile) {
                    $extension = $singleFile->getClientOriginalExtension();
                    $check = in_array($extension, $allowedFiles);
                    if ($check) {
                        $uuid = (string) Str::uuid();
                        $fileName = $uuid . '.' . $extension;
                        $singleFile->move(public_path('auction/files'), $fileName);
                        $auction->saveMeta('floor_plan', 'auction/files/' . $fileName);
                    }
                }
            }
            // if ($request->hasFile('photo')) {
            //     $photo = $request->photo;
            //     $extension = $photo->getClientOriginalExtension();
            //     $check = in_array($extension, $allowedFiles);
            //     if ($check) {
            //         $uuid = (string) Str::uuid();
            //         $photoName = $uuid . '.' . $extension;
            //         $photo->move(public_path('auction/images'), $photoName);
            //         $auction->saveMeta('photo', 'auction/images/' . $photoName);
            //     }
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
                $auction->saveMeta('photos', json_encode($photoLinks));
            }

            // Picture Upload
            // Video Upload
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
            // Video Upload
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
                $auction->saveMeta('disclosures', json_encode($disclosures));
            }

            $auction->saveMeta("listing_ai_faq", json_encode($request->listing_ai_faq ?? []));

            DB::commit();
            // SellerPropertyAuctionCreated::dispatch($auction);

            return redirect()->route('view-pl', $auction->id)->with('success', 'Property listing added successfully.');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add property listing.');
        }
    }

    public function edit($id, Request $request)
    {
        $page_data['auction'] = PropertyAuction::find($id);
        $page_data['title'] = 'Edit Seller\'s Property Listing';
        $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        return view('seller_property.edit', $page_data);
    }

    public function update($id, Request $request)
    {
        try {
            DB::beginTransaction();

            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lengths = explode(' ', $request->auction_length);
                $auction_length_days = current($auction_lengths);
            } else {
                $auction_length_days = '-1';
            }
            $auction = PropertyAuction::find($id);
            $auction->user_id = Auth::user()->id;
            $auction->address = $request->address;
            $auction->auction_type = $request->auction_type;
            $auction->auction_length = $auction_length_days;
            $auction->description = $request->description;
            $keywords = str_replace(' ', ',', $request->keywords);
            $auction->keywords = $keywords;
            $auction->autobid_price = $request->autobid_price;
            $auction->autobid_price2 = $request->autobid_price2;
            $auction->autobid_price3 = $request->autobid_price3;
            $auction->escrow_amount = $request->escrow_amount;
            $auction->escrow_amount2 = $request->escrow_amount2;
            $auction->inspection_period = $request->inspection_period;
            $auction->inspection_period2 = $request->inspection_period2;
            $auction->closing_days2 = $request->closing_days2;
            $auction->closing_days = $request->closing_days;
            $auction->auto_bid = $request->auto_bid;
            $listing_date = Carbon::parse($request->listing_date);
            $expiration_date = Carbon::parse($request->expiration_date);
            $auction->listing_date = $listing_date;
            $auction->expiration_date = $expiration_date;
            $auction->update();
            $auction->saveMeta("unit_number", $request->unit_number);
            $auction->saveMeta("address", $request->address);
            $auction->saveMeta("property_lat", $request->property_lat);
            $auction->saveMeta("property_lng", $request->property_lng);
            $auction->saveMeta("google_place_id", $request->google_place_id);
            $auction->saveMeta("formatted_address", $request->formatted_address);
            $auction->saveMeta("city", $request->city);
            $auction->saveMeta("county", $request->county);
            $auction->saveMeta("state", $request->state);
            $auction->saveMeta("listing_date", $request->listing_date);
            $auction->saveMeta("expiration_date", $request->expiration_date);
            $auction->saveMeta("service_type", $request->service_type);
            $auction->saveMeta("representation", $request->representation);
            $auction->saveMeta("special_sale", $request->special_sale);
            $auction->saveMeta("custom_special_sale_provision", $request->custom_special_sale_provision);
            $auction->saveMeta("contribute_term", $request->contribute_term);
            $auction->saveMeta("commercialseller_contract_yes", $request->commercialseller_contract_yes);
            $auction->saveMeta("custom_seller_contract_no", $request->custom_seller_contract_no);
            $auction->saveMeta("auction_type", $request->auction_type);
            $auction->saveMeta("auction_length", $request->auction_length);
            $auction->saveMeta("price", $request->price);
            $auction->saveMeta("buy_now_price", $request->buy_now_price);
            $auction->saveMeta("buy_now_price_per_sqfeet", $request->buy_now_price_per_sqfeet);
            $auction->saveMeta("starting_price", $request->starting_price);
            $auction->saveMeta("reserve_price", $request->reserve_price);
            $auction->saveMeta("price_per_sqfeet", $request->price_per_sqfeet);
            $auction->saveMeta("escrow_amount", $request->escrow_amount);
            $auction->saveMeta("closing_days", $request->closing_days);
            $auction->saveMeta("contigencies_accepted_by_seller", json_encode($request->contigencies_accepted_by_seller));
            $auction->saveMeta("inspection", $request->inspection);
            $auction->saveMeta("appraisal", $request->appraisal);
            $auction->saveMeta("finance", $request->finance);
            $auction->saveMeta("saleContingency", $request->saleContingency);
            $auction->saveMeta("acceptable", $request->acceptable);
            $auction->saveMeta("acceptable_days", $request->acceptable_days);

            $auction->saveMeta("inspection_auction", $request->inspection_auction);
            $auction->saveMeta("appraisal_auction", $request->appraisal_auction);
            $auction->saveMeta("finance_auction", $request->finance_auction);
            $auction->saveMeta("saleContingency_auction", $request->saleContingency_auction);
            $auction->saveMeta("acceptable_auction", $request->acceptable_auction);
            $auction->saveMeta("acceptable_days_auction", $request->acceptable_days_auction);

            $auction->saveMeta("term_financings", json_encode($request->term_financings));
            $auction->saveMeta("otherFinancing", $request->otherFinancing);
            $auction->saveMeta("type_of_NFT_accepted", $request->type_of_NFT_accepted);
            $auction->saveMeta("percentage_in_NFT", $request->percentage_in_NFT);
            $auction->saveMeta("percentage_in_cash", $request->percentage_in_cash);
            $auction->saveMeta("cryptocurrency_type", $request->cryptocurrency_type);
            $auction->saveMeta("percentage_in_crypto", $request->percentage_in_crypto);
            $auction->saveMeta("purchase_price_seller_financing", $request->purchase_price_seller_financing);
            $auction->saveMeta("down_payment_seller_financing", $request->down_payment_seller_financing);
            $auction->saveMeta("seller_financing_amount", $request->seller_financing_amount);
            $auction->saveMeta("interest_rate_seller_financing", $request->interest_rate_seller_financing);
            $auction->saveMeta("term_seller_financing", $request->term_seller_financing);
            $auction->saveMeta("monthly_payment_seller_financing", $request->monthly_payment_seller_financing);
            $auction->saveMeta("closing_costs", $request->closing_costs);
            $auction->saveMeta("ballonPenalty", $request->ballonPenalty);
            $auction->saveMeta("ballonPenaltyYes", $request->ballonPenaltyYes);
            $auction->saveMeta("balloonPay", $request->balloonPay);
            $auction->saveMeta("balloonPayment", $request->balloonPayment);
            $auction->saveMeta("balloonDue", $request->balloonDue);
            $auction->saveMeta("desired_offering_price", $request->desired_offering_price);
            $auction->saveMeta("lease_option_terms", $request->lease_option_terms);
            $auction->saveMeta("proposed_lease_duration", $request->proposed_lease_duration);
            $auction->saveMeta("monthly_payment_amount", $request->monthly_payment_amount);
            $auction->saveMeta("lease_option_conditions", $request->lease_option_conditions);
            $auction->saveMeta("price_changes_possibility", $request->price_changes_possibility);
            $auction->saveMeta("exchange_trade", $request->exchange_trade);
            $auction->saveMeta("sellerFeeOption", $request->sellerFeeOption);
            $auction->saveMeta("sellerFeeOptionYes", $request->sellerFeeOptionYes);
            $auction->saveMeta("desired_offering_price_lease_purchase", $request->desired_offering_price_lease_purchase);
            $auction->saveMeta("lease_purchase_terms", $request->lease_purchase_terms);
            $auction->saveMeta("proposed_lease_duration_lease_purchase", $request->proposed_lease_duration_lease_purchase);
            $auction->saveMeta("monthly_payment_amount_lease_purchase", $request->monthly_payment_amount_lease_purchase);
            $auction->saveMeta("lease_purchase_conditions", $request->lease_purchase_conditions);
            $auction->saveMeta("price_changes_possibility_lease_purchase", $request->price_changes_possibility_lease_purchase);
            $auction->saveMeta("sellerFeePurchase", $request->sellerFeePurchase);
            $auction->saveMeta("sellerFeePurchaseYes", $request->sellerFeePurchaseYes);
            $auction->saveMeta("assumable_terms_offered", $request->assumable_terms_offered);
            $auction->saveMeta("restrictions_or_qualifications", $request->restrictions_or_qualifications);
            $auction->saveMeta("assumable_interest", $request->assumable_interest);
            $auction->saveMeta("assumable_monthly_payment", $request->assumable_monthly_payment);
            $auction->saveMeta("assumable_balance_loan", $request->assumable_balance_loan);
            $auction->saveMeta("outstandingBalanceYes", $request->outstandingBalanceYes);
            $auction->saveMeta("loan_balance_down_payment", $request->loan_balance_down_payment);
            $auction->saveMeta("otherTrade", $request->otherTrade);
            $auction->saveMeta("estimatedTrade", $request->estimatedTrade);
            $auction->saveMeta("specificTrade", $request->specificTrade);
            $auction->saveMeta("cashTrade", $request->cashTrade);
            $auction->saveMeta("valueTrade", $request->valueTrade);
            $auction->saveMeta("sellerOffer", $request->sellerOffer);
            $auction->saveMeta("sellerOfferYes", $request->sellerOfferYes);
            $auction->saveMeta("escrow_amount2", $request->escrow_amount2);
            $auction->saveMeta("closing_days2", $request->closing_days2);
            $auction->saveMeta("timeFrame", $request->timeFrame);
            $auction->saveMeta("multiTimeFrame", $request->multiTimeFrame);
            $auction->saveMeta("property_type", $request->property_type);
            $auction->saveMeta("propertyStyles", $request->propertyStyles);
            $auction->saveMeta("property_items", $request->property_items);
            $auction->saveMeta("otherProperty", $request->otherProperty);
            $auction->saveMeta("prop_condition", $request->prop_condition);
            $auction->saveMeta("otherCondition", $request->otherCondition);
            $auction->saveMeta("bedrooms", $request->bedrooms);
            $auction->saveMeta("custom_bedrooms", $request->custom_bedrooms);
            $auction->saveMeta("bathrooms", $request->bathrooms);
            $auction->saveMeta("custom_bathrooms", $request->custom_bathrooms);
            $auction->saveMeta("bathroomsCom", $request->bathroomsCom);
            $auction->saveMeta("custom_bathrooms_com", $request->custom_bathrooms_com);
            $auction->saveMeta("unit_type_data", json_encode($request->unit_type_data));
            $auction->saveMeta("unit_type", $request->unit_type);
            $auction->saveMeta("beds_unit", $request->beds_unit);
            $auction->saveMeta("baths_unit", $request->baths_unit);
            $auction->saveMeta("sqt_ft_heated", $request->sqt_ft_heated);
            $auction->saveMeta("number_of_units", $request->number_of_units);
            $auction->saveMeta("occupied", $request->occupied);
            $auction->saveMeta("custom_occupied", $request->custom_occupied);
            $auction->saveMeta("current_rent", $request->current_rent);
            $auction->saveMeta("expected_rent", $request->expected_rent);
            $auction->saveMeta("garage_spaces_unit", $request->garage_spaces_unit);
            $auction->saveMeta("unit_type_of_description", $request->unit_type_of_description);
            $auction->saveMeta("annual_gross_income", $request->annual_gross_income);
            $auction->saveMeta("total_monthly_rent", $request->total_monthly_rent);
            $auction->saveMeta("total_monthly_expenses", $request->total_monthly_expenses);
            $auction->saveMeta("annual_net_income", $request->annual_net_income);
            $auction->saveMeta("est_annual_market_income", $request->est_annual_market_income);
            $auction->saveMeta("annual_expenses", $request->annual_expenses);

            $auction->saveMeta("annual_ttl_schedule_income", $request->annual_ttl_schedule_income);
            $auction->saveMeta("annual_income_type", $request->annual_income_type);
            $auction->saveMeta("saleInclude", json_encode($request->saleInclude));
            $auction->saveMeta("otherSale", $request->otherSale);
            $auction->saveMeta("number_of_tenants", $request->number_of_tenants);
            $auction->saveMeta("class_of_space", $request->class_of_space);
            $auction->saveMeta("sale_include", $request->sale_include);
            $auction->saveMeta("number_of_hotel", $request->number_of_hotel);
            $auction->saveMeta("number_of_conference", $request->number_of_conference);
            $auction->saveMeta("number_of_restrooms", $request->number_of_restrooms);
            $auction->saveMeta("number_of_bays_high", $request->number_of_bays_high);
            $auction->saveMeta("number_of_bays_level", $request->number_of_bays_level);
            $auction->saveMeta("number_of_offices", $request->number_of_offices);
            $auction->saveMeta("has_condo_enviornment", $request->has_condo_enviornment);
            $auction->saveMeta("condo_fee_terms", $request->condo_fee_terms);
            $auction->saveMeta("association_manager_contact_name", $request->association_manager_contact_name);
            $auction->saveMeta("association_manager_contact_email", $request->association_manager_contact_email);
            $auction->saveMeta("association_manager_contact_number", $request->association_manager_contact_number);
            $auction->saveMeta("association_manager_contact_website", $request->association_manager_contact_website);
            $auction->saveMeta("community_features", json_encode($request->community_features));

            $auction->saveMeta("custom_leases_length", $request->custom_leases_length);
            $auction->saveMeta("length_of_lease", json_encode($request->length_of_lease));
            $auction->saveMeta("terms_of_lease", $request->terms_of_lease);
            $auction->saveMeta("otherTermLease", $request->otherTermLease);
            $auction->saveMeta("tenant_pays", $request->tenant_pays);
            $auction->saveMeta("otherTenantPay", $request->otherTenantPay);
            $auction->saveMeta("financial_sources", $request->financial_sources);
            $auction->saveMeta("total_number_of_units", $request->total_number_of_units);
            $auction->saveMeta("heated_sqft", $request->heated_sqft);
            $auction->saveMeta("heated_sqft_com", $request->heated_sqft_com);
            $auction->saveMeta("accessibilityFeatures", json_encode($request->accessibilityFeatures));
            $auction->saveMeta("laundryFeatures", json_encode($request->laundryFeatures));
            $auction->saveMeta("total_sqft", $request->total_sqft);
            $auction->saveMeta("total_sqft_com", $request->total_sqft_com);
            $auction->saveMeta("heated_source", $request->heated_source);
            $auction->saveMeta("heated_source_com", $request->heated_source_com);
            $auction->saveMeta("total_aceage", $request->total_aceage);
            $auction->saveMeta("total_aceage_com", $request->total_aceage_com);
            $auction->saveMeta("total_aceage_vac", $request->total_aceage_vac);
            $auction->saveMeta("lot_size", $request->lot_size);
            $auction->saveMeta("lot_size_com", $request->lot_size_com);
            $auction->saveMeta("lot_size_vac", $request->lot_size_vac);
            $auction->saveMeta("year_built", $request->year_built);
            $auction->saveMeta("year_built_com", $request->year_built_com);
            $auction->saveMeta("legal_description", $request->legal_description);
            $auction->saveMeta("legal_description_com", $request->legal_description_com);
            $auction->saveMeta("legal_description_vac", $request->legal_description_vac);
            $auction->saveMeta("legal_subdivison_name", $request->legal_subdivison_name);
            $auction->saveMeta("legal_subdivison_name_com", $request->legal_subdivison_name_com);
            $auction->saveMeta("legal_subdivison_name_vac", $request->legal_subdivison_name_vac);
            $auction->saveMeta("appliances", json_encode($request->appliances));
            $auction->saveMeta("otherAppliances", $request->otherAppliances);
            $auction->saveMeta("otherAppliancesCom", $request->otherAppliancesCom);
            $auction->saveMeta("fireplace", $request->fireplace);
            $auction->saveMeta("has_furnishing", $request->has_furnishing);
            $auction->saveMeta("furnishings_include", $request->furnishings_include);
            $auction->saveMeta("has_additional_fees", $request->has_additional_fees);
            $auction->saveMeta("listed_furniture_price", $request->listed_furniture_price);
            $auction->saveMeta("has_furnishing_com", $request->has_furnishing_com);
            $auction->saveMeta("furnishings_include_com", $request->furnishings_include_com);
            $auction->saveMeta("has_additional_fees_com", $request->has_additional_fees_com);
            $auction->saveMeta("listed_furniture_price_com", $request->listed_furniture_price_com);
            $auction->saveMeta("interior_features", json_encode($request->interior_features));
            $auction->saveMeta("otherInterior", $request->otherInterior);
            $auction->saveMeta("additionalRooms", json_encode($request->additionalRooms));
            $auction->saveMeta("number_of_buildings", $request->number_of_buildings);
            $auction->saveMeta("total_number", $request->total_number);
            $auction->saveMeta("number_of_buildings_com", $request->number_of_buildings_com);
            $auction->saveMeta("floors_in_unit", $request->floors_in_unit);
            $auction->saveMeta("floors_in_unit_com", $request->floors_in_unit_com);
            $auction->saveMeta("total_floors", $request->total_floors);
            $auction->saveMeta("total_floors_com", $request->total_floors_com);
            $auction->saveMeta("building_elevator", $request->building_elevator);
            $auction->saveMeta("building_elevator_com", $request->building_elevator_com);
            $auction->saveMeta("floor_covering", json_encode($request->floor_covering));
            $auction->saveMeta("otherFloorCovering", $request->otherFloorCovering);
            $auction->saveMeta("otherFloorCoveringCom", $request->otherFloorCoveringCom);
            $auction->saveMeta("front_exposure", $request->front_exposure);
            $auction->saveMeta("front_exposure_com", $request->front_exposure_com);
            $auction->saveMeta("front_exposure_vac", $request->front_exposure_vac);
            $auction->saveMeta("foundation", json_encode($request->foundation));
            $auction->saveMeta("otherFoundation", $request->otherFoundation);
            $auction->saveMeta("otherFoundationCom", $request->otherFoundationCom);
            $auction->saveMeta("exterior_construction", json_encode($request->exterior_construction));
            $auction->saveMeta("otherExterior", $request->otherExterior);
            $auction->saveMeta("otherExteriorCom", $request->otherExteriorCom);
            $auction->saveMeta("otherConstruction", $request->otherConstruction);
            $auction->saveMeta("otherConstructionCom", $request->otherConstructionCom);
            $auction->saveMeta("exterior_feature", json_encode($request->exterior_feature));
            $auction->saveMeta("lot_features", json_encode($request->lot_features));
            $auction->saveMeta("otherLotFeature", $request->otherLotFeature);
            $auction->saveMeta("otherLotFeatureCom", $request->otherLotFeatureCom);
            $auction->saveMeta("otherLotFeatureVac", $request->otherLotFeatureVac);
            $auction->saveMeta("otherStructureOpt", $request->otherStructureOpt);
            $auction->saveMeta("otherStruct", json_encode($request->otherStruct));
            $auction->saveMeta("otherStructure", $request->otherStructure);
            $auction->saveMeta("unitStructure", json_encode($request->unitStructure));
            $auction->saveMeta("unitStructureOther", $request->unitStructureOther);
            $auction->saveMeta("sqftStructure", $request->sqftStructure);
            $auction->saveMeta("totalSqft", $request->totalSqft);
            $auction->saveMeta("roof", json_encode($request->roof));
            $auction->saveMeta("otherRoof", $request->otherRoof);
            $auction->saveMeta("otherRoofCom", $request->otherRoofCom);
            $auction->saveMeta("road_surface_type", json_encode($request->road_surface_type));
            $auction->saveMeta("otherSurface", $request->otherSurface);
            $auction->saveMeta("otherSurfaceCom", $request->otherSurfaceCom);
            $auction->saveMeta("otherSurfaceVac", $request->otherSurfaceVac);
            $auction->saveMeta("garage", $request->garage);
            $auction->saveMeta("garage_spaces", $request->garage_spaces);
            $auction->saveMeta("carport", $request->carport);
            $auction->saveMeta("carport_spaces", $request->carport_spaces);
            $auction->saveMeta("pool", $request->pool);
            $auction->saveMeta("poolOpt", $request->poolOpt);
            $auction->saveMeta("ptes_Allowed", $request->ptes_Allowed);
            $auction->saveMeta("ptes_Allowed_vac", $request->ptes_Allowed_vac);
            $auction->saveMeta("acceptablePet", $request->acceptablePet);
            $auction->saveMeta("has_rental_restrictions", $request->has_rental_restrictions);
            $auction->saveMeta("total_pets_allowed", $request->total_pets_allowed);
            $auction->saveMeta("custom_pets_allowed", $request->custom_pets_allowed);
            $auction->saveMeta("max_pet_weight", $request->max_pet_weight);
            $auction->saveMeta("pet_restrictions", $request->pet_restrictions);
            $auction->saveMeta("tax_id", $request->tax_id);
            $auction->saveMeta("tax_year", $request->tax_year);
            $auction->saveMeta("taxes_annual_amount", $request->taxes_annual_amount);
            $auction->saveMeta("has_homestead", $request->has_homestead);
            $auction->saveMeta("additionalParcels", $request->additionalParcels);
            $auction->saveMeta("additionalParcelsCom", $request->additionalParcelsCom);
            $auction->saveMeta("additionalParcelsVac", $request->additionalParcelsVac);
            $auction->saveMeta("total_number_of_parcels", $request->total_number_of_parcels);
            $auction->saveMeta("total_number_of_parcels_com", $request->total_number_of_parcels_com);
            $auction->saveMeta("total_number_of_parcels_vac", $request->total_number_of_parcels_vac);
            $auction->saveMeta("additional_tax_id", $request->additional_tax_id);
            $auction->saveMeta("additional_tax_id_com", $request->additional_tax_id_com);
            $auction->saveMeta("additional_tax_id_vac", $request->additional_tax_id_vac);
            $auction->saveMeta("zoning", $request->zoning);
            $auction->saveMeta("zoning_com", $request->zoning_com);
            $auction->saveMeta("zoning_vac", $request->zoning);
            $auction->saveMeta("is_in_flood_zone", $request->is_in_flood_zone);
            $auction->saveMeta("flood_zone_code", $request->flood_zone_code);
            $auction->saveMeta("flood_zone_code_com", $request->flood_zone_code_com);
            $auction->saveMeta("flood_zone_code_vac", $request->flood_zone_code_vac);
            $auction->saveMeta("front_footage", $request->front_footage);
            $auction->saveMeta("utilities", json_encode($request->utilities));
            $auction->saveMeta("otherUtilitise", $request->otherUtilitise);
            $auction->saveMeta("otherUtilitiseCom", $request->otherUtilitiseCom);
            $auction->saveMeta("otherUtilitiseVac", $request->otherUtilitiseVac);
            $auction->saveMeta("water", json_encode($request->water));
            $auction->saveMeta("otherWater", $request->otherWater);
            $auction->saveMeta("otherWaterCom", $request->otherWaterCom);
            $auction->saveMeta("otherWaterVac", $request->otherWaterVac);
            $auction->saveMeta("sewer", json_encode($request->sewer));
            $auction->saveMeta("otherSewer", $request->otherSewer);
            $auction->saveMeta("otherSewerCom", $request->otherSewerCom);
            $auction->saveMeta("otherSewerVac", $request->otherSewerVac);
            $auction->saveMeta("air_conditioning", json_encode($request->air_conditioning));
            $auction->saveMeta("otherAirCondition", $request->otherAirCondition);
            $auction->saveMeta("air_conditioning_com", json_encode($request->air_conditioning_com));
            $auction->saveMeta("otherAirConditionCom", $request->otherAirConditionCom);
            $auction->saveMeta("heating_and_fuel", json_encode($request->heating_and_fuel));
            $auction->saveMeta("otherHeatingFuel", $request->otherHeatingFuel);
            $auction->saveMeta("otherHeatingFuelCom", $request->otherHeatingFuelCom);
            $auction->saveMeta("room_type", json_encode($request->room_type));
            $auction->saveMeta("room_details_data", json_encode($request->room_details_data));
            $auction->saveMeta("approximate_room_dimensions", $request->approximate_room_dimensions);
            $auction->saveMeta("room_feature", json_encode($request->room_feature));
            $auction->saveMeta("custom_room_features", $request->custom_room_features);
            $auction->saveMeta("has_water_access", $request->has_water_access);
            $auction->saveMeta("has_water_access_com", $request->has_water_access_com);
            $auction->saveMeta("has_water_access_vac", $request->has_water_access_vac);
            $auction->saveMeta("water_access", json_encode($request->water_access));
            $auction->saveMeta("has_water_view", $request->has_water_view);
            $auction->saveMeta("has_water_view_com", $request->has_water_view_com);
            $auction->saveMeta("has_water_view_vac", $request->has_water_view_vac);
            $auction->saveMeta("water_view", json_encode($request->water_view));
            $auction->saveMeta("has_water_extra", $request->has_water_extra);
            $auction->saveMeta("has_water_extra_com", $request->has_water_extra_com);
            $auction->saveMeta("has_water_extra_vac", $request->has_water_extra_vac);
            $auction->saveMeta("water_extras", json_encode($request->water_extras));
            $auction->saveMeta("has_dock", $request->has_dock);
            $auction->saveMeta("has_dock_com", $request->has_dock_com);
            $auction->saveMeta("has_dock_vac", $request->has_dock_vac);
            $auction->saveMeta("dock", json_encode($request->dock));
            $auction->saveMeta("custom_dock", $request->custom_dock);
            $auction->saveMeta("dock_lift_capacity", $request->dock_lift_capacity);
            $auction->saveMeta("dock_year_built", $request->dock_year_built);
            $auction->saveMeta("dock_dimension", $request->dock_dimension);
            $auction->saveMeta("dock_maintenance_fee", $request->dock_maintenance_fee);

            $auction->saveMeta("green_features", $request->green_features);
            $auction->saveMeta("building_verification", $request->building_verification);
            $auction->saveMeta("building_verification_other", $request->building_verification_other);
            $auction->saveMeta("green_status", $request->green_status);
            $auction->saveMeta("green_year", $request->green_year);
            $auction->saveMeta("green_version", $request->green_version);
            $auction->saveMeta("green_body", $request->green_body);
            $auction->saveMeta("green_metric", $request->green_metric);
            $auction->saveMeta("green_rating", $request->green_rating);
            $auction->saveMeta("green_source", $request->green_source);
            $auction->saveMeta("green_source_other", $request->green_source_other);
            $auction->saveMeta("green_url", $request->green_url);
            $auction->saveMeta("green_sustainability", json_encode($request->green_sustainability));
            $auction->saveMeta("green_generation", json_encode($request->green_generation));
            $auction->saveMeta("green_water_features", json_encode($request->green_water_features));
            $auction->saveMeta("green_energy_features", json_encode($request->green_energy_features));
            $auction->saveMeta("green_landscaping", json_encode($request->green_landscaping));
            $auction->saveMeta("green_solar", json_encode($request->green_solar));
            $auction->saveMeta("green_disaster", json_encode($request->green_disaster));
            $auction->saveMeta("green_air", json_encode($request->green_air));

            $auction->saveMeta("licenses", $request->licenses);
            $auction->saveMeta("custom_licenses", $request->custom_licenses);
            $auction->saveMeta("other_structures", json_encode($request->other_structures));
            $auction->saveMeta("custom_other_structures", $request->custom_other_structures);

            $auction->saveMeta("has_water_fontage", $request->has_water_fontage);
            $auction->saveMeta("has_water_fontage_com", $request->has_water_fontage_com);
            $auction->saveMeta("has_water_fontage_vac", $request->has_water_fontage_vac);
            $auction->saveMeta("water_frontage", json_encode($request->water_frontage));
            $auction->saveMeta("viewOpt", $request->viewOpt);
            $auction->saveMeta("view", json_encode($request->view));
            $auction->saveMeta("otherView", $request->otherView);
            $auction->saveMeta("ownership", $request->ownership);
            $auction->saveMeta("ownership_com", $request->ownership_com);
            $auction->saveMeta("ownership_vac", $request->ownership_vac);
            $auction->saveMeta("otherOwnership", $request->otherOwnership);
            $auction->saveMeta("occupant_type", $request->occupant_type);
            $auction->saveMeta("occupant_type_com", $request->occupant_type_com);
            $auction->saveMeta("exiting_lease_or_tenant", $request->exiting_lease_or_tenant);
            $auction->saveMeta("exiting_lease_or_tenant_com", $request->exiting_lease_or_tenant_com);
            $auction->saveMeta("end_of_lease_date", $request->end_of_lease_date);
            $auction->saveMeta("end_of_lease_date_com", $request->end_of_lease_date_com);
            $auction->saveMeta("monthToMonth", $request->monthToMonth);
            $auction->saveMeta("monthToMonth_com", $request->monthToMonth_com);
            $auction->saveMeta("monthly_rental_ammount", $request->monthly_rental_ammount);
            $auction->saveMeta("monthly_rental_ammount_com", $request->monthly_rental_ammount_com);
            $auction->saveMeta("days_notice_to_terminate", $request->days_notice_to_terminate);
            $auction->saveMeta("days_notice_to_terminate_com", $request->days_notice_to_terminate_com);
            $auction->saveMeta("operating_expenses", $request->operating_expenses);
            $auction->saveMeta("net_operating_income", $request->net_operating_income);
            $auction->saveMeta("net_operating_income_type", $request->net_operating_income_type);
            $auction->saveMeta("has_leasing", $request->has_leasing);
            $auction->saveMeta("has_lease_restriction", $request->has_lease_restriction);
            $auction->saveMeta("association_approval_required", $request->association_approval_required);
            $auction->saveMeta("minimum_lease_period", $request->minimum_lease_period);
            $auction->saveMeta("minimum_lease_per_year", $request->minimum_lease_per_year);
            $auction->saveMeta("years_of_ownership", $request->years_of_ownership);
            $auction->saveMeta("number_of_ownership_prior_lease", $request->number_of_ownership_prior_lease);
            $auction->saveMeta("has_hoa", $request->has_hoa);
            $auction->saveMeta("has_hoa_vac", $request->has_hoa_vac);
            $auction->saveMeta("community_feature", json_encode($request->community_feature));
            $auction->saveMeta("association_amenitie", json_encode($request->association_amenitie));
            $auction->saveMeta("otherAssocAmenities", $request->otherAssocAmenities);
            $auction->saveMeta("otherAssocAmenities_vac", $request->otherAssocAmenities_vac);
            $auction->saveMeta("fee_include", json_encode($request->fee_include));
            $auction->saveMeta("otherFeeInclude", $request->otherFeeInclude);
            $auction->saveMeta("otherFeeInclude_vac", $request->otherFeeInclude_vac);
            $auction->saveMeta("amenities_with_additional_fees", $request->amenities_with_additional_fees);
            $auction->saveMeta("amenities_with_additional_fees_vac", $request->amenities_with_additional_fees_vac);
            $auction->saveMeta("comm_assoc_water_features", $request->comm_assoc_water_features);
            $auction->saveMeta("has_cdd", $request->has_cdd);
            $auction->saveMeta("annual_cdd_fee", $request->annual_cdd_fee);
            $auction->saveMeta("has_cdd_vac", $request->has_cdd_vac);
            $auction->saveMeta("annual_cdd_fee_vac", $request->annual_cdd_fee_vac);
            $auction->saveMeta("has_land_lease", $request->has_land_lease);
            $auction->saveMeta("land_lease_fee", $request->land_lease_fee);
            $auction->saveMeta("has_land_lease_vac", $request->has_land_lease_vac);
            $auction->saveMeta("land_lease_fee_vac", $request->land_lease_fee_vac);
            $auction->saveMeta("hoaFeeRequirements", $request->hoaFeeRequirements);
            $auction->saveMeta("hoaFeeRequirements_vac", $request->hoaFeeRequirements_vac);
            $auction->saveMeta("hoaFeeAmount", $request->hoaFeeAmount);
            $auction->saveMeta("hoaFeeAmount_vac", $request->hoaFeeAmount_vac);
            $auction->saveMeta("paymentSchedules", $request->paymentSchedules);
            $auction->saveMeta("paymentSchedules_vac", $request->paymentSchedules_vac);
            $auction->saveMeta("condoFeeAmount", $request->condoFeeAmount);
            $auction->saveMeta("condoFeeAmount_com", $request->condoFeeAmount_com);
            $auction->saveMeta("condoFee_vac", $request->condoFee_vac);
            $auction->saveMeta("condoFeeAmount_vac", $request->condoFeeAmount_vac);
            $auction->saveMeta("condoPay", $request->condoPay);
            $auction->saveMeta("condoPay_vac", $request->condoPay_vac);
            $auction->saveMeta("masterAssoc", $request->masterAssoc);
            $auction->saveMeta("masterAssociationFeeAmount", $request->masterAssociationFeeAmount);
            $auction->saveMeta("masterAssociationFeeAmount_vac", $request->masterAssociationFeeAmount_vac);
            $auction->saveMeta("assocSchedule", $request->assocSchedule);
            $auction->saveMeta("assocSchedule_vac", $request->assocSchedule_vac);
            $auction->saveMeta("masterAssociationName", $request->masterAssociationName);
            $auction->saveMeta("masterAssociationName_vac", $request->masterAssociationName_vac);
            $auction->saveMeta("masterAssociationContactPhone", $request->masterAssociationContactPhone);
            $auction->saveMeta("masterAssociationContactPhone_vac", $request->masterAssociationContactPhone_vac);
            $auction->saveMeta("additionalFees", $request->additionalFees);
            $auction->saveMeta("additionalFees_vac", $request->additionalFees_vac);
            $auction->saveMeta("additionalFeeReason", $request->additionalFeeReason);
            $auction->saveMeta("otherFeeAmount", $request->otherFeeAmount);
            $auction->saveMeta("otherFeeAmount_vac", $request->otherFeeAmount_vac);
            $auction->saveMeta("otherFee", $request->otherFee);
            $auction->saveMeta("associationManagerContactName", $request->associationManagerContactName);
            $auction->saveMeta("associationManagerContactName_vac", $request->associationManagerContactName_vac);
            $auction->saveMeta("associationManagerContactEmail", $request->associationManagerContactEmail);
            $auction->saveMeta("associationManagerContactEmail_vac", $request->associationManagerContactEmail_vac);
            $auction->saveMeta("associationManagerContactPhone", $request->associationManagerContactPhone);
            $auction->saveMeta("associationManagerContactPhone_vac", $request->associationManagerContactPhone_vac);
            $auction->saveMeta("associationManagerContactWebsite", $request->associationManagerContactWebsite);
            $auction->saveMeta("associationManagerContactWebsite_vac", $request->associationManagerContactWebsite_vac);
            $auction->saveMeta("olderPersons", $request->olderPersons);
            $auction->saveMeta("olderPersons_vac", $request->olderPersons_vac);
            $auction->saveMeta("description", $request->description);
            $auction->saveMeta("descriptionCom", $request->descriptionCom);
            $auction->saveMeta("descriptionVac", $request->descriptionVac);
            $auction->saveMeta("keywords", $request->keywords);
            $auction->saveMeta("disclamer", $request->disclamer);
            $auction->saveMeta("disclamer_com", $request->disclamer_com);
            $auction->saveMeta("disclamer_vac", $request->disclamer_vac);
            $auction->saveMeta("driving_directions", $request->driving_directions);
            $auction->saveMeta("driving_directions_com", $request->driving_directions_com);
            $auction->saveMeta("driving_directions_vac", $request->driving_directions_vac);
            $auction->saveMeta("looking_other_property", $request->looking_other_property);
            $auction->saveMeta("compensation_amount", $request->compensation_amount);
            $auction->saveMeta("compensation_amount_com", $request->compensation_amount_com);
            $auction->saveMeta("compensation_amount_vac", $request->compensation_amount_vac);
            $auction->saveMeta("listing_link", $request->listing_link);
            $auction->saveMeta("title_company_name", $request->title_company_name);
            $auction->saveMeta("title_company_name_com", $request->title_company_name_com);
            $auction->saveMeta("title_company_name_vac", $request->title_company_name_vac);
            $auction->saveMeta("title_company_address", $request->title_company_address);
            $auction->saveMeta("title_company_address_com", $request->title_company_address_com);
            $auction->saveMeta("title_company_address_vac", $request->title_company_address_vac);
            $auction->saveMeta("title_company_phone", $request->title_company_phone);
            $auction->saveMeta("title_company_phone_com", $request->title_company_phone_com);
            $auction->saveMeta("title_company_phone_vac", $request->title_company_phone_vac);
            $auction->saveMeta("title_company_email", $request->title_company_email);
            $auction->saveMeta("title_company_email_com", $request->title_company_email_com);
            $auction->saveMeta("title_company_email_vac", $request->title_company_email_vac);
            $auction->saveMeta("agent_first_name", $request->agent_first_name);
            $auction->saveMeta("agent_first_name_com", $request->agent_first_name_com);
            $auction->saveMeta("agent_first_name_vac", $request->agent_first_name_vac);
            $auction->saveMeta("agent_last_name", $request->agent_last_name);
            $auction->saveMeta("agent_last_name_com", $request->agent_last_name_com);
            $auction->saveMeta("agent_last_name_vac", $request->agent_last_name_vac);
            $auction->saveMeta("agent_phone", $request->agent_phone);
            $auction->saveMeta("agent_phone_com", $request->agent_phone_com);
            $auction->saveMeta("agent_phone_vac", $request->agent_phone_vac);
            $auction->saveMeta("agent_email", $request->agent_email);
            $auction->saveMeta("agent_email_com", $request->agent_email_com);
            $auction->saveMeta("agent_email_vac", $request->agent_email_vac);
            $auction->saveMeta("agent_brokerage", $request->agent_brokerage);
            $auction->saveMeta("agent_brokerage_com", $request->agent_brokerage_com);
            $auction->saveMeta("agent_brokerage_vac", $request->agent_brokerage_vac);
            $auction->saveMeta("agent_license_no", $request->agent_license_no);
            $auction->saveMeta("agent_license_no_com", $request->agent_license_no_com);
            $auction->saveMeta("agent_license_no_vac", $request->agent_license_no_vac);
            $auction->saveMeta("agent_mls_id", $request->agent_mls_id);
            $auction->saveMeta("agent_mls_id_com", $request->agent_mls_id_com);
            $auction->saveMeta("agent_mls_id_vac", $request->agent_mls_id_vac);
            $auction->saveMeta("realEstateAgent", $request->realEstateAgent);
            $auction->saveMeta("three_d_tour", $request->three_d_tour);
            $auction->saveMeta('video_type', $request->video_type);
            $auction->saveMeta('youtube_video_link', $request->youtube_video_link);
            $auction->saveMeta('vimeo_video_link', $request->vimeo_video_link);

            // Pictures and Video Upload

            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedVideos = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v'];

            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps'];
            $visible_upload_file = [];
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            // $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf

            // 8 June 2023

            if ($request->hasFile('floor_plan')) {
                $file = $request->floor_plan[0];
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $file->move(public_path('auction/files'), $fileName);
                    $auction->saveMeta('floor_plan', 'auction/files/' . $fileName);
                }
            }
            // if ($request->hasFile('photo')) {
            //     $photo = $request->photo;
            //     $extension = $photo->getClientOriginalExtension();
            //     $check = in_array($extension, $allowedFiles);
            //     if ($check) {
            //         $uuid = (string) Str::uuid();
            //         $photoName = $uuid . '.' . $extension;
            //         $photo->move(public_path('auction/images'), $photoName);
            //         $auction->saveMeta('photo', 'auction/images/' . $photoName);
            //     }
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
            //     $auction->saveMeta('photos', json_encode($photoLinks));
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
                $auction->saveMeta('photos', json_encode($photoLinks));
            }

            // Picture Upload
            // Video Upload
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
            // Video Upload
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
                $auction->saveMeta('disclosures', json_encode($disclosures));
            }
            // File Upload


            // 3 Jul 2023
            // if ($request->hasFile('audio')) {
            //     $audio = $request->audio;
            //     $extension = $audio->getClientOriginalExtension();
            //     $check = in_array($extension, $allowedAudios);
            //     if ($check) {
            //         $uuid = (string) Str::uuid();
            //         $audioName = $uuid . '.' . $extension;
            //         $audio->move(public_path('auction/audios'), $audioName);
            //         $auction->saveMeta('audio', 'auction/audios/' . $audioName);
            //     }
            // }

            $auction->saveMeta("listing_ai_faq", json_encode($request->listing_ai_faq ?? []));

            DB::commit();
            SellerPropertyAuctionUpdated::dispatch($auction);
            return redirect()->route('view-pl', $id)->with('success', 'Property listing updated successfully.');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to update property listing.');
        }
    }

    public function list(Request $request)
    {
        $page_data['title'] = 'Seller\'s Property Auctions';
        $page_data['type'] = $type = $request->type ?? "2";

        $pendingAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'sold' => false]);
        $pendingApprovalAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'sold' => false]);
        $liveAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => false]);
        $soldAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => true, 'is_paid' => true]);

        $pendingPaymentAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => true, 'is_paid' => false]);

        if ($type == "0") {
            $auctions = $pendingAuctions->get();
        } elseif ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } elseif ($type == "2") {
            $auctions = $liveAuctions->get();
        } elseif ($type == '3') {
            $auctions = $soldAuctions->get();
        } elseif ($type == "4") {
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

        return view('seller_property.list', $page_data);
    }


    public function viewPropertyListing($id, Request $request)
    {

        $data = PropertyAuction::with('meta')->find($id);
        $auction = PropertyAuction::whereId($id)->with('user', 'bids')->first();
        $bids = PropertyAuctionBid::with('meta')->where('property_auction_id', $id)->get();
        if (!$auction) {
            // Resource not found, handle the error
            return redirect('/login');
        } else {
            $calcData = $this->buildCalcData($auction);
            return view('seller_property.view', compact('data', 'auction', 'bids', 'calcData'));
        }
    }

    private function buildCalcData(PropertyAuction $auction): array
    {
        $meta = $auction->get;

        // --- Price: starting_price first, then buy_now_price, then price ---
        $price       = null;
        $priceSource = 'estimated';
        $rawPrice    = $meta->starting_price ?? $meta->buy_now_price ?? $meta->price ?? null;
        if ($rawPrice && (float) $rawPrice > 0) {
            $price       = (float) $rawPrice;
            $priceSource = 'from listing';
        }

        // --- HOA: normalize from hoaFeeAmount + paymentSchedules ---
        $hoaMonthly  = 0.0;
        $hoaSource   = 'estimated';
        $hoaAssumed  = false;
        $hoaRaw      = $meta->hoaFeeAmount ?? $meta->hoaFeeAmount_vac ?? null;
        if ($hoaRaw && (float) $hoaRaw > 0) {
            $schedule = strtolower($meta->paymentSchedules ?? $meta->assocSchedule ?? '');
            $hoaAmt   = (float) $hoaRaw;
            if (str_contains($schedule, 'quarter')) {
                $hoaMonthly = $hoaAmt / 3;
            } elseif (str_contains($schedule, 'annual') || str_contains($schedule, 'year')) {
                $hoaMonthly = $hoaAmt / 12;
            } elseif (str_contains($schedule, 'month')) {
                $hoaMonthly = $hoaAmt;
            } else {
                // Unknown schedule — treat as monthly but flag it
                $hoaMonthly = $hoaAmt;
                $hoaAssumed = true;
            }
            $hoaSource = 'from listing';
        }

        // --- Taxes ---
        $taxesAnnual = 0.0;
        $taxesSource = 'estimated';
        $taxRaw      = $meta->taxes_annual_amount ?? $meta->taxes_annual_amount_com ?? null;
        if ($taxRaw && (float) $taxRaw > 0) {
            $taxesAnnual = (float) $taxRaw;
            $taxesSource = 'from listing';
        }

        // --- Admin defaults (with hardcoded emergency fallbacks) ---
        $interestRate  = (float) (get_setting('calc_interest_rate')    ?: 6.5);
        $downPct       = (float) (get_setting('calc_down_payment_pct') ?: 10);
        $loanTerm      = (int)   (get_setting('calc_loan_term')        ?: 30);
        $taxRate       = (float) (get_setting('calc_tax_rate')         ?: 1.1);
        $insuranceRate = (float) (get_setting('calc_insurance_rate')   ?: 0.5);
        $pmiRate       = (float) (get_setting('calc_pmi_rate')         ?: 0.85);

        return [
            'price'             => $price,
            'price_source'      => $priceSource,
            'hoa_monthly'       => round($hoaMonthly, 2),
            'hoa_source'        => $hoaSource,
            'hoa_assumed'       => $hoaAssumed,
            'taxes_annual'      => $taxesAnnual,
            'taxes_source'      => $taxesSource,
            'insurance_source'  => 'estimated',
            'interest_rate'     => $interestRate,
            'down_pct'          => $downPct,
            'loan_term'         => $loanTerm,
            'tax_rate'          => $taxRate,
            'insurance_rate'    => $insuranceRate,
            'pmi_rate'          => $pmiRate,
        ];
    }

    public function endAuction($id)
    {
        $auction = PropertyAuction::findOrFail($id);
        // Authorization (Phase 1): only the listing owner may end the auction.
        abort_unless(auth()->check() && (int) $auction->user_id === (int) auth()->id(), 403);
        $auction->update(['auction_ended' => true]);
        return response()->json(['message' => 'Auction ended successfully']);
    }

    public function bidsVisibility($id, $vis)
    {
        $auction = PropertyAuction::where('id', $id)->first();
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

    public function searchListing(Request $request)
    {
        $page_data['title'] = 'Search Property Listings (Sale)';
        $page_data['property_types'] = PropertyType::orderBy('id', 'DESC')->get();
        $page_data['bedrooms'] = Bedroom::all();
        $page_data['bathrooms'] = Bathroom::all();

        // PropertyAuction query (existing Seller Property Auctions)
        $paQuery = PropertyAuction::selectRaw("*, (SELECT meta_value FROM property_auction_metas WHERE property_auction_metas.property_auction_id = property_auctions.id AND meta_key = 'starting_price') as price")
            ->where('sold', false)
            ->where('is_approved', true);

        if ($request->title != "") {
            $paQuery->where('address', 'like', '%' . $request->title . '%');
        }
        if ($request->bedrooms != "") {
            $paQuery->whereHas('meta', function ($m) use ($request) {
                $m->where('meta_key', 'bedrooms')->where('meta_value', $request->bedrooms);
            });
        }
        if ($request->bathrooms != "") {
            $paQuery->whereHas('meta', function ($m) use ($request) {
                $m->where('meta_key', 'bathrooms')->where('meta_value', $request->bathrooms);
            });
        }
        if ($request->property_type != "") {
            $paQuery->whereHas('meta', function ($m) use ($request) {
                $m->where('meta_key', 'property_type')->where('meta_value', $request->property_type);
            });
        }
        $paResults = $paQuery->get();

        // OfferAuction seller query (Seller Create Offer Listings)
        $oaQuery = \App\Models\OfferAuction::where('is_approved', true)
            ->where('is_draft', false)
            ->where(function ($q) {
                $q->whereHas('metas', function ($m) {
                    $m->where('meta_key', 'user_type')->where('meta_value', 'seller');
                })->orWhereHas('metas', function ($m) {
                    $m->where('meta_key', 'listing_role')->where('meta_value', 'seller');
                });
            })
            ->with('metas');

        if ($request->title != "") {
            $oaQuery->whereHas('metas', function ($m) use ($request) {
                $m->where('meta_key', 'address')->where('meta_value', 'like', '%' . $request->title . '%');
            });
        }
        if ($request->bedrooms != "") {
            $oaQuery->whereHas('metas', function ($m) use ($request) {
                $m->where('meta_key', 'bedrooms')->where('meta_value', $request->bedrooms);
            });
        }
        if ($request->bathrooms != "") {
            $oaQuery->whereHas('metas', function ($m) use ($request) {
                $m->where('meta_key', 'bathrooms')->where('meta_value', $request->bathrooms);
            });
        }
        if ($request->property_type != "") {
            $oaQuery->whereHas('metas', function ($m) use ($request) {
                $m->where('meta_key', 'property_type')->where('meta_value', $request->property_type);
            });
        }
        $oaResults = $oaQuery->get();

        // Normalize & merge into a unified keyed collection
        $normalized = collect();

        foreach ($paResults as $pa) {
            $pa->_source_key = 'property_auction:' . $pa->id;
            $pa->_view_url   = route('view-pl', $pa->id);
            $normalized->put($pa->_source_key, $pa);
        }

        foreach ($oaResults as $oa) {
            $sourceKey = 'offer_auction:' . $oa->id;
            if ($normalized->has($sourceKey)) {
                continue;
            }
            $metaMap = $oa->metas->pluck('meta_value', 'meta_key');

            $photosRaw = $metaMap['property_photos'] ?? null;
            $photos    = [];
            if ($photosRaw) {
                $decoded = json_decode($photosRaw, true);
                if (is_array($decoded)) {
                    $photos = array_values(array_filter($decoded));
                }
            }
            $photoUrls = array_map(fn($p) => 'storage/auction/images/' . $p, $photos);

            $priceRaw = $metaMap['maximum_budget'] ?? $metaMap['starting_price'] ?? null;
            $price    = $priceRaw ? preg_replace('/[^0-9.]/', '', str_replace(',', '', $priceRaw)) : null;

            $displayTitle = $metaMap['listing_title'] ?? ($metaMap['address'] ?? '');

            $getObj = (object) [
                'address'             => $displayTitle,
                'bedrooms'            => $metaMap['bedrooms']  ?? '',
                'bathrooms'           => $metaMap['bathrooms'] ?? '',
                'property_type'       => $metaMap['property_type'] ?? '',
                'agent_mls_id'        => null,
                'auction_length_days' => 0,
                'photos'              => $photoUrls,
            ];

            $listingStatus  = $metaMap['listing_status'] ?? '';
            $listingExpiry  = $metaMap['listing_expiration'] ?? null;
            $isStatusClosed = in_array($listingStatus, ['Accepted', 'Withdrawn', 'Expired']);
            $isExpired      = $listingExpiry && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($listingExpiry));

            $wrapper                = new \stdClass();
            $wrapper->id            = $oa->id;
            $wrapper->get           = $getObj;
            $wrapper->heated_sqft   = $metaMap['minimum_heated_square'] ?? null;
            $wrapper->price         = $price;
            $wrapper->created_at    = $oa->created_at;
            $wrapper->auction_length = 0;
            $wrapper->auction_ended  = $isStatusClosed || $isExpired;
            $wrapper->_source_key   = $sourceKey;
            $wrapper->_view_url     = route('offer.listing.view', ['id' => $oa->id]) . '?role=seller';

            $normalized->put($sourceKey, $wrapper);
        }

        // Sort: 1=Title Z-a (desc), 2=Title A-z (asc), 3=Date new, 4=Date old, 5=Price high, 6=Price low
        $sort = $request->sort;
        $getTitle = fn($a) => strtolower((string) ($a->get->address ?? ''));
        $getDate  = fn($a) => (string) ($a->created_at ?? '');
        $getPrice = fn($a) => floatval($a->price ?? 0);
        if ($sort == 1) {
            $sorted = $normalized->sortByDesc($getTitle);
        } elseif ($sort == 2) {
            $sorted = $normalized->sortBy($getTitle);
        } elseif ($sort == 3) {
            $sorted = $normalized->sortByDesc($getDate);
        } elseif ($sort == 4) {
            $sorted = $normalized->sortBy($getDate);
        } elseif ($sort == 5) {
            $sorted = $normalized->sortByDesc($getPrice);
        } elseif ($sort == 6) {
            $sorted = $normalized->sortBy($getPrice);
        } else {
            $sorted = $normalized->sortByDesc(fn($a) => $a->id ?? 0);
        }

        // Paginate the combined result as a single unit
        $perPage     = 12;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $items       = $sorted->values()->forPage($currentPage, $perPage);
        $paginator   = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $sorted->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $page_data['count']    = $sorted->count();
        $page_data['pAuctions'] = $paginator;
        return view('seller_property.search', $page_data);
    }

    public function renew($id)
    {
        $property_auction = PropertyAuction::whereId($id)->first();
        return view('seller_property.renew', compact('property_auction'));
    }
    public function renew_save(Request $request)
    {
        // Authorization (Phase 1): only the listing owner may renew it.
        $auction = PropertyAuction::findOrFail($request->id);
        abort_unless(auth()->check() && (int) $auction->user_id === (int) auth()->id(), 403);
        $auction->update([
            'listing_date' => $request->listing_date,
            'expiration_date' => $request->expiration_date,
        ]);

        return redirect()->route('myAuctions');
    }
    public function seller_property_partial_view(Request $request)
    {
        $html = (string)view('partial_view.seller_property');
        return response()->json([
            'message' => 200,
            'html' => $html,
        ]);
    }
}
