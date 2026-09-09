<?php

namespace App\Services\ListingImport\Mls;

/**
 * Tier 2b — the MLS's own account of the LISTING, as opposed to the property.
 *
 * MLS number, status, when it came on the market, how long it has been there,
 * what it first asked, when it last changed, and the unbranded virtual tour.
 * Every one of these is already published on `/stellar/property/{listingKey}`;
 * before this class none of them survived an import, which is most of the
 * "search has / import loses" list in the 2026-09-04 audit.
 *
 * WHY IT IS NOT PART OF THE PROPERTY-FACTS PRESENTER
 * --------------------------------------------------
 * Two reasons, and the second is the load-bearing one.
 *
 * It describes a different subject. "Days on Market: 16" is not a fact about a
 * building; it is a fact about an offer to sell one, and a reader scanning
 * construction and utilities is not looking for it.
 *
 * And its display gate is different. A property fact is shown when it is
 * populated. Everything here is shown only while the feed still permits the
 * listing to be displayed at all — {@see MlsDisplayPermissions::listingDisplayable()}
 * — because status, price history and market timing are precisely what a
 * withdrawn listing stops authorising. Mixing the two groups would mean one
 * `if` deciding both, and the wrong one would eventually win.
 *
 * BRANDED TOURS ARE NOT HERE. `VirtualTourURLBranded` carries the listing
 * brokerage's own marketing and is excluded in the catalog; the unbranded tour
 * is the one syndication permits.
 */
final class MlsListingContextPresenter
{
    /** @var array<string, array<string,string>> */
    public const FIELDS = MlsFieldCatalog::LISTING_CONTEXT;

    /**
     * Values that are dates, and should print as dates rather than as the
     * feed's ISO-8601 timestamps. A consumer reading "Listed On:
     * 2026-07-14T02:07:24.770Z" learns less than one reading "14 Jul 2026".
     */
    private const DATE_FIELDS = [
        'ListingContractDate', 'OnMarketDate', 'OffMarketDate', 'ExpirationDate',
        'CloseDate', 'PurchaseContractDate', 'PriceChangeTimestamp',
        'StatusChangeTimestamp', 'ModificationTimestamp', 'PhotosChangeTimestamp',
        'STELLAR_BOMDate', 'STELLAR_ComingSoonDate', 'STELLAR_ExpectedOnMarketDate',
    ];

    /** Values that are money and should print with a currency prefix. */
    private const MONEY_FIELDS = [
        'OriginalListPrice', 'PreviousListPrice',
    ];

    /**
     * @param array<string,mixed> $raw
     * @return array<string, list<array{key:string,label:string,value:string,url:?string}>>
     */
    public function present(array $raw, ?MlsDisplayPermissions $permissions = null): array
    {
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

                $isUrl = in_array($field, MlsFieldCatalog::URL_FIELDS, true);

                if ($isUrl && ! $this->isSafeUrl($value)) {
                    continue;
                }

                $rows[] = [
                    'key'   => $field,
                    'label' => $label,
                    'value' => $this->display($field, $value),
                    'url'   => $isUrl ? $value : null,
                ];
            }

            if ($rows !== []) {
                $sections[$section] = $rows;
            }
        }

        return $sections;
    }

    private function display(string $field, string $value): string
    {
        if (in_array($field, self::MONEY_FIELDS, true) && is_numeric($value)) {
            return '$' . number_format((float) $value, 0);
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            $timestamp = strtotime($value);

            // A value we cannot parse is shown exactly as the feed sent it
            // rather than as a guess or as an epoch date.
            return $timestamp === false ? $value : date('j M Y', $timestamp);
        }

        if ($field === 'VirtualTourURLUnbranded' || $field === 'STELLAR_VirtualTourURLUnbranded2') {
            return 'View virtual tour';
        }

        return $value;
    }

    /**
     * Only absolute https URLs reach an `href`.
     *
     * Same rule, and the same reason, as {@see \App\Services\ListingImport\Media\MlsMediaPolicy::allowsUrl()}:
     * a feed value that reaches an attribute must never be able to carry
     * anything but a fetch, and http on an https page is a mixed-content block
     * that looks like our bug.
     */
    private function isSafeUrl(string $value): bool
    {
        if (! str_starts_with(strtolower($value), 'https://')) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }
}
