<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * The closed role vocabulary a chain membership may carry.
 *
 * STOREFRONT  the chain's own store (a Publix supermarket, a Shell station).
 * DEPARTMENT  something inside a store (Publix pharmacy, a Walmart grocery department). Credible
 *             identity, but never a confirmed storefront on its own: {@see self::storefrontStatus()}.
 * FUEL        a fuel representation of a c-store chain (7-Eleven, Wawa, RaceTrac, Speedway).
 */
final class ChainRole
{
    public const STOREFRONT = 'storefront';
    public const DEPARTMENT = 'department';
    public const FUEL = 'fuel';

    /** Storefront status of a single membership, as reported to later consumers. */
    public const STATUS_STOREFRONT = 'storefront';
    public const STATUS_STOREFRONT_UNCONFIRMED = 'storefront_unconfirmed';
    public const STATUS_FUEL = 'fuel';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::STOREFRONT, self::DEPARTMENT, self::FUEL];
    }

    public static function storefrontStatus(string $role): string
    {
        switch ($role) {
            case self::STOREFRONT:
                return self::STATUS_STOREFRONT;
            case self::DEPARTMENT:
                return self::STATUS_STOREFRONT_UNCONFIRMED;
            case self::FUEL:
                return self::STATUS_FUEL;
        }

        throw new \InvalidArgumentException("unknown chain role: {$role}");
    }
}
