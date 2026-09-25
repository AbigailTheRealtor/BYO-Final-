<?php

namespace App\Services\Stellar\Matching\Parity;

use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Support\Listing\PropertyTypeVocabulary;
use App\Support\Matching\MonthlyEquivalent;
use ReflectionClass;
use ReflectionParameter;

/**
 * P1-B / P1-B2 — THE registry of allowed differences between the two ways of building
 * the match engine's input:
 *
 *   facts A  BridgeProperty → BridgeListingMatchFactsBuilder
 *   facts B  CanonicalListing + ListingMatchResidualFacts → CanonicalListingMatchFactsBuilder
 *
 * PARITY INFRASTRUCTURE ONLY. Moved verbatim from the P1-B test harness
 * (tests/Support/Matching/CanonicalFactsParity.php, which now delegates here) so the
 * offline runner and the in-suite parity tests read ONE definition. No matching rule,
 * scorer, explanation, controller or live service may consult it — a guard test
 * (CanonicalParityArchitectureGuardTest) fails if anything outside the parity namespace,
 * the parity command and the tests names this class.
 *
 * It is deliberately not a generic ignore: an entry names exact fields and the exact
 * legacy/canonical value shapes it accepts, and it declares which outcome keys it may
 * move. Adding, removing or widening an entry is a reviewed change with its own
 * demonstration test (CanonicalFactsAllowedDifferenceTest).
 */
final class CanonicalParityAllowedDifferences
{
    /**
     * The only differences between facts A and facts B that may pass.
     *
     * `propagates` lists the outcome keys (see OUTCOME_KEYS) a difference is allowed to
     * move; an empty list means the difference must not reach any score, place row or
     * explanation at all. `closes_at` is the stage that removes the entry.
     *
     * @var array<string, array{fields: list<string>, reason: string, propagates: list<string>, closes_at: string}>
     */
    public const ENTRIES = [
        'AD-1' => [
            'fields'     => ['poolPrivate', 'garage', 'waterfront'],
            'reason'     => 'legacy stored false (BridgePropertyNormalizer::filterBool() turns a not-stated value into false); '
                          . 'the canonical listing publishes only true, so false reads as unknown',
            'propagates' => [],
            'closes_at'  => 'FU-2 boolean normalization plus a canonical change that accepts false',
        ],
        'AD-2' => [
            'fields'     => ['latitude', 'longitude'],
            'reason'     => 'legacy keeps an invalid pair (Null Island, out of range); canonical drops it (CoordinateValidator::isValidPair)',
            'propagates' => ['category_scores', 'total_score', 'important_places', 'caution_flags', 'confidence', 'recommendations', 'why_not', 'why_this_matches'],
            'closes_at'  => 'P1-F2 (accepted as a divergence when canonical scoring is activated)',
        ],
        'AD-3' => [
            'fields'     => ['listPrice', 'livingArea', 'yearBuilt'],
            'reason'     => 'legacy keeps a zero, negative or implausible number; canonical treats it as unknown',
            'propagates' => ['category_scores', 'total_score', 'why_this_matches', 'tradeoffs', 'missing_data', 'confidence', 'recommendations', 'why_not'],
            'closes_at'  => 'P1-F2 (accepted as a divergence when canonical scoring is activated)',
        ],
        'AD-4' => [
            'fields'     => ['leaseFrequency'],
            'reason'     => 'legacy carries a rent period on a sale-type record; canonical carries a period only on a lease',
            'propagates' => [],
            'closes_at'  => 'P1-H (legacy builder retired)',
        ],
        'AD-5' => [
            'fields'     => ['propertyType'],
            'reason'     => 'legacy carries the stored type verbatim; canonical yields the primary recognised type for its '
                          . 'meaning (a RESO alias or another spelling) or null (an unrecognised type)',
            'propagates' => ['category_scores', 'total_score', 'why_this_matches', 'confidence'],
            'closes_at'  => 'P1-F2 (the §12 non-residential routing decision)',
        ],
        'AD-6' => [
            'fields'     => ['listingKey', 'city', 'stateOrProvince', 'postalCode', 'countyOrParish'],
            'reason'     => 'legacy keeps surrounding whitespace (and a blank string); canonical trims (blank is unknown)',
            'propagates' => ['listing_key', 'category_scores', 'total_score', 'why_this_matches', 'recommendations', 'why_not'],
            'closes_at'  => 'P1-F2 (accepted as a divergence when canonical scoring is activated)',
        ],
    ];

    /** The fields compared with something other than strict ===, and why. */
    public const COMPARATORS = [
        // The legacy column is untyped (the driver decides int vs numeric string).
        'livingArea'     => 'numeric',
        // The canonical period token and the feed wording mean the same period: compare
        // the monthly factor the rules and explanations actually use.
        'leaseFrequency' => 'lease_factor',
    ];

    /**
     * Every outcome key a `propagates` list may name — the keys of
     * CanonicalMatchingParityRunner::outcome(). A test pins that the two agree.
     */
    public const OUTCOME_KEYS = [
        'exception', 'listing_key', 'total_score', 'category_scores', 'important_places', 'why_this_matches',
        'tradeoffs', 'caution_flags', 'missing_data', 'why_not', 'confidence', 'recommendations',
    ];

    /** @return list<string> the ListingMatchFacts constructor parameters, in order. */
    public static function factFields(): array
    {
        return array_map(
            static fn (ReflectionParameter $p): string => $p->getName(),
            (new ReflectionClass(ListingMatchFacts::class))->getConstructor()->getParameters()
        );
    }

    /**
     * Every field whose values differ under its comparator.
     *
     * @return array<string, array{legacy: mixed, canonical: mixed}>
     */
    public static function factsDifferences(ListingMatchFacts $a, ListingMatchFacts $b): array
    {
        $out = [];

        foreach (self::factFields() as $field) {
            $legacy    = $a->{$field};
            $canonical = $b->{$field};

            $same = match (self::COMPARATORS[$field] ?? 'strict') {
                'numeric'      => ($legacy === null) === ($canonical === null)
                                  && ($legacy === null || (float) $legacy === (float) $canonical),
                'lease_factor' => MonthlyEquivalent::leaseFactor($legacy) === MonthlyEquivalent::leaseFactor($canonical),
                default        => $legacy === $canonical,
            };

            if (!$same) {
                $out[$field] = ['legacy' => $legacy, 'canonical' => $canonical];
            }
        }

        return $out;
    }

    /**
     * The registry entry that accepts this difference, or null. Each predicate checks
     * the exact value shapes its entry describes — never the field name alone.
     */
    public static function allowedDifference(string $field, mixed $legacy, mixed $canonical, ListingMatchFacts $a): ?string
    {
        foreach (self::ENTRIES as $id => $entry) {
            if (!in_array($field, $entry['fields'], true)) {
                continue;
            }

            $accepted = match ($id) {
                'AD-1' => $legacy === false && $canonical === null,
                'AD-2' => $canonical === null && $legacy !== null
                          && !CoordinateValidator::isValidPair(
                              $a->latitude === null ? null : (float) $a->latitude,
                              $a->longitude === null ? null : (float) $a->longitude,
                          ),
                'AD-3' => $canonical === null && $legacy !== null && is_numeric($legacy)
                          && ($field === 'yearBuilt'
                              ? ((int) $legacy < 1700 || (int) $legacy > (int) date('Y') + 2)
                              : (float) $legacy <= 0.0),
                'AD-4' => $canonical === null && MonthlyEquivalent::leaseFactor($legacy) !== null
                          && PropertyTypeVocabulary::transactionFor(is_string($a->propertyType) ? $a->propertyType : null)
                             === PropertyTypeVocabulary::TRANSACTION_SALE,
                'AD-5' => is_string($legacy)
                          && $canonical === PropertyTypeVocabulary::recognisedTypeFor(
                              PropertyTypeVocabulary::roleCategoryFor($legacy, 'seller'),
                              PropertyTypeVocabulary::transactionFor($legacy),
                          ),
                'AD-6' => is_string($legacy)
                          && $canonical === (trim($legacy) === '' ? null : trim($legacy)),
                default => false,
            };

            if ($accepted) {
                return $id;
            }
        }

        return null;
    }

    /** @return list<string> the outcome keys entry $id may move. */
    public static function propagates(string $id): array
    {
        return self::ENTRIES[$id]['propagates'] ?? [];
    }
}
