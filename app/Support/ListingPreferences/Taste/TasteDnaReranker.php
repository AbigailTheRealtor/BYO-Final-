<?php

namespace App\Support\ListingPreferences\Taste;

use App\Support\SmartTags\SmartTagTaxonomy;
use LogicException;

/**
 * Phase 5 — Taste DNA as a BOUNDED, post-score reorder of an already-ranked list.
 *
 * Pure and container-free: no database, no clock, no network, no randomness.
 * Everything it reads is handed to it: the customer's own TasteProfile (Phase 4,
 * reused, not re-derived) and each candidate's governed facts, batch-loaded by
 * the caller before the call.
 *
 * WHAT IT GUARANTEES, BY CONSTRUCTION
 * -----------------------------------
 *   • MEMBERSHIP. The output is a permutation of the input keys — asserted
 *     before returning. Nothing is added, removed or hidden; a listing the
 *     customer Passed stays in the list.
 *   • THE SCORE. No base score is written, returned or adjusted. The adjustment
 *     below is an ordering key that exists only inside this method.
 *   • BOUNDED INFLUENCE. Every candidate's adjustment lies in
 *     [−MAX_INFLUENCE, +MAX_INFLUENCE], so one listing can be placed above
 *     another only when its base score is LESS than 2 × MAX_INFLUENCE (2.5)
 *     points lower. On the integer 0–100 Stellar scale that is a gap of at most
 *     2 points; a gap of 3 or more is never crossed, whatever the profile says.
 *   • DETERMINISM. Sorted by (base + adjustment) descending, then by the
 *     candidate's ORIGINAL position — so equal influence preserves the existing
 *     order exactly, and the same inputs always give the same order.
 *
 * WHY 2.5 POINTS (see governance §14 for the full reasoning)
 * -----------------------------------------------------------
 * The Stellar Match DNA total is an integer sum of eight capped categories. The
 * smallest distinctions it draws are one-point items — a lifestyle signal (new
 * construction, energy efficiency, pets, one community feature), a view, a
 * water view. A 3-point gap is already a substantive difference: a garage the
 * buyer asked for, a subtype match, a band of price proximity. Taste may
 * therefore decide between listings the matcher considers near-equal (0, 1 or 2
 * points apart) and never between listings it has actually separated — an 88
 * cannot pass a 98, and cannot pass a 91.
 *
 * Crossing even one point needs strong evidence: a single established signal
 * the customer STATED moves a listing by 0.625, which only breaks exact ties.
 * Crossing two points needs the maximum positive on one listing and a strong
 * negative on the other.
 *
 * WHICH SIGNALS COUNT
 * -------------------
 *   • Only `emerging` or `established` confidence — `insufficient` never moves
 *     anything, so one choice is never a ranking signal (Phase 4 already needs
 *     two homes for `emerging`).
 *   • Only `positive` or `negative` direction. `mixed` and `uncertain` carry no
 *     direction and contribute nothing.
 *   • Only dimensions describing a characteristic a candidate can be checked
 *     for: a Smart Tag it has resolved PRESENT, and its structured sub-type.
 *       - `reason` (price, size, fees) is EXCLUDED: those reasons describe the
 *         customer's criteria, which the matcher already scores explicitly, and
 *         a learned price tendency would read as affordability.
 *       - bedrooms / bathrooms / living area / lot size are EXCLUDED: they are
 *         observed-only correlations (never a stated reason), they move with
 *         price, and the customer's explicit size criteria are already enforced
 *         and scored. A learned "usually 2 bedrooms" must never rank against an
 *         explicit "3+ bedrooms". They stay visible on Your Home Taste.
 *   • A Smart Tag must be seeker-selectable NOW, asked of the taxonomy — the
 *     same gate as Phase 4, so accessible_features, playground and any retired
 *     or pending tag cannot act through ranking.
 *   • A tag that is ABSENT on a candidate is not evidence either way: a
 *     negative signal demotes only a listing that HAS the characteristic.
 *
 * A stated reason outweighs an observed correlation (SOURCE_STATED vs
 * SOURCE_OBSERVED), mirroring Phase 4's 1.0 / 0.5 — the deriver's own weights
 * are inside `strength` and are not re-applied here.
 *
 * NOT READ, AND NOT PRESENT IN THIS FILE: where a home is, anything about any
 * other customer, the customer's current Save / Maybe / Pass on the candidate
 * itself (learned taste only — a direct per-listing boost would count the same
 * feedback twice), and anything from Ask AI.
 */
final class TasteDnaReranker
{
    /** Bump whenever any rule or constant below changes. */
    public const RULES_VERSION = '2026-09-23.1';

    /** The largest ordering adjustment any one candidate can receive, in score points. */
    public const MAX_INFLUENCE = 1.25;

    /** Raw evidence at which a candidate's adjustment saturates at MAX_INFLUENCE. */
    public const SATURATION = 2.0;

    public const TIER_EMERGING    = 0.5;
    public const TIER_ESTABLISHED = 1.0;

    public const SOURCE_STATED   = 1.0;
    public const SOURCE_OBSERVED = 0.5;

    /** Dimensions a candidate can be checked against. Numeric and `reason` are deliberately absent. */
    public const RANKING_DIMENSIONS = [
        TasteDimension::SmartTag,
        TasteDimension::PropertySubtype,
    ];

    /**
     * @param list<TasteRerankCandidate> $candidates in the EXISTING order
     */
    public static function rerank(TasteProfile $profile, array $candidates): TasteRerankResult
    {
        $signals = self::rankingSignals($profile);

        $keys = array_map(static fn (TasteRerankCandidate $c): string => $c->key, $candidates);

        if (count($keys) !== count(array_unique($keys))) {
            throw new LogicException('Taste reranking needs one key per candidate.');
        }

        $rows = [];

        foreach (array_values($candidates) as $position => $candidate) {
            $contributions = $signals === [] ? [] : self::contributions($signals, $candidate->facts);
            $raw           = 0.0;

            foreach ($contributions as $contribution) {
                $raw += $contribution->weight;
            }

            $rows[] = [
                'candidate'     => $candidate,
                'position'      => $position,
                'adjustment'    => self::bound($raw),
                'contributions' => $contributions,
            ];
        }

        $ordered = $rows;

        usort($ordered, static function (array $a, array $b): int {
            $ka = (float) $a['candidate']->baseScore + $a['adjustment'];
            $kb = (float) $b['candidate']->baseScore + $b['adjustment'];

            // Rounded so float noise can never decide an order two equal keys
            // should leave alone; the original position is the tie-break.
            return [round($kb, 6), $a['position']] <=> [round($ka, 6), $b['position']];
        });

        $orderedKeys = [];
        $influences  = [];

        foreach ($ordered as $finalPosition => $row) {
            $key           = $row['candidate']->key;
            $orderedKeys[] = $key;

            $influences[$key] = new TasteRerankInfluence(
                key:           $key,
                basePosition:  $row['position'],
                finalPosition: $finalPosition,
                adjustment:    $row['adjustment'],
                contributions: $row['contributions'],
            );
        }

        self::assertPermutation($candidates, $orderedKeys);

        return new TasteRerankResult($orderedKeys, $influences, $signals !== []);
    }

    /**
     * The profile's signals that may influence ordering at all.
     *
     * @return list<TasteSignal>
     */
    public static function rankingSignals(TasteProfile $profile): array
    {
        // An incomplete profile has no signals by construction; asked again so a
        // later change to TasteProfile cannot make a partial history act.
        if (! $profile->complete) {
            return [];
        }

        $out = [];

        foreach ($profile->signals as $signal) {
            if (! $signal->isDisplayable()
                || ! in_array($signal->direction, [TasteDirection::Positive, TasteDirection::Negative], true)
                || ! in_array($signal->dimension, self::RANKING_DIMENSIONS, true)) {
                continue;
            }

            if ($signal->dimension === TasteDimension::SmartTag) {
                $tag = SmartTagTaxonomy::get($signal->key);

                if ($tag === null || ! $tag->isSeekerSelectable()) {
                    continue;
                }
            }

            $out[] = $signal;
        }

        return $out;
    }

    /** The weight one signal carries, before direction. Internal. */
    public static function signalWeight(TasteSignal $signal): float
    {
        $tier = $signal->confidence === TasteConfidence::Established ? self::TIER_ESTABLISHED : self::TIER_EMERGING;

        $source = $signal->hasSource(TasteSource::StatedReason) ? self::SOURCE_STATED : self::SOURCE_OBSERVED;

        return $tier * $source;
    }

    /**
     * @param  list<TasteSignal> $signals
     * @return list<TasteRerankContribution> strongest first, then by signal id
     */
    private static function contributions(array $signals, ?TasteListingFacts $facts): array
    {
        if ($facts === null) {
            return [];
        }

        $tags = array_fill_keys(array_filter($facts->tagKeys, 'is_string'), true);

        $subtypes = [];

        foreach ($facts->subtypes as $subtype) {
            $key = TasteDnaDeriver::subtypeKey($subtype);

            if ($key !== null) {
                $subtypes[$key] = true;
            }
        }

        $out = [];

        foreach ($signals as $signal) {
            $has = match ($signal->dimension) {
                TasteDimension::SmartTag        => isset($tags[$signal->key]),
                TasteDimension::PropertySubtype => isset($subtypes[$signal->key]),
                default                         => false,
            };

            if (! $has) {
                continue;
            }

            $sign  = $signal->direction === TasteDirection::Positive ? 1.0 : -1.0;
            $out[] = new TasteRerankContribution($signal, $sign * self::signalWeight($signal));
        }

        usort($out, static fn (TasteRerankContribution $a, TasteRerankContribution $b): int =>
            [abs($b->weight), $a->signal->id()] <=> [abs($a->weight), $b->signal->id()]);

        return $out;
    }

    private static function bound(float $raw): float
    {
        $ratio = max(-1.0, min(1.0, $raw / self::SATURATION));

        return round(self::MAX_INFLUENCE * $ratio, 6);
    }

    /**
     * @param list<TasteRerankCandidate> $candidates
     * @param list<string>               $orderedKeys
     */
    private static function assertPermutation(array $candidates, array $orderedKeys): void
    {
        $in = array_map(static fn (TasteRerankCandidate $c): string => $c->key, array_values($candidates));
        $out = $orderedKeys;

        sort($in, SORT_STRING);
        sort($out, SORT_STRING);

        if ($in !== $out) {
            throw new LogicException('Taste reranking must return exactly the candidates it was given.');
        }
    }
}
