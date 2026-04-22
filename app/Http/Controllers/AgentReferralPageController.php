<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ReferralLinkService;

/**
 * AgentReferralPageController
 *
 * Serves GET /agent/my-referrals — a read-only, agent-facing page that
 * surfaces every referral_visit, referred signup, attributed listing, and
 * accepted-bid-summary for the current agent in a single filterable table.
 *
 * SAFE: reads only. No attribution logic, no counter mutation, no bid flows.
 */
class AgentReferralPageController extends Controller
{
    private const VALID_STAGES = ['all', 'clicks', 'signups', 'listings', 'hires'];

    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->user_type !== 'agent') {
            abort(403, 'Agent access only.');
        }

        $uid   = $user->id;
        $stage = $request->get('stage', 'all');

        if (!in_array($stage, self::VALID_STAGES, true)) {
            $stage = 'all';
        }

        // ── Summary counters (from stored link row — authoritative) ──────────
        $link = null;
        try {
            $linkArr = ReferralLinkService::getOrCreateForAgent($uid);
            $link    = $linkArr ? (object) $linkArr : null;
        } catch (\Throwable $e) {
            Log::error('AgentReferralPage: referral link load failed', [
                'user_id' => $uid,
                'error'   => $e->getMessage(),
            ]);
        }

        // ── Activity rows ────────────────────────────────────────────────────
        $rows = collect();

        // ── CLICKS: referral_visits ──────────────────────────────────────────
        if (in_array($stage, ['all', 'clicks'], true)) {
            try {
                $clicks = DB::table('referral_visits')
                    ->where('referral_visits.agent_id', $uid)
                    ->leftJoin('users', 'referral_visits.visitor_user_id', '=', 'users.id')
                    ->select([
                        'referral_visits.created_at',
                        'referral_visits.ip_address',
                        'users.name  as user_name',
                        'users.email as user_email',
                    ])
                    ->orderByDesc('referral_visits.created_at')
                    ->get()
                    ->map(fn ($r) => (object) [
                        'date'         => $r->created_at,
                        'person_name'  => $r->user_name  ?? null,
                        'email'        => $r->user_email ?? null,
                        'stage'        => 'click',
                        'listing_id'   => null,
                        'listing_type' => null,
                        'status'       => null,
                        'note'         => $r->ip_address,   // shown as "Source IP" when no user
                    ]);

                $rows = $rows->merge($clicks);
            } catch (\Throwable $e) {
                Log::error('AgentReferralPage: clicks query failed', [
                    'uid' => $uid, 'err' => $e->getMessage(),
                ]);
            }
        }

        // ── SIGNUPS: users.referred_by_agent_id ──────────────────────────────
        if (in_array($stage, ['all', 'signups'], true)) {
            try {
                $signups = DB::table('users')
                    ->where('referred_by_agent_id', $uid)
                    ->select(['name', 'email', 'user_type', 'created_at'])
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(fn ($r) => (object) [
                        'date'         => $r->created_at,
                        'person_name'  => $r->name,
                        'email'        => $r->email,
                        'stage'        => 'signup',
                        'listing_id'   => null,
                        'listing_type' => null,
                        'status'       => ucfirst($r->user_type ?? ''),
                        'note'         => null,
                    ]);

                $rows = $rows->merge($signups);
            } catch (\Throwable $e) {
                Log::error('AgentReferralPage: signups query failed', [
                    'uid' => $uid, 'err' => $e->getMessage(),
                ]);
            }
        }

        // ── LISTINGS: all 5 listing tables with referring_agent_id ───────────
        if (in_array($stage, ['all', 'listings'], true)) {
            $listingTables = [
                'seller_agent_auctions'    => 'Seller',
                'buyer_agent_auctions'     => 'Buyer',
                'landlord_agent_auctions'  => 'Landlord',
                'tenant_agent_auctions'    => 'Tenant',
                'tenant_criteria_auctions' => 'Tenant Criteria',
            ];

            foreach ($listingTables as $table => $typeLabel) {
                try {
                    $listingRows = DB::table($table)
                        ->where("{$table}.referring_agent_id", $uid)
                        ->leftJoin('users', "{$table}.user_id", '=', 'users.id')
                        ->select([
                            DB::raw("{$table}.id as listing_id"),
                            "{$table}.created_at",
                            'users.name  as user_name',
                            'users.email as user_email',
                        ])
                        ->orderByDesc("{$table}.created_at")
                        ->get()
                        ->map(fn ($r) => (object) [
                            'date'         => $r->created_at,
                            'person_name'  => $r->user_name  ?? null,
                            'email'        => $r->user_email ?? null,
                            'stage'        => 'listing',
                            'listing_id'   => $r->listing_id,
                            'listing_type' => $typeLabel,
                            'status'       => 'Published',
                            'note'         => null,
                        ]);

                    $rows = $rows->merge($listingRows);
                } catch (\Throwable $e) {
                    Log::error("AgentReferralPage: {$table} query failed", [
                        'uid' => $uid, 'err' => $e->getMessage(),
                    ]);
                }
            }
        }

        // ── HIRES: accepted_bid_summaries.referring_agent_id ─────────────────
        if (in_array($stage, ['all', 'hires'], true)) {
            try {
                $hires = DB::table('accepted_bid_summaries')
                    ->where('accepted_bid_summaries.referring_agent_id', $uid)
                    ->leftJoin('users as client', 'accepted_bid_summaries.tenant_user_id', '=', 'client.id')
                    ->leftJoin('users as hired',  'accepted_bid_summaries.agent_user_id',  '=', 'hired.id')
                    ->select([
                        'accepted_bid_summaries.listing_id',
                        'accepted_bid_summaries.listing_type',
                        'accepted_bid_summaries.referral_status',
                        'accepted_bid_summaries.created_at',
                        'client.name  as client_name',
                        'client.email as client_email',
                        'hired.name   as hired_agent_name',
                    ])
                    ->orderByDesc('accepted_bid_summaries.created_at')
                    ->get()
                    ->map(fn ($r) => (object) [
                        'date'         => $r->created_at,
                        'person_name'  => $r->client_name  ?? null,
                        'email'        => $r->client_email ?? null,
                        'stage'        => 'hire',
                        'listing_id'   => $r->listing_id,
                        'listing_type' => ucfirst($r->listing_type ?? ''),
                        'status'       => $r->referral_status,
                        'note'         => $r->hired_agent_name,  // shown as "Hired Agent"
                    ]);

                $rows = $rows->merge($hires);
            } catch (\Throwable $e) {
                Log::error('AgentReferralPage: hires query failed', [
                    'uid' => $uid, 'err' => $e->getMessage(),
                ]);
            }
        }

        // Sort combined set newest-first
        $rows = $rows->sortByDesc('date')->values();

        return view('agent.my_referrals', [
            'link'  => $link,
            'rows'  => $rows,
            'stage' => $stage,
            'user'  => $user,
        ]);
    }
}
