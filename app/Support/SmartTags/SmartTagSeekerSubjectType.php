<?php

namespace App\Support\SmartTags;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Services\Listing\ListingWorkflowResolver;
use App\Support\Listing\ListingWorkflow;
use App\Support\ListingPreferences\SeekerRole;

/**
 * WHICH seeker record a Buyer/Tenant's Smart Tag preferences belong to.
 *
 * DELIBERATELY NOT `SmartTagListingType`. That enum is the single producer of
 * `bridge` / `seller_agent` / `landlord_agent`, and those values key
 * `smart_tag_evidence` and `smart_tag_assignments` — statements that a PROPERTY
 * has a characteristic. A seeker preference is the opposite claim: a person
 * wants one. Adding buyer/tenant cases there would let evidence be attached to a
 * seeker record through any code path that takes a listing type, and would widen
 * a type that several guards pin. So this is its own small enum, exactly as
 * ListingPreferences minted its own {@see SeekerRole} rather than overloading.
 *
 * FOUR SUBJECTS, TWO SHAPES.
 *
 *   • `buyer_criteria` / `tenant_criteria` — the legacy criteria records.
 *   • `buyer_offer_listing` / `tenant_offer_listing` — the Buyer/Tenant Offer
 *     Listings, which are what Stellar matching, Match Check and Matching V2
 *     actually read. Seeker preferences on criteria records alone could never
 *     reach a ranking path; that gap is why these two cases exist.
 *
 * The string values never collide, so `buyer_criteria` #100 and
 * `buyer_offer_listing` #100 are two unrelated rows under the table's
 * (subject_type, subject_id, tag_key) unique index.
 *
 * THE OFFER LISTING TABLES ARE SHARED WITH HIRE AGENT. `buyer_agent_auctions`
 * and `tenant_agent_auctions` hold Hire Agent rows too, and a Hire row is an
 * agent engagement, not a search. So {@see forModel()} answers an Offer Listing
 * case only for a row that {@see ListingWorkflowResolver} classifies
 * definitively as `offer_listing` — ambiguous, unclassified and Hire rows all
 * answer null, which the writer refuses and the reader reads as "no
 * preferences". {@see forModelClass()} is class-only on purpose: it serves the
 * deletion purge, where the row is already gone and removing its preferences
 * is correct whichever product it belonged to.
 */
enum SmartTagSeekerSubjectType: string
{
    case BuyerCriteria      = 'buyer_criteria';
    case TenantCriteria     = 'tenant_criteria';
    case BuyerOfferListing  = 'buyer_offer_listing';
    case TenantOfferListing = 'tenant_offer_listing';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    public function modelClass(): string
    {
        return match ($this) {
            self::BuyerCriteria      => BuyerCriteriaAuction::class,
            self::TenantCriteria     => TenantCriteriaAuction::class,
            self::BuyerOfferListing  => BuyerAgentAuction::class,
            self::TenantOfferListing => TenantAgentAuction::class,
        };
    }

    /**
     * The subject type for a model CLASS, with no workflow check.
     *
     * Exact class match, never instanceof. Used by the deletion purge, which is
     * handed a class-string and ids of rows that no longer exist — there is no
     * row left to classify, and removing the preferences of a deleted row is
     * correct whatever that row was. Every WRITE and READ goes through
     * {@see forModel()} instead.
     */
    public static function forModelClass(string $modelClass): ?self
    {
        $modelClass = ltrim($modelClass, '\\');

        foreach (self::cases() as $case) {
            if ($case->modelClass() === $modelClass) {
                return $case;
            }
        }

        return null;
    }

    /**
     * The subject type for a live record, or null when Smart Tag preferences do
     * not attach to it.
     *
     * For the Offer Listing cases the row must ALSO be definitively an Offer
     * Listing: the shared tables carry Hire Agent rows, and the resolver's
     * fail-closed answer on any doubt is exactly the posture a write needs.
     */
    public static function forModel(object $model): ?self
    {
        $type = self::forModelClass($model::class);

        if ($type === null || ! $type->isOfferListing()) {
            return $type;
        }

        return (new ListingWorkflowResolver())->matches($model, ListingWorkflow::OFFER_LISTING)
            ? $type
            : null;
    }

    public function isOfferListing(): bool
    {
        return $this === self::BuyerOfferListing || $this === self::TenantOfferListing;
    }

    /** Buyers act on sale contexts, tenants on lease contexts. */
    public function role(): SeekerRole
    {
        return match ($this) {
            self::BuyerCriteria, self::BuyerOfferListing   => SeekerRole::Buyer,
            self::TenantCriteria, self::TenantOfferListing => SeekerRole::Tenant,
        };
    }

    /**
     * The exact property-type vocabulary THIS subject's form stores, mapped to
     * a canonical context.
     *
     * One map per subject type rather than one per role, because the four forms
     * spell property types four different ways and the same string does not
     * mean the same thing across them: `Residential Property` is a SALE on the
     * Buyer Criteria form and a LEASE on both Tenant forms, and the Buyer Offer
     * Listing form says `Commercial` where Buyer Criteria says `Commercial
     * Property`. The subject type picks the map, so no caller can read a string
     * through the wrong one. Both the writer and the pickers use this.
     *
     * @return array<string, SmartTagContext>
     */
    public function propertyTypeContexts(): array
    {
        return match ($this) {
            self::BuyerCriteria      => SmartTagContextResolver::BUYER_CRITERIA,
            self::TenantCriteria     => SmartTagContextResolver::TENANT_CRITERIA,
            self::BuyerOfferListing  => SmartTagContextResolver::SALE_ROLE,
            self::TenantOfferListing => SmartTagContextResolver::LEASE_ROLE,
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
        return match ($this->role()) {
            SeekerRole::Buyer => [
                SmartTagContext::ResidentialSale,
                SmartTagContext::IncomeSale,
                SmartTagContext::CommercialSale,
                SmartTagContext::BusinessSale,
                SmartTagContext::LandSale,
            ],
            SeekerRole::Tenant => [
                SmartTagContext::ResidentialLease,
                SmartTagContext::CommercialLease,
            ],
        };
    }
}
