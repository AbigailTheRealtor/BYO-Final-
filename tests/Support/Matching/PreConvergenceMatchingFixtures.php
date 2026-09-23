<?php

namespace Tests\Support\Matching;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\Matching\ImportantPlaceMatcher;

/**
 * P1-A0 — shared seeding and projection for the pre-convergence matching baseline.
 *
 * Every listing here is one of the seven committed Stellar fixtures
 * (tests/fixtures/mls/bridge/*.json) stored through the REAL
 * BridgePropertyNormalizer::normalize(), so the native columns the matcher reads
 * are exactly what an import writes. normalize() + create() rather than upsert():
 * upsert() may dispatch Location DNA, and nothing in this baseline may start work.
 *
 * The projection helpers read only what the live path already exposes (result
 * DTO fields, the explanation blocks, the view card). They never inspect how a
 * value was derived, so P1-A may restructure the scorer's input freely as long
 * as these outputs do not move.
 */
trait PreConvergenceMatchingFixtures
{
    /**
     * The seven supported Stellar categories, keyed by the fixture file slug.
     *
     * @var array<string,string> slug => RESO PropertyType
     */
    protected static array $CATEGORIES = [
        'residential'          => 'Residential',
        'residential_lease'    => 'Residential Lease',
        'income'               => 'Income',
        'commercial_sale'      => 'Commercial Sale',
        'commercial_lease'     => 'Commercial Lease',
        'business_opportunity' => 'Business Opportunity',
        'vacant_land'          => 'Vacant Land',
    ];

    /** Deterministic ListingKey for a seeded fixture (optionally a named variant). */
    protected static function baselineKey(string $slug, ?string $variant = null): string
    {
        return 'P1A0-' . strtoupper($slug) . ($variant !== null ? '-' . strtoupper($variant) : '');
    }

    /** @return array<string,mixed> the decoded committed fixture record. */
    protected function fixtureRecord(string $slug): array
    {
        $path = base_path("tests/fixtures/mls/bridge/{$slug}.json");
        $record = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($record, "fixture {$slug} must decode");

        return $record;
    }

    /**
     * Store one fixture through the real normalizer.
     *
     * @param array<string,mixed> $rawOverrides  merged into the RAW feed record before normalizing
     * @param array<string,mixed> $columnOverrides applied to the normalized columns (for states the
     *                                              feed cannot express, e.g. a malformed raw_json)
     */
    protected function storeBaselineFixture(
        string $slug,
        array $rawOverrides = [],
        ?string $variant = null,
        array $columnOverrides = [],
    ): BridgeProperty {
        $key    = self::baselineKey($slug, $variant);
        $record = array_merge($this->fixtureRecord($slug), ['ListingKey' => $key, 'ListingId' => $key . '-ID'], $rawOverrides);

        $columns = (new BridgePropertyNormalizer())->normalize($record);
        $this->assertNotNull($columns, "fixture {$slug} must normalize");

        return BridgeProperty::create(array_merge($columns, $columnOverrides));
    }

    /** @return array<string,BridgeProperty> slug => stored listing, all seven categories. */
    protected function storeAllBaselineFixtures(): array
    {
        $out = [];
        foreach (array_keys(self::$CATEGORIES) as $slug) {
            $out[$slug] = $this->storeBaselineFixture($slug);
        }

        return $out;
    }

    /** A payload with the two required keys filled; overrides win. */
    protected function baselinePayload(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    /**
     * The live BuyerMatchService with the lazy import stubbed to "cached" — the
     * one collaborator that could reach the network. Everything else is real.
     */
    protected function baselineMatchService(?LazyBridgeImportService $lazyImport = null): BuyerMatchService
    {
        if ($lazyImport === null) {
            $lazyImport = $this->createMock(LazyBridgeImportService::class);
            $lazyImport->method('importForCriteria')->willReturn(LazyImportResult::cached(0));
        }

        return new BuyerMatchService(
            new BuyerMatchQueryBuilder(),
            new BuyerMatchScorer(),
            new BuyerMatchResultBuilder(),
            $lazyImport,
        );
    }

    /**
     * Everything the live paths expose about one scored listing:
     *  - `score`     the BuyerMatchResult fields (results page, Match Check lean result)
     *  - `explain`   BuyerMatchResultBuilder::build() blocks (results page)
     *  - `detailed`  buildDetailed()'s extra blocks (Match Check report path)
     *  - `places`    ImportantPlaceMatcher::present() rows (the public projection)
     *  - `card`      BuyerResultViewMapper::mapOne() minus the autoincrement row id
     *
     * Round-tripped through JSON so an in-memory result and a committed snapshot
     * compare with identical PHP types.
     *
     * @return array<string,mixed>
     */
    protected function projectScoredListing(BridgeProperty $listing, BuyerCriteriaPayload $criteria): array
    {
        $scorer  = new BuyerMatchScorer();
        $builder = new BuyerMatchResultBuilder();

        $batch = $builder->build($scorer->score($listing, $criteria), $criteria);

        // buildDetailed() mutates the result it is given; score afresh so the batch
        // projection above is not disturbed by the detailed one.
        $detailed = $builder->buildDetailed($scorer->score($listing, $criteria), $criteria);

        $card = (new BuyerResultViewMapper())->mapOne($batch);
        unset($card['bridge_property_id']);

        return self::jsonRoundTrip([
            'score' => [
                'listing_key'     => $batch->listingKey,
                'total_score'     => $batch->totalScore,
                'category_scores' => $batch->categoryScores,
            ],
            'explain' => [
                'why_this_matches' => $batch->whyThisMatches,
                'tradeoffs'        => $batch->tradeoffs,
                'caution_flags'    => $batch->cautionFlags,
                'missing_data'     => $batch->missingData,
            ],
            'detailed' => [
                'why_not'         => $detailed->whyNot,
                'confidence'      => $detailed->confidence,
                'recommendations' => $detailed->recommendations,
            ],
            'places' => ImportantPlaceMatcher::present($batch->importantPlaceMatches),
            'card'   => $card,
        ]);
    }

    /** The lean projection of a result that came out of BuyerMatchService::match(). */
    protected static function projectServiceResult(BuyerMatchResult $r): array
    {
        return self::jsonRoundTrip([
            'listing_key'      => $r->listingKey,
            'total_score'      => $r->totalScore,
            'category_scores'  => $r->categoryScores,
            'why_this_matches' => $r->whyThisMatches,
            'tradeoffs'        => $r->tradeoffs,
            'caution_flags'    => $r->cautionFlags,
            'missing_data'     => $r->missingData,
        ]);
    }

    protected static function jsonRoundTrip(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), true);
    }
}
