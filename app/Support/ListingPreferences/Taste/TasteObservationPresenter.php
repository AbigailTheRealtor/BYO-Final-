<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * Words for a Taste DNA profile — the ONLY path from a signal to a customer.
 *
 * Pure and container-free.
 *
 * WHAT IT GUARANTEES
 * ------------------
 *   • Only displayable signals are worded; `insufficient` never reaches a page,
 *     so one Save can never be announced as a taste.
 *   • Confidence reaches the customer as a WORD ("tend to", "often"), never as
 *     a number. Strength and agreement stay internal.
 *   • Every sentence says where it came from — the customer's own choices, and
 *     whether those were reasons they picked or details the homes list — and
 *     how many homes support it. Counts are shown because they are the honest
 *     answer to "why do you think that?"; percentages are not, because three
 *     clicks do not have a percentage.
 *   • Conflict is called mixed, not averaged into a verdict.
 *   • Headlines are catalog and taxonomy LABELS, never keys.
 */
final class TasteObservationPresenter
{
    public const GROUP_LABELS = [
        TasteObservation::GROUP_SAVE  => 'What you tend to Save',
        TasteObservation::GROUP_PASS  => 'What you tend to Pass on',
        TasteObservation::GROUP_MIXED => 'Mixed or unsure',
    ];

    /**
     * @return list<TasteObservation>
     */
    public static function present(TasteProfile $profile): array
    {
        $out = [];

        foreach ($profile->displayable() as $signal) {
            $out[] = new TasteObservation(
                group:    self::group($signal->direction),
                headline: $signal->label,
                summary:  $signal->dimension->isNumeric() ? self::numericSummary($signal) : self::summary($signal),
                evidence: self::evidence($signal),
                source:   self::source($signal),
            );
        }

        return $out;
    }

    /**
     * @param  list<TasteObservation> $observations
     * @return array<string, list<TasteObservation>> group => observations, in GROUP_LABELS order, empty groups dropped
     */
    public static function grouped(array $observations): array
    {
        $groups = array_fill_keys(array_keys(self::GROUP_LABELS), []);

        foreach ($observations as $observation) {
            $groups[$observation->group][] = $observation;
        }

        return array_filter($groups, static fn (array $g): bool => $g !== []);
    }

    private static function group(TasteDirection $direction): string
    {
        return match ($direction) {
            TasteDirection::Positive => TasteObservation::GROUP_SAVE,
            TasteDirection::Negative => TasteObservation::GROUP_PASS,
            default                  => TasteObservation::GROUP_MIXED,
        };
    }

    private static function summary(TasteSignal $signal): string
    {
        $often = $signal->confidence === TasteConfidence::Established;

        if ($signal->dimension === TasteDimension::Reason) {
            return match ($signal->direction) {
                TasteDirection::Positive  => $often
                    ? 'You often mention this when you Save a home.'
                    : 'You have mentioned this when Saving several homes.',
                TasteDirection::Negative  => $often
                    ? 'You often mention this when you Pass on a home.'
                    : 'You have mentioned this when Passing on several homes.',
                TasteDirection::Mixed     => 'You have mentioned this both when Saving homes and when Passing on them.',
                TasteDirection::Uncertain => 'You have mentioned this on several homes you marked Maybe.',
            };
        }

        $object = $signal->dimension === TasteDimension::PropertySubtype ? 'homes of this type' : 'homes with this';

        return match ($signal->direction) {
            TasteDirection::Positive  => ($often ? 'You often Save ' : 'You tend to Save ') . $object . '.',
            TasteDirection::Negative  => ($often ? 'You often Pass on ' : 'You tend to Pass on ') . $object . '.',
            TasteDirection::Mixed     => 'Your choices here are mixed: you have Saved some ' . $object . ' and Passed on others.',
            TasteDirection::Uncertain => 'You have marked several ' . $object . ' as Maybe.',
        };
    }

    private static function numericSummary(TasteSignal $signal): string
    {
        if ($signal->direction === TasteDirection::Negative && $signal->passBand !== null) {
            return 'Homes you Pass on usually have ' . self::range($signal->dimension, $signal->passBand) . '.';
        }

        $text = 'Homes you Save usually have ' . self::range($signal->dimension, $signal->saveBand) . '.';

        // The contrast is only stated when the Passed homes are themselves a
        // pattern — three or more, and a range that does not overlap.
        if ($signal->passBand !== null
            && $signal->passBand->count >= TasteDnaDeriver::NUMERIC_EMERGING_HOMES
            && ! $signal->saveBand->overlaps($signal->passBand)) {
            $text .= ' The ones you Pass on usually have ' . self::range($signal->dimension, $signal->passBand) . '.';
        }

        return $text;
    }

    private static function range(TasteDimension $dimension, TasteNumericBand $band): string
    {
        $fmt = static fn (float $v): string => match ($dimension) {
            TasteDimension::LivingArea => number_format($v),
            TasteDimension::LotSize    => rtrim(rtrim(number_format($v, 2), '0'), '.'),
            default                    => rtrim(rtrim(number_format($v, 1), '0'), '.'),
        };

        $span = $band->low === $band->high
            ? $fmt($band->low)
            : $fmt($band->low) . '–' . $fmt($band->high);

        $plural = ! ($band->low === $band->high && $band->low === 1.0);

        return $span . ' ' . match ($dimension) {
            TasteDimension::Bedrooms   => $plural ? 'bedrooms' : 'bedroom',
            TasteDimension::Bathrooms  => $plural ? 'bathrooms' : 'bathroom',
            TasteDimension::LivingArea => 'sq ft',
            TasteDimension::LotSize    => $plural ? 'acres' : 'acre',
            default                    => '',
        };
    }

    private static function evidence(TasteSignal $signal): string
    {
        $parts = [];

        if ($signal->saveCount > 0) {
            $parts[] = $signal->saveCount . ' Saved';
        }

        if ($signal->maybeCount > 0 && ! $signal->dimension->isNumeric()) {
            $parts[] = $signal->maybeCount . ' Maybe';
        }

        if ($signal->passCount > 0) {
            $parts[] = $signal->passCount . ' Passed';
        }

        $homes = $signal->dimension->isNumeric() ? $signal->saveCount + $signal->passCount : $signal->supportCount;

        return 'From ' . $homes . ' ' . ($homes === 1 ? 'home' : 'homes') . ' you chose: ' . implode(', ', $parts) . '.';
    }

    /**
     * Where the observation came from — and the line between what the customer
     * TOLD us and what we NOTICED is drawn in the words, not left to inference.
     *
     *   a reason they picked       "Based on reasons you picked."
     *   a feature the homes list   "Seen across homes you Saved …" — a correlation
     *                              against the details those homes CURRENTLY list,
     *                              and said to be not a reason they picked, so a
     *                              garage on three Saved homes never reads as
     *                              "you told us you want a garage".
     */
    private static function source(TasteSignal $signal): string
    {
        $stated   = $signal->hasSource(TasteSource::StatedReason);
        $observed = $signal->hasSource(TasteSource::ListingCharacteristic);

        if ($stated) {
            return $observed
                ? 'Based on reasons you picked, and also seen in details those homes currently list.'
                : 'Based on reasons you picked.';
        }

        $homes = match ($signal->direction) {
            TasteDirection::Positive => 'homes you Saved',
            TasteDirection::Negative => 'homes you Passed on',
            default                  => 'homes you chose',
        };

        return 'Seen across ' . $homes . ', from details those homes currently list — not a reason you picked.';
    }
}
