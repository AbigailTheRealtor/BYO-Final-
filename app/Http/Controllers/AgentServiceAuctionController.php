<?php

namespace App\Http\Controllers;

use App\Models\AgentService;
use App\Models\AgentServiceAuction;
use App\Models\AgentServiceAuctionBid;
use App\Models\City;
use App\Models\County;
use App\Models\State;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentServiceAuctionController extends Controller
{
    public function index()
    {
        $page_data['title'] = 'Add Agent Service Auction';
        return view('agent_service.add', $page_data);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        if (str_contains(strtolower($request->auction_length), 'hour') || str_contains(strtolower($request->auction_length), 'minute')) {
            $ar = explode(' ', $request->auction_length);
            if (str_contains(strtolower($request->auction_length), 'minute')) {
                $auction_length_days = current($ar) . 'M';
            } else {
                $auction_length_days = (current($ar) * 60) . 'M';
            }
        } else {
            $ar = explode(' ', $request->auction_length);
            $auction_length_days = current($ar);
        }
        try {
            DB::beginTransaction();
            $auction = new AgentServiceAuction();
            $auction->user_id = Auth::user()->id;
            $auction->auction_length = $auction_length_days;
            $auction->save();
            // 17 July 2023
            // Changes
            $auction->saveMeta('buyers_flat_fee_list', json_encode($request->buyers_flat_fee_list));
            $auction->saveMeta('seller_flat_fee_list', json_encode($request->seller_flat_fee_list));
            $auction->saveMeta('tenant_flat_fee_list', json_encode($request->tenant_flat_fee_list));
            $auction->saveMeta('real_estate_flat_fee_list', json_encode($request->real_estate_flat_fee_list));
            $auction->saveMeta('custom_buyers_flat_fee_list', $request->custom_buyers_flat_fee_list);
            $auction->saveMeta('has_non_negotiable_amenities', $request->has_non_negotiable_amenities);
            $auction->saveMeta('custom_real_state_cooperating_agent', $request->custom_real_state_cooperating_agent);
            $auction->saveMeta('custom_real_estatet_fee_list', $request->custom_real_estatet_fee_list);
            $auction->saveMeta('custom_seller_cooperating_agent', $request->custom_seller_cooperating_agent);
            $auction->saveMeta('custom_landlord_cooperating_agent', $request->custom_landlord_cooperating_agent);
            $auction->saveMeta('custom_non_negotiable_terms', $request->custom_non_negotiable_terms);
            $auction->saveMeta('agent_need', $request->agent_need);
            $auction->saveMeta('private_details', $request->private_details);
            $auction->saveMeta('service_type', $request->service_type);
            $auction->saveMeta('titleListing', $request->titleListing);
            // Changes
            $auction->saveMeta('service_type', $request->service_type);
            $auction->saveMeta('listing_date', Carbon::parse($request->listing_date));
            $auction->saveMeta('expiration_date', Carbon::parse($request->expiration_date));
            $auction->saveMeta('city', $request->city);
            $auction->saveMeta('county', $request->county);
            $auction->saveMeta('state', $request->state);
            $auction->saveMeta('auction_length', $request->auction_length);
            $auction->saveMeta('type_of_client', $request->type_of_client);
            $auction->saveMeta('service_date', $request->service_date);
            $auction->saveMeta('services', json_encode($request->services));
            $auction->saveMeta('custom_services', $request->custom_services);
            $auction->saveMeta('contact_name', $request->contact_name);
            $auction->saveMeta('contact_phone', $request->contact_phone);
            $auction->saveMeta('contact_email', $request->contact_email);
            $auction->saveMeta('showing_with_agent', $request->showing_with_agent);
            $auction->saveMeta('meeting_date', $request->meeting_date);
            $auction->saveMeta('meeting_time', $request->meeting_time);
            $auction->saveMeta('instructions', $request->instructions);
            $auction->saveMeta('offerer_commission', $request->offerer_commission);
            $auction->saveMeta('exact_amount', $request->exact_amount);
            $auction->saveMeta('has_negotiable', $request->has_negotiable);
            $auction->saveMeta('have_preferred_agent', $request->have_preferred_agent);
            $auction->saveMeta('first_name', $request->first_name);
            $auction->saveMeta('last_name', $request->last_name);
            $auction->saveMeta('brokerage', $request->brokerage);
            $auction->saveMeta('phone', $request->phone);
            $auction->saveMeta('email', $request->email);
            $auction->saveMeta('public_note', $request->public_note);
            $auction->saveMeta('private_note', $request->public_note);
            $auction->saveMeta('referral_fee', $request->referral_fee);
            $auction->saveMeta('custom_referral_fee', $request->custom_referral_fee);
            // 17 July 2023
            $auction->saveMeta('city', $request->city);
            $auction->saveMeta('county', $request->county);
            $auction->saveMeta('state', $request->state);
            $auction->saveMeta('offerer_commission_negotiable', $request->offerer_commission_negotiable);
            $auction->saveMeta('private_notes', $request->private_notes);
            $auction->saveMeta('additional_details', $request->additional_details);
            $auction->saveMeta('address', $request->address);
            DB::commit();
            return redirect()->back()->with('success', 'Auction added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add Agent Service Auction');
        }
    }

    public function edit($id)
    {
        $page_data['id'] = $id;
        $page_data['auction'] = AgentServiceAuction::find($id);
        $page_data['title'] = 'Edit Agent Service Auction';
        return view('agent_service.edit', $page_data);
    }

    public function update(Request $request)
    {
        // dd($request);
        if (str_contains(strtolower($request->auction_length), 'hour') || str_contains(strtolower($request->auction_length), 'minute')) {
            $ar = explode(' ', $request->auction_length);
            if (str_contains(strtolower($request->auction_length), 'minute')) {
                $auction_length_days = current($ar) . 'M';
            } else {
                $auction_length_days = (current($ar) * 60) . 'M';
            }
        } else {
            $ar = explode(' ', $request->auction_length);
            $auction_length_days = current($ar);
        }
        try {
            DB::beginTransaction();
            $auction = AgentServiceAuction::find($request->id);
            $auction->user_id = Auth::user()->id;
            $auction->auction_length = $auction_length_days;
            $auction->save();
            $auction->saveMeta('service_type', $request->service_type);
            $auction->saveMeta('referral_fee', $request->referral_fee);
            $auction->saveMeta('custom_referral_fee', $request->custom_referral_fee);
            $auction->saveMeta('type_of_client', $request->type_of_client);
            $auction->saveMeta('city', $request->city);
            $auction->saveMeta('county', $request->county);
            $auction->saveMeta('state', $request->state);
            $auction->saveMeta('services', json_encode($request->services));
            $auction->saveMeta('custom_services', $request->custom_services);
            $auction->saveMeta('offerer_commission', $request->offerer_commission);
            $auction->saveMeta('offerer_commission_negotiable', $request->offerer_commission_negotiable);
            $auction->saveMeta('service_date', $request->service_date);
            $auction->saveMeta('auction_length', $request->auction_length);
            $auction->saveMeta('first_name', $request->first_name);
            $auction->saveMeta('last_name', $request->last_name);
            $auction->saveMeta('brokerage', $request->brokerage);
            $auction->saveMeta('phone', $request->phone);
            $auction->saveMeta('email', $request->email);
            $auction->saveMeta('private_notes', $request->private_notes);
            $auction->saveMeta('additional_details', $request->additional_details);
            $auction->saveMeta('address', $request->address);
            $auction->saveMeta('contact_name', $request->contact_name);
            $auction->saveMeta('contact_phone', $request->contact_phone);
            $auction->saveMeta('contact_email', $request->contact_email);
            $auction->saveMeta('showing_with_agent', $request->showing_with_agent);
            $auction->saveMeta('meeting_date', $request->meeting_date);
            $auction->saveMeta('meeting_time', $request->meeting_time);
            $auction->saveMeta('instructions', $request->instructions);
            DB::commit();
            return redirect()->back()->with('success', 'Auction updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to update Agent Service Auction');
        }
    }

    public function list(Request $request)
    {
        $page_data['title'] = 'Agent Service Auctions';
        $page_data['type'] = $type = $request->type ?? "2";
        $pendingApprovalAuctions = AgentServiceAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'is_sold' => false]);
        $liveAuctions = AgentServiceAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => false]);
        $soldAuctions = AgentServiceAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'is_sold' => true]);

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
        return view('agent_service.list', $page_data);
    }


    public function view($id)
    {
        // $data = AgentServiceAuction::with('meta')->find($id);

        $page_data['auction'] = AgentServiceAuction::find($id);
        $page_data['counties'] = County::all();
        $page_data['id'] = $id;
        return view('agent_service.view', $page_data);
    }


    public function searchListing(Request $request)
    {
        $page_data['title'] = 'Search Listings';
        $auctions = AgentServiceAuction::query();

        $auctions->where('is_sold', false)->where('is_approved', 1);

        if ($request->title != "") {
            // $auctions->whereHas('meta', 'like', '%' . $request->title . '%');
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where(function ($query) use ($request) {
                    $query->where('meta_key', 'services')->where('meta_value', 'like', '%' . $request->title . '%');
                })->orWhere(function ($query) use ($request) {
                    $query->where('meta_key', 'custom_services')->where('meta_value', 'like', '%' . $request->title . '%');
                });
            });
        }


        if ($request->sort) {
            $sort = $request->sort;
            if ($sort == 1) {
                $sort_by = 'created_at';
                $sort_type = 'DESC';
            } elseif ($sort == 2) {
                $sort_by = 'created_at';
                $sort_type = 'ASC';
            } else {
                $sort_by = 'id';
                $sort_type = 'ASC';
            }
            $auctions->orderBy($sort_by, $sort_type);
        } else {
            $auctions->orderBy(DB::raw('RAND()'));
        }

        $page_data['count'] = $auctions->clone()->count();
        // dd($page_data['count']);
        $page_data['pAuctions'] = $auctions->paginate(12);
        return view('agent_service.search', $page_data);
    }
}
