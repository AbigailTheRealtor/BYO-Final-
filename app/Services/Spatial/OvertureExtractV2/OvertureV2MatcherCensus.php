<?php

namespace App\Services\Spatial\OvertureExtractV2;

use App\Services\Spatial\ChainRegistry\ChainMatcher;
use App\Services\Spatial\ChainRegistry\ChainMatchInput;
use App\Services\Spatial\ChainRegistry\ChainMatchResult;

/**
 * Runs the pure chain-registry matcher over both lanes OFFLINE. It writes nothing, groups nothing,
 * de-duplicates nothing and answers no query. It produces two things: the census counts for the
 * extraction manifest, and the supplementary lane with every rescue candidate RESOLVED.
 *
 * Resolution is where a rescue is decided, row by row, and recorded on the row: a candidate the
 * matcher admits under one of the row's own lanes becomes `admitted` / `rescued`; a candidate the
 * matcher refuses becomes `refused` / `matcher_only`. Candidacy is by token, so a refused row is
 * routine (CVS Beauty, MinuteClinic, and non-CVS names filed under the same token).
 *
 * It also enforces the lane contract from the matcher's side, and every breach aborts the run
 * rather than being counted: a base row that produced a rescued membership; a `diagnostic` row that
 * matched; a candidate membership outside a lane its own record names, or with no rescue at all;
 * a candidate with more than one membership, which would make its rescue ambiguous.
 */
final class OvertureV2MatcherCensus
{
    public function __construct(private readonly ChainMatcher $matcher, private readonly OvertureExtractV2Config $config)
    {
    }

    /**
     * @return array{summary: array<string, mixed>, supplementary: list<OvertureV2Record>} the
     *         census counts, and the supplementary lane in the same order with every verdict resolved
     */
    public function run(OvertureV2ExtractResult $result): array
    {
        $memberships = 0;
        $fromBase = 0;
        $rescued = 0;
        $ambiguous = 0;
        $coBranded = 0;
        $byChain = [];
        $byLane = [];
        $verdicts = [OvertureV2Record::RESCUE_ADMITTED => 0, OvertureV2Record::RESCUE_REFUSED => 0];
        $resolved = [];

        foreach ([$result->base(), $result->supplementary()] as $records) {
            foreach ($records as $rec) {
                $match = $this->matcher->match(new ChainMatchInput(
                    $rec->name,
                    $rec->brandName,
                    $rec->brandWikidata,
                    $rec->categoryKey,
                    $rec->operatingStatus,
                    $rec->sourceCategory,
                ));
                if ($match->outcome === ChainMatchResult::AMBIGUOUS) {
                    $ambiguous++;
                }
                foreach ($match->memberships as $m) {
                    $memberships++;
                    $byChain[$m->brandKey] = ($byChain[$m->brandKey] ?? 0) + 1;
                    if ($m->coBrandWith !== []) {
                        $coBranded++;
                    }
                    if ($rec->isBase() && $m->rescuedFromSourceCategory !== null) {
                        throw new InvalidOvertureExtractV2("base row {$rec->sourceRef} produced a rescued membership");
                    }
                }
                if ($rec->isBase()) {
                    $fromBase += count($match->memberships);
                    continue;
                }

                if ($rec->supplementaryRole !== OvertureV2Record::ROLE_RESCUE_CANDIDATE) {
                    if ($match->memberships !== []) {
                        throw new InvalidOvertureExtractV2("supplementary row {$rec->sourceRef} produced a {$match->memberships[0]->brandKey} membership outside a rescue lane");
                    }
                    $resolved[] = $rec;
                    continue;
                }
                if (count($match->memberships) > 1) {
                    throw new InvalidOvertureExtractV2("rescue candidate {$rec->sourceRef} produced more than one membership; its rescue would be ambiguous");
                }
                if ($match->memberships === []) {
                    $resolved[] = $rec->withRescueVerdict(OvertureV2Record::RESCUE_REFUSED);
                    $verdicts[OvertureV2Record::RESCUE_REFUSED]++;
                    continue;
                }
                $m = $match->memberships[0];
                $lane = $this->laneFor($rec, $m->brandKey, $m->rescuedFromSourceCategory);
                $resolved[] = $rec->withRescueVerdict(
                    OvertureV2Record::RESCUE_ADMITTED,
                    $lane,
                    $m->brandKey,
                    $this->config->rescueLanes[$lane]['as_category'],
                    $m->formatKey,
                );
                $verdicts[OvertureV2Record::RESCUE_ADMITTED]++;
                $rescued++;
                $byLane[$lane] = ($byLane[$lane] ?? 0) + 1;
            }
        }
        ksort($byChain, SORT_STRING);
        ksort($byLane, SORT_STRING);

        return [
            'summary' => [
                'memberships' => $memberships,
                'memberships_from_base' => $fromBase,
                'memberships_rescued_from_supplementary' => $rescued,
                'rescued_by_lane' => $byLane,
                'rescue_verdicts' => $verdicts,
                'ambiguous_rows' => $ambiguous,
                'co_branded_memberships' => $coBranded,
                'memberships_by_chain' => $byChain,
            ],
            'supplementary' => $resolved,
        ];
    }

    private function laneFor(OvertureV2Record $rec, string $chain, ?string $rescuedFrom): string
    {
        if ($rescuedFrom === null) {
            throw new InvalidOvertureExtractV2("supplementary row {$rec->sourceRef} produced a {$chain} membership outside a rescue lane");
        }
        foreach ($rec->rescueLanes as $lane) {
            $l = $this->config->rescueLanes[$lane];
            if ($l['chain'] === $chain && $l['source_category'] === $rescuedFrom) {
                return $lane;
            }
        }
        throw new InvalidOvertureExtractV2("supplementary row {$rec->sourceRef}: {$chain} rescue from {$rescuedFrom} is not one of its lanes");
    }
}
