<?php

namespace App\Http\Controllers;

use App\Models\AgentService;
use App\Models\AgentServiceAuctionBid;
use App\Models\HireAgentLead;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerCriteriaAuctionBid;
use App\Models\ByaReviewLog;
use App\Models\City;
use App\Models\ListingCompatibilityScore;
use App\Models\Country;
use App\Models\County;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordAuctionBid;
use App\Models\PropertyAuction;
use App\Models\PropertyAuctionBid;
use App\Models\PropertyType;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\State;
use App\Models\AcceptedBidSummary;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCriteriaAuctionBid;
use App\Models\User;
use App\Services\ReferralLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $user  = Auth::user();
        $uid   = $user->id;
        $page_data['title'] = 'Dashboard';

        // ── Notifications (unchanged) ──────────────────────────────────────────
        $page_data['notifications'] = $user->unreadNotifications()
            ->whereIn('type', [
                'App\Notifications\BidAcceptedNotification',
                'App\Notifications\CounterBidAcceptedNotification',
                'App\Notifications\BidCounteredNotification',
                'App\Notifications\BidRejectedNotification',
                'App\Notifications\BidSubmittedNotification',
                'App\Notifications\CounterBidSubmittedNotification',
                'App\Notifications\Offers\OfferSubmittedNotification',
                'App\Notifications\Offers\OfferCounteredNotification',
                'App\Notifications\Offers\OfferAcceptedNotification',
                'App\Notifications\Offers\OfferRejectedNotification',
                'App\Notifications\Offers\OfferWithdrawnNotification',
            ])
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        // ── Listing counts per role — filtered to match each role's default page view ──
        // Tenant/Landlord/Seller/Buyer pages all default to type 2 (Live).
        // Buyer uses whereIn because its is_approved/is_sold columns store mixed values ('true'/'false'/'1'/'0').
        // Seller stores is_sold as the string 'false' (not boolean), matching its controller's own live filter.
        $page_data['listingCounts'] = [
            'tenant'   => TenantAgentAuction::where('user_id', $uid)->where('is_approved', true)->where('is_sold', false)->where('is_draft', false)->count(),
            'landlord' => LandlordAgentAuction::where('user_id', $uid)->where('is_approved', true)->where('is_sold', false)->where('is_draft', false)->count(),
            'buyer'    => BuyerAgentAuction::where('user_id', $uid)->whereIn('is_approved', ['true', '1', true])->whereIn('is_sold', ['false', '0', false])->where('is_draft', false)->count(),
            'seller'   => SellerAgentAuction::where('user_id', $uid)->where('is_approved', true)->where('is_sold', 'false')->where('is_draft', false)
                            ->whereDoesntHave('meta', fn($m) => $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing'))
                            ->whereDoesntHave('meta', fn($m) => $m->whereIn('meta_key', SellerOfferListingController::OFFER_LISTING_META_KEYS))
                            ->count(),
        ];

        // ── Pending bids on user's listings (awaiting owner decision) ──────────
        // Uses the exact DB field names per role; no bid_status accessor needed.
        $tenantAuctionIds   = TenantAgentAuction::where('user_id', $uid)->pluck('id');
        $landlordAuctionIds = LandlordAgentAuction::where('user_id', $uid)->pluck('id');
        $buyerAuctionIds    = BuyerAgentAuction::where('user_id', $uid)->pluck('id');
        $sellerAuctionIds = SellerAgentAuction::where('user_id', $uid)
            ->whereDoesntHave('meta', fn($m) => $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing'))
            ->whereDoesntHave('meta', fn($m) => $m->whereIn('meta_key', SellerOfferListingController::OFFER_LISTING_META_KEYS))
            ->pluck('id');

        $page_data['pendingBidCounts'] = [
            // Tenant: no accepted_date and no rejected_date = not yet decided
            'tenant' => $tenantAuctionIds->isNotEmpty()
                ? TenantAgentAuctionBid::whereIn('tenant_agent_auction_id', $tenantAuctionIds)
                    ->whereNull('accepted_date')
                    ->whereNull('rejected_date')
                    ->count()
                : 0,
            // Landlord: accepted=0 = undecided (integer column)
            'landlord' => $landlordAuctionIds->isNotEmpty()
                ? LandlordAgentAuctionBid::whereIn('landlord_agent_auction_id', $landlordAuctionIds)
                    ->where('accepted', 0)
                    ->count()
                : 0,
            // Buyer: accepted='0' = undecided
            'buyer' => $buyerAuctionIds->isNotEmpty()
                ? BuyerAgentAuctionBid::whereIn('buyer_agent_auction_id', $buyerAuctionIds)
                    ->where('accepted', '0')
                    ->count()
                : 0,
            // Seller: accepted='0' = undecided
            'seller' => $sellerAuctionIds->isNotEmpty()
                ? SellerAgentAuctionBid::whereIn('seller_agent_auction_id', $sellerAuctionIds)
                    ->where('accepted', '0')
                    ->count()
                : 0,
        ];

        // ── Accepted summaries awaiting the listing owner's signature ──────────
        $page_data['unsignedSummariesCount'] = AcceptedBidSummary::where('tenant_user_id', $uid)
            ->whereNull('tenant_signed_at')
            ->count();

        // ── "Your Hired Agent" dashboard block — all accepted summaries for this user ──
        // Fetch summaries with the agent user eager-loaded, then bulk-resolve listing
        // address/display-id from each role's table to avoid N+1 queries.
        $rawSummaries = AcceptedBidSummary::where('tenant_user_id', $uid)
            ->with('agent')
            ->orderBy('created_at', 'desc')
            ->get();

        $listingModelMap = [
            'tenant'   => TenantAgentAuction::class,
            'landlord' => LandlordAgentAuction::class,
            'buyer'    => BuyerAgentAuction::class,
            'seller'   => SellerAgentAuction::class,
        ];
        $listingCache = [];
        foreach ($rawSummaries->groupBy('listing_type') as $type => $group) {
            if (isset($listingModelMap[$type])) {
                $listingCache[$type] = $listingModelMap[$type]::whereIn('id', $group->pluck('listing_id'))
                    ->get(['id', 'address', 'listing_id', 'title'])
                    ->keyBy('id');
            }
        }
        $page_data['acceptedSummaries'] = $rawSummaries->map(function ($s) use ($listingCache) {
            $s->listingSnapshot = $listingCache[$s->listing_type][$s->listing_id] ?? null;
            return $s;
        });

        // ── Hire Agent Leads summary (agents only) ────────────────────────────
        if ($user->user_type === 'agent') {
            try {
                $page_data['hireAgentLeadSummary'] = [
                    'new'      => HireAgentLead::forAgent($uid)->where('status', 'new')->count(),
                    'pending'  => HireAgentLead::forAgent($uid)->where('status', 'pending')->count(),
                    'accepted' => HireAgentLead::forAgent($uid)->where('status', 'accepted')->count(),
                    'declined' => HireAgentLead::forAgent($uid)->where('status', 'declined')->count(),
                    'recent'   => HireAgentLead::forAgent($uid)
                        ->orderByDesc('created_at')
                        ->limit(3)
                        ->get(),
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Dashboard: could not load hire agent leads', [
                    'user_id' => $uid,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // ── Referral partner link + recent activity (agents only) ─────────────
        $page_data['referralLink']    = null;
        $page_data['recentReferrals'] = collect();

        if ($user->user_type === 'agent') {
            try {
                $page_data['referralLink'] = ReferralLinkService::getOrCreateForAgent($uid);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Dashboard: could not load referral link', [
                    'user_id' => $uid,
                    'error'   => $e->getMessage(),
                ]);
            }

            try {
                $page_data['recentReferrals'] = DB::table('accepted_bid_summaries')
                    ->where('accepted_bid_summaries.referring_agent_id', $uid)
                    ->leftJoin('users as ha', 'accepted_bid_summaries.agent_user_id', '=', 'ha.id')
                    ->select([
                        'accepted_bid_summaries.id',
                        'accepted_bid_summaries.listing_id',
                        'accepted_bid_summaries.listing_type',
                        'accepted_bid_summaries.referral_source_code',
                        'accepted_bid_summaries.referral_status',
                        'accepted_bid_summaries.created_at',
                        'accepted_bid_summaries.agent_user_id',
                        'ha.name as hired_agent_name',
                    ])
                    ->orderByDesc('accepted_bid_summaries.created_at')
                    ->limit(10)
                    ->get();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Dashboard: could not load recent referrals', [
                    'user_id' => $uid,
                    'error'   => $e->getMessage(),
                ]);
            }

            // Sum of admin-entered partner earnings where status is not void.
            // Returns 0.0 when no amounts have been entered yet.
            try {
                $page_data['pendingReferralEarnings'] = DB::table('accepted_bid_summaries')
                    ->where('referring_agent_id', $uid)
                    ->where(function ($q) {
                        $q->whereNull('referral_status')
                          ->orWhere('referral_status', '!=', 'void');
                    })
                    ->whereNotNull('partner_referral_amount')
                    ->sum('partner_referral_amount');
            } catch (\Throwable $e) {
                $page_data['pendingReferralEarnings'] = null;
            }
        }

        // ── Consumer Compatibility Report Links (beta + GA paths) ───────────────
        // Loads approved compatibility reports for this user's demand-side listings.
        // Filtered through ByaCompatibilityAccessResolver — handles kill switch,
        // feature flags, ownership, report approval, and GA rollout in one place.
        // Empty collection for agents, when kill switch is on, or when no reports qualify.
        $page_data['consumerBetaScores'] = collect();
        $byaKillSwitch  = config('bya_compatibility.kill_switch', true);
        $byaBetaEnabled = config('bya_consumer_beta.consumer_beta_enabled', false);
        $byaGaEnabled   = config('bya_compatibility.ga_enabled', false);

        if ($user->user_type !== 'agent' && !$byaKillSwitch && ($byaBetaEnabled || $byaGaEnabled)) {
            try {
                $buyerIds = DB::table('buyer_criteria_auctions')
                    ->where('user_id', $uid)
                    ->pluck('id');

                // tenant_criteria_auctions may not exist in all environments.
                $tenantIds = \Illuminate\Support\Facades\Schema::hasTable('tenant_criteria_auctions')
                    ? DB::table('tenant_criteria_auctions')->where('user_id', $uid)->pluck('id')
                    : collect();

                $scores = ListingCompatibilityScore::where(function ($q) use ($buyerIds, $tenantIds) {
                    $q->where(function ($q2) use ($buyerIds) {
                        $q2->where('demand_listing_type', 'buyer')
                           ->whereIn('demand_listing_id', $buyerIds);
                    })->orWhere(function ($q2) use ($tenantIds) {
                        $q2->where('demand_listing_type', 'tenant')
                           ->whereIn('demand_listing_id', $tenantIds);
                    });
                })->get(['id', 'demand_listing_type', 'demand_listing_id']);

                $approvedScoreIds = ByaReviewLog::whereIn('listing_compatibility_score_id', $scores->pluck('id'))
                    ->orderBy('created_at', 'desc')
                    ->get(['listing_compatibility_score_id', 'status'])
                    ->groupBy('listing_compatibility_score_id')
                    ->filter(function ($logs) {
                        $latest = $logs->first();
                        return $latest && in_array($latest->status, ['approved', 'approved_with_notes'], true);
                    })
                    ->keys()
                    ->all();

                $candidates = $scores->whereIn('id', $approvedScoreIds)->values();

                // Filter through the resolver — handles GA rollout bucket and allowlist.
                $resolver = app(\App\Services\Bya\ByaCompatibilityAccessResolver::class);
                $page_data['consumerBetaScores'] = $candidates->filter(function ($cbScore) use ($resolver, $user) {
                    return $resolver->resolve($user, $cbScore)['allowed'];
                })->values();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Dashboard: could not load consumer compatibility scores', [
                    'user_id' => $uid,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return view('dashboard', $page_data);
    }

    public function settings(Request $request)
    {
        $page_data['title'] = 'Profile Settings';
        $page_data['user'] = $user = Auth::user();
        $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        $page_data['countries'] = Country::whereId('231')->get();
        $page_data['cities'] = City::where('state_id', '3930')->get();
        $page_data['states'] = State::whereId('3930')->get();
        $page_data['counties'] = County::all();
        $page_data['services'] = AgentService::orderBy('sort', 'asc')->get();
        // dd($user->toArray());
        return view('settings', $page_data);
    }

    public function getStates(Request $request)
    {
        $country_id = $request->input('country_id');
        $states = State::where('country_id', $country_id)->get();
        return response()->json(['success' => true, 'states' => $states]);
    }

    public function getCities(Request $request)
    {
        $state_id = $request->input('state_id');
        $cities = City::where('state_id', $state_id)->get();
        return response()->json(['success' => true, 'cities' => $cities]);
    }


    public function saveSettings(Request $request)
    {
        $user = User::findOrFail(Auth::user()->id);

        // ── Account Information ─────────────────────────────────────────────
        if ($request->has('name'))  { $user->name  = $request->name; }
        if ($request->has('phone')) { $user->phone = $request->phone; }

        // ── Profile Details ─────────────────────────────────────────────────
        if ($request->has('first_name')) { $user->first_name = $request->first_name; }
        if ($request->has('last_name'))  { $user->last_name  = $request->last_name; }
        if ($request->has('bio'))        { $user->saveMeta('bio', $request->bio); }

        // ── Preferences ─────────────────────────────────────────────────────
        if ($request->has('preferred_contact_method')) {
            $user->saveMeta('preferred_contact_method', $request->preferred_contact_method);
        }
        if ($request->has('best_time_to_contact')) {
            $user->saveMeta('best_time_to_contact', $request->best_time_to_contact);
        }

        // ── Password change (requires current password verification) ────────
        // Only attempt if current_password is explicitly provided (≥6 chars prevents autofill noise)
        $newPass          = trim($request->input('password', ''));
        $confirmPass      = trim($request->input('confirm_password', ''));
        $currentPass      = trim($request->input('current_password', ''));
        $passwordAttempted = (strlen($currentPass) >= 6 && strlen($newPass) >= 6);
        $passwordChanged  = false;
        $passwordError    = null;

        if ($passwordAttempted) {
            if ($newPass !== $confirmPass) {
                $passwordError = 'The new passwords you entered do not match — your password was not updated.';
            } elseif (!Hash::check($currentPass, $user->password)) {
                $passwordError = 'The current password you entered is incorrect — your password was not updated.';
            } else {
                $user->password = Hash::make($newPass);
                $passwordChanged = true;
            }
        }

        // ── Profile photo: uploaded file only ───────────────────────────────
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $imageName = (string) Str::uuid() . '.' . $ext;
                    $file->move(public_path('images/avatar'), $imageName);
                    $user->avatar = $imageName;
                }
            }
        }

        $user->save();

        // Use 'profile_success' (not 'success') to avoid the Flasher package interceptor
        $flash = ['profile_success' => 'Profile settings updated successfully.'];
        if ($passwordChanged) {
            $flash['password_success'] = 'Your password was also updated successfully.';
        } elseif ($passwordError) {
            $flash['password_error'] = $passwordError;
        }

        return redirect()->back()->with($flash);
    }

    public function deleteAccount(Request $request)
    {
        // Server-side guard: require typed "DELETE" confirmation
        if (trim($request->input('delete_confirm', '')) !== 'DELETE') {
            return redirect()->back()->with('error', 'Account not deleted — you must type DELETE exactly to confirm.');
        }

        $user = User::findOrFail(Auth::user()->id);
        $user->is_deleted = 1;
        $user->save();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/')->with('success', 'Your account has been deactivated.');
    }

    public function myBids($type = "seller-property")
    {
        $page_data['title'] = 'My Bids';
        $page_data['type'] = $type;
        $page_data['user'] = $user = auth()->user();
        if ($type == 'seller-property') {
            $page_data['bids'] = PropertyAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.seller_property', $page_data);
        } else if ($type == 'landlord-property') {
            $page_data['bids'] = $bid = LandlordAuctionBid::where('user_id', $user->id)->with('auction', function ($qry) {
                $qry->with('bids');
            })->get();
            // dd($bid->toArray());
            return view('my-bids.landlord_property', $page_data);
        } else if ($type == 'buyer-criteria') {
            $page_data['bids'] = $bid = BuyerCriteriaAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.buyer-criteria', $page_data);
        } else if ($type == 'tenant-criteria') {
            $page_data['bids'] = $bid = TenantCriteriaAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.tenant-criteria', $page_data);
        } else if ($type == 'agent-service') {
            $page_data['bids'] = $bid = AgentServiceAuctionBid::where('user_id', $user->id)->with('auction')->get();
            return view('my-bids.agent-service', $page_data);
        } else if ($type == 'buyer-agent') {
            $page_data['bids'] = BuyerAgentAuctionBid::where('user_id', $user->id)
                ->with(['auction', 'meta', 'counterTerms', 'acceptedBidSummary'])
                ->get();
            return view('my-bids.buyer-agent', $page_data);
        } else if ($type == 'seller-agent') {
            $page_data['bids'] = SellerAgentAuctionBid::where('user_id', $user->id)
                ->with(['auction', 'meta', 'counterTerms', 'acceptedBidSummary'])
                ->get();
            return view('my-bids.seller-agent', $page_data);
        } else if ($type == 'landlord-agent') {
            $page_data['bids'] = LandlordAgentAuctionBid::where('user_id', $user->id)
                ->with(['auction', 'meta', 'counterTerms', 'acceptedBidSummary'])
                ->get();
            return view('my-bids.landlord-agent', $page_data);
        } else if ($type == 'tenant-agent') {
            $page_data['bids'] = TenantAgentAuctionBid::where('user_id', $user->id)
                ->with(['auction', 'meta', 'counterTerms', 'acceptedBidSummary'])
                ->get();
            return view('my-bids.tenant-agent', $page_data);
        } else if ($type == 'agent-bids') {
            $userAuctions = \App\Models\TenantAgentAuction::where('user_id', $user->id)->pluck('id');
            $page_data['pendingAgentBids'] = TenantAgentAuctionBid::whereIn('tenant_agent_auction_id', $userAuctions)
                ->with(['user', 'auction', 'meta', 'counterTerms', 'acceptedBidSummary'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function($bid) {
                    return in_array($bid->bid_status, ['Active', 'Countered']);
                });
            return view('my-bids.agent-bids', $page_data);
        } else if ($type == 'hire-landlord-agent-bids') {
            $userAuctions = LandlordAgentAuction::where('user_id', $user->id)->pluck('id');
            $page_data['pendingAgentBids'] = LandlordAgentAuctionBid::whereIn('landlord_agent_auction_id', $userAuctions)
                ->with(['user', 'auction', 'meta', 'acceptedBidSummary'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function($bid) {
                    return in_array($bid->bid_status, ['Active', 'Countered']);
                });
            return view('my-bids.hire-landlord-agent-bids', $page_data);
        } else if ($type == 'hire-buyer-agent-bids') {
            $userAuctions = BuyerAgentAuction::where('user_id', $user->id)->pluck('id');
            $page_data['pendingAgentBids'] = BuyerAgentAuctionBid::whereIn('buyer_agent_auction_id', $userAuctions)
                ->with(['user', 'auction', 'meta', 'acceptedBidSummary'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function($bid) {
                    return in_array($bid->bid_status, ['Active', 'Countered']);
                });
            return view('my-bids.hire-buyer-agent-bids', $page_data);
        } else if ($type == 'hire-seller-agent-bids') {
            $userAuctions = SellerAgentAuction::where('user_id', $user->id)->pluck('id');
            $page_data['pendingAgentBids'] = SellerAgentAuctionBid::whereIn('seller_agent_auction_id', $userAuctions)
                ->with(['user', 'auction', 'meta', 'acceptedBidSummary'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function($bid) {
                    return in_array($bid->bid_status, ['Active', 'Countered']);
                });
            return view('my-bids.hire-seller-agent-bids', $page_data);
        } else {
            abort(404);
        }
    }

    public function myAuctions(Request $request)
    {
        $page_data['title'] = 'My Auctions';
        $page_data['type'] = $type = $request->type ?? "2";

        $pendingAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'sold' => false]);
        $pendingApprovalAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => false, 'sold' => false]);
        $liveAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => false]);
        $soldAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => true, 'is_paid' => true]);
        $pendingPaymentAuctions = PropertyAuction::where(['user_id' => Auth::user()->id, 'is_approved' => true, 'sold' => true, 'is_paid' => false]);

        if ($type == "0") {
            $auctions = $pendingAuctions->get();
        } else if ($type == "1") {
            $auctions = $pendingApprovalAuctions->get();
        } else if ($type == "2") {
            $auctions = $liveAuctions->get();
        } else if ($type == '3') {
            $auctions = $soldAuctions->get();
        } else if ($type == "4") {
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

        // dd($page_data['count_my_auctions']);
        return view('my-auctions', $page_data);
    }

    public function myFriends()
    {
        $page_data['title'] = 'My Friends';
        return view('my-friends', $page_data);
    }

    public function qrSettings(Request $request)
    {
        $page_data['title'] = "QR Code Settings";
        return view('qr.settings', $page_data);
    }

    public function update_qr(Request $request)
    {
        $user = User::findOrFail(Auth::id());
        if ($user->saveMeta('qr', $request->uri)) {
            return redirect()->back()->with('success', "QR Code settings updated successfully");
        } else {
            return redirect()->back()->with('error', "Unable to update QR Code settings");
        }
    }

    public function allListings()
    {
        $uid = Auth::id();
        $offerListingMetaKeys = SellerOfferListingController::OFFER_LISTING_META_KEYS;

        // Hire Seller's Agent listings — exclude Seller Offer Listings (two-tier detection).
        $sellerHireListings = SellerAgentAuction::where('user_id', $uid)
            ->whereDoesntHave('meta', function ($m) { $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing'); })
            ->whereDoesntHave('meta', function ($m) use ($offerListingMetaKeys) { $m->whereIn('meta_key', $offerListingMetaKeys); })
            ->withCount('bids')->latest()->get();

        // Seller Offer Listings — the other half of the SellerAgentAuction table.
        $sellerOfferListings = SellerAgentAuction::where('user_id', $uid)
            ->where(function ($q) use ($offerListingMetaKeys) {
                $q->whereHas('meta', function ($m) { $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing'); })
                  ->orWhereHas('meta', function ($m) use ($offerListingMetaKeys) { $m->whereIn('meta_key', $offerListingMetaKeys); });
            })
            ->withCount('bids')->latest()->get();

        return view('myListings', [
            'title'              => 'My Listings',
            'tenantListings'     => TenantAgentAuction::where('user_id', $uid)->withCount('bids')->latest()->get(),
            'landlordListings'   => LandlordAgentAuction::where('user_id', $uid)->withCount('bids')->latest()->get(),
            'buyerListings'      => BuyerAgentAuction::where('user_id', $uid)->withCount('bids')->latest()->get(),
            'sellerHireListings' => $sellerHireListings,
            'sellerOfferListings'=> $sellerOfferListings,
        ]);
    }
}
