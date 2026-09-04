<?php

namespace App\Services\ListingImport\Mls;

use App\Services\ListingImport\MlsPropertyDetailsPresenter;

/**
 * The supplemental MLS payload that is persisted with an imported listing, and
 * the only shape any view is allowed to render.
 *
 * WHAT THIS FIXES
 * ---------------
 * The quick-import review screen has always assembled a rich set of MLS
 * attributes and then thrown them away: `MlsQuickImportResult::$details` was
 * built, shown once, and never handed to the draft writer, which persisted
 * `$result->facts` alone. Fifty-nine populated attributes per listing were
 * computed and discarded. This class is what gets written instead.
 *
 * WHY THE PRESENTER OUTPUT IS STORED, NOT THE RAW RECORD
 * ------------------------------------------------------
 * The allow-lists are applied at WRITE time, and the stored blob contains only
 * what they returned. Storing raw RESO and filtering at render would mean every
 * future widening of a catalog retroactively published new fields on listings
 * imported months earlier, with no import, no review and no diff. It would also
 * put the compliance boundary in a Blade file. Here, what a listing shows is
 * what was cleared on the day it was imported; widening the catalog changes new
 * imports and refreshes, which is a reviewable event.
 *
 * The raw record is not lost by this choice — `bridge_properties.raw_json` still
 * holds all 553 fields, and a refresh re-derives the blob from it.
 *
 * DETERMINISM
 * -----------
 * Section order follows the catalog's declaration order, row order follows each
 * section's, and nothing is sorted at render. Re-importing an unchanged record
 * produces a byte-identical blob, which is what makes re-import idempotent and
 * makes a diff between two imports mean something.
 *
 * EVERY ROW IS POPULATED, BY CONSTRUCTION
 * ---------------------------------------
 * A field with no value never becomes a row, so a section with nothing in it
 * never becomes a section, so the view has no empty state to render. The
 * "do not print blank rows" requirement is satisfied here, once, rather than by
 * an `@if` in every template.
 */
final class MlsSupplementalDetails
{
    /**
     * Bumped when the persisted shape changes incompatibly. A reader that meets
     * a version it does not know renders nothing rather than guessing.
     */
    public const VERSION = 1;

    public const SOURCE_LABEL = 'Stellar MLS via Bridge';

    /** @param list<array{title:string,group:string,rows:list<array<string,mixed>>}> $sections */
    private function __construct(
        public readonly array $sections,
        public readonly array $permissions,
        public readonly ?string $listingKey,
        public readonly ?string $mlsNumber,
        public readonly ?string $generatedAt,
    ) {}

    /**
     * Build from a raw Bridge record.
     *
     * @param array<string,mixed> $raw
     * @param string|null $role  omits property facts that reached this role's own form
     */
    public static function fromRecord(
        array $raw,
        ?string $role = null,
        ?MlsRelatedResources $related = null,
    ): self {
        $permissions = MlsDisplayPermissions::fromRecord($raw);
        $related   ??= MlsRelatedResources::none();

        $sections = [];

        // Order is the reading order of the finished page: what the listing IS,
        // then who is offering it, then when it can be seen, then the MLS's
        // bookkeeping about the offer.
        foreach ((new MlsPropertyDetailsPresenter())->present($raw, $role) as $title => $rows) {
            $sections[] = self::section($title, 'facts', $rows);
        }

        $contactRows  = (new MlsContactsPresenter())->present($raw, $permissions);
        $alreadyShown = [];

        foreach ($contactRows as $title => $rows) {
            $sections[] = self::section($title, 'contacts', $rows);

            foreach ($rows as $row) {
                $alreadyShown[$row['label']] = $row['value'];
            }
        }

        // Related-resource sections carry what Property does not: the agent's
        // direct phone and licence, the brokerage's address and website, and the
        // open houses. Gated on the same whole-listing permission as the contacts
        // they extend — a listing the feed has withdrawn publishes none of it.
        if ($permissions->listingDisplayable()) {
            foreach ($related->sections($alreadyShown) as $title => $rows) {
                $sections[] = self::section($title, 'related', $rows);
            }
        }

        foreach ((new MlsListingContextPresenter())->present($raw, $permissions) as $title => $rows) {
            $sections[] = self::section($title, 'listing', $rows);
        }

        return new self(
            sections:    $sections,
            permissions: $permissions->toArray(),
            listingKey:  self::text($raw['ListingKey'] ?? null),
            mlsNumber:   self::text($raw['ListingId'] ?? null),
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Rebuild from the persisted meta blob.
     *
     * Tolerant on purpose: a listing whose blob is missing, malformed, or
     * written by a newer version renders no MLS Details rather than raising
     * inside a listing page. The facts the user can edit are unaffected either
     * way, so a hard failure here would cost more than it caught.
     */
    public static function fromStored(mixed $stored): self
    {
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored  = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($stored) || (int) ($stored['version'] ?? 0) !== self::VERSION) {
            return self::empty();
        }

        $sections = [];

        foreach ($stored['sections'] ?? [] as $section) {
            if (! is_array($section) || ! is_array($section['rows'] ?? null) || $section['rows'] === []) {
                continue;
            }

            $rows = [];

            foreach ($section['rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $value = isset($row['value']) && is_scalar($row['value']) ? trim((string) $row['value']) : '';
                $label = isset($row['label']) && is_scalar($row['label']) ? trim((string) $row['label']) : '';

                // A stored row with no value is dropped rather than rendered.
                // Blobs written before a bug fix, or hand-edited ones, must not
                // be able to put an empty row on a page.
                if ($value === '' || $label === '') {
                    continue;
                }

                $rows[] = [
                    'key'   => isset($row['key']) && is_scalar($row['key']) ? (string) $row['key'] : '',
                    'label' => $label,
                    'value' => $value,
                    'url'   => self::safeUrl($row['url'] ?? null),
                    'link'  => self::safeLink($row['link'] ?? null),
                ];
            }

            if ($rows !== []) {
                $sections[] = self::section(
                    is_scalar($section['title'] ?? null) ? (string) $section['title'] : '',
                    is_scalar($section['group'] ?? null) ? (string) $section['group'] : 'facts',
                    $rows,
                );
            }
        }

        return new self(
            sections:    array_values(array_filter($sections, static fn ($s) => $s['title'] !== '')),
            permissions: is_array($stored['permissions'] ?? null) ? $stored['permissions'] : [],
            listingKey:  self::text($stored['listing_key'] ?? null),
            mlsNumber:   self::text($stored['mls_number'] ?? null),
            generatedAt: self::text($stored['generated_at'] ?? null),
        );
    }

    public static function empty(): self
    {
        return new self([], [], null, null, null);
    }

    public function isEmpty(): bool
    {
        return $this->sections === [];
    }

    public function rowCount(): int
    {
        return array_sum(array_map(static fn (array $s) => count($s['rows']), $this->sections));
    }

    public function permissions(): MlsDisplayPermissions
    {
        return MlsDisplayPermissions::fromStored($this->permissions);
    }

    /**
     * The listing brokerage, for the attribution block, or null.
     *
     * Read back out of the persisted payload rather than from the raw record,
     * so the attribution names the brokerage the listing was IMPORTED under and
     * cannot disagree with the Brokerage row rendered a few inches above it.
     * Null when the feed gave no office, or when the listing's display
     * permissions suppressed the contacts group entirely — in which case the
     * notice still renders and simply names nobody.
     */
    public function brokerageName(): ?string
    {
        foreach ($this->sections as $section) {
            foreach ($section['rows'] as $row) {
                if ($row['label'] === 'Brokerage' && trim($row['value']) !== '') {
                    return $row['value'];
                }
            }
        }

        return null;
    }

    /** When the MLS last changed this listing, for the attribution line, or null. */
    public function lastUpdatedLabel(): ?string
    {
        foreach ($this->sections as $section) {
            foreach ($section['rows'] as $row) {
                if ($row['label'] === 'Last Updated' && trim($row['value']) !== '') {
                    return $row['value'];
                }
            }
        }

        return null;
    }

    /** Only the sections in one group ('facts' | 'contacts' | 'related' | 'listing'). */
    public function group(string $group): array
    {
        return array_values(array_filter($this->sections, static fn (array $s) => $s['group'] === $group));
    }

    /**
     * The JSON-serialisable form written to listing meta.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'version'      => self::VERSION,
            'source'       => self::SOURCE_LABEL,
            'listing_key'  => $this->listingKey,
            'mls_number'   => $this->mlsNumber,
            'generated_at' => $this->generatedAt,
            'permissions'  => $this->permissions,
            'sections'     => $this->sections,
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function section(string $title, string $group, array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $clean[] = [
                'key'   => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ($row['value'] ?? ''),
                'url'   => self::safeUrl($row['url'] ?? null),
                'link'  => self::safeLink($row['link'] ?? null),
            ];
        }

        return ['title' => $title, 'group' => $group, 'rows' => $clean];
    }

    /**
     * A stored URL is re-validated on the way back out.
     *
     * The blob is written by us, but it is also a database row, and a row that
     * reaches an `href` must be checked at the point it is used rather than only
     * at the point it was written. Anything that is not an absolute https URL
     * becomes null and the row renders as plain text.
     */
    private static function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! str_starts_with(strtolower($value), 'https://')) {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $value : null;
    }

    /** As safeUrl(), plus the mailto: scheme the contacts presenter emits. */
    private static function safeLink(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with(strtolower($value), 'mailto:')) {
            $address = substr($value, 7);

            return filter_var($address, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $address : null;
        }

        return self::safeUrl($value);
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
