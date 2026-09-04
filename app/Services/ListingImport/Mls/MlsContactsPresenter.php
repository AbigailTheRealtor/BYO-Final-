<?php

namespace App\Services\ListingImport\Mls;

/**
 * Tier 2c — listing agent, brokerage, and association/management contact data.
 *
 * WHY THIS IS SEPARATE, AND WHY IT IS ALLOWED AT ALL
 * --------------------------------------------------
 * The 2026-09-04 audit found this codebase asserting two opposite positions on
 * the same data at the same time: `MlsPropertyDetailsPresenter::EXCLUDED`
 * withheld `ListOfficeName` as "brokerage identity", while
 * `/stellar/property/{listingKey}` rendered it publicly through
 * `x-stellar.property-office`. One of those had to be wrong.
 *
 * The resolution is that brokerage attribution is not a leak — IDX display
 * rules generally REQUIRE the listing brokerage to be named on any displayed
 * listing, which is why the Stellar page already does it. So the data is
 * rendered, and the boundary moves from "which fields" to "under what
 * permission": everything here is gated on
 * {@see MlsDisplayPermissions::listingDisplayable()}, so a listing the feed has
 * withdrawn from IDX renders no agent, no brokerage, and no phone number.
 *
 * WHAT IS STILL WITHHELD
 * ----------------------
 * The counterparty. `BuyerAgent*`, `CoBuyerAgent*` and `BuyerOffice*` are in
 * {@see MlsFieldCatalog::RESTRICTED} and never reach this class: on a sold
 * record those columns name the people on the other side of somebody else's
 * transaction, which is not attribution and has no display rationale. So are
 * escrow and title contacts, and the showing call-centre number, which is access
 * routing rather than attribution.
 *
 * THE ASSOCIATION GROUP IS NOT THE SAME AS THE AGENT GROUP
 * --------------------------------------------------------
 * `AssociationName` is frequently a named individual sitting beside an
 * `AssociationPhone` — the audit found values like "Jerilyn Smith" and
 * "Karla Baumann <kscpoa@aol.com>". It is genuine, useful, publicly-syndicated
 * HOA contact information rather than a private leak, so it renders; it lives in
 * its own section so that a future decision to withhold personal HOA contacts
 * can be made without touching brokerage attribution, and so a reader is never
 * shown a person's name under a heading that implies they are the listing agent.
 */
final class MlsContactsPresenter
{
    /** @var array<string, array<string,string>> */
    public const FIELDS = MlsFieldCatalog::CONTACTS;

    /**
     * `ListAgentKey`, `ListOfficeKey` and their co-list twins are deliberately
     * NOT in this presenter's allow-list. They are opaque 32-hex provider
     * handles — "Agent Key: 15c8b5f049e923c990a16e7d51222739" is noise to a
     * buyer and publishes the shape of our integration — so the catalog
     * classifies them as INTERNAL rather than as contact data.
     *
     * @param array<string,mixed> $raw
     * @return array<string, list<array{key:string,label:string,value:string,link:?string}>>
     */
    public function present(
        array $raw,
        ?MlsDisplayPermissions $permissions = null,
    ): array {
        $permissions ??= MlsDisplayPermissions::fromRecord($raw);

        if (! $permissions->listingDisplayable()) {
            return [];
        }

        $sections = [];

        foreach (self::FIELDS as $section => $fields) {
            $rows = [];

            foreach ($fields as $field => $label) {
                $value = MlsValueFormatter::format($raw[$field] ?? null);

                if ($value === null) {
                    continue;
                }

                $rows[] = [
                    'key'   => $field,
                    'label' => $label,
                    'value' => $value,
                    'link'  => $this->link($field, $value),
                ];
            }

            if ($rows !== []) {
                $sections[$section] = $rows;
            }
        }

        return $sections;
    }

    /**
     * An href for a value that is genuinely one, or null.
     *
     * Every kind is validated before it becomes an attribute. A feed that sends
     * `javascript:` in an email column produces a plain text row, not a link —
     * the value is still shown, it simply is not clickable.
     */
    private function link(string $field, string $value): ?string
    {
        $kind = MlsFieldCatalog::CONTACT_LINK_FIELDS[$field] ?? null;

        if ($kind === null) {
            return null;
        }

        if ($kind === 'mailto') {
            // Stellar sometimes packs a display name around the address:
            // "Karla Baumann <kscpoa@aol.com>". Link the address, show the lot.
            if (preg_match('/<([^>]+)>/', $value, $m)) {
                $value = trim($m[1]);
            }

            return filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $value : null;
        }

        $url = str_starts_with(strtolower($value), 'http') ? $value : 'https://' . $value;

        if (! str_starts_with(strtolower($url), 'https://')) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $url : null;
    }
}
