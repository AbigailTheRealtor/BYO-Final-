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
use App\Models\AgentDefaultProfile;
use App\Models\AcceptedBidSummary;
use App\Services\AcceptedBidSummaryService;
use App\Services\SellerAcceptedBidSummaryService;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use App\Notifications\SellerAgentHiredNotification;
use Illuminate\Support\Facades\Log;
use DateTime;

class SellerAgentAuctionController extends Controller
{
    public function sellerAgentHireAuction(Request $request)
    {
        $page_data['title'] = 'Hire Seller\'s Agent';
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
        $pendingApprovalAuctions = SellerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => 'false', 'is_draft' => false])
            ->whereDoesntHave('meta', function ($m) { $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing'); })
            ->whereDoesntHave('meta', function ($m) { $m->whereIn('meta_key', SellerOfferListingController::OFFER_LISTING_META_KEYS); })
            ->with(['bids.user', 'bids.meta']);
        $liveAuctions = SellerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => 'false', 'is_draft' => false])
            ->whereDoesntHave('meta', function ($m) { $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing'); })
            ->whereDoesntHave('meta', function ($m) { $m->whereIn('meta_key', SellerOfferListingController::OFFER_LISTING_META_KEYS); })
            ->with(['bids.user', 'bids.meta']);
        $soldAuctions = SellerAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => 'true', 'is_draft' => false])
            ->whereDoesntHave('meta', function ($m) { $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing'); })
            ->whereDoesntHave('meta', function ($m) { $m->whereIn('meta_key', SellerOfferListingController::OFFER_LISTING_META_KEYS); })
            ->with(['bids.user', 'bids.meta']);

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
        return redirect()->route('hire.agent.auction.edit', ['auctionId' => $id, 'user_type' => 'seller']);
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
            $auction->saveMeta('property_city', $request->property_city);
            $auction->saveMeta('property_state', $request->property_state);
            $auction->saveMeta('property_county', $request->property_county);
            $auction->saveMeta('property_zip', $request->property_zip);
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
            $auction->saveMeta('offered_financing', json_encode($request->financings));

            $rawExchange = $request->input('exchange_item');
            $isMeaningful = false;
            if (is_array($rawExchange)) {
                $filtered = array_values(array_filter(array_map('trim', $rawExchange), fn($v) => $v !== ''));
                $isMeaningful = count($filtered) > 0;
            } elseif (is_string($rawExchange)) {
                $isMeaningful = trim($rawExchange) !== '' && trim($rawExchange) !== '[]';
            }
            if ($isMeaningful) {
                $auction->saveMeta('exchange_item', json_encode($filtered ?? [$rawExchange]));
                $auction->saveMeta('other_exchange_item', $request->other_exchange_item);
                $auction->saveMeta('exchange_item_value', $request->exchange_item_value);
                $auction->saveMeta('exchange_item_condition', $request->exchange_item_condition);
                $auction->saveMeta('additional_cash', $request->additional_cash);
                $auction->saveMeta('value_determination', $request->value_determination);
                $auction->saveMeta('exchange_transfer_method', $request->exchange_transfer_method);
                $auction->saveMeta('exchange_liens_disclosure', $request->exchange_liens_disclosure);
                $auction->saveMeta('exchange_liens_details', $request->exchange_liens_details);
                $auction->saveMeta('exchange_inspection_rights', $request->exchange_inspection_rights);
            }

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
        $auction = SellerAgentAuction::with(['bids.user', 'bids.meta', 'user', 'meta'])->find($id);

        if (!$auction) {
            abort(404, 'Listing not found');
        }

        // Guard: if this record is actually a Seller Offer Listing, redirect to the correct route.
        // Use the already-eager-loaded meta collection — no extra query.
        $workflowType = $auction->meta->where('meta_key', 'workflow_type')->first()?->meta_value;
        if ($workflowType === 'offer_listing') {
            return redirect()->route('offer.listing.seller.view', $id);
        }
        // Fallback: detect older offer listing records that predate the workflow_type stamp.
        // Skip the fallback if the listing is already confirmed as hire_agent.
        $offerMetaKeys = SellerOfferListingController::OFFER_LISTING_META_KEYS;
        if ($workflowType !== 'hire_agent' && $auction->meta->whereIn('meta_key', $offerMetaKeys)->isNotEmpty()) {
            return redirect()->route('offer.listing.seller.view', $id);
        }

        $data = $auction;

        // Auto-transition Bidding Period listing to Pending when timer ends
        $this->autoTransitionBpToPending($auction);

        $page_data['title']         = $auction->address ?? 'Listing Details';
        $page_data['counties']      = County::all();
        $page_data['id']            = $id;
        $page_data['auth_id']       = auth()->id();
        $page_data['lowest_bidder'] = $auction->bids->sortByDesc('created_at')->first();

        return view('hire_seller_agent.view', compact('auction', 'data') + $page_data);
    }


    public function add_bid($id, Request $request)
    {
        $page_data['auction'] = $auction = SellerAgentAuction::find($id);
        $page_data['title'] = "Add Bid to Hiring Seller's Agent - " . $auction->address;

        $propertyType = $auction->get->property_type ?? 'residential';
        $defaultProfile = AgentDefaultProfile::findForAgent(Auth::id(), 'seller', $propertyType);
        $page_data['defaultProfileData'] = $defaultProfile ? ($defaultProfile->profile_data ?? []) : [];

        return view('hire_seller_agent.add-bid', $page_data);
    }

    /**
     * Save Seller's agent bid.
     * */
    public function saveSABid(Request $request)
    {
        $request->validate([
            'bio'                 => 'required|string',
            'why_hire_you'        => 'required|string',
            'what_sets_you_apart' => 'required|string',
            'marketing_plan'      => 'required|string',
        ], [
            'bio.required'                 => 'Please fill in "About Agent".',
            'why_hire_you.required'        => 'Please fill in "Why should you be hired as their agent?".',
            'what_sets_you_apart.required' => 'Please fill in "What sets you apart from other agents?".',
            'marketing_plan.required'      => 'Please fill in "What is your marketing strategy?".',
        ]);

        // Save default profile if requested
        if ($request->boolean('save_as_default') && Auth::check()) {
            $auctionId = $request->auction_id;
            $auction = SellerAgentAuction::find($auctionId);
            $propertyType = $auction ? ($auction->get->property_type ?? 'residential') : 'residential';
            AgentDefaultProfile::upsertForAgent(Auth::id(), 'seller', $propertyType, [
                'bio'                 => $request->bio,
                'why_hire_you'        => $request->why_hire_you,
                'what_sets_you_apart' => $request->what_sets_you_apart,
                'marketing_plan'      => $request->marketing_plan,
            ]);
        }

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
            // Seller broker compensation fields
            $bid->saveMeta('purchase_fee_type', $request->purchase_fee_type);
            $bid->saveMeta('purchase_fee_flat', $request->purchase_fee_flat);
            $bid->saveMeta('purchase_fee_percentage', $request->purchase_fee_percentage);
            $bid->saveMeta('purchase_fee_flat_combo', $request->purchase_fee_flat_combo);
            $bid->saveMeta('purchase_fee_percentage_combo', $request->purchase_fee_percentage_combo);
            $bid->saveMeta('purchase_fee_other', $request->purchase_fee_other);
            $bid->saveMeta('nominal', $request->nominal);
            $bid->saveMeta('commission_structure', $request->commission_structure);
            $bid->saveMeta('commission_structure_type', $request->commission_structure_type);
            $bid->saveMeta('commission_structure_type_fee_flat', $request->commission_structure_type_fee_flat);
            $bid->saveMeta('commission_structure_type_fee_percentage', $request->commission_structure_type_fee_percentage);
            $bid->saveMeta('commission_structure_type_fee_other', $request->commission_structure_type_fee_other);
            $bid->saveMeta('interested_purchase_fee_type', $request->interested_purchase_fee_type);
            $bid->saveMeta('seller_leasing_fee_type', $request->seller_leasing_fee_type);
            $bid->saveMeta('seller_leasing_gross', $request->seller_leasing_gross);
            $bid->saveMeta('seller_leasing_gross_rental', $request->seller_leasing_gross_rental);
            $bid->saveMeta('seller_leasing_gross_month_rent', $request->seller_leasing_gross_month_rent);
            $bid->saveMeta('seller_leasing_gross_other', $request->seller_leasing_gross_other);
            $bid->saveMeta('seller_leasing_gross_purchase_fee_flat_amount', $request->seller_leasing_gross_purchase_fee_flat_amount);
            $bid->saveMeta('seller_leasing_gross_purchase_fee_other', $request->seller_leasing_gross_purchase_fee_other);
            $bid->saveMeta('interested_lease_option_agreement', $request->interested_lease_option_agreement);
            $bid->saveMeta('lease_type', $request->lease_type);
            $bid->saveMeta('lease_value', $request->lease_value);
            $bid->saveMeta('purchase_type', $request->purchase_type);
            $bid->saveMeta('purchase_value', $request->purchase_value);
            $bid->saveMeta('protection_period', $request->protection_period);
            $bid->saveMeta('early_termination_fee_option', $request->early_termination_fee_option);
            $bid->saveMeta('early_termination_fee_amount', $request->early_termination_fee_amount);
            $bid->saveMeta('retainer_fee_option', $request->retainer_fee_option);
            $bid->saveMeta('retainer_fee_amount', $request->retainer_fee_amount);
            $bid->saveMeta('retainer_fee_application', $request->retainer_fee_application);
            $bid->saveMeta('retained_deposits', $request->retained_deposits);
            $bid->saveMeta('agency_agreement_timeframe', $request->agency_agreement_timeframe);
            $bid->saveMeta('agency_agreement_custom', $request->agency_agreement_custom);
            $bid->saveMeta('brokerage_relationship', $request->brokerage_relationship);
            $bid->saveMeta('additional_details_broker', $request->additional_details_broker);

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

            // Increment 1 day by adding one bid — Bidding Period listings only
            $bid_count = SellerAgentAuctionBid::where('seller_agent_auction_id', $request->auction_id)->count();
            $seller_auction = SellerAgentAuction::with('meta')->find($request->auction_id);
            $sellerAuctionTypeMeta = strtolower(trim($seller_auction->get->auction_type ?? ''));
            if (in_array($sellerAuctionTypeMeta, ['bidding period', 'auction (timer)'])) {
                $date = new DateTime($seller_auction->get->expiration_date);
                $date->modify('+1 day');
                $date->setTime(0, 0, 0);
                $increase_day = $date->format('Y-m-d H:i:s');
                SellerAgentAuctionMeta::where('meta_key', 'expiration_date')
                    ->where('seller_agent_auction_id', $request->auction_id)
                    ->update(['meta_value' => $increase_day]);
            }

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
            $page_data['auctions'] = SellerAgentAuction::where('is_sold', 'true')->get();
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
        app(\App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService::class)->buildSilently('seller', (int) $id);
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    /**
     * Seller can accept Seller's Agent bid on his/her auction.
     **/
    public function acceptSABid(Request $request)
    {
        $pab = SellerAgentAuctionBid::whereId($request->bid_id)->first();
        if (!$pab) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $pa = SellerAgentAuction::find($pab->seller_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        if ($request->has('auction_id') && (int)$request->auction_id !== (int)$pa->id) {
            abort(403, 'Bid does not belong to this auction.');
        }

        if ($pa->user_id !== Auth::id()) {
            abort(403, 'Only the listing owner can accept bids.');
        }

        if ($pab->accepted === 'accepted' || $pab->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This bid has already been ' . $pab->accepted . '.');
        }

        // Expiry guard: prevent accept/reject on expired listings via direct POST
        $expiryDate = $pa->get->expiration_date ?? null;
        if ($expiryDate && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($expiryDate))) {
            return redirect()->back()->with('error', 'This listing is expired and can no longer accept or reject bids.');
        }

        try {
            DB::beginTransaction();

            $pab->accepted = 'accepted';
            $pab->accepted_date = date('Y-m-d H:i:s');
            $pab->save();

            $pa->is_sold = true;
            $pa->sold_date = date('Y-m-d H:i:s');
            $pa->save();
            $pa->saveMeta('listing_status', 'Hired Agent');

            SellerAgentAuctionBid::where('seller_agent_auction_id', $pa->id)
                ->where('id', '!=', $pab->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            $ua = new UserAgent();
            $ua->user_id = Auth::id();
            $ua->agent_id = $pab->user_id;
            $ua->type = 'seller';
            $ua->save();

            DB::commit();

            $summaryId = null;
            try {
                $summaryService = new SellerAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($pab, null);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to generate accepted bid summary after seller bid acceptance', [
                    'bid_id' => $pab->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            try {
                $agent = User::find($pab->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($pab, $pa, $summaryId, 'seller_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send seller bid accepted notification', [
                    'bid_id'   => $pab->id,
                    'agent_id' => $pab->user_id,
                    'error'    => $e->getMessage(),
                ]);
            }

            try {
                $seller = User::find($pa->user_id);
                if ($seller) {
                    $seller->notify(new SellerAgentHiredNotification($pab, $pa, $summaryId, 'seller_agent'));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send seller hired notification', [
                    'bid_id'    => $pab->id,
                    'seller_id' => $pa->user_id,
                    'error'     => $e->getMessage(),
                ]);
            }

            if ($summaryId) {
                return redirect()->route('accepted-bid-summary.view', $summaryId)
                    ->with('success', 'Bid accepted successfully! Your Accepted Bid Summary is ready to review and sign.');
            }
            return redirect()->back()->with('success', 'Bid accepted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error accepting bid: ' . $e->getMessage());
        }
    }

    public function rejectSABid(Request $request)
    {
        $pab = SellerAgentAuctionBid::whereId($request->bid_id)->first();
        if (!$pab) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $pa = SellerAgentAuction::find($pab->seller_agent_auction_id);
        if (!$pa) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        if ($request->has('auction_id') && (int)$request->auction_id !== (int)$pa->id) {
            abort(403, 'Bid does not belong to this auction.');
        }

        if ($pa->user_id !== Auth::id()) {
            abort(403, 'Only the listing owner can reject bids.');
        }

        if ($pab->accepted === 'accepted' || $pab->accepted === 'rejected') {
            return redirect()->back()->with('error', 'This bid has already been ' . $pab->accepted . '.');
        }

        // Expiry guard: prevent accept/reject on expired listings via direct POST
        $expiryDate = $pa->get->expiration_date ?? null;
        if ($expiryDate && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($expiryDate))) {
            return redirect()->back()->with('error', 'This listing is expired and can no longer accept or reject bids.');
        }

        $pab->accepted = 'rejected';
        $pab->accepted_date = date('Y-m-d H:i:s');

        if ($pab->save()) {
            try {
                $agent = User::find($pab->user_id);
                if ($agent) {
                    $agent->notify(new BidRejectedNotification($pab, $pa));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send seller bid rejected notification', [
                    'bid_id'   => $pab->id,
                    'agent_id' => $pab->user_id,
                    'error'    => $e->getMessage(),
                ]);
            }
            return redirect()->back()->with('success', 'Bid rejected successfully!');
        }
        return redirect()->back()->with('error', 'Error rejecting bid.');
    }

    public function view_counter_terms($bid_id)
    {
        $bid = SellerAgentAuctionBid::with(['meta', 'auction', 'auction.user', 'user'])->find($bid_id);

        if (!$bid) {
            return redirect()->back()->with('error', 'Bid not found.');
        }

        $auction = SellerAgentAuction::with(['user', 'meta'])->find($bid->seller_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        $userId  = Auth::id();
        $isAgent = ($bid->user_id === $userId);
        $isSeller = ($auction->user_id === $userId);

        if (!$isAgent && !$isSeller) {
            abort(403, 'You do not have permission to view these counter terms.');
        }

        // Load ALL counter terms for this bid (ordered oldest→newest by updated_at for chain display)
        $allCounters = \App\Models\SellerCounterTerm::with('meta')
            ->where('seller_agent_auction_bid_id', $bid_id)
            ->orderBy('updated_at', 'asc')
            ->get();

        // Seller's latest *active* (status=1) original counter (parent_counter_id = null)
        // Rejected counters (status=0) do not advance the stage; only active ones matter.
        $sellerCounter = $allCounters
            ->filter(fn($c) => $c->user_id === $auction->user_id && is_null($c->parent_counter_id) && (int)$c->status === 1)
            ->last();

        // Agent's latest *active* counter-back (parent_counter_id set, user = agent)
        $agentCounterBack = $allCounters
            ->filter(fn($c) => $c->user_id === $bid->user_id && !is_null($c->parent_counter_id) && (int)$c->status === 1)
            ->last();

        // Determine active stage using updated_at so edits by either party advance the turn.
        // Stage: 'seller_needs_response' (agent counter-backed most recently)
        //        'agent_needs_response'  (seller countered or re-countered most recently)
        //        'no_counter'            (no counters yet)
        $activeStage = 'no_counter';
        if ($sellerCounter && $agentCounterBack) {
            // Whichever was updated most recently determines whose turn it is
            $activeStage = $sellerCounter->updated_at >= $agentCounterBack->updated_at
                ? 'agent_needs_response'
                : 'seller_needs_response';
        } elseif ($sellerCounter) {
            $activeStage = 'agent_needs_response';
        } elseif ($agentCounterBack) {
            $activeStage = 'seller_needs_response';
        }

        $viewerRole = $isAgent ? 'agent' : 'seller';

        return view('hire_seller_agent.view_counter_terms', [
            'bid'             => $bid,
            'auction'         => $auction,
            'sellerCounter'   => $sellerCounter,
            'agentCounterBack'=> $agentCounterBack,
            'allCounters'     => $allCounters,
            'activeStage'     => $activeStage,
            'viewerRole'      => $viewerRole,
            'isAgent'         => $isAgent,
            'isSeller'        => $isSeller,
            'isOfferListing'  => $auction->info('workflow_type') === 'offer_listing',
        ]);
    }

    /**
     * Agent accepts the seller's counter terms.
     * Mirrors TenantAgentAuctionBidController::accept_counter_bid.
     */
    public function accept_seller_counter(Request $request)
    {
        $counterTerm = \App\Models\SellerCounterTerm::find($request->counter_term_id);
        if (!$counterTerm) {
            return redirect()->back()->with('error', 'Counter term not found.');
        }

        $bid = SellerAgentAuctionBid::find($counterTerm->seller_agent_auction_bid_id);
        if (!$bid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }

        $auction = SellerAgentAuction::find($bid->seller_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        // The opposite party of whoever created the counter term may accept it:
        // - If seller created it (parent_counter_id = null): agent accepts
        // - If agent created it (parent_counter_id set): seller accepts
        $isSellerCreated = is_null($counterTerm->parent_counter_id) && ($counterTerm->user_id === $auction->user_id);
        $isAgentCreated  = !is_null($counterTerm->parent_counter_id) && ($counterTerm->user_id === $bid->user_id);

        $currentUser = Auth::id();
        $agentIsActing  = ($currentUser === $bid->user_id);
        $sellerIsActing = ($currentUser === $auction->user_id);

        $authorized = ($isSellerCreated && $agentIsActing) || ($isAgentCreated && $sellerIsActing);
        if (!$authorized) {
            abort(403, 'You are not authorized to accept this counter offer.');
        }

        // Guard: cannot accept an already-rejected counter term
        if ((int)$counterTerm->status === 0) {
            return redirect()->back()->with('error', 'This counter offer has already been rejected.');
        }

        if (in_array($bid->accepted, ['accepted', 'rejected'], true)) {
            return redirect()->back()->with('error', 'This bid has already been ' . $bid->accepted . '.');
        }

        try {
            DB::beginTransaction();

            $bid->accepted      = 'accepted';
            $bid->accepted_date = date('Y-m-d H:i:s');
            $bid->save();

            $auction->is_sold  = true;
            $auction->sold_date = date('Y-m-d H:i:s');
            $auction->save();
            $auction->saveMeta('listing_status', 'Hired Agent');

            SellerAgentAuctionBid::where('seller_agent_auction_id', $auction->id)
                ->where('id', '!=', $bid->id)
                ->where('accepted', '!=', 'accepted')
                ->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);

            // UserAgent relationship — seller hired this agent
            $ua = new UserAgent();
            $ua->user_id  = $auction->user_id;
            $ua->agent_id = $bid->user_id;
            $ua->type     = 'seller';
            $ua->save();

            DB::commit();

            // Record recommendation attribution for bid_accepted.
            // getRecContext() looks up session context stored when the bid was viewed
            // via a recommendation link (?from_rec=1&surface=...).
            try {
                $recCtx = \App\Services\BidAnalyticsService::getRecContext('seller_agent', (int) $bid->id);
                \App\Services\BidAnalyticsService::recordRecommendationInteraction(
                    'bid_accepted', 'seller',
                    $recCtx['from_recommendation'], $recCtx['surface'],
                    'seller_agent', (int) $bid->id,
                    $auction->property_type ?? null, Auth::id()
                );
            } catch (\Throwable $e) {
                // Analytics failure must not disrupt bid acceptance
            }

            $summaryId = null;
            try {
                $summaryService = new SellerAcceptedBidSummaryService();
                $summary = $summaryService->generateSummary($bid, $counterTerm);
                if ($summary) {
                    $summaryId = $summary->id;
                }
            } catch (\Exception $e) {
                Log::error('[SellerCounter] Failed to generate accepted bid summary', [
                    'bid_id'       => $bid->id,
                    'counter_id'   => $counterTerm->id,
                    'error'        => $e->getMessage(),
                ]);
            }

            try {
                $agent = User::find($bid->user_id);
                if ($agent) {
                    $agent->notify(new BidAcceptedNotification($bid, $auction, $summaryId, 'seller_agent'));
                }
            } catch (\Exception $e) {
                Log::error('[SellerCounter] Failed to send counter accepted notification to agent', ['error' => $e->getMessage()]);
            }

            try {
                $seller = User::find($auction->user_id);
                if ($seller) {
                    $seller->notify(new SellerAgentHiredNotification($bid, $auction, $summaryId, 'seller_agent'));
                }
            } catch (\Exception $e) {
                Log::error('[SellerCounter] Failed to send seller hired notification', ['error' => $e->getMessage()]);
            }

            if ($summaryId) {
                return redirect()->route('accepted-bid-summary.view', $summaryId)
                    ->with('success', 'Counter offer accepted! Your Accepted Bid Summary is ready.');
            }
            return redirect()->route('seller.agent.auction.detail', $auction->id)
                ->with('success', 'Counter offer accepted!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error accepting counter offer: ' . $e->getMessage());
        }
    }

    /**
     * Agent rejects the seller's counter terms.
     * Mirrors TenantAgentAuctionBidController::reject_counter_bid.
     */
    public function reject_seller_counter(Request $request)
    {
        $counterTerm = \App\Models\SellerCounterTerm::find($request->counter_term_id);
        if (!$counterTerm) {
            return redirect()->back()->with('error', 'Counter term not found.');
        }

        $bid = SellerAgentAuctionBid::find($counterTerm->seller_agent_auction_bid_id);
        if (!$bid) {
            return redirect()->back()->with('error', 'Original bid not found.');
        }

        $auction = SellerAgentAuction::find($bid->seller_agent_auction_id);
        if (!$auction) {
            return redirect()->back()->with('error', 'Auction not found.');
        }

        // The opposite party of whoever created the counter term may reject it
        $isSellerCreated = is_null($counterTerm->parent_counter_id) && ($counterTerm->user_id === $auction->user_id);
        $isAgentCreated  = !is_null($counterTerm->parent_counter_id) && ($counterTerm->user_id === $bid->user_id);

        $currentUser = Auth::id();
        $agentIsActing  = ($currentUser === $bid->user_id);
        $sellerIsActing = ($currentUser === $auction->user_id);

        $authorized = ($isSellerCreated && $agentIsActing) || ($isAgentCreated && $sellerIsActing);
        if (!$authorized) {
            abort(403, 'You are not authorized to reject this counter offer.');
        }

        // Guard: do not allow rejecting a counter that is already resolved
        if ((int)$counterTerm->status === 0) {
            return redirect()->back()->with('error', 'This counter offer has already been rejected.');
        }

        // Reject the counter term record (not the bid) — mirrors Tenant reject_counter_bid behavior.
        // status=0 means rejected; the parent bid remains active/negotiable.
        $counterTerm->status = 0;
        if ($counterTerm->save()) {
            return redirect()->route('seller.agent.auction.detail', $auction->id)
                ->with('success', 'Counter offer rejected. The bid remains active.');
        }
        return redirect()->back()->with('error', 'Error rejecting counter offer.');
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

        // Offer Listing-specific meta keys — sourced from the single authoritative list
        // in SellerOfferListingController to avoid duplication.
        $offerListingMetaKeys = SellerOfferListingController::OFFER_LISTING_META_KEYS;

        $auctions->selectRaw("*, (SELECT meta_value FROM seller_agent_auction_metas WHERE seller_agent_auction_metas.seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'ideal_price') as price")
            ->where('is_approved', true)
            ->where('is_draft', false)
            // Exclude Seller Offer Listings — primary check: workflow_type = offer_listing
            ->whereDoesntHave('meta', function ($m) {
                $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing');
            })
            // Exclude Seller Offer Listings — fallback: presence of any offer-listing-specific meta key,
            // but only if the listing is not already confirmed as hire_agent by workflow_type.
            ->where(function ($q) use ($offerListingMetaKeys) {
                $q->whereHas('meta', fn($m) => $m->where('meta_key', 'workflow_type')->where('meta_value', 'hire_agent'))
                  ->orWhereDoesntHave('meta', fn($m) => $m->whereIn('meta_key', $offerListingMetaKeys));
            })
            // Exclude Direct Hire listings — these are stamped with hire_me_flow = '1' by HireAgentDirectController
            // and share workflow_type = 'hire_agent' with normal marketplace listings, so they must be filtered separately.
            ->whereDoesntHave('meta', function ($m) {
                $m->where('meta_key', 'hire_me_flow')->where('meta_value', '1');
            });

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

        $sort = $request->sort ?? 'newest';
        if ($sort === 'most_viewed') {
            $auctions->orderByRaw('(SELECT COUNT(*) FROM seller_agent_auction_bids WHERE seller_agent_auction_bids.seller_agent_auction_id = seller_agent_auctions.id) DESC');
        } elseif ($sort === 'ending_soon') {
            $auctions->orderByRaw("
                CASE
                    WHEN NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '') IS NOT NULL
                        AND NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int > 0
                        AND (seller_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int) > NOW()
                    THEN EXTRACT(EPOCH FROM (seller_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int))
                    WHEN COALESCE((SELECT meta_value FROM seller_agent_auction_metas
                        WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1), '') <> ''
                        AND (SELECT meta_value FROM seller_agent_auction_metas
                            WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date >= CURRENT_DATE
                    THEN EXTRACT(EPOCH FROM (SELECT meta_value FROM seller_agent_auction_metas
                        WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date::timestamp)
                    ELSE 9999999999
                END ASC, seller_agent_auctions.created_at DESC
            ");
        } else {
            $auctions->orderBy('created_at', 'DESC');
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

    public function bidDetail($bid_id)
    {
        $bid = SellerAgentAuctionBid::with(['user', 'meta'])->findOrFail($bid_id);
        $auction = $bid->auction()->with(['user', 'meta'])->first();
        if (!$auction) {
            abort(404, 'Listing not found.');
        }
        $authId = Auth::id();
        if (!$authId || ((int)$authId !== (int)$auction->user_id && (int)$authId !== (int)$bid->user_id)) {
            abort(403);
        }

        // Track bid_viewed for Matching Analytics recommendation attribution.
        // Pass ?from_rec=1&surface=<surface_name> to attribute the view to a recommendation surface.
        try {
            $fromRec = (bool) request()->query('from_rec', false);
            $surface = $fromRec ? (request()->query('surface') ?: 'direct') : null;
            \App\Services\BidAnalyticsService::recordRecommendationInteraction(
                'bid_viewed', 'seller', $fromRec, $surface,
                'seller_agent', (int) $bid->id,
                $auction->property_type ?? null, Auth::id()
            );
        } catch (\Throwable $e) {
            // Analytics failure must not disrupt bid viewing
        }

        return view('hire_seller_agent.bid_detail', compact('bid', 'auction'));
    }
}
