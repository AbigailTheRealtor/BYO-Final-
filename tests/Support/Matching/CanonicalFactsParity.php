<?php

namespace Tests\Support\Matching;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeListingMatchFactsBuilder;
use App\Services\Bridge\BridgeResidualMatchFactsReader;
use App\Services\Bridge\MlsCanonicalListingResolver;
use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\CanonicalListingMatchFactsBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Support\Listing\PropertyTypeVocabulary;
use App\Support\Matching\MonthlyEquivalent;
use ReflectionClass;
use Throwable;

/**
 * P1-B — compare the two ways of building the match engine's input.
 *
 *   legacy     BridgeProperty → BridgeListingMatchFactsBuilder                        → facts A
 *   canonical  BridgeProperty → MlsCanonicalListingResolver → CanonicalListing ┐
 *              BridgeProperty → BridgeResidualMatchFactsReader → residual      ┴→ CanonicalListingMatchFactsBuilder → facts B
 *
 * Facts are compared field by field with a DECLARED comparator per field; every
 * difference must be one of the six named ALLOWED_DIFFERENCES below, or the test
 * fails. Outcomes (score, category scores, Important Place rows, every explanation
 * block, exception class) are then compared by feeding A and B independently through
 * the unchanged BuyerMatchScorer::scoreFacts() and BuyerMatchResultBuilder.
 *
 * TEST-ONLY. The registry lives here and nowhere in app/, so no production code can
 * consult it. It is deliberately not a generic ignore: an entry names exact fields and
 * the exact legacy/canonical value shapes it accepts, and it declares which outcome
 * keys it may move. Adding an entry is a reviewed change with its own test.
 */
trait CanonicalFactsParity
{
    /**
     * The only differences between facts A and facts B that may pass.
     *
     * `propagates` lists the outcome keys (see outcome()) a difference is allowed to
     * move; an empty list means the difference must not reach any score, place row or
     * explanation at all. `closes_at` is the stage that removes the entry.
     *
     * @var array<string, array{fields: list<string>, reason: string, propagates: list<string>, closes_at: string}>
     */
    protected static array $ALLOWED_DIFFERENCES = [
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
    protected static array $COMPARATORS = [
        // The legacy column is untyped (the driver decides int vs numeric string).
        'livingArea'     => 'numeric',
        // The canonical period token and the feed wording mean the same period: compare
        // the monthly factor the rules and explanations actually use.
        'leaseFrequency' => 'lease_factor',
    ];

    protected function legacyFacts(BridgeProperty $row): ListingMatchFacts
    {
        return BridgeListingMatchFactsBuilder::build($row);
    }

    /** Facts B, or null when the row has no canonical listing (not comparable). */
    protected function canonicalFacts(BridgeProperty $row): ?ListingMatchFacts
    {
        $canonical = (new MlsCanonicalListingResolver())->forBridgeProperty($row);

        if ($canonical === null) {
            return null;
        }

        return CanonicalListingMatchFactsBuilder::build($canonical, BridgeResidualMatchFactsReader::read($row));
    }

    /** @return list<string> the ListingMatchFacts constructor parameters, in order. */
    protected static function factFields(): array
    {
        return array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new ReflectionClass(ListingMatchFacts::class))->getConstructor()->getParameters()
        );
    }

    /**
     * Every field whose values differ under its comparator.
     *
     * @return array<string, array{legacy: mixed, canonical: mixed}>
     */
    protected static function factsDifferences(ListingMatchFacts $a, ListingMatchFacts $b): array
    {
        $out = [];

        foreach (self::factFields() as $field) {
            $legacy    = $a->{$field};
            $canonical = $b->{$field};

            $same = match (self::$COMPARATORS[$field] ?? 'strict') {
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
    protected static function allowedDifference(string $field, mixed $legacy, mixed $canonical, ListingMatchFacts $a): ?string
    {
        foreach (self::$ALLOWED_DIFFERENCES as $id => $entry) {
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

    /**
     * Everything the live paths expose about one facts object scored against one
     * criteria set, through the UNCHANGED scorer and result builder. The row is only
     * carried (BuyerMatchResult requires one); the result builder reads the facts.
     * The result is assembled exactly as BuyerMatchScorer::score() assembles it,
     * including the seeker Smart Tag match its explanations read.
     *
     * @return array<string,mixed>
     */
    protected function outcome(ListingMatchFacts $facts, BridgeProperty $row, BuyerCriteriaPayload $criteria): array
    {
        try {
            $scorer  = new BuyerMatchScorer();
            $builder = new BuyerMatchResultBuilder();
            $score   = $scorer->scoreFacts($facts, $criteria);

            $result = static function () use ($facts, $score, $row): BuyerMatchResult {
                $r = new BuyerMatchResult($facts->listingKey, $score->totalScore, $score->categoryScores, $row);
                $r->importantPlaceMatches = $score->importantPlaceMatches;
                $r->seekerFeatureMatch    = $score->seekerFeatureMatch;
                $r->facts                 = $facts;

                return $r;
            };

            $batch    = $builder->build($result(), $criteria);
            $detailed = $builder->buildDetailed($result(), $criteria);

            return [
                'exception'        => null,
                'listing_key'      => $batch->listingKey,
                'total_score'      => $batch->totalScore,
                'category_scores'  => $batch->categoryScores,
                'important_places' => $batch->importantPlaceMatches,
                'why_this_matches' => $batch->whyThisMatches,
                'tradeoffs'        => $batch->tradeoffs,
                'caution_flags'    => $batch->cautionFlags,
                'missing_data'     => $batch->missingData,
                'why_not'          => $detailed->whyNot,
                'confidence'       => $detailed->confidence,
                'recommendations'  => $detailed->recommendations,
            ];
        } catch (Throwable $e) {
            return ['exception' => get_class($e)];
        }
    }

    /** @return list<string> the outcome keys whose values differ. */
    protected static function outcomeDifferences(array $a, array $b): array
    {
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));

        return array_values(array_filter($keys, static fn (string $k): bool => ($a[$k] ?? null) !== ($b[$k] ?? null)));
    }
}
