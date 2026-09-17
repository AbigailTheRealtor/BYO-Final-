<?php

namespace App\Support\ListingPreferences;

use App\Support\SmartTags\SmartTagContext;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * ONE answer to "may this viewer express a preference on this listing?", asked
 * by the renderer and by the write path alike.
 *
 * WHY ONE CLASS FOR BOTH. If the control renders under one rule and the write
 * refuses under another, a customer clicks Save and is told no — which is worse
 * than never offering it. So the component and the controller ask exactly this,
 * and a refusal reason is available for the render to act on.
 *
 * FAIL-CLOSED. Every unknown resolves to "no": a missing config reads as
 * disabled, a null user is a guest, an unrecognised user_type is not a seeker.
 *
 * GUESTS ARE NOT A PERSISTENCE CASE. `forGuest()` is deliberately distinct from
 * `refused()` — the surface may still show the control and route the visitor
 * through the existing login flow, but no anonymous row is ever created.
 */
final class ListingPreferenceAvailability
{
    public const ALLOWED             = 'allowed';
    public const REASON_DISABLED     = 'feature_disabled';
    public const REASON_GUEST        = 'not_authenticated';
    public const REASON_NOT_A_SEEKER = 'not_a_buyer_or_tenant';
    public const REASON_WRONG_MARKET = 'seeker_role_does_not_cover_this_listing';

    private function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly ?SeekerRole $seekerRole,
    ) {
    }

    /**
     * The master gate. Phase 1 shipped this flag inert; Phase 2 is the first
     * thing that reads it, and it is read here alone so there is one answer.
     */
    public static function featureEnabled(): bool
    {
        return config('listing_preferences.enabled') === true;
    }

    /** Guest capture is a decided product position, not a dial: never persist. */
    public static function guestCaptureEnabled(): bool
    {
        return config('listing_preferences.guest_capture_enabled') === true;
    }

    /**
     * @param SmartTagContext|null $context the listing's resolved context, when it could be resolved
     */
    public static function for(?Authenticatable $user, ?SmartTagContext $context): self
    {
        if (! self::featureEnabled()) {
            return new self(false, self::REASON_DISABLED, null);
        }

        if ($user === null) {
            return new self(false, self::REASON_GUEST, null);
        }

        $role = SeekerRole::forUserType(is_string($user->user_type ?? null) ? $user->user_type : null);

        if ($role === null) {
            // Never guess Buyer. An agent, seller, landlord or admin account is
            // not a seeker, and inventing a role for them would file their
            // opinion under a market they are not shopping in.
            return new self(false, self::REASON_NOT_A_SEEKER, null);
        }

        // A buyer does not express rental preferences and a tenant does not
        // express purchase preferences. Where the listing's context IS known,
        // the mismatch is refused HERE so the control is never rendered to
        // someone whose click would be rejected. Where it is unknown, the
        // coverage question is unanswerable and is not invented.
        if ($context !== null && ! $role->coversContext($context)) {
            return new self(false, self::REASON_WRONG_MARKET, $role);
        }

        return new self(true, self::ALLOWED, $role);
    }

    /** True when the only thing missing is a signed-in account. */
    public function isGuest(): bool
    {
        return $this->reason === self::REASON_GUEST;
    }

    public function isDisabled(): bool
    {
        return $this->reason === self::REASON_DISABLED;
    }

    /**
     * Should the surface render the control at all?
     *
     * Yes when allowed, and yes for a guest — who gets a control that routes
     * through the existing login flow. No when the feature is off, and no for
     * an account that is not a seeker in this market, because there is nothing
     * signing in would change.
     */
    public function shouldRender(): bool
    {
        return $this->allowed || $this->isGuest();
    }
}
