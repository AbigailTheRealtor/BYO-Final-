<?php

namespace App\Support\OfferListing;

/**
 * The rules a listing page follows when it prints a term that has follow-up
 * questions underneath it.
 *
 * WHY A CLASS AND NOT FOUR BLADE CLOSURES
 * ---------------------------------------
 * "Your Terms" is one field set with one storage shape, written by three entry
 * paths (manual Create, manual Edit, MLS Quick Import) and read by the seller
 * and landlord listing pages. The parent/child rule — show a follow-up answer
 * only when the parent selection makes it applicable AND the listing actually
 * holds an answer — was previously spelled out inline at each of the ~40 places
 * a branch is rendered, in slightly different words each time. That is how the
 * page came to show an Assumable Mortgage block for a cash-only listing whose
 * seller had once, briefly, selected "Assumable": the condition asked
 * `$hasAssumable || $str('assumable_loan_type')`, so ANY surviving child value
 * re-opened a branch the seller had since closed.
 *
 * The rule now lives here, once:
 *
 *   THE PARENT DECIDES WHETHER A BRANCH IS SHOWN AT ALL.
 *   A child value alone never re-opens a branch. A stale answer left behind by
 *   a previous parent selection is data we still hold and deliberately do not
 *   publish — the seller's current answer is the one the page states.
 *
 *   THE CHILD DECIDES WHETHER ITS OWN ROW IS SHOWN.
 *   Null, empty string, whitespace, an empty array and an unanswered select all
 *   render nothing, so an applicable branch never prints a blank row.
 *
 * Nothing here reads or writes storage. It is display logic only, and the
 * canonical fields keep their values whatever it decides.
 */
final class ConditionalTerms
{
    /**
     * Did the seller/landlord actually choose this option?
     *
     * Multi-selects arrive as arrays from a live component, as JSON strings from
     * EAV meta, and occasionally as a bare string from an older row. All three
     * mean the same thing and all three are accepted; nothing else is.
     */
    public static function chose(mixed $selection, string $option): bool
    {
        foreach (self::toList($selection) as $value) {
            if (strcasecmp(trim($value), $option) === 0) {
                return true;
            }
        }

        return false;
    }

    /** True when any of the given options was chosen. */
    public static function choseAny(mixed $selection, array $options): bool
    {
        foreach ($options as $option) {
            if (self::chose($selection, (string) $option)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A multi-select's answers with "Other" replaced by what was typed for it.
     *
     * The "Other" free-text box is the most common sub-question in this form,
     * and printing the literal word "Other" instead of the answer is the same
     * bug in forty places. When nothing was typed the literal option survives —
     * the seller did choose it, and we do not know what they meant.
     *
     * @return list<string>
     */
    public static function withOther(mixed $selection, mixed $otherText): array
    {
        $other = self::text($otherText);

        return array_values(array_map(
            static fn (string $value) => ($other !== '' && strcasecmp(trim($value), 'Other') === 0)
                ? $other
                : $value,
            self::toList($selection),
        ));
    }

    /**
     * A single-select's answer with "Other" replaced by what was typed.
     *
     * The scalar sibling of withOther(). Returns '' when nothing was answered,
     * so it drops out of a row helper on its own.
     */
    public static function valueWithOther(mixed $selection, mixed $otherText): string
    {
        $value = self::text($selection);
        $other = self::text($otherText);

        return (strcasecmp($value, 'Other') === 0 && $other !== '') ? $other : $value;
    }

    /**
     * Is this child answer worth printing?
     *
     * The second half of the rule: an applicable branch still prints nothing for
     * a question the user skipped.
     */
    public static function answered(mixed $value): bool
    {
        // Routed through toList() rather than a truthiness test so the stored
        // shapes agree: an empty multi-select reaches a view as [], as the string
        // '[]', or as ''. All three mean "not answered", and a plain emptiness
        // check on the string form would publish an empty section heading.
        return self::toList($value) !== [];
    }

    /**
     * A money-or-percentage answer, formatted by the $ / % toggle beside it.
     *
     * Deposits, assignment fees, assumption fees, gap payments and seller
     * financing all pair an amount with a type control, and the page used to
     * format several of them as dollars unconditionally — so a seller asking for
     * a 3% initial deposit published a request for $3.
     *
     * Returns null when there is no amount, which is what a row helper needs in
     * order to omit the row entirely.
     */
    public static function amount(mixed $value, mixed $type = null): ?string
    {
        $raw = self::text($value);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9.]/', '', $raw);

        if ($digits === '' || ! is_numeric($digits)) {
            return $raw;
        }

        $number = (float) $digits;

        if (self::text($type) === '%') {
            return (floor($number) == $number ? (string) (int) $number : (string) $number) . '%';
        }

        return '$' . number_format($number, 0);
    }

    /**
     * Normalise any of the three stored shapes into a list of non-empty strings.
     *
     * @return list<string>
     */
    public static function toList(mixed $selection): array
    {
        if ($selection === null) {
            return [];
        }

        if (is_string($selection)) {
            $trimmed = trim($selection);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            // Meta can hold a doubly-encoded array; one more pass is enough and
            // anything still not an array is treated as the single answer it is.
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $selection = is_array($decoded) ? $decoded : [$trimmed];
        }

        if (! is_array($selection)) {
            $selection = [$selection];
        }

        $out = [];

        foreach ($selection as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $text = self::text($value);

            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    private static function text(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return is_bool($value) ? ($value ? 'Yes' : '') : '';
        }

        return trim((string) $value);
    }
}
