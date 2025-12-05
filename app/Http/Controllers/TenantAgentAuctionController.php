<?php

namespace App\Http\Controllers;

use App\Models\County;
use App\Models\Financing;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBidMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class TenantAgentAuctionController extends Controller
{
    public function index()
    {

        $page_data['title'] = 'Hire Tenant\'s Agent Auction';
        return view('hire_tenant_agent.add', $page_data);
    }

    public function store(Request $request)
    {

        // if ($request != null) {

        // dd($request->all());
        $request->validate([
            // 'auction_type' => ['required'],
        ], [
            // 'auction_type.required' => 'Please select auction type',
        ]);
        $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
        $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
        $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
        $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv'];
        try {
            DB::beginTransaction();
            $auction = new TenantAgentAuction();
            $auction->user_id = Auth::user()->id;
            // $auction->title = $request->title;
            // 14 Jul 2023

            $auction->title = $request->listing_title;
            $auction->save();
            $auction->saveMeta('working_with_agent', $request->working_with_agent);
            $auction->saveMeta('cities', json_encode($request->cities));
            $auction->saveMeta('counties', json_encode($request->counties));
            $auction->saveMeta('states', $request->state);
            $auction->saveMeta('listing_date', $request->listing_date);
            $auction->saveMeta('expiration_date', $request->expiration_date);
            $auction->saveMeta('auction_type', $request->auction_type);
            $auction->saveMeta('auction_length', $request->auction_length);
            $auction->saveMeta('listing_title', $request->listing_title);
            $auction->saveMeta('property_type', $request->property_type);
            $auction->saveMeta('property_items', json_encode($request->property_items));
            $auction->saveMeta('bedrooms', $request->bedrooms);
            $auction->saveMeta('other_bedrooms', $request->other_bedrooms);
            $auction->saveMeta('bathrooms', $request->bathrooms);
            $auction->saveMeta('other_bathrooms', $request->other_bathrooms);
            $auction->saveMeta('minimum_heated_square', $request->minimum_heated_square);
            $auction->saveMeta('minimum_net_leasable_square', $request->minimum_net_leasable_square);
            $auction->saveMeta("tenant_lease", $request->tenant_lease);
            $auction->saveMeta("condition_prop", json_encode($request->condition_prop));
            $auction->saveMeta('tenant_require', json_encode($request->tenant_require));
            $auction->saveMeta('has_non_negotiable_terms', $request->has_non_negotiable_terms);
            $auction->saveMeta('non_negotiable_terms', json_encode($request->non_negotiable_terms));
            $auction->saveMeta('custom_non_negotiable_terms', $request->custom_non_negotiable_terms);
            $auction->saveMeta('budget', $request->budget);
            $auction->saveMeta('total_acreage', $request->total_acreage);
            $auction->saveMeta('garageOptions', $request->garageOptions);
            $auction->saveMeta('custom_garage', $request->custom_garage);
            $auction->saveMeta('carportOptions', $request->carportOptions);
            $auction->saveMeta('custom_carport', $request->custom_carport);
            $auction->saveMeta('garageOption', $request->garageOption);
            $auction->saveMeta('garage_parking', json_encode($request->garage_parking));
            $auction->saveMeta('prefrenceOptions', $request->prefrenceOptions);
            $auction->saveMeta('other_garage', $request->other_garage);
            $auction->saveMeta('poolOptions', $request->poolOptions);
            $auction->saveMeta('poolOpt', $request->poolOpt);
            $auction->saveMeta('prefrence', json_encode($request->prefrence));
            $auction->saveMeta('preferenceOther', $request->preferenceOther);
            $auction->saveMeta('petOptions', $request->petOptions);
            $auction->saveMeta('petsNumber', $request->petsNumber);
            $auction->saveMeta('petsType', $request->petsType);
            $auction->saveMeta('petsBreed', $request->petsBreed);
            $auction->saveMeta('petsWeight', $request->petsWeight);
            $auction->saveMeta('purchasing_props', $request->purchasing_props);
            $auction->saveMeta('has_non_negotiable_terms', $request->has_non_negotiable_terms);
            $auction->saveMeta('custom_non_negotiable_terms', $request->custom_non_negotiable_terms);
            $auction->saveMeta('lease_by', $request->lease_by);
            $auction->saveMeta('lease_for', $request->lease_for);
            $auction->saveMeta('custom_lease_for', $request->custom_lease_for);
            $auction->saveMeta('has_pets', $request->has_pets);
            $auction->saveMeta('custom_has_pets', $request->custom_has_pets);
            $auction->saveMeta('credit_score', $request->credit_score);
            $auction->saveMeta('evicted', $request->evicted);
            $auction->saveMeta('custom_evicted', $request->custom_evicted);
            $auction->saveMeta('convicted', $request->convicted);
            $auction->saveMeta('custom_convicted', $request->custom_convicted);
            $auction->saveMeta('monthly_income', $request->monthly_income);
            $auction->saveMeta('tenant_terms', $request->tenant_terms);
            $auction->saveMeta('custom_tenant_terms', $request->custom_tenant_terms);
            $auction->saveMeta('agents_fee', $request->agents_fee);
            $auction->saveMeta('custom_agents_fee', $request->custom_agents_fee);
            $auction->saveMeta('has_pay_fee', $request->has_pay_fee);
            $auction->saveMeta('fee', $request->fee);
            $auction->saveMeta('services', json_encode($request->services));
            $auction->saveMeta('other_services', $request->other_services);
            $auction->saveMeta('has_preferred_agent', $request->has_preferred_agent);
            $auction->saveMeta('first_name', $request->first_name);
            $auction->saveMeta('last_name', $request->last_name);
            $auction->saveMeta('brokerage', $request->brokerage);
            $auction->saveMeta('phone', $request->phone);
            $auction->saveMeta('email', $request->email);
            $auction->saveMeta('important_aspects', $request->important_aspects);
            $auction->saveMeta('Other_property_value', $request->Other_property_value);
            $auction->saveMeta('Other_property_fee', $request->Other_property_fee);
            $auction->saveMeta('property_fee', $request->property_fee);
            $auction->saveMeta('find_property', $request->find_property);
            $auction->saveMeta('additional_details', $request->additional_details);
            $auction->saveMeta('custom_convicted', $request->custom_convicted);
            $auction->saveMeta('custom_negotiable_terms', $request->custom_negotiable_terms);
            $auction->saveMeta('other_property_condition', $request->other_property_condition);
            $auction->saveMeta('people', $request->people);

            if ($request->hasFile('photo')) {
                $photo = $request->photo;
                $extensionPhoto = $photo->getClientOriginalExtension();
                $check = in_array($extensionPhoto, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $photoName = $uuid . '.' . $extensionPhoto;
                    $photo->move(public_path('auction/images'), $photoName);
                    $auction->saveMeta('photo', $photoName);
                }
            }
            if ($request->hasFile('video')) {
                $video = $request->video;
                $extensionVideo = $video->getClientOriginalExtension();
                $check = in_array($extensionVideo, $allowedVideos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $videoName = $uuid . '.' . $extensionVideo;
                    $video->move(public_path('auction/videos'), $videoName);
                    $auction->saveMeta('video', $videoName);
                }
            }

            // 14 Jul 2023
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lengths = explode(' ', $request->auction_length);
                $auction_length_days = current($auction_lengths);
                $auction->saveMeta('auction_length_days', $auction_length_days);
            } else {
                $auction->saveMeta('auction_length_days', '-1');
            }
            DB::commit();
            return redirect()->back()->with('success', 'Auction added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add auction');
        }
    }

    public function edit($id)
    {
        $page_data['auction'] = TenantAgentAuction::findOrFail($id);
        $page_data['title'] = 'Edit Hire Tenant\'s Agent Auction - ' . $page_data['auction']->get->title;
        return view('hire_tenant_agent.edit', $page_data);
    }

    public function update($id, Request $request)
    {
        // dd($request->all());
        try {
            DB::beginTransaction();
            $auction = TenantAgentAuction::findOrFail($id);
            $auction->user_id = Auth::user()->id;
            // $auction->title = $request->title;
            $auction->title = "Tenant’s Agent Required for " . $request->property_type;
            $auction->save();
            $auction->saveMeta('working_with_agent', $request->working_with_agent);
            $auction->saveMeta('auction_type', $request->auction_type);
            $auction->saveMeta('auction_length', $request->auction_length);
            if (str_contains(strtolower($request->auction_length), 'day')) {
                $auction_lengths = explode(' ', $request->auction_length);
                $auction_length_days = current($auction_lengths);
                $auction->saveMeta('auction_length_days', $auction_length_days);
            } else {
                $auction->saveMeta('auction_length_days', '-1');
            }
            $auction->saveMeta('state', $request->state);
            $auction->saveMeta('cities', json_encode($request->cities));
            $auction->saveMeta('county', $request->county);
            // $auction->saveMeta('title', $request->title);
            $auction->saveMeta('title', "Need Tenant's Agent for " . $request->property_type);
            $auction->saveMeta('bedrooms', $request->bedrooms);
            $auction->saveMeta('other_bedrooms', $request->other_bedrooms);
            $auction->saveMeta('bathrooms', $request->bathrooms);
            $auction->saveMeta('other_bathrooms', $request->other_bathrooms);
            $auction->saveMeta('budget', $request->budget);
            // $auction->saveMeta('custom_budget', $request->custom_budget);
            $auction->saveMeta('property_type', $request->property_type);
            $auction->saveMeta('property_items', json_encode($request->property_items));
            $auction->saveMeta('lease_by', $request->lease_by);
            $auction->saveMeta('custom_lease_by', $request->custom_lease_by);
            $auction->saveMeta('lease_for', $request->lease_for);
            $auction->saveMeta('custom_lease_for', $request->custom_lease_for);
            $auction->saveMeta('tenant_terms', $request->tenant_terms);
            $auction->saveMeta('custom_tenant_terms', $request->custom_tenant_terms);
            $auction->saveMeta('agents_fee', $request->agents_fee);
            $auction->saveMeta('custom_agents_fee', $request->custom_agents_fee);
            $auction->saveMeta('credit_score', $request->credit_score);
            $auction->saveMeta('has_pets', $request->has_pets);
            $auction->saveMeta('custom_has_pets', $request->custom_has_pets);
            $auction->saveMeta('evicted', $request->evicted);
            $auction->saveMeta('custom_evicted', $request->custom_evicted);
            $auction->saveMeta('convicted', $request->convicted);
            $auction->saveMeta('custom_convicted', $request->custom_convicted);
            $auction->saveMeta('monthly_income', $request->monthly_income);
            $auction->saveMeta('services', json_encode($request->services));
            $auction->saveMeta('other_services', $request->other_services);
            $auction->saveMeta('has_preferred_agent', $request->has_preferred_agent);
            $auction->saveMeta('first_name', $request->first_name);
            $auction->saveMeta('last_name', $request->last_name);
            $auction->saveMeta('brokerage', $request->brokerage);
            $auction->saveMeta('phone', $request->phone);
            $auction->saveMeta('email', $request->email);
            $auction->saveMeta('business_card', $request->business_card);
            DB::commit();
            return redirect()->back()->with('success', 'Auction updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to update auction');
        }
    }

    public function list(Request $request)
    {


        $page_data['title'] = 'Hire Tenant\'s Agent Auctions';
        $page_data['type'] = $type = $request->type ?? "2";
        $pendingApprovalAuctions = TenantAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false, 'is_draft' => false]);
        $liveAuctions = TenantAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false, 'is_draft' => false]);
        $soldAuctions = TenantAgentAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true, 'is_draft' => false]);
        if ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } else if ($type == "2") {
            $auctions = $liveAuctions->get();
        } else if ($type == '3') {
            $auctions = $soldAuctions->get();
        } else {
            $auctions = $liveAuctions->get();
        }

        $page_data['pendingApprovalCount'] = $pendingApprovalAuctions->count();
        $page_data['liveCount'] = $liveAuctions->count();
        $page_data['soldCount'] = $soldAuctions->count();

        $page_data['auctions'] = $auctions;
        return view('hire_tenant_agent.list', $page_data);
    }

    public function view($id, Request $request)
    {

        // $tenant = TenantAgentAuction::with('meta')->find($id);
        // return $tenant->get;
        $page_data['auction'] = $auction = TenantAgentAuction::with(['bids.user', 'bids.meta'])->find($id);
        $page_data['title'] = $auction->address;
        $page_data['counties'] = County::all();
        $page_data['id'] = $id;
        return view('hire_tenant_agent.view', $page_data);
    }

    public function search(Request $request)
    {

        $page_data['title'] = 'Search Listings';

        $auctions = TenantAgentAuction::query();

        $auctions->selectRaw("*, (SELECT meta_value FROM tenant_agent_auction_metas WHERE tenant_agent_auction_metas.tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'ideal_price') as price")->where('is_sold', false)->where('is_approved', true);

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
            // $auctions->whereHas('meta', function ($meta) use ($request) {
            //     $meta->where('meta_key', 'property_items')->where('meta_value', $request->property_type);
            // });
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'property_items')
                    ->where('meta_value', 'LIKE', '%"' . $request->property_type . '"%');
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

        $page_data['count'] = $auctions->clone()->count();
        $page_data['pAuctions'] = $auctions->paginate(12);
        return view('hire_tenant_agent.search', $page_data);
    }

    public function admin_list(Request $request)
    {

        $page_data['title'] = "Hire Tenant's Agent";
        $page_data['type'] = $type = $request->type ?? 0;
        if ($type == 1) {
            $page_data['auctions'] = TenantAgentAuction::where('is_approved', true)->where('is_draft', false)->get();
        } else if ($type == 2) {
            $page_data['auctions'] = TenantAgentAuction::where('is_sold', true)->where('is_draft', false)->get();
        } else {
            $page_data['auctions'] = TenantAgentAuction::where('is_approved', false)->where('is_draft', false)->get();
        }
        return view('admin.tenantAgentAuctions', $page_data);
    }

    public function approve($id)
    {
        $auction = TenantAgentAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }
}
