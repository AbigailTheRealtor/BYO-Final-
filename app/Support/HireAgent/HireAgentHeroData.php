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
 * The roles were once assumed to share one money key, `budget`, whose MEANING differed by role —
 * sale price, monthly rent, purchase budget, rental budget — which is why the label is decided
 * here. They do not share it. Buyer and tenant read `budget`; landlord reads
 * `desired_rental_amount` (M5.0a); seller reads `maximum_budget` (T3). Each correction was the
 * same discovery: the key the presenter asked for was not the key the role's form writes, so the
 * figure silently resolved to null on every real listing. See figure() for both.
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
     * Status labels the accessor can return, mapped to a presentation tone and icon.
     *
     * The MAPPING lives here; the LABEL does not. Every label below is produced by the role
     * model's own `status` accessor — this class never decides that a listing is expired, it only
     * decides what colour "Expired" is drawn in. An unrecognised label falls back to a neutral
     * tone rather than being suppressed, so a new status still renders its own text.
     */
    private const STATUS_PRESENTATION = [
        'Active'      => ['tone' => 'success', 'icon' => 'fa-solid fa-circle-check'],
        'Pending'     => ['tone' => 'warning', 'icon' => 'fa-solid fa-clock'],
        'Hired Agent' => ['tone' => 'primary', 'icon' => 'fa-solid fa-user'],
        'Expired'     => ['tone' => 'neutral', 'icon' => 'fa-solid fa-circle-xmark'],
        'Draft'       => ['tone' => 'neutral', 'icon' => 'fa-solid fa-pen-to-square'],
    ];

    /**
     * The single reader of the pilot flag.
     *
     * Both halves are required: the master switch, and the role allowlist. Centralised here so
     * that the component, the role view and the tests cannot disagree about what "enabled" means,
     * and so that enabling a further role is a config change reviewed in one place.
     *
     * This is the ONLY place in the application permitted to read `config('hire_agent_hero.*')`.
     */
    public static function redesignEnabledFor(string $role): bool
    {
        if (! config('hire_agent_hero.redesign_enabled', false)) {
            return false;
        }

        return in_array($role, (array) config('hire_agent_hero.redesign_roles', []), true);
    }

    /**
     * @return array{
     *     title: string,
     *     subtitle: ?string,
     *     listingId: ?string,
     *     figure: ?array{label: string, value: string},
     *     facts: array<int, array{label: string, value: string}>,
     *     status: ?string,
     *     statusTone: ?string,
     *     statusIcon: ?string,
     *     posted: ?string,
     *     listingType: ?string
     * }
     */
    public static function for(string $role, $auction): array
    {
        $meta   = $auction->get ?? null;
        $status = self::str($auction->status ?? null);

        return [
            'title'       => self::title($role, $auction, $meta),
            'subtitle'    => self::subtitle($role, $auction, $meta),
            'listingId'   => self::listingId($auction),
            'figure'      => self::figure($role, $meta),
            'facts'       => self::facts($role, $meta),
            'status'      => $status,
            'statusTone'  => $status === null ? null : (self::STATUS_PRESENTATION[$status]['tone'] ?? 'neutral'),
            'statusIcon'  => $status === null ? null : (self::STATUS_PRESENTATION[$status]['icon'] ?? 'fa-solid fa-circle-info'),
            'posted'      => self::postedDate($auction),
            'listingType' => self::listingType($meta),
        ];
    }

    /**
     * The listing's own public identifier, passed through untouched.
     *
     * `listing_id` is a native column on every role table and holds an ALPHANUMERIC value
     * (`LAA-PI6P1GNN`), not an integer. It is never cast, truncated, zero-padded or reformatted —
     * it is the string the owner sees quoted back to them in correspondence.
     */
    private static function listingId($auction): ?string
    {
        return self::str($auction->listing_id ?? null);
    }

    // ── title / subtitle ─────────────────────────────────────────────────────

    /**
     * Seller and Landlord describe a property, so the address leads and the listing title backs
     * it up. Buyer and Tenant describe a need, which has no address — their title leads.
     *
     * LANDLORD HAS NO `address` COLUMN. `landlord_agent_auctions` stores address in EAV meta, so
     * `$auction->address` is unconditionally null there and the effective chain has always been
     * meta address -> meta listing_title -> title. Landlord is listed separately below to say that
     * outright rather than leave a candidate that can never match. This is a statement of existing
     * behaviour, not a change to it: removing an always-null first candidate cannot alter which
     * value wins. Seller keeps `$auction->address` first, because Seller does have that column.
     */
    private static function title(string $role, $auction, $meta): string
    {
        if ($role === 'landlord') {
            $candidates = [
                self::str($meta->address ?? null),
                self::str($meta->listing_title ?? null),
                self::str($auction->title ?? null),
            ];
        } else {
            $candidates = $role === 'seller'
                ? [self::str($auction->address ?? null), self::str($meta->address ?? null), self::str($meta->listing_title ?? null), self::str($auction->title ?? null)]
                : [self::str($meta->listing_title ?? null), self::str($auction->title ?? null)];
        }

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
     * The headline figure, formatted with the helper the views already use.
     *
     * ── M5.0a: LANDLORD READS A DIFFERENT KEY ────────────────────────────────────────────────
     *
     * Every role used to read `budget`. For landlord that key is never written — the rent lives
     * in `desired_rental_amount` — so the landlord figure resolved to null on every listing and
     * the slot silently never rendered. That was a wiring defect, not missing data: the rent was
     * there the whole time, under a name this presenter did not ask for.
     *
     * Only landlord was re-pointed then. `budget` is confirmed correct for tenant, and buyer
     * stores its own budget keys; seller had the same class of defect and was left recorded as
     * "SELLER'S HEADLINE FIGURE IS STILL DEAD" for whoever migrated seller next.
     *
     * ── T3: SELLER READS `maximum_budget`, AND THE OLD NOTE NAMED THE WRONG KEY ──────────────
     *
     * That note said seller's price "lives in the native `min_price` column". It does not. The
     * column exists on `seller_agent_auctions` and is written by NOTHING in the Hire Agent seller
     * flow and read by NOTHING in the seller detail views — verified by grep over
     * app/Http/Livewire/HireSellerAgent and resources/views/hire_seller_agent before this change.
     * Acting on that note would have swapped one dead key for another.
     *
     * The seller sale price is `maximum_budget`. It is written by SellerAgentAuction::save() and
     * SellerAgentAuctionEdit::save(), and it is the value the detail body already renders as
     * "Desired Sale Price". `budget` meanwhile has no `wire:model` binding anywhere in the seller
     * tabs — its saveMeta() call sits in a lease-oriented block (unit_buildings, lease_for,
     * lease_by) that is residue from the landlord flow — so it is never populated on a seller
     * listing and the figure resolved to null on every real one.
     *
     * SELLER DOES NOT FALL BACK TO `budget`. A fallback would re-introduce the dead key as a
     * silent second source and make it impossible to tell, from a rendered page, which one
     * answered. If `maximum_budget` is absent the slot is omitted, which is what every other role
     * does with a missing figure.
     *
     * THIS IS VISIBLE WITH BOTH FLAGS OFF, deliberately. The presenter feeds the legacy hero as
     * well as the redesigned one, so seller listings gain a Listing Price line they should always
     * have had. That is the defect being fixed, not a side effect to be gated — flag-gating a
     * correction would leave the wrong behaviour as the default.
     *
     * The label stays the short-form "Listing Price". The detail body's own "Desired Sale Price"
     * row is a different surface with a different width budget and is not renamed here.
     */
    private static function figure(string $role, $meta): ?array
    {
        if ($role === 'landlord') {
            return self::landlordRentFigure($meta);
        }

        $raw = $role === 'seller'
            ? ($meta->maximum_budget ?? null)
            : ($meta->budget ?? null);

        if (! ListingDisplayHelper::hasValue($raw)) {
            return null;
        }

        return [
            'label' => match ($role) {
                'seller'   => 'Listing Price',
                'buyer'    => 'Purchase Budget',
                'tenant'   => 'Rental Budget',
                default    => 'Budget',
            },
            'value' => ListingDisplayHelper::fmtMoneyWhole($raw),
        ];
    }

    /**
     * Landlord rent, labelled by the frequency the owner actually chose.
     *
     * "Monthly Rent" was hard-coded before. The stored `lease_amount_frequency` is Monthly on most
     * listings but Annually and Seasonal on others, so a fixed label would have mislabelled real
     * records — stating a monthly figure for an annual amount, which is worse than showing no
     * label at all. The frequency is read, never computed, and an unrecognised or absent value
     * falls back to the unqualified "Rent" rather than guessing.
     */
    private static function landlordRentFigure($meta): ?array
    {
        $raw = $meta->desired_rental_amount ?? null;

        if (! ListingDisplayHelper::hasValue($raw)) {
            return null;
        }

        return [
            'label' => self::rentLabel(self::str($meta->lease_amount_frequency ?? null)),
            'value' => ListingDisplayHelper::fmtMoneyWhole($raw),
        ];
    }

    /** Stored frequency → figure label. Anything unrecognised stays unqualified. */
    private static function rentLabel(?string $frequency): string
    {
        return self::RENT_LABELS[strtolower(trim((string) $frequency))] ?? 'Rent';
    }

    // ── the secondary facts ──────────────────────────────────────────────────

    /**
     * Buyer/Tenant get their preferred area; Seller/Landlord get no first fact at all.
     *
     * ── M5.0b: AGENT COMPENSATION IS NOT PUBLIC ──────────────────────────────────────────────
     *
     * THIS IS A PRODUCT-RULE CHANGE, NOT A TEST ADJUSTMENT. Hire Agent listings must not publicly
     * display agent compensation. What stood here was the opposite: seller and landlord heroes
     * carried a "Broker Compensation" / "Leasing Compensation" fact, sourced from the first
     * non-empty of `commission_structure`, `purchase_fee_type`, `lease_fee_type` and
     * `brokerage_relationship`.
     *
     * It was published to EVERYONE. The hero primitive cannot see who is looking — by design, and
     * enforced by its guard tests — so there was no viewer distinction to fall back on. A
     * logged-out visitor read the compensation arrangement straight off the page.
     *
     * The exposure was NOT limited to the redesigned hero. This presenter feeds both treatments,
     * so the legacy hero published it too, on every role, behind no feature flag. Removing it here
     * closes both at once, which is why the fix belongs in the presenter rather than in a view.
     *
     * The fallback chain is gone with it, and deliberately so — `brokerage_relationship` holds a
     * relationship type ("Single Agent"), not compensation at all, so it could render a
     * non-compensation value under a compensation label.
     *
     * Property roles are therefore left with `Property Type` alone. That asymmetry is intended: a
     * property listing's remaining public facts describe the property, and nothing was substituted
     * for the removed row. Do not reintroduce a compensation field here, or anywhere else public,
     * without an explicit product decision reversing this one.
     */
    private static function facts(string $role, $meta): array
    {
        $facts = [];

        if (! in_array($role, ['seller', 'landlord'], true)) {
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
     * Stored lease frequencies, lower-cased, mapped to the label the figure carries.
     *
     * A closed list on purpose: a frequency nobody has reviewed should degrade to "Rent" rather
     * than have a label invented for it.
     */
    private const RENT_LABELS = [
        'monthly'  => 'Monthly Rent',
        'annually' => 'Annual Rent',
        'seasonal' => 'Seasonal Rent',
    ];

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
