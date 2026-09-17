<?php

namespace App\Support\ListingPreferences;

use App\Support\SmartTags\SmartTagContext;

/**
 * Which side of the market a preference was expressed from: Buyer or Tenant.
 *
 * WHY THIS IS STORED AND NOT DERIVED. `users.user_type` is a single-valued enum
 * and a customer's row can change; deriving the role at read time would
 * retroactively reinterpret every preference they ever expressed. Worse, buying
 * and renting are different intents about different inventory — a Pass on a
 * rental must never suppress a purchase. So the role is captured at write time
 * and forms part of the uniqueness of a preference.
 *
 * It is NOT `users.user_type` narrowed: an agent acting for a client, or a
 * customer who later rents as well as buys, still resolves to exactly one role
 * per preference row. The default comes from user_type; the column is the truth.
 *
 * Deliberately only two cases. Seller and Landlord express preferences about
 * their OWN listing through Smart Tags (SURFACE_OWNER); they are not seekers.
 */
enum SeekerRole: string
{
    case Buyer  = 'buyer';
    case Tenant = 'tenant';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * The default role for a `users.user_type` value, or null when that user
     * type is not a seeker at all.
     *
     * A caller must treat null as "this account cannot express a seeker
     * preference", never as a reason to guess Buyer.
     */
    public static function forUserType(?string $userType): ?self
    {
        return match ($userType) {
            'buyer'  => self::Buyer,
            'tenant' => self::Tenant,
            default  => null,
        };
    }

    /**
     * Whether a listing context belongs to this role's side of the market.
     *
     * Buyers act on sale contexts, tenants on lease contexts — the same split
     * SmartTagContext already draws, reused rather than restated.
     */
    public function coversContext(SmartTagContext $context): bool
    {
        return match ($this) {
            self::Buyer  => $context->isSale(),
            self::Tenant => $context->isLease(),
        };
    }
}
