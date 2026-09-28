<?php

namespace Tests\Support\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Services\Stellar\Matching\Parity\CanonicalMatchingParityRunner;
use App\Services\Stellar\Matching\Parity\CanonicalParityAllowedDifferences;

/**
 * P1-B — compare the two ways of building the match engine's input.
 *
 *   legacy     BridgeProperty → BridgeListingMatchFactsBuilder                        → facts A
 *   canonical  BridgeProperty → MlsCanonicalListingResolver → CanonicalListing ┐
 *              BridgeProperty → BridgeResidualMatchFactsReader → residual      ┴→ CanonicalListingMatchFactsBuilder → facts B
 *
 * A THIN DELEGATE since P1-B2. The allowed-difference registry, the per-field comparators
 * and the outcome projection moved, verbatim, into the parity namespace
 * (App\Services\Stellar\Matching\Parity) so the offline runner and these tests read ONE
 * definition. Nothing is defined here any more: the two static properties below alias the
 * registry's constants, and every method forwards. There is no second registry.
 */
trait CanonicalFactsParity
{
    /** @see CanonicalParityAllowedDifferences::ENTRIES — the one registry, aliased. */
    protected static array $ALLOWED_DIFFERENCES = CanonicalParityAllowedDifferences::ENTRIES;

    /** @see CanonicalParityAllowedDifferences::COMPARATORS */
    protected static array $COMPARATORS = CanonicalParityAllowedDifferences::COMPARATORS;

    protected function legacyFacts(BridgeProperty $row): ListingMatchFacts
    {
        return CanonicalMatchingParityRunner::legacyFacts($row);
    }

    /** Facts B, or null when the row has no canonical listing (not comparable). */
    protected function canonicalFacts(BridgeProperty $row): ?ListingMatchFacts
    {
        return CanonicalMatchingParityRunner::canonicalFacts($row);
    }

    /** @return list<string> the ListingMatchFacts constructor parameters, in order. */
    protected static function factFields(): array
    {
        return CanonicalParityAllowedDifferences::factFields();
    }

    /** @return array<string, array{legacy: mixed, canonical: mixed}> */
    protected static function factsDifferences(ListingMatchFacts $a, ListingMatchFacts $b): array
    {
        return CanonicalParityAllowedDifferences::factsDifferences($a, $b);
    }

    protected static function allowedDifference(string $field, mixed $legacy, mixed $canonical, ListingMatchFacts $a): ?string
    {
        return CanonicalParityAllowedDifferences::allowedDifference($field, $legacy, $canonical, $a);
    }

    /** @return array<string,mixed> */
    protected function outcome(ListingMatchFacts $facts, BridgeProperty $row, BuyerCriteriaPayload $criteria): array
    {
        return CanonicalMatchingParityRunner::outcome($facts, $row, $criteria);
    }

    /** @return list<string> the outcome keys whose values differ. */
    protected static function outcomeDifferences(array $a, array $b): array
    {
        return CanonicalMatchingParityRunner::outcomeDifferences($a, $b);
    }
}
