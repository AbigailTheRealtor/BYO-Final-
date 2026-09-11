<?php

namespace App\Services\ListingImport\Sync;

use App\Services\ListingImport\MlsFieldMap;
use App\Support\Listing\PropertyTypeVocabulary;

/**
 * The source-of-truth boundary: which listing values Stellar owns, and which
 * belong to the person who wrote them.
 *
 * THIS IS AN INTERSECTION, NOT A DENY-LIST.
 * -----------------------------------------
 * A canonical fact reaches a listing field during sync by being NAMED in
 * {@see MlsFieldMap} for that role AND surviving the exclusions below. It can
 * never arrive by failing to appear on a list of forbidden things. That is the
 * same shape as {@see \App\Support\HireAgent\CompatibilityPreferencePolicy} and
 * {@see \App\Support\OfferListing\LandlordScreeningPolicy}, and for the same
 * reason: a deny-list fails open, and the day the feed adds a field is the day
 * that matters.
 *
 * WHY SYNC NEEDS ITS OWN PRECEDENCE AT ALL
 * ----------------------------------------
 * The IMPORT path's rule, pinned by MlsReimportBehaviourTest, is "editable
 * fields — the user wins": a re-import never overwrites a populated Create Offer
 * field, because somebody who corrected a bedroom count the feed got wrong must
 * not have that reverted by pressing a button.
 *
 * The owner's live-sync contract states the opposite for MLS-sourced FACTS:
 * Stellar is authoritative for beds, baths, square footage, year built, property
 * type, features, HOA and tax information, and those must follow the feed
 * without a manual re-import.
 *
 * Both rules stand, because they are about different acts. Pressing "import"
 * again is a user action on a draft they are editing. A live sync is the
 * platform keeping an MLS-linked listing honest about the MLS. So the MAPPING is
 * shared — one {@see \App\Services\ListingImport\Sync\MlsFactProjection}, one
 * MlsFieldMap, one vocabulary translation — and only the precedence differs.
 * There is no second mapper.
 *
 * WHAT IS NEVER SYNCED, AND WHY EACH ONE
 * --------------------------------------
 * The exclusions are not a tidy-up. Each is a field whose NAME matches an MLS
 * fact and whose MEANING does not, which is the specific mistake CLAUDE.md warns
 * against and which sync would otherwise commit on a schedule.
 */
final class MlsSyncFieldPolicy
{
    /**
     * Canonical import keys that sync must never write, whatever MlsFieldMap
     * says, keyed to the reason.
     *
     * The first four are the seller's own INTENT wearing an MLS field's name.
     * `minimum_cap_rate` and `minimum_annual_net_income` are the figure a seller
     * will ACCEPT, not the figure the building earns; overwriting them with the
     * property's actual performance silently rewrites the seller's floor, and
     * would do it every six hours.
     *
     * `garage_parking_spaces` is a Yes/No control on the form, not a count, so a
     * feed integer written into it renders as nothing and destroys the answer.
     * `unit_number` is the ADDRESS's unit ("17208"), not a building's unit count,
     * and the two are one word apart.
     *
     * @var array<string,string>
     */
    private const NEVER_SYNC = [
        'cap_rate'                   => "seller's minimum acceptable cap rate, not the property's actual",
        'net_operating_income'       => "seller's minimum acceptable NOI, not the property's actual",
        'annual_net_income_business' => "seller's minimum acceptable net income, not the business's actual",
        'garage_spaces'              => 'target is a Yes/No control, not a count',
        'parking_spaces_count'       => 'target is a Yes/No control, not a count',
        'number_of_units'            => "target is the address's unit number, not a building's unit count",

        // ── The address block, deferred rather than excluded on principle ────
        //
        // An address IS an MLS fact and Stellar correcting one is real. But an
        // address change cascades: PropertyAddress::coordinateLookupLine() is the
        // corpus and cache key, the coordinate ladder re-resolves, Location DNA's
        // POI tiles and flood/school lookups are keyed off the point, and
        // property_location_dna carries geocode provenance that would then
        // describe a different place. None of that is in Phase 1A's scope, and a
        // listing whose address moved while its coordinate stayed is worse than
        // one whose address did not move at all.
        //
        // Coordinates are excluded alongside for the same reason and one more:
        // writing a latitude straight from the feed bypasses
        // CoordinatePrecision entirely, which is the enum that exists to stop a
        // coarse point being measured from.
        'address'   => 'address changes cascade into the coordinate ladder and Location DNA — Phase 1B',
        'city'      => 'part of the address block — Phase 1B',
        'state'     => 'part of the address block — Phase 1B',
        'zip'       => 'part of the address block — Phase 1B',
        'county'    => 'part of the address block — Phase 1B',
        'latitude'  => 'bypasses CoordinatePrecision; belongs to the coordinate ladder — Phase 1B',
        'longitude' => 'bypasses CoordinatePrecision; belongs to the coordinate ladder — Phase 1B',
    ];

    /**
     * Listing meta keys sync must never touch, whatever route reaches them.
     *
     * The belt to NEVER_SYNC's braces. NEVER_SYNC filters canonical facts before
     * they are projected; this filters the resulting meta keys, so a future
     * MlsFieldMap entry that happens to point at a BYO term cannot slip through
     * by arriving under a canonical key nobody thought to exclude.
     *
     * Everything the owner's "BYO DATA MUST REMAIN USER-OWNED" clause names, in
     * the keys this codebase actually stores them under.
     *
     * @var list<string>
     */
    private const PROTECTED_META_KEYS = [
        // Listing method / product identity
        'listing_method', 'service_type', 'auction_type', 'auction_time', 'workflow_type',

        // Your Terms and the seller/landlord's own intent
        'contract_terms', 'important_info', 'additional_services', 'description_ideal_agent',
        'seller_motivation', 'price_firmness', 'reason_for_selling',
        'offered_financing', 'financings', 'concessions', 'seller_concessions',

        // Bidding configuration and the canonical window
        'starting_price', 'reserve_price', 'buy_now_price', 'reserve_price_public',
        'starting_rent', 'reserve_rent', 'lease_now_price',
        'bidding_starts_at', 'bidding_ends_at', 'expiration_date',

        // Platform lifecycle the user or the platform owns
        'listing_status', 'is_draft', 'is_approved', 'is_sold', 'listing_date',

        // Offer requirements and platform instructions
        'offer_requirements', 'showing_instructions', 'special_instructions',
        'compatibility_preferences',

        // The gallery is reconciled by MlsListingGallerySync, which owns the
        // user-upload rule. Sync must never write this key directly.
        'property_photos', 'property_photos_order_customized',
    ];

    /**
     * The only roles that can have an MLS-linked listing at all.
     *
     * STRUCTURAL, NOT A ROLLOUT DIAL — the same reasoning as
     * `mls_direct_import.prefill_roles`. A Buyer or Tenant listing describes
     * search criteria across many areas rather than one property, so there is no
     * single source record for it to be reconciled against. There is nothing to
     * sync, not "syncing is switched off".
     *
     * Declared here as well as in `config/mls_sync.php` deliberately, and the
     * two are not redundant. The config list is operational and may only NARROW
     * this one; this constant is the structural fact and cannot be widened by
     * editing a config file. Without it, `MlsFieldMap::forRole('buyer')` returns
     * a perfectly good map of buyer targets, and anything calling the projector
     * directly — bypassing the service, which is where the config check lives —
     * would happily write a feed value into a buyer's search criteria.
     *
     * @var list<string>
     */
    public const SYNCABLE_ROLES = ['seller', 'landlord'];

    /**
     * The canonical facts sync may write for a role, as canonical key => meta
     * target (leading '*' preserved, exactly as MlsFieldMap expresses it).
     *
     * @return array<string,string>
     */
    public static function syncableTargets(string $role): array
    {
        if (! in_array($role, self::SYNCABLE_ROLES, true)) {
            return [];
        }

        $out = [];

        foreach (MlsFieldMap::forRole($role) as $canonicalKey => $target) {
            if ($target === null || $target === '') {
                continue;
            }

            if (isset(self::NEVER_SYNC[$canonicalKey])) {
                continue;
            }

            if (in_array(ltrim($target, '*'), self::PROTECTED_META_KEYS, true)) {
                continue;
            }

            $out[$canonicalKey] = $target;
        }

        return $out;
    }

    /** May sync write this canonical fact for this role? */
    public static function allowsFact(string $role, string $canonicalKey): bool
    {
        return array_key_exists($canonicalKey, self::syncableTargets($role));
    }

    /** Is this meta key protected from every sync write? */
    public static function isProtectedMetaKey(string $metaKey): bool
    {
        return in_array(ltrim($metaKey, '*'), self::PROTECTED_META_KEYS, true);
    }

    /** @return array<string,string> canonical key => why it is never synced */
    public static function neverSyncReasons(): array
    {
        return self::NEVER_SYNC;
    }

    /** @return list<string> */
    public static function protectedMetaKeys(): array
    {
        return self::PROTECTED_META_KEYS;
    }

    /**
     * May the asking price be synced into this role's price field from a record
     * of this property type?
     *
     * ONE RULE, BOTH ROLES: the record's transaction kind must match the role's.
     *
     * Landlord: only from a LEASE record. `price` maps to
     * `desired_rental_amount` — the key the published landlord page actually
     * reads — and a sale ListPrice written there is how a $100,000 sale price
     * once became a $100,000 monthly rent on a live page.
     *
     * Seller: only from a SALE record, and this half is new. `if ($role !==
     * 'landlord') return true;` exempted every other role unconditionally, so a
     * lease record's ListPrice — which IS the monthly rent — was written into
     * `maximum_budget`, the meta key behind "Desired Sale Price". The same
     * defect as the landlord one, pointing the other way, and it had no guard at
     * all. `SellerMlsQuickImport::seededPrice()` refuses the same case on the
     * import side; this is that rule applied to the path that runs unattended.
     *
     * THE TWO ROLES ARE DELIBERATELY NOT SYMMETRICAL ON AN *UNKNOWN* TYPE.
     *
     *   Landlord — must be POSITIVELY a lease. Unknown refuses. Unchanged.
     *   Seller   — refuses only when the record is positively a LEASE. Unknown
     *              is allowed.
     *
     * That asymmetry is the honest one, not an oversight. A non-lease ListPrice
     * IS a sale price, so for a seller the only value that can be wrong is a
     * lease rent, and that is exactly what this now refuses. For a landlord the
     * failing direction is the common one — most records are sales — and the
     * rent is a required field, so refusing costs one number typed.
     *
     * It also matters because this method has a THIRD caller that neither
     * docblock mentioned: {@see \App\Support\Listing\ListingPriceDisplay} asks it
     * before rendering an already-stored MLS price. Rows written before
     * `mls_source_property_type` existed carry no source type at all, so a
     * blanket fail-closed here does not protect a write — it blanks the MLS
     * price block on existing seller listings that are displaying a perfectly
     * correct sale price. Refusing to WRITE an unclassifiable value and refusing
     * to SHOW one already written are different decisions, and the unknown case
     * is where they come apart.
     *
     * Nothing depends on unknown-permits for a NEW import: MlsQuickImportEligibility
     * refuses an unrecognised property type before any of this is reached.
     *
     * Asked through {@see PropertyTypeVocabulary::transactionFor()} rather than
     * by matching on the substring 'lease' so that one table decides what a
     * lease is, everywhere.
     */
    public static function allowsPriceSync(string $role, ?string $sourcePropertyType): bool
    {
        $transaction = PropertyTypeVocabulary::transactionFor($sourcePropertyType);

        return match ($role) {
            'landlord' => $transaction === PropertyTypeVocabulary::TRANSACTION_LEASE,
            'seller'   => $transaction !== PropertyTypeVocabulary::TRANSACTION_LEASE,

            // Buyer and Tenant listings describe search criteria across many
            // areas rather than one property, so they are not quick-import or
            // sync targets and never reach this method with a Bridge record.
            // Left permissive so this change cannot alter a path it was not
            // written for.
            default    => true,
        };
    }
}
