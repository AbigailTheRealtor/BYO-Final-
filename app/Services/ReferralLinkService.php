<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ReferralLinkService
 *
 * Phase 3 — Referral link generation and retrieval.
 *
 * Responsibilities:
 *  • Get or create a single active referral link row per agent.
 *  • Generate short, human-shareable, collision-free codes.
 *  • Build the full shareable URL for a given code.
 *
 * Out of scope here:
 *  • Signup / listing / hire attribution (later phases)
 *  • Dashboard / admin UI
 *  • Model relationships
 */
class ReferralLinkService
{
    /**
     * Return an existing active referral link for the agent, or create one.
     *
     * Returns an array with: id, agent_id, code, is_active, url.
     */
    public static function getOrCreateForAgent(int $agentId): array
    {
        // Look for an existing active row first.
        $existing = DB::table('agent_referral_links')
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return [
                'id'        => $existing->id,
                'agent_id'  => $existing->agent_id,
                'code'      => $existing->code,
                'is_active' => $existing->is_active,
                'url'       => static::buildUrl($existing->code),
            ];
        }

        // None exists — generate a unique code and insert.
        $code = static::generateUniqueCode($agentId);
        $now  = now();

        $id = DB::table('agent_referral_links')->insertGetId([
            'agent_id'      => $agentId,
            'code'          => $code,
            'slug'          => $code,
            'is_active'     => true,
            'click_count'   => 0,
            'signup_count'  => 0,
            'listing_count' => 0,
            'hire_count'    => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return [
            'id'        => $id,
            'agent_id'  => $agentId,
            'code'      => $code,
            'is_active' => true,
            'url'       => static::buildUrl($code),
        ];
    }

    /**
     * Generate a unique, short, human-shareable referral code.
     *
     * Format: agt{agentId}-{6 random alphanumeric chars}
     * Example: agt11-k7x2qm
     *
     * Retries on collision (virtually never needed in practice).
     */
    public static function generateUniqueCode(int $agentId): string
    {
        do {
            $code = 'agt' . $agentId . '-' . Str::lower(Str::random(6));
        } while (
            DB::table('agent_referral_links')->where('code', $code)->exists()
        );

        return $code;
    }

    /**
     * Build the full absolute shareable URL for a given code.
     *
     * Uses the referral.capture route — /invite/{code}.
     */
    public static function buildUrl(string $code): string
    {
        return route('referral.capture', ['code' => $code]);
    }
}
