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
 * Only a listing Taste RAISED, on evidence of its own, is explained — and only by
 * what it has that the customer tends to Save. A listing that moved only because
 * a neighbour moved says nothing (there is nothing true to say about it), and a
 * listing Taste LOWERED says nothing either: the card is there to help the
 * customer judge the home, not to tell them "you usually Pass on this".
 */
final class TasteRerankExplanation
{
    public const MAX_LINES = 2;

    public const HEADLINE_RAISED = 'Fits things you tend to Save';

    /**
     * @return array{headline: string, lines: list<string>}|null
     */
    public static function for(?TasteRerankInfluence $influence): ?array
    {
        if ($influence === null || ! $influence->raised()) {
            return null;
        }

        // Only what it has that the customer tends to Save — never a Pass pattern.
        $relevant = array_values(array_filter(
            $influence->contributions,
            static fn (TasteRerankContribution $c): bool => $c->isPositive(),
        ));

        if ($relevant === []) {
            return null;
        }

        $lines = [];

        foreach (array_slice($relevant, 0, self::MAX_LINES) as $contribution) {
            $lines[] = self::line($contribution->signal);
        }

        return [
            'headline' => self::HEADLINE_RAISED,
            'lines'    => $lines,
        ];
    }

    private static function line(TasteSignal $signal): string
    {
        $often = $signal->confidence === TasteConfidence::Established;
        $label = $signal->label;

        if ($signal->hasSource(TasteSource::StatedReason)) {
            return $label . ' is a reason you have picked when Saving homes.';
        }

        if ($signal->dimension === TasteDimension::PropertySubtype) {
            return 'You ' . ($often ? 'often' : 'tend to') . ' Save homes of this type (' . $label . ').';
        }

        return $label . ' appears ' . ($often ? 'often' : 'regularly') . ' in homes you Save.';
    }
}
