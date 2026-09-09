<?php

namespace App\Services\ListingImport\Mls;

/**
 * Where each stored MLS section belongs on a published listing page.
 *
 * WHAT THIS FIXES
 * ---------------
 * The supplemental payload used to render as ONE dense block titled "MLS
 * Property Details", in its own typography, immediately below the listing's own
 * "Property Details" card. Nothing was duplicated at the FIELD level — the
 * import already suppresses a Tier-1 fact that reached an editable field, so the
 * two blocks never printed the same key — but the reader met two competing
 * "Property Details" presentations of the same house, one of which looked like a
 * report pasted onto the page. That is the duplication this class removes: not
 * repeated values, a repeated PRESENTATION.
 *
 * So each stored section is given a SLOT on the page:
 *
 *   SLOT_PROPERTY  merged into the listing's own Property Details card, under an
 *                  "MLS Property Details" sub-heading — one Property Details
 *                  card on the page, not two.
 *   SLOT_TAX_HOA   merged into the Tax / Legal / HOA card, for the same reason:
 *                  the canonical card already answers "is there an HOA, what
 *                  does it cost", and the MLS rows extend that answer rather
 *                  than restating it somewhere else.
 *   SLOT_FACTS     its own section-card — Interior, Exterior, Waterfront /
 *                  Views, Lease / Rental and the rest have no canonical card to
 *                  collide with, so they become ordinary cards in the page's own
 *                  visual system.
 *   SLOT_CONTACTS  the agent, brokerage, association and open-house cards.
 *   SLOT_MLS_INFO  the MLS's own bookkeeping, rendered last, beside the
 *                  attribution block it belongs with.
 *
 * NOTHING CAN BE DROPPED BY BEING UNRECOGNISED.
 * A title this class has never heard of still lands in a slot, chosen from the
 * section's own group. Widening MlsFieldCatalog therefore publishes the new
 * section automatically instead of silently losing it, which is the failure mode
 * a title allow-list would have.
 *
 * THE ONE THING THAT IS DROPPED, AND WHY
 * --------------------------------------
 * A related-resource row whose VALUE already appears in the contacts group.
 * `MlsRelatedResources::rowsFrom()` already tries to do this at import time, but
 * it compares label AND value — and the related sections deliberately use their
 * own labels ("Email", "Direct Phone", "Board of REALTORS®") for the same facts
 * the contacts section calls "Agent Email", "Agent Phone", "Agent Board of
 * REALTORS®". So the comparison never matched and the same phone number reached
 * the page three times. Comparing values catches it. It is done HERE, at read
 * time, rather than there, because the duplicate rows are already sitting in
 * every blob written before today and a write-time fix would not reach them.
 *
 * Contacts rows are never deduplicated against each other: an agent phone and a
 * brokerage phone that happen to match are two facts, and collapsing them would
 * assert something the feed did not.
 */
final class MlsDetailLayout
{
    public const SLOT_PROPERTY = 'property';
    public const SLOT_TAX_HOA  = 'tax_hoa';
    public const SLOT_FACTS    = 'facts';
    public const SLOT_CONTACTS = 'contacts';
    public const SLOT_MLS_INFO = 'mls_info';

    public const SLOTS = [
        self::SLOT_PROPERTY,
        self::SLOT_TAX_HOA,
        self::SLOT_FACTS,
        self::SLOT_CONTACTS,
        self::SLOT_MLS_INFO,
    ];

    /**
     * Sections that must NOT become a card of their own, because the listing
     * page already has one making the same kind of claim.
     *
     * @var array<string,string>
     */
    private const TITLE_SLOTS = [
        'Property Details'  => self::SLOT_PROPERTY,
        'HOA / Association' => self::SLOT_TAX_HOA,
        'Taxes / Financial' => self::SLOT_TAX_HOA,
    ];

    /** Fallback for any title this class does not know: decided by group. */
    private const GROUP_SLOTS = [
        'facts'    => self::SLOT_FACTS,
        'contacts' => self::SLOT_CONTACTS,
        'related'  => self::SLOT_CONTACTS,
        'listing'  => self::SLOT_MLS_INFO,
    ];

    /** Card icons, in the page's existing Font Awesome vocabulary. */
    private const ICONS = [
        'Interior'                    => 'fa-solid fa-couch',
        'Exterior'                    => 'fa-solid fa-house-chimney',
        'Parking / Garage'            => 'fa-solid fa-car',
        'Pool / Spa'                  => 'fa-solid fa-person-swimming',
        'Waterfront / Views'          => 'fa-solid fa-water',
        'Utilities'                   => 'fa-solid fa-bolt',
        'Schools'                     => 'fa-solid fa-school',
        'Lease / Rental'              => 'fa-solid fa-file-signature',
        'Commercial / Business'       => 'fa-solid fa-briefcase',
        'Listing Agent / Brokerage'   => 'fa-solid fa-id-badge',
        'Listing Agent Contact'       => 'fa-solid fa-id-badge',
        'Co-Listing Agent Contact'    => 'fa-solid fa-id-badge',
        'Brokerage Contact'           => 'fa-solid fa-building',
        'Co-Listing Brokerage Contact' => 'fa-solid fa-building',
        'HOA / Management Contact'    => 'fa-solid fa-address-book',
        'Open Houses'                 => 'fa-solid fa-door-open',
        'MLS Information'             => 'fa-solid fa-database',
    ];

    /** @param array<string, list<array{title:string,group:string,rows:list<array<string,mixed>>}>> $slots */
    private function __construct(private readonly array $slots)
    {
    }

    public static function from(mixed $details): self
    {
        $slots = array_fill_keys(self::SLOTS, []);

        if (! $details instanceof MlsSupplementalDetails || $details->isEmpty()) {
            return new self($slots);
        }

        $contactValues = self::contactValues($details);

        foreach ($details->sections as $section) {
            $title = (string) ($section['title'] ?? '');
            $group = (string) ($section['group'] ?? 'facts');
            $rows  = is_array($section['rows'] ?? null) ? $section['rows'] : [];

            if ($group === 'related') {
                $rows = array_values(array_filter(
                    $rows,
                    static fn (array $row) => ! isset($contactValues[self::normalise($row['value'] ?? '')]),
                ));
            }

            if ($title === '' || $rows === []) {
                continue;
            }

            $slot = self::TITLE_SLOTS[$title]
                ?? self::GROUP_SLOTS[$group]
                ?? self::SLOT_FACTS;

            $slots[$slot][] = ['title' => $title, 'group' => $group, 'rows' => $rows];
        }

        return new self($slots);
    }

    public static function empty(): self
    {
        return new self(array_fill_keys(self::SLOTS, []));
    }

    /** @return list<array{title:string,group:string,rows:list<array<string,mixed>>}> */
    public function slot(string $slot): array
    {
        return $this->slots[$slot] ?? [];
    }

    public function has(string $slot): bool
    {
        return $this->slot($slot) !== [];
    }

    public function isEmpty(): bool
    {
        foreach (self::SLOTS as $slot) {
            if ($this->has($slot)) {
                return false;
            }
        }

        return true;
    }

    public function rowCount(): int
    {
        $count = 0;

        foreach (self::SLOTS as $slot) {
            foreach ($this->slot($slot) as $section) {
                $count += count($section['rows']);
            }
        }

        return $count;
    }

    public static function iconFor(string $title): string
    {
        return self::ICONS[$title] ?? 'fa-solid fa-circle-info';
    }

    /**
     * Every value already printed by the contacts group, normalised for compare.
     *
     * @return array<string,true>
     */
    private static function contactValues(MlsSupplementalDetails $details): array
    {
        $seen = [];

        foreach ($details->group('contacts') as $section) {
            foreach ($section['rows'] as $row) {
                $value = self::normalise($row['value'] ?? '');

                if ($value !== '') {
                    $seen[$value] = true;
                }
            }
        }

        return $seen;
    }

    private static function normalise(mixed $value): string
    {
        return is_scalar($value) ? strtolower(trim((string) $value)) : '';
    }
}
