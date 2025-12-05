<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\City;
use App\Models\State;
use App\Models\County;
use App\Models\Bedroom;
use App\Models\Country;
use App\Models\Bathroom;
use App\Models\Financing;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\CounterTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BuyerAgentAuctionController extends Controller
{
    public function addAuction()
    {
        $page_data['title'] = 'Hire Buyer\'s Agent Auction';
        $page_data['financings'] = Financing::orderBy('sort', 'ASC')->get();
        //New code by waqas
        $countries = Country::all();
        $cities = City::all();
        $states = State::all();

        return view('addBuyerAgentAuction', compact('countries', 'cities', 'states', 'page_data'));
    }

    public function storeAuction(Request $request)
    {
        // dd($request->all());
        try {
            DB::beginTransaction();
            $auction = new BuyerAgentAuction();
            $auction->user_id = Auth::user()->id;
            // dd(Auth::user()->id);
            $auction->title = $request->title_of_listing;
            $auction->save();
            $auction->saveMeta('working_with_agent', $request->working_with_agent);
            $auction->saveMeta('interested_in', $request->interested_in);
            $auction->saveMeta('auction_length', $request->auction_length);
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lenths = explode(' ', $request->auction_length);
                $auction_lenth_days = current($auction_lenths);
                $auction->saveMeta('auction_length_days', $auction_lenth_days);
            } else {
                $auction->saveMeta('auction_length_days', '-1');
            }
            // Changes 02-Dec-2023
            $auction->saveMeta("propsCondition", json_encode($request->propsCondition));
            $auction->saveMeta("custom_other", $request->custom_other ? json_encode($request->custom_other) : null);
            $auction->saveMeta("property_items", $request->property_items ? json_encode($request->property_items) : null);
            $auction->saveMeta("custom_condition_income", $request->custom_condition_income ? json_encode($request->custom_condition_income) : null);
            $auction->saveMeta("custom_cryptocurrency", $request->custom_cryptocurrency ? json_encode($request->custom_cryptocurrency) : null);
            $auction->saveMeta("custom_exchange_trade_res", $request->custom_exchange_trade_res ? json_encode($request->custom_exchange_trade_res) : null);
            $auction->saveMeta("units", $request->units);
            $auction->saveMeta("annualIncome", $request->annualIncome);
            $auction->saveMeta("capRate", $request->capRate);
            $auction->saveMeta("additiaonalDetails", $request->additiaonalDetails);
            $auction->saveMeta("garage_parking", json_encode($request->garage_parking));
            $auction->saveMeta("garageOption", $request->garageOption);
            $auction->saveMeta("other_garage", $request->other_garage);
            $auction->saveMeta("special_sale", json_encode($request->special_sale));
            $auction->saveMeta("commission", $request->commission);
            $auction->saveMeta("commissionOpt", $request->commissionOpt);
            $auction->saveMeta("compensate", json_encode($request->compensate));
            $auction->saveMeta("OtherCommission", $request->OtherCommission);
            $auction->saveMeta("OtherCompensate", $request->OtherCompensate);
            $auction->saveMeta("OtherComOptions", $request->OtherComOptions);
            $auction->saveMeta("custom_props_condition", $request->custom_props_condition);
            $auction->saveMeta("commercialseller_contract_yes", $request->commercialseller_contract_yes);
            $auction->saveMeta("commercial_seller_contract_no", $request->commercial_seller_contract_no);
            $auction->saveMeta("total_acreage", $request->total_acreage);
            $auction->saveMeta("custom_service_buyer_want_vancat", $request->custom_service_buyer_want_vancat);
            $auction->saveMeta("custom_unit_type", $request->custom_unit_type);
            $auction->saveMeta("custom_non_negotiable_terms_other_income", $request->custom_non_negotiable_terms_other_income);
            $auction->saveMeta("custom_special_sale", $request->custom_special_sale);
            $auction->saveMeta("contribute_term", $request->contribute_term);
            $auction->saveMeta("residentialSeller_contract_yes", $request->residentialSeller_contract_yes);
            $auction->saveMeta("residential_seller_contract_no", $request->residential_seller_contract_no);
            $auction->saveMeta("custom_condition_interested_res", $request->custom_condition_interested_res);
            $auction->saveMeta("has_non_negotiable_terms", $request->has_non_negotiable_terms);
            $auction->saveMeta("financings", $request->financings ? json_encode($request->financings) : null);
            $auction->saveMeta("customOptions", json_encode($request->customOptions));
            $auction->saveMeta("customOptionsYes", $request->customOptionsYes);
            $auction->saveMeta("customOptionsNoInput", $request->customOptionsNoInput);
            $auction->saveMeta("conventionalOptionsYesNo", json_encode($request->conventionalOptionsYesNo));
            $auction->saveMeta("financingOptionsConventional", json_encode($request->financingOptionsConventional));
            $auction->saveMeta("financingOptionsCash", json_encode($request->financingOptionsCash));
            $auction->saveMeta("financingOptionsLease", json_encode($request->financingOptionsLease));
            $auction->saveMeta("financingOptionsNft", json_encode($request->financingOptionsNft));
            $auction->saveMeta("financingOptionsSeller", json_encode($request->financingOptionsSeller));
            $auction->saveMeta("financingOptionsTrade", json_encode($request->financingOptionsTrade));
            $auction->saveMeta("financingOptionsAssumble", json_encode($request->financingOptionsAssumble));
            $auction->saveMeta("financingOptionsCrypto", json_encode($request->financingOptionsCrypto));
            $auction->saveMeta("financingOther", json_encode($request->financingOther));
            $auction->saveMeta("customCash", $request->customCash);
            $auction->saveMeta("custom_lease_option", $request->custom_lease_option ? json_encode($request->custom_lease_option) : null);
            $auction->saveMeta("custom_seller_financing", $request->custom_seller_financing ? json_encode($request->custom_seller_financing) : null);
            $auction->saveMeta("custom_exchange_trade", $request->custom_exchange_trade ? json_encode($request->custom_exchange_trade) : null);
            $auction->saveMeta("custom_assumable", $request->custom_assumable);
            $auction->saveMeta("custom_cryptocurrencyRes", $request->custom_cryptocurrencyRes ? json_encode($request->custom_cryptocurrencyRes) : null);
            $auction->saveMeta("custom_service_buyer_want_res", $request->custom_service_buyer_want_res);
            $auction->saveMeta("aspect_hiring_agent", $request->aspect_hiring_agent);
            $auction->saveMeta("aspect_hiring_agent_res", $request->aspect_hiring_agent_res);
            $auction->saveMeta("incomeSeller_contract_yes", $request->incomeSeller_contract_yes);
            $auction->saveMeta("incomeSeller_contract_no", $request->incomeSeller_contract_no);
            // Changes 02-Dec-2023
            $listing_date = Carbon::parse($request->listing_date);
            $expiration_date = Carbon::parse($request->expiration_date);
            $auction->saveMeta('carportOptions', $request->carportOptions);
            $auction->saveMeta('carportCustom', $request->carportCustom);
            $auction->saveMeta('garageOptions', $request->garageOptions);
            $auction->saveMeta('garageCustom', $request->garageCustom);
            $auction->saveMeta('poolOptions', $request->poolOptions);
            $auction->saveMeta('poolCustom', json_encode($request->poolCustom));
            $auction->saveMeta('prefrenceOptions', $request->prefrenceOptions);
            $auction->saveMeta('prefrence', json_encode($request->prefrence));
            $auction->saveMeta('preferenceOther', $request->preferenceOther);
            $auction->saveMeta('petOptions', $request->petOptions);
            $auction->saveMeta('petsNumber', $request->petsNumber);
            $auction->saveMeta('petsType', $request->petsType);
            $auction->saveMeta('petsBreed', $request->petsBreed);
            $auction->saveMeta('petsWeight', $request->petsWeight);
            $auction->saveMeta('purchasing_props', $request->purchasing_props);
            $auction->saveMeta('customOptions', $request->customOptions);
            $auction->saveMeta('customOptionsYes', $request->customOptionsYes);
            $auction->saveMeta('customCash', $request->customCash);
            $auction->saveMeta('lease_option', json_encode($request->lease_option));
            $auction->saveMeta('lease_purchase', json_encode($request->lease_purchase));
            $auction->saveMeta('nft', json_encode($request->nft));
            $auction->saveMeta('working_with_agent', $request->working_with_agent);
            $auction->saveMeta("state", $request->state ? json_encode($request->state) : null);
            $auction->saveMeta('listing_date', $listing_date);
            $auction->saveMeta('expiration_date', $expiration_date);
            $auction->saveMeta('auction_type', $request->auction_type);
            $auction->saveMeta('title_of_listing', $request->title_of_listing);
            $auction->saveMeta('property_type', $request->property_type);
            $auction->saveMeta('sale_provision', $request->sale_provision);
            $auction->saveMeta('financing_info', $request->financing_info);
            $auction->saveMeta('finder_fee', $request->finder_fee);
            $auction->saveMeta('another_contract', $request->another_contract);
            $auction->saveMeta('custom_sale_provisions', $request->custom_sale_provisions);
            $auction->saveMeta('bedrooms', $request->bedrooms);
            $auction->saveMeta('other_bedrooms', $request->other_bedrooms);
            $auction->saveMeta('bathrooms', $request->bathrooms);
            $auction->saveMeta('other_bathrooms', $request->other_bathrooms);
            $auction->saveMeta('minimum_heated_sqft', $request->minimum_heated_sqft);
            $auction->saveMeta('has_non_negotiable_amenities', $request->has_non_negotiable_amenities);
            $auction->saveMeta('non_negotiable_terms', json_encode($request->non_negotiable_terms));
            $auction->saveMeta('custom_non_negotiable_terms', $request->custom_non_negotiable_terms);
            $auction->saveMeta('are_they_been_pre_approved', $request->are_they_been_pre_approved);
            $auction->saveMeta('term_financings', $request->term_financings);
            $auction->saveMeta('custom_term_financings', $request->custom_term_financings);
            $auction->saveMeta('custom_term_financings', $request->custom_term_financings);
            $auction->saveMeta('custom_buyer_looking_to_purchase', $request->custom_buyer_looking_to_purchase);
            $auction->saveMeta('other_timeframe', $request->other_timeframe);
            $auction->saveMeta('timeframe', $request->timeframe);
            $auction->saveMeta('other_services', $request->other_services);
            $auction->saveMeta('aspect_hiring_agent', $request->aspect_hiring_agent);
            $auction->saveMeta('additional_details', $request->additional_details);
            $auction->saveMeta('additional_details_res', $request->additional_details_res);
            $auction->saveMeta('unit_types', $request->unit_types);
            $auction->saveMeta('purchase_of_business', $request->purchase_of_business);
            $auction->saveMeta('buyer_budget', $request->buyer_budget);
            $auction->saveMeta('cities', $request->cities ? json_encode($request->cities) : null);
            $auction->saveMeta('counties', $request->counties ? json_encode($request->counties) : null);
            $auction->saveMeta('condition_interested', $request->condition_interested ? json_encode($request->condition_interested) : null);
            $auction->saveMeta('services', $request->services ? json_encode($request->services) : null);
            // Old comment Code Uncommented By waqas
            $auction->saveMeta('title', $request->title);
            // Old comment Code Uncommented By waqas
            $auction->saveMeta('terms_of_contract', $request->terms_of_contract);
            $auction->saveMeta('custom_contract_terms', $request->custom_contract_terms);
            $auction->saveMeta('property_type', $request->property_type);
            $auction->saveMeta('property_style', $request->property_style);
            $auction->saveMeta('current_use', $request->current_use);
            $auction->saveMeta('type_of_prop', json_encode($request->type_of_prop));
            $auction->saveMeta('buyer_budget', $request->buyer_budget);
            $auction->saveMeta('bedrooms', $request->bedrooms);
            $auction->saveMeta('other_bedrooms', $request->other_bedrooms);
            $auction->saveMeta('bathrooms', $request->bathrooms);
            $auction->saveMeta('other_bathrooms', $request->other_bathrooms);
            $auction->saveMeta('concession', $request->concession);
            $auction->saveMeta('financing_currency', json_encode($request->financing_currency));
            $auction->saveMeta('financing_info', $request->financing_info);
            $auction->saveMeta('are_they_been_pre_approved', $request->are_they_been_pre_approved);
            $auction->saveMeta('buyer_pre_approved', $request->buyer_pre_approved);
            $auction->saveMeta('buyer_budget_purchasing', $request->buyer_budget_purchasing);
            $auction->saveMeta('buyer_get_pre_approved', $request->buyer_get_pre_approved);
            $auction->saveMeta('term_financings', json_encode($request->term_financings));
            $auction->saveMeta('buyer_budget', $request->buyer_budget);
            $auction->saveMeta('finder_fee', $request->finder_fee);
            $auction->saveMeta('condition_interested', $request->condition_interested);
            $auction->saveMeta('notify_particpate', $request->notify_particpate);
            $auction->saveMeta('services', json_encode($request->services));
            $auction->saveMeta('other_services', $request->other_services);
            $auction->saveMeta('aspect_hiring_agent', $request->aspect_hiring_agent);
            $auction->saveMeta('preapproval_amount', $request->preapproval_amount);
            $auction->saveMeta('cash_budget', $request->cash_budget);
            $auction->saveMeta('crypto_budget', $request->crypto_budget);
            $auction->saveMeta('cryptocurrency_value', $request->cryptocurrency_value);
            $auction->saveMeta('cryptocurrency_putdown_plan', $request->cryptocurrency_putdown_plan);
            $auction->saveMeta('additional_details', $request->additional_details);
            $auction->saveMeta('first_name', $request->first_name);
            $auction->saveMeta('last_name', $request->last_name);
            $auction->saveMeta('phone', $request->phone);
            $auction->saveMeta('email', $request->email);
            $auction->saveMeta('agent_brokerage', $request->agent_brokerage);
            $auction->saveMeta('agent_license_no', $request->agent_license_no);
            $auction->saveMeta('agent_mls_id', $request->agent_mls_id);
            $auction->saveMeta('agent_commission_percent', $request->agent_commission_percent);
            $auction->saveMeta('buying_timeframe', $request->buying_timeframe);
            $auction->saveMeta('custom_amount', $request->custom_amount);
            $auction->saveMeta('buyer_prefered_timeframe', $request->buyer_prefered_timeframe);

            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
            $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];
            if ($request->photo != "") {
                $extension = $request->photo->getClientOriginalExtension();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = rand();
                    $photo = $uuid . '.' . $extension;
                    $request->photo->move(public_path('auction/images/'), $photo);
                    $auction->saveMeta('photo', 'auction/images/' . $photo);
                }
            }
            if ($request->video != "") {
                $videoExtension = $request->video->getClientOriginalExtension();
                $check = in_array($videoExtension, $allowedVideos);
                if ($check) {
                    $uuid = rand();
                    $video = $uuid . '.' . $videoExtension;
                    $request->video->move(public_path('auction/video/'), $video);
                    $auction->saveMeta('video', 'auction/video/' . $video);
                }
            }
            DB::commit();
            $route = route('buyer.view-auction', $auction->id);
            return redirect()->to($route)->with('success', 'Buyer\'s Agent Auction is added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'There was some error while adding Buyer\'s Agent Auction.');
        }
    }

    public function buyerAgentAuctions(Request $request)
    {


        $page_data['title'] = 'Hire Buyer\'s Agent Auctions';
        $page_data['type'] = $type = $request->type ?? "1";
        $pendingApprovalAuctions = BuyerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false, 'is_draft' => false]);
        $liveAuctions = BuyerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false, 'is_draft' => false]);
        $soldAuctions = BuyerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true, 'is_draft' => false]);

        if ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } elseif ($type == "2") {
            $auctions = $liveAuctions->get();
        } elseif ($type == '3') {
            $auctions = $soldAuctions->get();
        } else {
            $auctions = $liveAuctions->get();
        }

        // dd($auctions[0]['id']);

        // $counter = CounterTerm::where(['buyer_auction_id' => $auctions[0]['id']]);

        $page_data['pendingApprovalCount'] = $pendingApprovalAuctions->count();
        $page_data['liveCount'] = $liveAuctions->count();
        $page_data['soldCount'] = $soldAuctions->count();

        $page_data['auctions'] = $auctions;
        // $page_data['counter'] = $counter;

        // dd($page_data);

        return view('hire-buyer-agent-auctions', $page_data);
    }

    public function editBuyerAgentAction($id)
    {
        $page_data['auction'] = $auction = BuyerAgentAuction::find($id);
        $page_data['title'] = 'Edit Buyer\'s Agent Auction ' . $auction->address;
        $page_data['financings'] = Financing::orderBy('sort', 'ASC')->get();
        $page_data['id'] = $id;
        return view('editBuyerAgentAuction', $page_data);
    }

    public function updateBuyerAgentAuction(Request $request)
    {
        try {
            DB::beginTransaction();
            $auction = BuyerAgentAuction::find($request->id);
            // $auction->user_id = Auth::user()->id;
            $auction->title = $request->title;
            $auction->state = $request->state;
            $auction->cities = $request->cities;
            $auction->counties = $request->counties;
            $auction->save();
            $auction->saveMeta('working_with_agent', $request->working_with_agent);
            $auction->saveMeta('interested_in', $request->interested_in);
            $auction->saveMeta('auction_type', $request->auction_type);
            $auction->saveMeta('auction_length', $request->auction_length);
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lenths = explode(' ', $request->auction_length);
                $auction_lenth_days = current($auction_lenths);
                $auction->saveMeta('auction_length_days', $auction_lenth_days);
            } else {
                $auction->saveMeta('auction_length_days', '-1');
            }

            $auction->saveMeta('state', $request->state);
            $auction->saveMeta('cities', $request->cities);
            $auction->saveMeta('counties', $request->counties);
            // $auction->saveMeta('title', $request->title);
            $auction->saveMeta('terms_of_contract', $request->terms_of_contract);
            $auction->saveMeta('custom_contract_terms', $request->custom_contract_terms);
            $auction->saveMeta('property_type', $request->property_type);
            $auction->saveMeta('property_items', json_encode($request->property_items));
            $auction->saveMeta('type_of_prop', json_encode($request->type_of_prop));
            $auction->saveMeta('buyer_budget', $request->buyer_budget);
            $auction->saveMeta('bedrooms', $request->bedrooms);
            $auction->saveMeta('minimum_heated_sqft', $request->minimum_heated_sqft);
            $auction->saveMeta('minimum_net_leasable_sqft', $request->minimum_net_leasable_sqft);
            $auction->saveMeta('total_acreage', $request->total_acreage);
            $auction->saveMeta('other_bedrooms', $request->other_bedrooms);
            $auction->saveMeta('bathrooms', $request->bathrooms);
            $auction->saveMeta('other_bathrooms', $request->other_bathrooms);
            $auction->saveMeta('concession', $request->concession);
            $auction->saveMeta('financing_currency', json_encode($request->financing_currency));
            $auction->saveMeta('financing_info', $request->financing_info);
            $auction->saveMeta('any_non_negotiable', $request->any_non_negotiable);
            $auction->saveMeta('non_negotiable_terms', $request->non_negotiable_terms);
            $auction->saveMeta('condition_interested', $request->condition_interested);
            $auction->saveMeta('services', json_encode($request->services));
            $auction->saveMeta('other_services', $request->other_services);
            $auction->saveMeta('preapproval_amount', $request->preapproval_amount);
            $auction->saveMeta('cash_budget', $request->cash_budget);
            $auction->saveMeta('crypto_budget', $request->crypto_budget);
            $auction->saveMeta('cryptocurrency_value', $request->cryptocurrency_value);
            $auction->saveMeta('cryptocurrency_putdown_plan', $request->cryptocurrency_putdown_plan);
            $auction->saveMeta('additional_details', $request->additional_details);
            $auction->saveMeta('buying_timeframe', $request->buying_timeframe);
            $auction->saveMeta('custom_amount', $request->custom_amount);
            DB::commit();
            return redirect()->back()->with('success', 'Buyer\'s Agent Auction updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to update Buyer\'s Agent Auction.');
        }
    }



    public function list(Request $request)
    {
        $page_data['title'] = 'Hire Buyer\'s Agent Auctions';
        $page_data['type'] = $type = $request->type ?? "2";
        $pendingApprovalAuctions = BuyerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false, 'is_draft' => false]);
        $liveAuctions = BuyerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false, 'is_draft' => false]);
        $soldAuctions = BuyerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true, 'is_draft' => false]);

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

        return view('buyerAgentAuctionList', $page_data);
    }
    public function viewAuctionDetails($id)
    {

        $auction = BuyerAgentAuction::find($id);

        $page_data['title'] = $auction->address;
        $counties = County::all();
        $page_data['id'] = $id;
        $data = BuyerAgentAuction::with('meta')->find($id);
        $counterTerms = CounterTerm::where('buyer_auction_id', $id)->first();
        // dd($counterTerms);
        return view('buyerAgentAuctionDetail', compact('counties', 'auction', 'data', 'counterTerms'));
    }

    public function buyerAgentAuctionsAdmin(Request $request)
    {
        $page_data['title'] = "Hire Buyer's Agent";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = BuyerAgentAuction::where('is_approved', true)->where('is_draft', false)->get();
        } elseif ($type == 2) {
            $page_data['auctions'] = BuyerAgentAuction::where('is_sold', true)->where('is_draft', false)->get();
        } else {
            $page_data['auctions'] = BuyerAgentAuction::where('is_approved', false)->where('is_draft', false)->get();
        }
        return view('admin.buyerAgentAuctions', $page_data);
    }

    public function approveBuyerAgentAuction($id)
    {
        $auction = BuyerAgentAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    public function buyerAgents()
    {
        $page_data['mySellerAgents'] = 'My Agents';
        // dd($page_data);
        // dd('ok');
        return view('buyerAgents', $page_data);
    }

    public function searchListing(Request $request)
    {
        $page_data['title'] = 'Search Listings';

        $auctions = BuyerAgentAuction::query();

        $auctions->selectRaw("*, (SELECT meta_value FROM buyer_agent_auction_metas WHERE buyer_agent_auction_metas.buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'ideal_price') as price")->where('is_approved', true);

        if ($request->title != "") {
            $auctions->where('title', 'like', '%' . $request->title . '%');
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
                $sort_by = 'title';
                $sort_type = 'DESC';
            } elseif ($sort == 2) {
                $sort_by = 'title';
                $sort_type = 'ASC';
            } elseif ($sort == 3) {
                $sort_by = 'created_at';
                $sort_type = 'DESC';
            } elseif ($sort == 4) {
                $sort_by = 'created_at';
                $sort_type = 'ASC';
            }/*  else if ($sort == 5) {
                $sort_by = 'price';
                $sort_type = 'DESC';
            } else if ($sort == 6) {
                $sort_by = 'price';
                $sort_type = 'ASC';
            } */ else {
                $sort_by = 'id';
                $sort_type = 'ASC';
            }
            $auctions->orderBy($sort_by, $sort_type);
        } else {
            // $auctions->orderBy(DB::raw('RAND()'));
        }


        $auctions_c = $auctions;
        $count = $auctions_c->count();
        // dd($page_data['count']);
        $pAuctions = $auctions->paginate(12);
        $buyers = BuyerAgentAuction::where('is_approved', 1)->get();
        return view('search-buyer-agent-auctions', compact('count', 'pAuctions', 'buyers'));
    }
    //Changes By Waqas
    public function dynamic_option(Request $request)
    {

        $country_id = $request->input('country_name');
        // dd($country_id);
        $country = Country::all();
        $country_get = collect($country)->where('name', $country_id)->first();
        // dd($country_get->states->first()->name);

        foreach ($country_get->states as $state) {
            $statesArray[] = [
                'name' => $state->name,

            ];
        }
        $html = (string)view('partial_view.option_dynamic', compact('statesArray'));
        return response()->json([
            'html' => $html,
            'message' => '200',
            'states_Array' => $statesArray,
        ]);
        // dd($statesArray);

        // dd('ok');
    }
    public function dynamic_option_city(Request $request)
    {


        $state_id = $request->input('state_name');
        $states = State::all();
        $states_get = collect($states)->where('name', $state_id)->first();
        // dd($states_get->cities->first()->name);
        foreach ($states_get->cities as $city) {
            $cityArray[] = [
                'name' => $city->name,

            ];
        }
        // dd($cityArray);
        $html1 = (string)view('partial_view.option_dynamic_city', compact('cityArray'));
        $html2 = (string)view('partial_view.option_dynamic_city2', compact('cityArray'));

        return response()->json([
            'html' => $html1,
            'message' => '200',
            'html2' => $html2,
            'cityArray' => $cityArray,
        ]);
        // dd($statesArray);

        // dd('ok');
    }
    public function counterTerms(Request $request, $id)
    {
        $buyerId = $id;
        return view('counter_terms.add', compact('buyerId'));
        // $counter = new CounterTerm();
        // $counter->buyer_auction_id = $id;
        // $counter->save();
    }
    public function addCounterTerms(Request $request)
    {
        $counter = new CounterTerm();
        $counter->buyer_auction_id = $request->buyerId;
        $counter->timeframe = $request->timeframe;
        $counter->commission = $request->commission;
        $counter->services = json_encode($request->services);
        $counter->additionalDetails = $request->additionalDetails;
        $counter->save();
        return redirect('buyer/hire/agent/auctions')->with('success', 'Countered Terms Added Successfully!');
    }
}
