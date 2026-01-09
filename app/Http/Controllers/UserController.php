<?php

namespace App\Http\Controllers;

use App\Models\AgentServiceAuction;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\PropertyType;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\Financing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function author($id, Request $request)
    {
        $page_data['user'] = $user = User::findOrFail($id);
        $page_data['id'] = $id;
        $page_data['title'] = $page_data['user']->name;
        $page_data['type'] = $type = $request->type ?? 0;

        if ($user->user_type == 'agent') {
            if ($type == 0) {
                $page_data['auctions'] = PropertyAuction::where(['user_id' => $user->id, 'is_approved' => true, 'sold' => false])->paginate(12);
                return view('author_inc.property_auctions', $page_data);
            } else if ($type == 1) {
                $page_data['auctions'] = LandlordAuction::where('is_sold', false)->where('is_approved', 1)->paginate(12);
                return view('author_inc.landlord_auctions', $page_data);
            } else if ($type == 2) {
                $page_data['pAuctions'] = BuyerCriteriaAuction::where('is_sold', false)->where('is_approved', 1)->paginate(12);
                return view('author_inc.buyers_criteria_auctions', $page_data);
            } else if ($type == 3) {
                $page_data['pAuctions'] = TenantCriteriaAuction::where('is_sold', false)->where('is_approved', 1)->paginate(12);
                return view('author_inc.tenant_criteria_auctions', $page_data);
            } else if ($type == 4) {
                $page_data['pAuctions'] = AgentServiceAuction::where('is_sold', false)->where('is_approved', 1)->paginate(12);
                return view('author_inc.agent_service_auctions', $page_data);
            }
        } else if ($user->user_type == 'buyer') {
            $page_data['pAuctions'] = BuyerAgentAuction::where('is_sold', false)->where('is_approved', 1)->paginate(12);
            return view('author_inc.buyer_agent_auctions', $page_data);
        } else if ($user->user_type == 'seller') {
            $page_data['pAuctions'] = SellerAgentAuction::where('is_sold', false)->where('is_approved', 1)->paginate(12);
            return view('author_inc.seller_agent_auctions', $page_data);
        } else if ($user->user_type == 'landlord') {
            $page_data['pAuctions'] = LandlordAgentAuction::where('is_sold', false)->where('is_approved', 1)->paginate(12);
            return view('author_inc.landlord_agent_auctions', $page_data);
        } else if ($user->user_type == 'tenant') {
            // Tenant dashboard: Owner sees all their listings (including unapproved),
            // but other visitors only see approved listings for moderation
            $isOwner = Auth::id() === $user->id;
            
            if ($type == 0) {
                $query = TenantAgentAuction::where('user_id', $user->id)
                    ->where('is_sold', false)
                    ->where('is_draft', false);
                if (!$isOwner) {
                    $query->where('is_approved', 1);
                }
                $page_data['pAuctions'] = $query->paginate(12);
                return view('author_inc.tenant_agent_auctions', $page_data);
            } else if ($type == 1) {
                $query = SellerAgentAuction::where('user_id', $user->id)
                    ->where('is_sold', false)
                    ->where('is_draft', false);
                if (!$isOwner) {
                    $query->where('is_approved', 1);
                }
                $page_data['pAuctions'] = $query->paginate(12);
                return view('author_inc.seller_agent_auctions', $page_data);
            } else if ($type == 2) {
                $query = BuyerAgentAuction::where('user_id', $user->id)
                    ->where('is_sold', false)
                    ->where('is_draft', false);
                if (!$isOwner) {
                    $query->where('is_approved', 1);
                }
                $page_data['pAuctions'] = $query->paginate(12);
                return view('author_inc.buyer_agent_auctions', $page_data);
            } else if ($type == 3) {
                $query = LandlordAgentAuction::where('user_id', $user->id)
                    ->where('is_sold', false)
                    ->where('is_draft', false);
                if (!$isOwner) {
                    $query->where('is_approved', 1);
                }
                $page_data['pAuctions'] = $query->paginate(12);
                return view('author_inc.landlord_agent_auctions', $page_data);
            } else {
                $query = TenantAgentAuction::where('user_id', $user->id)
                    ->where('is_sold', false)
                    ->where('is_draft', false);
                if (!$isOwner) {
                    $query->where('is_approved', 1);
                }
                $page_data['pAuctions'] = $query->paginate(12);
                return view('author_inc.tenant_agent_auctions', $page_data);
            }
        } else {
            abort(404);
        }
    }

    public function changePasswordForm()
    {
        $page_data['title'] = 'Change Password';
        return view('auth.change-password', $page_data);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_pass' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!Hash::check($value, Auth::user()->password)) {
                        $fail('Invalid Old Password!');
                    }
                },
            ],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'confirm_password' => 'required|same:password',
        ], [
            'old_pass.required' => 'Old Password is required',
            'password.required' => 'New Password is required',
            'confirm_password.required' => 'Confirm Password is required',
        ]);

        $user = User::find(auth()->user()->id);
        $user->password = Hash::make($request->password);
        $user->update();
        return redirect()->back()->with('success', 'Password has been updated successfully!');
    }

    public function profileForm()
    {
        $page_data['title'] = 'Update Profile';
        return view('profile', $page_data);
    }

    public function short_uri($short_id)
    {
        $user = User::where("short_id", $short_id)->firstOrFail();
        $uri = $user->get->qr ?? route('author', $user->id);
        return redirect()->to($uri);
    }

    public function fetchPatches(Request $request)
    {

        $yes_or_nos = [
            ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
        ];
        $yes_or_nos_opt = [
            ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
        ];

        $page_data = [];
        $auction = null;

        if ($request->moduleName == 'edit-landlord-auction') {
            $auction = LandlordAuction::find($request->id);
            $page_data['auction'] = $auction;
            $page_data['title'] = "Edit Auction for Landlord";
            $page_data['property_types'] = PropertyType::orderBy('sort', 'asc')->get();
        }

        if ($request->moduleName == 'edit-landlord-agent-auction') {
            $auction = LandlordAgentAuction::find($request->id);
            $page_data['auction'] = $auction;
            $page_data['financings'] = Financing::orderBy('sort', 'asc')->get();
        }

        if ($request->moduleName == 'edit-property-auction') {
            $auction = PropertyAuction::find($request->id);
            $page_data['auction'] = $auction;
            $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        }

        // Pass $auction to the view, ensuring it's always available
        $html = view($request->patch, [
            'yes_or_nos' => $yes_or_nos,
            'yes_or_nos_opt' => $yes_or_nos_opt,
            'page_data' => $page_data,
            'auction' => $auction
        ])->render();


        return response()->json(['status' => true, 'html' => $html]);
    }
}
