<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * Words for why Your Home Taste moved one listing — the ONLY path from a rerank
 * influence to a customer. Pure and container-free.
 *
 * AN ALLOW-LIST OF STRINGS, like TasteObservation: a headline and up to
 * MAX_LINES short sentences, already worded. No score, adjustment, weight,
 * percentage, key, id or subject is ever returned, so none can reach a page.
 *
 * TOLD vs NOTICED, kept apart in the words (Phase 4's rule): a signal the
 * customer supported with a reason they PICKED says so; a characteristic that
 * merely appears on homes they chose is worded as something they tend to do,
 * never as something they told us.
 *
 * Only a listing whose position actually changed, AND which carries evidence of
 * its own, is explained. A listing that moved only because a neighbour moved
 * says nothing — there is nothing true to say about it.
 */
final class TasteRerankExplanation
{
    public const MAX_LINES = 2;

    public const HEADLINE_RAISED  = 'Fits things you tend to Save';
    public const HEADLINE_LOWERED = 'Has things you tend to Pass on';

    /**
     * @return array{headline: string, lines: list<string>}|null
     */
    public static function for(?TasteRerankInfluence $influence): ?array
    {
        if ($influence === null || ! $influence->moved()) {
            return null;
        }

        $raised = $influence->raised();

        // Explain in the direction the listing moved: a raised listing by what
        // it has that the customer Saves, a lowered one by what they Pass on.
        $relevant = array_values(array_filter(
            $influence->contributions,
            static fn (TasteRerankContribution $c): bool => $c->isPositive() === $raised,
        ));

        if ($relevant === []) {
            return null;
        }

        $lines = [];

        foreach (array_slice($relevant, 0, self::MAX_LINES) as $contribution) {
            $lines[] = self::line($contribution->signal);
        }

        return [
            'headline' => $raised ? self::HEADLINE_RAISED : self::HEADLINE_LOWERED,
            'lines'    => $lines,
        ];
    }

    private static function line(TasteSignal $signal): string
    {
        $positive = $signal->direction === TasteDirection::Positive;
        $often    = $signal->confidence === TasteConfidence::Established;
        $label    = $signal->label;

        if ($signal->hasSource(TasteSource::StatedReason)) {
            return $positive
                ? $label . ' is a reason you have picked when Saving homes.'
                : $label . ' is a reason you have picked when Passing on homes.';
        }

        if ($signal->dimension === TasteDimension::PropertySubtype) {
            return $positive
                ? 'You ' . ($often ? 'often' : 'tend to') . ' Save homes of this type (' . $label . ').'
                : 'You ' . ($often ? 'often' : 'tend to') . ' Pass on homes of this type (' . $label . ').';
        }

        return $positive
            ? $label . ' appears ' . ($often ? 'often' : 'regularly') . ' in homes you Save.'
            : $label . ' appears ' . ($often ? 'often' : 'regularly') . ' in homes you Pass on.';
    }
}
