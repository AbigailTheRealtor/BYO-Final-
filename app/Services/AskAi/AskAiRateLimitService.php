<?php

namespace App\Services\AskAi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AskAiRateLimitService
{
    private const SHARED_IP_HOURLY_LIMIT = 30;
    private const GUEST_IP_HOURLY_LIMIT  = 5;
    private const USER_HOURLY_LIMIT      = 20;
    private const ADMIN_DAILY_LIMIT      = 100;
    private const LISTING_HOURLY_LIMIT   = 10;

    private const HOURLY_DECAY = 3600;
    private const DAILY_DECAY  = 86400;

    /**
     * Evaluate all rate limit dimensions in order:
     *   1. Shared IP (30/hr)
     *   2. Guest IP (5/hr) OR logged-in user (20/hr) OR admin (100/day)
     *   3. Per-listing (10/hr)
     *
     * Returns null when all limits pass (and increments all counters).
     * Returns ['limit_type' => string, 'retry_after' => int] on the first exceeded limit.
     */
    public function check(Request $request, string $listingType, int $listingId): ?array
    {
        $hashedIp    = hash('sha256', $request->ip());
        $user        = $request->user();
        $sharedIpKey = "ask_ai:ip:{$hashedIp}:hourly";
        $listingKey  = "ask_ai:listing:{$listingType}:{$listingId}:hourly";

        // 1. Shared IP limit
        if (RateLimiter::tooManyAttempts($sharedIpKey, self::SHARED_IP_HOURLY_LIMIT)) {
            return [
                'limit_type'  => 'ip_shared_hourly',
                'retry_after' => RateLimiter::availableIn($sharedIpKey),
            ];
        }

        // 2. Identity-based limit
        if ($user === null) {
            $identityKey   = "ask_ai:guest:{$hashedIp}:hourly";
            $identityLimit = self::GUEST_IP_HOURLY_LIMIT;
            $identityDecay = self::HOURLY_DECAY;
            $limitType     = 'guest_ip_hourly';
        } elseif ($user->user_type === 'admin') {
            $identityKey   = "ask_ai:admin:{$user->id}:daily";
            $identityLimit = self::ADMIN_DAILY_LIMIT;
            $identityDecay = self::DAILY_DECAY;
            $limitType     = 'admin_daily';
        } else {
            $identityKey   = "ask_ai:user:{$user->id}:hourly";
            $identityLimit = self::USER_HOURLY_LIMIT;
            $identityDecay = self::HOURLY_DECAY;
            $limitType     = 'user_hourly';
        }

        if (RateLimiter::tooManyAttempts($identityKey, $identityLimit)) {
            return [
                'limit_type'  => $limitType,
                'retry_after' => RateLimiter::availableIn($identityKey),
            ];
        }

        // 3. Per-listing limit
        if (RateLimiter::tooManyAttempts($listingKey, self::LISTING_HOURLY_LIMIT)) {
            return [
                'limit_type'  => 'listing_hourly',
                'retry_after' => RateLimiter::availableIn($listingKey),
            ];
        }

        // All limits passed — increment all counters
        RateLimiter::hit($sharedIpKey, self::HOURLY_DECAY);
        RateLimiter::hit($identityKey, $identityDecay);
        RateLimiter::hit($listingKey, self::HOURLY_DECAY);

        return null;
    }
}
