<?php

namespace App\Services\ListingImport\QuickImport;

use App\Support\Listing\PropertyTypeVocabulary;

/**
 * May THIS role import THIS MLS record — and if not, what do we tell the user?
 *
 * WHY THIS EXISTS
 * ---------------
 * Quick Import had no guard at all between the role the user chose and the kind
 * of record they typed a number for. A seller could import a Residential Lease
 * and a landlord could import a Commercial Sale, and nothing anywhere said so.
 *
 * Two things went wrong when they did.
 *
 * 1. THE PRICE. On a lease record `ListPrice` IS the periodic rent; on a sale
 *    record it is a purchase price. The landlord side already refused to seed a
 *    rent box from a non-lease record — that guard exists because a $100,000
 *    condo was once advertised at $100,000 per month — but the seller side had
 *    no mirror, so a lease record's monthly rent was seeded straight into
 *    "Desired Sale Price". Same defect, opposite direction.
 *
 * 2. THE QUESTION SET. An unrecognised or role-inapplicable property type
 *    produced an EMPTY `$property_type`, and the canonical terms partials gate
 *    their conditional sections on an exact match. So the user was shown a
 *    partial form — shared fields present, every gated section silently absent
 *    — which is indistinguishable from a listing that legitimately has fewer
 *    questions. They could reach Review and Publish that way.
 *
 * FAIL CLOSED, AND SAY WHY
 * ------------------------
 * Every refusal here names a next action the user can actually take. "This MLS
 * number is a rental listing. Use Landlord MLS Import for lease listings." is a
 * different instruction from "we could not identify this property type", and
 * collapsing them into one generic error sends somebody off to re-check a
 * number that was correct.
 *
 * WHY IT ASKS TWO SEPARATE QUESTIONS
 * ----------------------------------
 * {@see PropertyTypeVocabulary} answers vocabulary ("what kind of building")
 * and transaction ("sale or lease") separately, because they genuinely differ:
 * a Commercial Sale record describes a perfectly good Commercial Property AND
 * is the wrong record for a landlord. This class is where the two are put back
 * together into one decision, so no caller has to remember to ask both.
 *
 * It decides from the SOURCE property type only. It never consults
 * `StandardStatus` or `MlsStatus` — those are market status, not property type,
 * and an Active/Pending/Closed transition must never be able to move a listing
 * between the Residential and Commercial tracks.
 */
final class MlsQuickImportEligibility
{
    public const REASON_ALLOWED           = 'allowed';
    public const REASON_UNCLASSIFIED      = 'unclassified_property_type';
    public const REASON_WRONG_TRANSACTION = 'wrong_transaction_for_role';
    public const REASON_NO_ROLE_CATEGORY  = 'no_category_for_role';

    private function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly string $message,
        public readonly ?string $sourcePropertyType,
        public readonly ?string $transaction,
        public readonly ?string $category,
    ) {}

    /**
     * The transaction kind each role's listings are about.
     *
     * Seller listings sell a property; Landlord listings let one. This is not a
     * rollout dial and is not configurable — it is what the two forms ask.
     */
    private const ROLE_TRANSACTION = [
        'seller'   => PropertyTypeVocabulary::TRANSACTION_SALE,
        'landlord' => PropertyTypeVocabulary::TRANSACTION_LEASE,
    ];

    public static function for(string $role, ?string $sourcePropertyType): self
    {
        $known = PropertyTypeVocabulary::classifySource($sourcePropertyType);

        // Unrecognised, missing or blank. Includes Farm and Manufactured In
        // Park, which are real RESO members with no BidYourOffer category —
        // see the note in PropertyTypeVocabulary. Deliberately NOT defaulted to
        // Residential: a default here is a silent misclassification with the
        // authority of an import behind it.
        if ($known === null) {
            return new self(
                allowed:            false,
                reason:             self::REASON_UNCLASSIFIED,
                message:            self::unclassifiedMessage($sourcePropertyType),
                sourcePropertyType: $sourcePropertyType,
                transaction:        null,
                category:           null,
            );
        }

        $transaction = $known['transaction'];
        $expected    = self::ROLE_TRANSACTION[$role] ?? null;

        // A recognised type whose transaction we cannot state. Reachable only
        // with a BidYourOffer category as the source value, which a Bridge
        // record never carries — but "recognised" must not be allowed to imply
        // "eligible", so it is refused rather than assumed.
        if ($transaction === null || $expected === null) {
            return new self(
                allowed:            false,
                reason:             self::REASON_UNCLASSIFIED,
                message:            self::unclassifiedMessage($sourcePropertyType),
                sourcePropertyType: $sourcePropertyType,
                transaction:        null,
                category:           $known[$role === 'landlord' ? 'landlord' : 'seller'],
            );
        }

        if ($transaction !== $expected) {
            return new self(
                allowed:            false,
                reason:             self::REASON_WRONG_TRANSACTION,
                message:            $role === 'seller'
                    ? 'This MLS number is a rental listing. Use Landlord MLS Import for lease listings.'
                    : 'This MLS number is a for-sale listing. Use Seller MLS Import for sale listings.',
                sourcePropertyType: $sourcePropertyType,
                transaction:        $transaction,
                category:           null,
            );
        }

        $category = ($role === 'landlord') ? $known['landlord'] : $known['seller'];

        // The right kind of transaction, but this role has no category for the
        // property type. No such record exists today — every lease type has a
        // Landlord category and every sale type has a Seller one — and the
        // branch is kept because "we added a property type and forgot one role"
        // must surface as a refusal, not as an empty $property_type.
        if ($category === null) {
            return new self(
                allowed:            false,
                reason:             self::REASON_NO_ROLE_CATEGORY,
                message:            self::unclassifiedMessage($sourcePropertyType),
                sourcePropertyType: $sourcePropertyType,
                transaction:        $transaction,
                category:           null,
            );
        }

        return new self(
            allowed:            true,
            reason:             self::REASON_ALLOWED,
            message:            '',
            sourcePropertyType: $sourcePropertyType,
            transaction:        $transaction,
            category:           $category,
        );
    }

    /**
     * The message for a record we cannot classify.
     *
     * Names the feed's own wording when there is one, because "we do not support
     * Farm listings" is actionable and "we could not identify this property" is
     * not. Both endings offer the two things the user can actually do: re-check
     * the number, or build the listing by hand.
     */
    private static function unclassifiedMessage(?string $sourcePropertyType): string
    {
        $named = trim((string) $sourcePropertyType);

        $what = $named === ''
            ? 'The MLS record does not say what type of property this is.'
            : sprintf('We do not support importing "%s" listings yet.', $named);

        return $what . ' Please check the MLS number, or create this listing manually.';
    }
}
