<?php

namespace App\Services\Dna\Relevance;

use InvalidArgumentException;

/**
 * BatchRelevanceMatcher — the pure computational core of two-way orchestration
 * (§F6). Given ONE subject's dna_scores and a caller-provided collection of
 * counterpart score-sets, it runs aggregate → classify for each pair and
 * returns a ranked, tiered RankedMatchSet.
 *
 * It composes the existing kernels (RelevanceAggregator + MatchTierClassifier)
 * and adds only direction-correct pairing, deterministic ranking, and tier
 * counting. It is pure, deterministic, read-only, and side-effect-free.
 *
 * DELIBERATELY OUT OF SCOPE (this is the SAFE half of orchestration): candidate
 * selection (which counterparts to consider), database querying, pagination,
 * marketplace-scale performance, persistence, and any consumer-facing exposure.
 * The CALLER supplies the already-resolved counterpart score-sets.
 *
 * A counterpart is an array:
 *   ['id' => int|string, 'scores' => iterable<DnaScore>, 'type' => ?string].
 * 'type' is optional (Matching V2 C6): when present it is carried through into the
 * resulting RankedMatch so a mixed-type candidate pool stays unambiguous. Omitting
 * it preserves the exact pre-C6 behaviour.
 */
class BatchRelevanceMatcher
{
    public function __construct(
        private readonly RelevanceAggregator $aggregator,
        private readonly MatchTierClassifier $classifier,
    ) {
    }

    /**
     * Match one listing (its property-side scores) against many demand profiles.
     *
     * @param iterable<\App\Models\DnaScore> $listingScores
     * @param iterable<array{id:int|string,scores?:iterable}> $demandProfiles
     */
    public function matchListingAgainstDemands(iterable $listingScores, iterable $demandProfiles): RankedMatchSet
    {
        $listingScores = $this->materialise($listingScores);

        $rows = [];
        foreach ($demandProfiles as $counterpart) {
            [$id, $scores, $type] = $this->counterpart($counterpart);
            // Subject is the property side; counterpart supplies the demand side.
            $aggregate = $this->aggregator->aggregate($listingScores, $scores);
            $rows[] = [$id, $this->classifier->classify($aggregate), $type];
        }

        return $this->rank($rows);
    }

    /**
     * Match one demand profile (its demand-side scores) against many listings.
     *
     * @param iterable<\App\Models\DnaScore> $demandScores
     * @param iterable<array{id:int|string,scores?:iterable}> $listings
     */
    public function matchDemandAgainstListings(iterable $demandScores, iterable $listings): RankedMatchSet
    {
        $demandScores = $this->materialise($demandScores);

        $rows = [];
        foreach ($listings as $counterpart) {
            [$id, $scores, $type] = $this->counterpart($counterpart);
            // Counterpart supplies the property side; subject is the demand side.
            $aggregate = $this->aggregator->aggregate($scores, $demandScores);
            $rows[] = [$id, $this->classifier->classify($aggregate), $type];
        }

        return $this->rank($rows);
    }

    /**
     * Partition determined vs undetermined, then order determined matches by
     * tier rank desc → overall value desc → id asc.
     *
     * @param array<int,array{0:int|string,1:MatchTierResult,2:?string}> $rows
     */
    private function rank(array $rows): RankedMatchSet
    {
        $determined        = [];
        $undeterminedCount = 0;

        foreach ($rows as [$id, $result, $type]) {
            if ($result->isUndetermined()) {
                $undeterminedCount++;
                continue;
            }
            $determined[] = new RankedMatch($id, $result, $type);
        }

        usort($determined, static function (RankedMatch $a, RankedMatch $b): int {
            // tier rank descending
            $byTier = $b->result->tier->rank() <=> $a->result->tier->rank();
            if ($byTier !== 0) {
                return $byTier;
            }
            // overall value descending
            $byValue = ($b->result->value ?? 0) <=> ($a->result->value ?? 0);
            if ($byValue !== 0) {
                return $byValue;
            }
            // id ascending, then type ascending — a total, deterministic tie-break
            // that stays stable even when ids collide across counterpart types.
            $byId = $a->counterpartId <=> $b->counterpartId;
            if ($byId !== 0) {
                return $byId;
            }
            return ($a->counterpartType ?? '') <=> ($b->counterpartType ?? '');
        });

        return new RankedMatchSet($determined, $undeterminedCount);
    }

    /**
     * @param array{id?:int|string,scores?:iterable,type?:?string} $counterpart
     * @return array{0:int|string,1:iterable,2:?string}
     */
    private function counterpart(array $counterpart): array
    {
        if (! array_key_exists('id', $counterpart)) {
            throw new InvalidArgumentException("Each counterpart must provide an 'id'.");
        }

        return [$counterpart['id'], $counterpart['scores'] ?? [], $counterpart['type'] ?? null];
    }

    /**
     * Materialise the subject scores so they can be re-read for every counterpart
     * (a generator would otherwise be exhausted after the first pairing).
     *
     * @param iterable<\App\Models\DnaScore> $scores
     * @return array<int,\App\Models\DnaScore>
     */
    private function materialise(iterable $scores): array
    {
        return is_array($scores) ? $scores : iterator_to_array($scores);
    }
}
