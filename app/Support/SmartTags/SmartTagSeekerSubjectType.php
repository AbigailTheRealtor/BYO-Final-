<?php

namespace App\Support\SmartTags;

use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use App\Support\ListingPreferences\SeekerRole;

/**
 * WHICH criteria record a seeker's Smart Tag preferences belong to.
 *
 * DELIBERATELY NOT `SmartTagListingType`. That enum is the single producer of
 * `bridge` / `seller_agent` / `landlord_agent`, and those values key
 * `smart_tag_evidence` and `smart_tag_assignments` — statements that a PROPERTY
 * has a characteristic. A seeker preference is the opposite claim: a person
 * wants one. Adding buyer/tenant cases there would let evidence be attached to a
 * criteria row through any code path that takes a listing type, and would widen
 * a type that several guards pin. So this is its own small enum, exactly as
 * ListingPreferences minted its own {@see SeekerRole} rather than overloading.
 *
 * Only CRITERIA records. Buyer/Tenant Offer Listings are a different workflow
 * and are not a seeker's saved search.
 */
enum SmartTagSeekerSubjectType: string
{
    case BuyerCriteria  = 'buyer_criteria';
    case TenantCriteria = 'tenant_criteria';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    public function modelClass(): string
    {
        return match ($this) {
            self::BuyerCriteria  => BuyerCriteriaAuction::class,
            self::TenantCriteria => TenantCriteriaAuction::class,
        };
    }

    public static function forModelClass(string $modelClass): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->modelClass() === $modelClass) {
                return $case;
            }
        }

        return null;
    }

    public static function forModel(object $model): ?self
    {
        return self::forModelClass($model::class);
    }

    /** Buyers act on sale contexts, tenants on lease contexts. */
    public function role(): SeekerRole
    {
        return match ($this) {
            self::BuyerCriteria  => SeekerRole::Buyer,
            self::TenantCriteria => SeekerRole::Tenant,
        };
    }

    /**
     * The contexts a subject of this type can ever resolve to. Used to fail
     * closed when a stored context and a subject type disagree.
     *
     * @return list<SmartTagContext>
     */
    public function possibleContexts(): array
    {
        return match ($this) {
            self::BuyerCriteria => [
                SmartTagContext::ResidentialSale,
                SmartTagContext::IncomeSale,
                SmartTagContext::CommercialSale,
                SmartTagContext::BusinessSale,
                SmartTagContext::LandSale,
            ],
            self::TenantCriteria => [
                SmartTagContext::ResidentialLease,
                SmartTagContext::CommercialLease,
            ],
        };
    }
}
