<?php

namespace App\Support\HireAgent;

use App\Helpers\ListingDisplayHelper;

/**
 * Hire Agent Listing Detail Framework — the hero's role-specific data contract.
 *
 * Milestone 4. The four Hire Agent detail views share a layout but describe four different
 * things: a property for sale, a property to let, a buyer's brief, a tenant's brief. The hero is
 * one component; the values it shows are role-specific. Rather than branch on role inside the
 * Blade, each role's fields are resolved here and the component renders whatever it is handed.
 *
 * WHY A PRESENTER AND NOT A CONTROLLER CHANGE. Milestone 4 says to adapt presentation to existing
 * controller data and to change controllers only where narrowly necessary. Everything below is
 * read from `$auction->get` — the EAV meta accessor the views already use — so no controller,
 * route, model or query changes, and no new queries: the meta relation is already loaded by the
 * time a view renders. This class is pure: given the same auction it returns the same array and
 * touches nothing.
 *
 * WHAT IT MAY NOT DO. Milestone 4 forbids inventing financial calculations, so nothing here
 * computes, sums, converts or infers a figure. Every value is an existing stored field passed
 * through the existing ListingDisplayHelper formatters the role views already use — the same
 * fmtMoneyWhole() the Tenant view calls on the very same `budget` key. Where a role has no
 * meaningful value for a slot, the slot is omitted rather than filled with a placeholder or a
 * derived stand-in.
 *
 * The four roles share one money key, `budget`, whose MEANING differs by role — sale price,
 * monthly rent, purchase budget, rental budget. That is why the label is role-specific while the
 * source key is not; the label is the only thing being decided here.
 *
 * FORBIDDEN BY THE GOVERNING RULES, and absent by construction: no countdown, no remaining time,
 * no auction duration, no "bidding ends", no competing-proposal count, rank, highest proposal or
 * last bidder. The only date exposed is the listing's own posted date. `auction_type` is
 * surfaced as a plain listing-type label and is suppressed when it names the retired bidding
 * period, so a legacy row cannot reintroduce that vocabulary through the new hero.
 */
final class HireAgentHeroData
{
    public const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    /** Legacy auction_type values that must never surface as a listing-type label. */
    private const RETIRED_TYPE_LABELS = ['bidding period', 'auction (timer)'];

    /**
     * @return array{
     *     title: string,
     *     subtitle: ?string,
     *     figure: ?array{label: string, value: string},
     *     facts: array<int, array{label: string, value: string}>,
     *     status: ?string,
     *     posted: ?string,
     *     listingType: ?string
     * }
     */
    public static function for(string $role, $auction): array
    {
        $meta = $auction->get ?? null;

        return [
            'title'       => self::title($role, $auction, $meta),
            'subtitle'    => self::subtitle($role, $auction, $meta),
            'figure'      => self::figure($role, $meta),
            'facts'       => self::facts($role, $meta),
            'status'      => self::str($auction->status ?? null),
            'posted'      => self::postedDate($auction),
            'listingType' => self::listingType($meta),
        ];
    }

    // ── title / subtitle ─────────────────────────────────────────────────────

    /**
     * Seller and Landlord describe a property, so the address leads and the listing title backs
     * it up. Buyer and Tenant describe a need, which has no address — their title leads.
     */
    private static function title(string $role, $auction, $meta): string
    {
        $candidates = in_array($role, ['seller', 'landlord'], true)
            ? [self::str($auction->address ?? null), self::str($meta->address ?? null), self::str($meta->listing_title ?? null), self::str($auction->title ?? null)]
            : [self::str($meta->listing_title ?? null), self::str($auction->title ?? null)];

        foreach ($candidates as $c) {
            if ($c !== null) {
                return $c;
            }
        }

        return 'Listing Details';
    }

    private static function subtitle(string $role, $auction, $meta): ?string
    {
        $title = self::title($role, $auction, $meta);

        $candidates = in_array($role, ['seller', 'landlord'], true)
            ? [self::str($meta->listing_title ?? null), self::str($auction->title ?? null)]
            : [self::str($auction->title ?? null)];

        foreach ($candidates as $c) {
            if ($c !== null && $c !== $title) {
                return $c;
            }
        }

        return null;
    }

    // ── the headline figure ──────────────────────────────────────────────────

    /**
     * One stored key, `budget`, formatted with the helper the views already use. Only the label
     * differs by role, because only the meaning differs.
     */
    private static function figure(string $role, $meta): ?array
    {
        $raw = $meta->budget ?? null;

        if (! ListingDisplayHelper::hasValue($raw)) {
            return null;
        }

        return [
            'label' => match ($role) {
                'seller'   => 'Listing Price',
                'landlord' => 'Monthly Rent',
                'buyer'    => 'Purchase Budget',
                'tenant'   => 'Rental Budget',
                default    => 'Budget',
            },
            'value' => ListingDisplayHelper::fmtMoneyWhole($raw),
        ];
    }

    // ── the secondary facts ──────────────────────────────────────────────────

    /**
     * Seller/Landlord get a compensation summary; Buyer/Tenant get their preferred area. Both are
     * read straight from stored fields — no derivation.
     */
    private static function facts(string $role, $meta): array
    {
        $facts = [];

        if (in_array($role, ['seller', 'landlord'], true)) {
            $comp = self::compensationSummary($meta);
            if ($comp !== null) {
                $facts[] = [
                    'label' => $role === 'landlord' ? 'Leasing Compensation' : 'Broker Compensation',
                    'value' => $comp,
                ];
            }
        } else {
            $area = self::preferredArea($meta);
            if ($area !== null) {
                $facts[] = ['label' => 'Preferred Area', 'value' => $area];
            }
        }

        $propType = self::str($meta->property_type ?? null);
        if ($propType !== null) {
            $facts[] = ['label' => 'Property Type', 'value' => $propType];
        }

        return $facts;
    }

    /**
     * The compensation *structure label* the owner already chose — not a computed fee.
     *
     * The Broker Compensation card carries dozens of interdependent fields (flat vs percentage vs
     * combo, lease vs purchase, timing, termination). Summarising those into a number would be
     * exactly the invented financial calculation this milestone forbids, and it would be wrong
     * often enough to matter. The hero therefore names the structure and leaves the detail to the
     * card, which is where the owner entered it.
     */
    private static function compensationSummary($meta): ?string
    {
        foreach (['commission_structure', 'purchase_fee_type', 'lease_fee_type', 'brokerage_relationship'] as $key) {
            $value = self::str($meta->{$key} ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /** Cities, else counties, else states — reusing the helper's list normalisation. */
    private static function preferredArea($meta): ?string
    {
        foreach (['cities', 'counties', 'states'] as $key) {
            $list = ListingDisplayHelper::normalizeListDeduped($meta->{$key} ?? null);
            $list = ListingDisplayHelper::stripStateSuffixList($list);
            $list = array_values(array_filter(array_map('trim', $list), fn ($v) => $v !== ''));

            if ($list !== []) {
                return count($list) > 3
                    ? implode(', ', array_slice($list, 0, 3)) . ' +' . (count($list) - 3) . ' more'
                    : implode(', ', $list);
            }
        }

        return null;
    }

    // ── date + type ──────────────────────────────────────────────────────────

    /**
     * The posted date. Note what this is NOT: it is when the listing went up, a fact about the
     * past. No expiry, no remaining time, nothing that counts.
     */
    private static function postedDate($auction): ?string
    {
        $created = $auction->created_at ?? null;
        if (empty($created)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($created)->format('F j, Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A plain listing-type label, with the retired bidding-period vocabulary filtered out.
     *
     * Milestone 3 removed the timer, but `auction_type` still holds 'Bidding Period' on legacy
     * rows. Echoing it here would put that vocabulary back on the page through the new hero, so
     * those values resolve to no label at all.
     */
    private static function listingType($meta): ?string
    {
        $type = self::str($meta->auction_type ?? null);

        if ($type === null || in_array(strtolower(trim($type)), self::RETIRED_TYPE_LABELS, true)) {
            return null;
        }

        return $type;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Trimmed string, or null for anything the display helper counts as absent. */
    private static function str($value): ?string
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        if (! ListingDisplayHelper::hasValue($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
