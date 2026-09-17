<?php

namespace App\Services\Stellar\Matching;

/**
 * The one place the match engine names the two Bridge fields that carry a PERIOD.
 *
 * `bridge_properties` promotes `AssociationFee` and `ListPrice` to native columns
 * and leaves the field that says what period each one covers behind in `raw_json`.
 * Reading those two keys is unavoidable today; naming them in eleven places is
 * not. They are named here, once.
 *
 * PROVIDER-SPECIFIC ON PURPOSE, AND ISOLATED ON PURPOSE
 * ----------------------------------------------------
 * `LeaseAmountFrequency` and `AssociationFeeFrequency` are RESO field names, so
 * a second RESO-aligned MLS would supply them under the same names — but a
 * non-RESO source would not, and nothing here should be mistaken for a canonical
 * property model. When the match engine is moved behind a canonical property
 * interface, this class is what the adapter absorbs: two accessors, no logic.
 * Everything downstream already speaks in periods rather than field names.
 *
 * Pure: an array in, a string or null out. No model, no query, no container.
 */
final class ListingPeriodFacts
{
    /** RESO: the period a lease `ListPrice` covers. */
    public const LEASE_FREQUENCY_FIELD = 'LeaseAmountFrequency';

    /** RESO: the period an `AssociationFee` covers. */
    public const ASSOCIATION_FEE_FREQUENCY_FIELD = 'AssociationFeeFrequency';

    /**
     * The listing's stated lease frequency, or null when the feed did not say.
     *
     * @param array<string,mixed> $rawJson the decoded Bridge record
     */
    public static function leaseFrequency(array $rawJson): ?string
    {
        return self::text($rawJson, self::LEASE_FREQUENCY_FIELD);
    }

    /**
     * The listing's stated association-fee frequency, or null when absent.
     *
     * @param array<string,mixed> $rawJson the decoded Bridge record
     */
    public static function associationFeeFrequency(array $rawJson): ?string
    {
        return self::text($rawJson, self::ASSOCIATION_FEE_FREQUENCY_FIELD);
    }

    /** @param array<string,mixed> $rawJson */
    private static function text(array $rawJson, string $key): ?string
    {
        $value = $rawJson[$key] ?? null;

        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
