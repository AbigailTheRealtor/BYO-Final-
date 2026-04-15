<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\County;
use App\Models\Financing;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordAgentAuctionMeta;
use Illuminate\Support\Facades\Auth;

class LandlordAgentAuctionController extends Controller
{
    public function index(Request $request)
    {
        $page_data['title'] = 'Hire Landlord\'s Agent';
        $page_data['financings'] = Financing::orderBy('sort', 'asc')->get();
        return view('hire_landlord_agent.add', $page_data);
    }

    public function store(Request $request)
    {

        try {
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            DB::beginTransaction();
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lengths = explode(' ', $request->auction_length);
                $auction_length_days = current($auction_lengths);
            } else {
                $auction_length_days = '-1';
            }

            $auction = new LandlordAgentAuction();
            $auction->user_id = Auth::user()->id;
            $auction->address = $request->address;
            $auction->auction_type = $request->auction_type;
            $auction->auction_length = $auction_length_days;
            $auction->is_approved = true;
            $auction->is_draft = false;
            $auction->save();
            $auction->saveMeta("working_with_agent", $request->working_with_agent);
            $auction->saveMeta("address", $request->address);
            $auction->saveMeta("unit_num", $request->unit_num);
            $auction->saveMeta("city", $request->city);
            $auction->saveMeta("county", $request->county);
            $auction->saveMeta("state", $request->state);
            $auction->saveMeta("listing_date", $request->listing_date);
            $auction->saveMeta("expiration_date", $request->expiration_date);
            $auction->saveMeta("auction_type", $request->auction_type);
            $auction->saveMeta("auction_length", $request->auction_length);
            $auction->saveMeta("titleListing", $request->titleListing);
            $auction->saveMeta("property_type", $request->property_type);
            $auction->saveMeta("property_items", json_encode($request->property_items));
            $auction->saveMeta("leaseRoom", $request->leaseRoom);
            $auction->saveMeta("sizeOfRoom", $request->sizeOfRoom);
            $auction->saveMeta("privateBathroom", $request->privateBathroom);
            $auction->saveMeta("storageSpace", $request->storageSpace);
            $auction->saveMeta("commonAreas", $request->commonAreas);
            $auction->saveMeta("areasManaged", $request->areasManaged);
            $auction->saveMeta("tenantsGuests", $request->tenantsGuests);
            $auction->saveMeta("maintenanceIssues", $request->maintenanceIssues);
            $auction->saveMeta("utilitiesSplit", $request->utilitiesSplit);
            $auction->saveMeta("prop_condition", $request->prop_condition);
            $auction->saveMeta("custom_property_condition", $request->custom_property_condition);
            $auction->saveMeta("bedrooms", $request->bedrooms);
            $auction->saveMeta("custom_bedrooms", $request->custom_bedrooms);
            $auction->saveMeta("bathrooms", $request->bathrooms);
            $auction->saveMeta("custom_bathrooms", $request->custom_bathrooms);
            $auction->saveMeta("heated_square_footage", $request->heated_square_footage);
            $auction->saveMeta("net_leasable_square_footage", $request->net_leasable_square_footage);
            $auction->saveMeta("heated_sqft", $request->heated_sqft);
            $auction->saveMeta("other_heated_sqft", $request->other_heated_sqft);
            $auction->saveMeta("totalSqft", $request->totalSqft);
            $auction->saveMeta("total_acreage", $request->total_acreage);
            $auction->saveMeta("garageOptions", $request->garageOptions);
            $auction->saveMeta("custom_garage", $request->custom_garage);
            $auction->saveMeta("carportOptions", $request->carportOptions);
            $auction->saveMeta("custom_carport", $request->custom_carport);
            $auction->saveMeta("poolOptions", $request->poolOptions);
            $auction->saveMeta("pool", $request->pool);
            $auction->saveMeta("lease_sqft", $request->lease_sqft);
            $auction->saveMeta("other_lease_sqft", $request->other_lease_sqft);
            $auction->saveMeta("parkingOptions", $request->parkingOptions);
            $auction->saveMeta("parking", $request->parking);
            $auction->saveMeta("parkingOther", $request->parkingOther);
            $auction->saveMeta("customPool", $request->customPool);
            $auction->saveMeta("view", json_encode($request->view));
            $auction->saveMeta("viewOther", $request->viewOther);
            $auction->saveMeta("appliances", json_encode($request->appliances));
            $auction->saveMeta("otherAppliances", $request->otherAppliances);
            $auction->saveMeta("furnishings", json_encode($request->furnishings));
            $auction->saveMeta("amenities", json_encode($request->amenities));
            $auction->saveMeta("otherAmenities", $request->otherAmenities);
            $auction->saveMeta("rent_include", json_encode($request->rent_include));
            $auction->saveMeta("other_rent_include", $request->other_rent_include);
            $auction->saveMeta("tenantPays", json_encode($request->tenantPays));
            $auction->saveMeta("otherTenantPays", $request->otherTenantPays);
            $auction->saveMeta("ownerPays", json_encode($request->ownerPays));
            $auction->saveMeta("otherOwnerPays", $request->otherOwnerPays);
            $auction->saveMeta("petOptions", $request->petOptions);
            $auction->saveMeta("petsNumber", $request->petsNumber);
            $auction->saveMeta("petsType", $request->petsType);
            $auction->saveMeta("petsWeight", $request->petsWeight);
            $auction->saveMeta("petsFee", $request->petsFee);
            $auction->saveMeta("petsFeeAmount", $request->petsFeeAmount);
            $auction->saveMeta("petsFund", $request->petsFund);
            $auction->saveMeta("propertyLoc", $request->propertyLoc);
            $auction->saveMeta("occupant_type", $request->occupant_type);
            $auction->saveMeta("occupied_until", $request->occupied_until);
            $auction->saveMeta("expectation", $request->expectation);
            $auction->saveMeta("custom_ready_timeframe", $request->custom_ready_timeframe);
            $auction->saveMeta("lease_period", $request->lease_period);
            $auction->saveMeta("custom_lease_period", $request->custom_lease_period);
            $auction->saveMeta("listing_term", $request->listing_term);
            $auction->saveMeta("custom_listing_terms", $request->custom_listing_terms);
            $auction->saveMeta("offered_commission", $request->offered_commission);
            $auction->saveMeta("offeredCommissionOther", $request->offeredCommissionOther);
            $auction->saveMeta("tenantCommission", $request->tenantCommission);
            $auction->saveMeta("tenantCommissionOther", $request->tenantCommissionOther);
            $auction->saveMeta("broker_compensation", $request->broker_compensation);
            $auction->saveMeta("compensation_percent", $request->compensation_percent);
            $auction->saveMeta("handle_compensation", $request->handle_compensation);
            $auction->saveMeta("compensation_amount", $request->compensation_amount);
            $auction->saveMeta("compensation_tenant_broker", $request->compensation_tenant_broker);
            $auction->saveMeta("payment_timing", $request->payment_timing);
            $auction->saveMeta("payment_timing_days", $request->payment_timing_days);
            $auction->saveMeta("early_termination", $request->early_termination);
            $auction->saveMeta("early_termination_amount", $request->early_termination_amount);
            $auction->saveMeta("protection_period", $request->protection_period);
            $auction->saveMeta("protection_period_days", $request->protection_period_days);
            $auction->saveMeta("compensation_new_lease_percent", $request->compensation_new_lease_percent);
            $auction->saveMeta("compensation_new_lease_amount", $request->compensation_new_lease_amount);
            $auction->saveMeta("compensation_new_lease", $request->compensation_new_lease);
            $auction->saveMeta("termLease", json_encode($request->termLease));
            $auction->saveMeta("termLeaseOther", $request->termLeaseOther);
            $auction->saveMeta("leaseAmount", $request->leaseAmount);
            $auction->saveMeta("season_runs_from", $request->season_runs_from);
            $auction->saveMeta("season_runs_to", $request->season_runs_to);
            $auction->saveMeta("description", $request->description);
            $auction->saveMeta("important_info", $request->important_info);
            $auction->saveMeta("services", json_encode($request->services));
            $auction->saveMeta("servicesOther", $request->servicesOther);
            $auction->saveMeta("first_name", $request->first_name);
            $auction->saveMeta("last_name", $request->last_name);
            $auction->saveMeta("email", $request->email);
            $auction->saveMeta("phone", $request->phone);
            $auction->saveMeta('video_type', $request->video_type);
            $auction->saveMeta('youtube_video_link', $request->youtube_video_link);
            $auction->saveMeta('vimeo_video_link', $request->vimeo_video_link);
            // adding 3 sections data
            // Pictures and Video Upload
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
            // 17 July 2023
            $auction->saveMeta('auction_length_days', $auction_length_days);
            DB::commit();
            return redirect()->route('landlord.agent.auction.view', $auction->id)->with('success', 'Auction added successfully');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add auction');
        }
    }
    public function edit($id, Request $request)
    {
        $page_data['auction'] = LandlordAgentAuction::findOrFail($id);
        $page_data['title'] = 'Edit Hire Landlord\'s Agent';
        $page_data['financings'] = Financing::orderBy('sort', 'asc')->get();
        return view('hire_landlord_agent.edit', $page_data);
    }

    public function update($id, Request $request)
    {
        try {
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            DB::beginTransaction();
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lengths = explode(' ', $request->auction_length);
                $auction_length_days = current($auction_lengths);
            } else {
                $auction_length_days = '-1';
            }

            $auction = LandlordAgentAuction::find($id);
            $auction->user_id = Auth::user()->id;
            $auction->address = $request->address;
            $auction->auction_type = $request->auction_type;
            $auction->auction_length = $auction_length_days;
            $auction->save();
            $auction->saveMeta("working_with_agent", $request->working_with_agent);
            $auction->saveMeta("address", $request->address);
            $auction->saveMeta("unit_num", $request->unit_num);
            $auction->saveMeta("city", $request->city);
            $auction->saveMeta("county", $request->county);
            $auction->saveMeta("state", $request->state);
            $auction->saveMeta("listing_date", $request->listing_date);
            $auction->saveMeta("expiration_date", $request->expiration_date);
            $auction->saveMeta("auction_type", $request->auction_type);
            $auction->saveMeta("auction_length", $request->auction_length);
            $auction->saveMeta("titleListing", $request->titleListing);
            $auction->saveMeta("property_type", $request->property_type);
            $auction->saveMeta("property_items", json_encode($request->property_items));
            $auction->saveMeta("leaseRoom", $request->leaseRoom);
            $auction->saveMeta("sizeOfRoom", $request->sizeOfRoom);
            $auction->saveMeta("privateBathroom", $request->privateBathroom);
            $auction->saveMeta("storageSpace", $request->storageSpace);
            $auction->saveMeta("commonAreas", $request->commonAreas);
            $auction->saveMeta("areasManaged", $request->areasManaged);
            $auction->saveMeta("tenantsGuests", $request->tenantsGuests);
            $auction->saveMeta("maintenanceIssues", $request->maintenanceIssues);
            $auction->saveMeta("utilitiesSplit", $request->utilitiesSplit);
            $auction->saveMeta("prop_condition", $request->prop_condition);
            $auction->saveMeta("custom_property_condition", $request->custom_property_condition);
            $auction->saveMeta("bedrooms", $request->bedrooms);
            $auction->saveMeta("custom_bedrooms", $request->custom_bedrooms);
            $auction->saveMeta("bathrooms", $request->bathrooms);
            $auction->saveMeta("custom_bathrooms", $request->custom_bathrooms);
            $auction->saveMeta("heated_square_footage", $request->heated_square_footage);
            $auction->saveMeta("net_leasable_square_footage", $request->net_leasable_square_footage);
            $auction->saveMeta("heated_sqft", $request->heated_sqft);
            $auction->saveMeta("other_heated_sqft", $request->other_heated_sqft);
            $auction->saveMeta("totalSqft", $request->totalSqft);
            $auction->saveMeta("total_acreage", $request->total_acreage);
            $auction->saveMeta("garageOptions", $request->garageOptions);
            $auction->saveMeta("custom_garage", $request->custom_garage);
            $auction->saveMeta("carportOptions", $request->carportOptions);
            $auction->saveMeta("custom_carport", $request->custom_carport);
            $auction->saveMeta("poolOptions", $request->poolOptions);
            $auction->saveMeta("pool", $request->pool);
            $auction->saveMeta("lease_sqft", $request->lease_sqft);
            $auction->saveMeta("other_lease_sqft", $request->other_lease_sqft);
            $auction->saveMeta("parkingOptions", $request->parkingOptions);
            $auction->saveMeta("parking", $request->parking);
            $auction->saveMeta("parkingOther", $request->parkingOther);
            $auction->saveMeta("customPool", $request->customPool);
            $auction->saveMeta("view", json_encode($request->view));
            $auction->saveMeta("viewOther", $request->viewOther);
            $auction->saveMeta("appliances", json_encode($request->appliances));
            $auction->saveMeta("otherAppliances", $request->otherAppliances);
            $auction->saveMeta("furnishings", json_encode($request->furnishings));
            $auction->saveMeta("amenities", json_encode($request->amenities));
            $auction->saveMeta("otherAmenities", $request->otherAmenities);
            $auction->saveMeta("rent_include", json_encode($request->rent_include));
            $auction->saveMeta("other_rent_include", $request->other_rent_include);
            $auction->saveMeta("tenantPays", json_encode($request->tenantPays));
            $auction->saveMeta("otherTenantPays", $request->otherTenantPays);
            $auction->saveMeta("ownerPays", json_encode($request->ownerPays));
            $auction->saveMeta("otherOwnerPays", $request->otherOwnerPays);
            $auction->saveMeta("petOptions", $request->petOptions);
            $auction->saveMeta("petsNumber", $request->petsNumber);
            $auction->saveMeta("petsType", $request->petsType);
            $auction->saveMeta("petsWeight", $request->petsWeight);
            $auction->saveMeta("petsFee", $request->petsFee);
            $auction->saveMeta("petsFeeAmount", $request->petsFeeAmount);
            $auction->saveMeta("petsFund", $request->petsFund);
            $auction->saveMeta("propertyLoc", $request->propertyLoc);
            $auction->saveMeta("occupant_type", $request->occupant_type);
            $auction->saveMeta("occupied_until", $request->occupied_until);
            $auction->saveMeta("expectation", $request->expectation);
            $auction->saveMeta("custom_ready_timeframe", $request->custom_ready_timeframe);
            $auction->saveMeta("lease_period", $request->lease_period);
            $auction->saveMeta("custom_lease_period", $request->custom_lease_period);
            $auction->saveMeta("listing_term", $request->listing_term);
            $auction->saveMeta("custom_listing_terms", $request->custom_listing_terms);
            $auction->saveMeta("offered_commission", $request->offered_commission);
            $auction->saveMeta("offeredCommissionOther", $request->offeredCommissionOther);
            $auction->saveMeta("tenantCommission", $request->tenantCommission);
            $auction->saveMeta("tenantCommissionOther", $request->tenantCommissionOther);
            $auction->saveMeta("broker_compensation", $request->broker_compensation);
            $auction->saveMeta("compensation_percent", $request->compensation_percent);
            $auction->saveMeta("handle_compensation", $request->handle_compensation);
            $auction->saveMeta("compensation_amount", $request->compensation_amount);
            $auction->saveMeta("compensation_tenant_broker", $request->compensation_tenant_broker);
            $auction->saveMeta("payment_timing", $request->payment_timing);
            $auction->saveMeta("payment_timing_days", $request->payment_timing_days);
            $auction->saveMeta("early_termination", $request->early_termination);
            $auction->saveMeta("early_termination_amount", $request->early_termination_amount);
            $auction->saveMeta("protection_period", $request->protection_period);
            $auction->saveMeta("protection_period_days", $request->protection_period_days);
            $auction->saveMeta("compensation_new_lease_percent", $request->compensation_new_lease_percent);
            $auction->saveMeta("compensation_new_lease_amount", $request->compensation_new_lease_amount);
            $auction->saveMeta("compensation_new_lease", $request->compensation_new_lease);
            $auction->saveMeta("termLease", json_encode($request->termLease));
            $auction->saveMeta("termLeaseOther", $request->termLeaseOther);
            $auction->saveMeta("leaseAmount", $request->leaseAmount);
            $auction->saveMeta("season_runs_from", $request->season_runs_from);
            $auction->saveMeta("season_runs_to", $request->season_runs_to);
            $auction->saveMeta("description", $request->description);
            $auction->saveMeta("important_info", $request->important_info);
            $auction->saveMeta("services", json_encode($request->services));
            $auction->saveMeta("servicesOther", $request->servicesOther);
            $auction->saveMeta("first_name", $request->first_name);
            $auction->saveMeta("last_name", $request->last_name);
            $auction->saveMeta("email", $request->email);
            $auction->saveMeta("phone", $request->phone);
            $auction->saveMeta('video_type', $request->video_type);
            $auction->saveMeta('youtube_video_link', $request->youtube_video_link);
            $auction->saveMeta('vimeo_video_link', $request->vimeo_video_link);
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
                        $auction->saveMeta('photos', json_encode($photosNames));
                    }
                }
            }
            DB::commit();
            return redirect()->route('landlord.agent.auction.view', $id)->with('success', 'Auction updated successfully!');
        } catch (\Exception $e) {
            //throw $e;
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to update auction!');
        }
    }

    public function list(Request $request)
    {
        $page_data['title'] = 'Hire Landlord\'s Agent Listings';
        $page_data['type'] = $type = $request->type ?? "2";
        $pendingApprovalAuctions = LandlordAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false, 'is_draft' => false])->with(['bids.user', 'bids.meta']);
        $liveAuctions = LandlordAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false, 'is_draft' => false])->with(['bids.user', 'bids.meta']);
        $soldAuctions = LandlordAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true, 'is_draft' => false])->with(['bids.user', 'bids.meta']);

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

        return view('hire_landlord_agent.list', $page_data);
    }

    public function admin_list(Request $request)
    {
        $page_data['title'] = "Hire Landlord's Agent";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = LandlordAgentAuction::where('is_approved', true)->where('is_draft', false)->get();
        } elseif ($type == 2) {
            $page_data['auctions'] = LandlordAgentAuction::where('is_sold', true)->where('is_draft', false)->get();
        } else {
            $page_data['auctions'] = LandlordAgentAuction::where('is_approved', false)->where('is_draft', false)->get();
        }
        return view('admin.landlordAgentAuctions', $page_data);
    }

    public function approve($id)
    {
        $auction = LandlordAgentAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    public function search(Request $request)
    {
        $page_data['title'] = 'Search Listings';

        $auctions = LandlordAgentAuction::query();

        $auctions->selectRaw("*, (SELECT meta_value FROM landlord_agent_auction_metas WHERE landlord_agent_auction_metas.landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'ideal_price') as price")->where('is_approved', true);

        // if ($request->title != "") {
        //     $auctions->where('title', 'like', '%' . $request->title . '%');
        // }

        if (!empty($request->title)) {
            $auctions->where(function ($query) use ($request) {
                // Search in title
                $query->where('title', 'like', '%' . $request->title . '%')
                    // OR search in meta address if title doesn’t match
                    ->orWhereHas('meta', function ($meta) use ($request) {
                        $meta->where('meta_key', 'address')
                            ->where('meta_value', 'like', '%' . $request->title . '%');
                    });
            });
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
                $meta->where('meta_key', 'property_type')
                    ->where('meta_value', 'LIKE', '%' . $request->property_type . '%');
            });
        }

        $sort = $request->sort ?? 'newest';
        if ($sort === 'most_viewed') {
            $auctions->orderByRaw('(SELECT COUNT(*) FROM landlord_agent_auction_bids WHERE landlord_agent_auction_bids.landlord_agent_auction_id = landlord_agent_auctions.id) DESC');
        } elseif ($sort === 'ending_soon') {
            $auctions->orderByRaw("
                CASE
                    WHEN NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '') IS NOT NULL
                        AND NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int > 0
                        AND (landlord_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int) > NOW()
                    THEN EXTRACT(EPOCH FROM (landlord_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int))
                    WHEN COALESCE((SELECT meta_value FROM landlord_agent_auction_metas
                        WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1), '') <> ''
                        AND (SELECT meta_value FROM landlord_agent_auction_metas
                            WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date >= CURRENT_DATE
                    THEN EXTRACT(EPOCH FROM (SELECT meta_value FROM landlord_agent_auction_metas
                        WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date::timestamp)
                    ELSE 9999999999
                END ASC, landlord_agent_auctions.created_at DESC
            ");
        } else {
            $auctions->orderBy('created_at', 'DESC');
        }

        $page_data['count'] = $auctions->clone()->count();
        $page_data['pAuctions'] = $auctions->paginate(12);
        return view('hire_landlord_agent.search', $page_data);
    }

    public function view($id, Request $request)
    {
        // $data = LandlordAgentAuction::with('meta')->find($id);
        // return $data->get;

        $page_data['auction'] = $auction = LandlordAgentAuction::with(['bids.user', 'bids.meta', 'user', 'meta'])->find($id);
        // Auto-transition Bidding Period listing to Pending when timer ends
        $this->autoTransitionBpToPending($auction);
        $page_data['title'] = $auction->title;
        $page_data['counties'] = County::all();
        $page_data['id'] = $id;
        $page_data['auth_id'] = auth()->id();
        $page_data['bids'] = $auction->bids->whereNull('counter_id');
        return view('hire_landlord_agent.view', $page_data);
    }

    public function endAuction($id)
    {
        $auction = LandlordAgentAuction::findOrFail($id);
        $auction->update(['auction_ended' => true]);
        return response()->json(['message' => 'Auction ended successfully']);
    }

    public function bidsVisibility($id, $vis)
    {
        $auction = landlordAgentAuction::where('id', $id)->first();
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
}
