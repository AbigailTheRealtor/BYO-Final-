<?php

namespace App\Support\ListingPreferences\Taste;

use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceReasonDimension;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * Taste DNA — the deterministic learner. ONE customer, ONE seeker role, their
 * OWN explicit choices, and nothing else.
 *
 * Pure and container-free: no database, no clock, no network, no model call.
 * The same histories and facts always produce the same profile, byte for byte.
 *
 * WHAT IT READS
 * -------------
 *   • the customer's choices (TasteChoiceTimeline) — Save, Maybe, Pass
 *   • the structured reasons they chose, resolved LIVE through
 *     ListingPreferenceReasonCatalog::learnable() — so `unspecified` reasons
 *     ("Other", "Layout") are never learned, and a retired reason stops counting
 *   • the governed structured facts of the homes they chose (TasteListingFacts)
 *
 * WHAT IT REFUSES TO READ, BY CONSTRUCTION
 * ----------------------------------------
 *   • location reasons — the preference carries no structural link to the
 *     customer's Important Places, so they are not learned in Phase 4 at all
 *   • any Smart Tag that is not seeker-selectable RIGHT NOW — the same gate that
 *     keeps accessible_features and playground out of the chip list, asked of the
 *     taxonomy rather than restated, whether the tag arrived as a reason or as a
 *     listing characteristic
 *   • free text — there is none in the snapshot, and none is parsed
 *   • anything about where a home is, or about any other customer
 *
 * WEIGHTING (all constants below; RULES_VERSION changes when any does)
 * --------------------------------------------------------------------
 *   Save → positive, Pass → negative, Maybe → UNCERTAIN (never half a Save:
 *   it only dilutes agreement, it never points a pattern either way).
 *
 *   A choice still in force weighs W_CURRENT; one the customer later changed
 *   or cleared weighs W_SUPERSEDED. Kept, never erased — and outweighed, so a
 *   later Pass beats an earlier Save on the same home without deleting it.
 *
 *   A reason the customer chose weighs M_STATED; a characteristic the home
 *   merely had weighs M_OBSERVED. Both may describe one choice; the stronger
 *   counts, never both, so one decision is never double-counted.
 *
 *   PER HOME, PER DIRECTION, THE MAXIMUM — not the sum. Toggling one house ten
 *   times is one house. Repetition strengthens a pattern only ACROSS homes,
 *   which is what "repeated independent choices" means.
 *
 *   Conflict lowers confidence: agreement is |P − N| over all the evidence
 *   (Maybes diluting at MAYBE_DILUTION), and the tiers demand it.
 *
 *   One choice is never enough: every displayable tier needs at least two
 *   homes and more strength than a single current choice can supply.
 *
 * HISTORICAL REASONS, CURRENT FACTS — the Phase 4 rule for the two sources:
 *
 *   • A stated reason is HISTORICAL evidence. It is the snapshot stored on the
 *     event when the customer chose, so it never changes afterwards, and it
 *     keeps counting when the listing is later archived, refused by the feed or
 *     deleted outright.
 *   • An observed characteristic is a CORRELATION against the facts the
 *     platform CURRENTLY publishes for the listing the customer last acted on
 *     for that home. If the listing changes, the correlation follows the current
 *     canonical fact on the next derivation; nothing is snapshotted and no
 *     history is rewritten. If the listing or the fact is unavailable (archived,
 *     draft, IDX-refused, deleted, blank, implausible), that characteristic is
 *     OMITTED — never guessed, never carried from an earlier value.
 *   • The page words the two differently (TasteObservationPresenter::source()),
 *     so a correlation never reads as something the customer told us.
 *
 * There is no wall-clock decay, deliberately: decay by age would make the
 * profile depend on WHEN it was computed, and "rebuild yields identical output
 * from the same history" would stop being true. Supersession — what the
 * customer did next — is the recency model.
 */
final class TasteDnaDeriver
{
    /** Bump whenever any rule or constant below changes. */
    public const RULES_VERSION = '2026-09-22.2';

    public const W_CURRENT    = 1.0;
    public const W_SUPERSEDED = 0.25;

    public const M_STATED   = 1.0;
    public const M_OBSERVED = 0.5;

    public const MAYBE_DILUTION = 0.5;

    public const EMERGING_MIN_HOMES     = 2;
    public const EMERGING_MIN_STRENGTH  = 1.5;
    public const EMERGING_MIN_AGREEMENT = 0.5;

    public const ESTABLISHED_MIN_HOMES     = 3;
    public const ESTABLISHED_MIN_STRENGTH  = 3.0;
    public const ESTABLISHED_MIN_AGREEMENT = 0.75;

    /** Both sides must carry at least this much before a conflict is called "mixed". */
    public const MIXED_MIN_SIDE_WEIGHT = 1.0;

    /** Below this |P − N| / (P + N) the two sides are comparable: mixed. */
    public const MIXED_MAX_NET_RATIO = 0.5;

    public const MIXED_MIN_HOMES = 3;

    public const NUMERIC_EMERGING_HOMES    = 3;
    public const NUMERIC_ESTABLISHED_HOMES = 5;

    /**
     * Reason dimensions Phase 4 may learn. `location` is deliberately absent
     * (see the class doc); `unspecified` is never learnable anywhere.
     */
    private const LEARNED_REASON_DIMENSIONS = [
        ListingPreferenceReasonDimension::SmartTag,
        ListingPreferenceReasonDimension::Criteria,
    ];

    private const NUMERIC_LABELS = [
        'bedrooms'    => 'Bedrooms',
        'bathrooms'   => 'Bathrooms',
        'living_area' => 'Living area',
        'lot_size'    => 'Lot size',
    ];

    /**
     * @param list<TasteSubjectHistory>         $histories
     * @param array<string, TasteListingFacts>  $factsByRef keyed "<type>:<id>"; a missing entry means
     *                                                      the home's facts are unavailable, and only
     *                                                      the customer's stated reasons count for it
     */
    public static function derive(int $userId, SeekerRole $role, array $histories, array $factsByRef): TasteProfile
    {
        /** @var array<string, array<string, mixed>> $acc */
        $acc         = [];
        $choiceCount = 0;

        foreach ($histories as $history) {
            $facts = $factsByRef[$history->refKey] ?? null;

            foreach ($history->choices as $choice) {
                $choiceCount++;
                $weight = $choice->current ? self::W_CURRENT : self::W_SUPERSEDED;

                foreach (self::statedSignals($choice->reasonKeys) as [$dimension, $key, $label]) {
                    self::contribute($acc, $dimension, $key, $label, $history->subjectKey, $choice, $weight * self::M_STATED, TasteSource::StatedReason);
                }

                if ($facts === null) {
                    continue;
                }

                foreach (self::observedSignals($facts) as [$dimension, $key, $label]) {
                    self::contribute($acc, $dimension, $key, $label, $history->subjectKey, $choice, $weight * self::M_OBSERVED, TasteSource::ListingCharacteristic);
                }
            }
        }

        $signals = [];

        foreach ($acc as $entry) {
            $signals[] = self::categorical($entry);
        }

        foreach (self::numericDimensions($role) as $dimension) {
            $signal = self::numeric($dimension, $histories, $factsByRef);

            if ($signal !== null) {
                $signals[] = $signal;
            }
        }

        usort($signals, [self::class, 'compare']);

        return new TasteProfile($userId, $role, $signals, count($histories), $choiceCount, self::RULES_VERSION);
    }

    // ------------------------------------------------------------ evidence

    /**
     * The learnable signals a choice's STATED reasons name.
     *
     * @param  list<string> $reasonKeys
     * @return list<array{0: TasteDimension, 1: string, 2: string}>
     */
    private static function statedSignals(array $reasonKeys): array
    {
        $learnable = ListingPreferenceReasonCatalog::learnable();
        $out       = [];

        foreach ($reasonKeys as $reasonKey) {
            $reason = $learnable[$reasonKey] ?? null;

            if ($reason === null || ! in_array($reason->dimension, self::LEARNED_REASON_DIMENSIONS, true)) {
                continue;
            }

            if ($reason->dimension === ListingPreferenceReasonDimension::SmartTag) {
                $tag = $reason->smartTagKey === null ? null : SmartTagTaxonomy::get($reason->smartTagKey);

                if ($tag === null || ! $tag->isSeekerSelectable()) {
                    continue;
                }

                $out[] = [TasteDimension::SmartTag, $tag->key, $tag->label];
                continue;
            }

            $out[] = [TasteDimension::Reason, $reason->key, $reason->label];
        }

        return $out;
    }

    /**
     * The categorical signals a home's governed facts carry.
     *
     * @return list<array{0: TasteDimension, 1: string, 2: string}>
     */
    private static function observedSignals(TasteListingFacts $facts): array
    {
        $out = [];

        foreach ($facts->tagKeys as $tagKey) {
            $tag = is_string($tagKey) ? SmartTagTaxonomy::get($tagKey) : null;

            // The same gate as a stated reason: a tag excluded as a seeker
            // preference is not learned by the back door of "the house had it".
            if ($tag !== null && $tag->isSeekerSelectable()) {
                $out[] = [TasteDimension::SmartTag, $tag->key, $tag->label];
            }
        }

        foreach ($facts->subtypes as $subtype) {
            $normalised = self::normaliseSubtype($subtype);

            if ($normalised !== null) {
                $out[] = [TasteDimension::PropertySubtype, strtolower($normalised), $normalised];
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $acc
     */
    private static function contribute(
        array &$acc,
        TasteDimension $dimension,
        string $key,
        string $label,
        string $subjectKey,
        TasteChoice $choice,
        float $weight,
        TasteSource $source,
    ): void {
        $id = $dimension->value . ':' . $key;

        $acc[$id] ??= [
            'dimension' => $dimension,
            'key'       => $key,
            'label'     => $label,
            'homes'     => [],
            'sources'   => [],
            'first'     => null,
            'last'      => null,
        ];

        $slot = match ($choice->state) {
            ListingPreferenceState::Save  => 'pos',
            ListingPreferenceState::Pass  => 'neg',
            ListingPreferenceState::Maybe => 'unc',
        };

        $home = $acc[$id]['homes'][$subjectKey] ?? ['pos' => 0.0, 'neg' => 0.0, 'unc' => 0.0];

        // Per home, per direction, the MAXIMUM: one house is one house.
        $home[$slot] = max($home[$slot], $weight);

        $acc[$id]['homes'][$subjectKey] = $home;
        $acc[$id]['sources'][$source->value] = $source;

        if ($acc[$id]['first'] === null || $choice->firstAt < $acc[$id]['first']) {
            $acc[$id]['first'] = $choice->firstAt;
        }

        if ($acc[$id]['last'] === null || $choice->lastAt > $acc[$id]['last']) {
            $acc[$id]['last'] = $choice->lastAt;
        }
    }

    // ------------------------------------------------------ classification

    /**
     * @param array<string, mixed> $entry
     */
    private static function categorical(array $entry): TasteSignal
    {
        $p = $n = $u = 0.0;
        $saves = $passes = $maybes = 0;

        foreach ($entry['homes'] as $home) {
            $p += $home['pos'];
            $n += $home['neg'];
            $u += $home['unc'];
            $saves  += $home['pos'] > 0 ? 1 : 0;
            $passes += $home['neg'] > 0 ? 1 : 0;
            $maybes += $home['unc'] > 0 ? 1 : 0;
        }

        $p = self::r($p);
        $n = self::r($n);
        $u = self::r($u);

        $sources = array_values($entry['sources']);
        usort($sources, static fn (TasteSource $a, TasteSource $b): int => strcmp($a->value, $b->value));

        $stated   = in_array(TasteSource::StatedReason, $sources, true);
        $homes    = count($entry['homes']);
        $decisive = $p + $n;
        $net      = $p - $n;

        if ($decisive <= 0.0) {
            // Only Maybes.
            [$direction, $strength, $agreement] = [TasteDirection::Uncertain, $u, 1.0];
            $confidence = self::uncertainTier($maybes, $u);
        } elseif (
            ($p >= self::MIXED_MIN_SIDE_WEIGHT && $n >= self::MIXED_MIN_SIDE_WEIGHT && abs($net) / $decisive < self::MIXED_MAX_NET_RATIO)
            || abs($net) < 1e-9
        ) {
            [$direction, $strength, $agreement] = [TasteDirection::Mixed, $decisive, abs($net) / $decisive];

            // A conflict is worth telling the customer about only when THEY
            // named the characteristic. A feature that is merely present on
            // homes they Saved and homes they Passed is not a pattern at all.
            $confidence = $stated && $homes >= self::MIXED_MIN_HOMES
                && $p >= self::MIXED_MIN_SIDE_WEIGHT && $n >= self::MIXED_MIN_SIDE_WEIGHT
                ? TasteConfidence::Emerging
                : TasteConfidence::Insufficient;
        } elseif ($u > $decisive) {
            [$direction, $strength, $agreement] = [TasteDirection::Uncertain, $u, $u / ($u + $decisive)];
            $confidence = self::uncertainTier($maybes, $u);
        } else {
            $direction = $net > 0 ? TasteDirection::Positive : TasteDirection::Negative;
            $strength  = abs($net);
            $agreement = $strength / ($decisive + self::MAYBE_DILUTION * $u);
            $confidence = self::tier($direction === TasteDirection::Positive ? $saves : $passes, $strength, $agreement);
        }

        return new TasteSignal(
            dimension:       $entry['dimension'],
            key:             $entry['key'],
            label:           $entry['label'],
            direction:       $direction,
            confidence:      $confidence,
            strength:        self::r($strength),
            agreement:       self::r($agreement),
            positiveWeight:  $p,
            negativeWeight:  $n,
            uncertainWeight: $u,
            supportCount:    $homes,
            saveCount:       $saves,
            maybeCount:      $maybes,
            passCount:       $passes,
            firstAt:         $entry['first'],
            lastAt:          $entry['last'],
            sources:         $sources,
        );
    }

    private static function tier(int $dominantHomes, float $strength, float $agreement): TasteConfidence
    {
        if ($dominantHomes >= self::ESTABLISHED_MIN_HOMES
            && $strength >= self::ESTABLISHED_MIN_STRENGTH
            && $agreement >= self::ESTABLISHED_MIN_AGREEMENT) {
            return TasteConfidence::Established;
        }

        if ($dominantHomes >= self::EMERGING_MIN_HOMES
            && $strength >= self::EMERGING_MIN_STRENGTH
            && $agreement >= self::EMERGING_MIN_AGREEMENT) {
            return TasteConfidence::Emerging;
        }

        return TasteConfidence::Insufficient;
    }

    /** Maybe is uncertainty by definition: it may be noticed, never called "often". */
    private static function uncertainTier(int $maybes, float $u): TasteConfidence
    {
        return $maybes >= self::EMERGING_MIN_HOMES && $u >= self::EMERGING_MIN_STRENGTH
            ? TasteConfidence::Emerging
            : TasteConfidence::Insufficient;
    }

    // ------------------------------------------------------------- numeric

    /** @return list<TasteDimension> */
    private static function numericDimensions(SeekerRole $role): array
    {
        $dimensions = [TasteDimension::Bedrooms, TasteDimension::Bathrooms, TasteDimension::LivingArea];

        // Lot size is a purchase consideration; a rental's lot is the landlord's.
        if ($role === SeekerRole::Buyer) {
            $dimensions[] = TasteDimension::LotSize;
        }

        return $dimensions;
    }

    /**
     * One numeric fact across the homes whose LATEST choice was Save, and
     * across those whose latest was Pass. Maybes describe no range.
     *
     * @param list<TasteSubjectHistory>        $histories
     * @param array<string, TasteListingFacts> $factsByRef
     */
    private static function numeric(TasteDimension $dimension, array $histories, array $factsByRef): ?TasteSignal
    {
        $save = $pass = [];
        $first = $last = null;
        $maybes = 0;

        foreach ($histories as $history) {
            $facts = $factsByRef[$history->refKey] ?? null;
            $value = $facts === null ? null : self::roundFact($dimension, $facts->numeric($dimension));

            if ($value === null) {
                continue;
            }

            $latest = $history->latest();

            match ($latest->state) {
                ListingPreferenceState::Save  => $save[] = $value,
                ListingPreferenceState::Pass  => $pass[] = $value,
                ListingPreferenceState::Maybe => $maybes++,
            };

            if ($latest->state !== ListingPreferenceState::Maybe) {
                $first = $first === null || $latest->firstAt < $first ? $latest->firstAt : $first;
                $last  = $last === null || $latest->lastAt > $last ? $latest->lastAt : $last;
            }
        }

        if ($save === [] && $pass === []) {
            return null;
        }

        $saveBand = TasteNumericBand::from($save);
        $passBand = TasteNumericBand::from($pass);
        $saveN    = count($save);
        $passN    = count($pass);
        $min      = self::NUMERIC_EMERGING_HOMES;

        if ($saveN >= $min && $passN >= $min && $saveBand->overlaps($passBand)) {
            // The homes they Save and the homes they Pass look alike on this
            // fact, so it does not explain their choices. Recorded, not shown.
            $direction  = TasteDirection::Mixed;
            $dominant   = max($saveN, $passN);
            $confidence = TasteConfidence::Insufficient;
        } elseif ($saveN >= $passN) {
            $direction  = TasteDirection::Positive;
            $dominant   = $saveN;
            $confidence = self::numericTier($saveN);
        } else {
            $direction  = TasteDirection::Negative;
            $dominant   = $passN;
            $confidence = self::numericTier($passN);
        }

        return new TasteSignal(
            dimension:       $dimension,
            key:             $dimension->value,
            label:           self::NUMERIC_LABELS[$dimension->value],
            direction:       $direction,
            confidence:      $confidence,
            strength:        (float) $dominant,
            agreement:       self::r($dominant / ($saveN + $passN)),
            positiveWeight:  (float) $saveN,
            negativeWeight:  (float) $passN,
            uncertainWeight: 0.0,
            supportCount:    $saveN + $passN,
            saveCount:       $saveN,
            maybeCount:      $maybes,
            passCount:       $passN,
            firstAt:         $first,
            lastAt:          $last,
            sources:         [TasteSource::ListingCharacteristic],
            saveBand:        $saveBand,
            passBand:        $passBand,
        );
    }

    private static function numericTier(int $homes): TasteConfidence
    {
        return match (true) {
            $homes >= self::NUMERIC_ESTABLISHED_HOMES => TasteConfidence::Established,
            $homes >= self::NUMERIC_EMERGING_HOMES    => TasteConfidence::Emerging,
            default                                   => TasteConfidence::Insufficient,
        };
    }

    /**
     * Each fact at the precision a person would describe it with, and bounded:
     * a value outside a plausible range is a data error, not a preference.
     */
    private static function roundFact(TasteDimension $dimension, ?float $value): ?float
    {
        if ($value === null || ! is_finite($value) || $value <= 0) {
            return null;
        }

        [$max, $rounded] = match ($dimension) {
            TasteDimension::Bedrooms   => [50.0, round($value)],
            TasteDimension::Bathrooms  => [50.0, round($value * 2) / 2],
            TasteDimension::LivingArea => [100_000.0, round($value / 100) * 100],
            TasteDimension::LotSize    => [10_000.0, round($value, 2)],
            default                    => [0.0, null],
        };

        return $rounded === null || $rounded <= 0 || $value > $max ? null : (float) $rounded;
    }

    // --------------------------------------------------------------- shared

    /**
     * A structured sub-type, cleaned — or null when it is not one.
     *
     * "Other" is the form's escape hatch, not a type; its companion free-text
     * box is never read. Placeholders ("Non-Applicable", "N/A", "None",
     * "Unknown") say nothing about the home and are dropped too. A value the
     * Fair Housing guard objects to is dropped rather than learned, whatever a
     * feed happened to publish — SmartTagComplianceGuard is asked of EVERY
     * sub-type, so this dimension cannot carry a concept the tag taxonomy may not.
     */
    private static function normaliseSubtype(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s+/', ' ', $value));

        if (strlen($clean) < 2 || strlen($clean) > 64 || preg_match('~^(?:other|non[\s-]*applicable|n/?a|none|unknown)\b~i', $clean) === 1) {
            return null;
        }

        return SmartTagComplianceGuard::isClean($clean) ? $clean : null;
    }

    private static function compare(TasteSignal $a, TasteSignal $b): int
    {
        $order = [
            TasteDirection::Positive->value  => 0,
            TasteDirection::Negative->value  => 1,
            TasteDirection::Mixed->value     => 2,
            TasteDirection::Uncertain->value => 3,
        ];

        return [
            $b->confidence->rank(),
            $order[$a->direction->value],
            $b->strength,
            $a->dimension->value,
            $a->key,
        ] <=> [
            $a->confidence->rank(),
            $order[$b->direction->value],
            $a->strength,
            $b->dimension->value,
            $b->key,
        ];
    }

    private static function r(float $value): float
    {
        return round($value, 3);
    }
}
