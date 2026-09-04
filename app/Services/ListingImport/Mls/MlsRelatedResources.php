<?php

namespace App\Services\ListingImport\Mls;

use App\Services\Bridge\BridgeRelatedResourceService;

/**
 * The Member, Office and OpenHouse rows for one listing, already through their
 * allow-lists and ready to become sections.
 *
 * WHY A SEPARATE TYPE
 * -------------------
 * So that {@see MlsSupplementalDetails} can be built with or without enrichment
 * and behave identically either way. Every test in this area constructs one of
 * these directly and never touches the network; the live path builds it from
 * {@see BridgeRelatedResourceService}. Nothing downstream can tell the
 * difference, which is what makes the enrichment genuinely additive.
 *
 * PROPERTY WINS TIES
 * ------------------
 * `Member.MemberFullName` and `Property.ListAgentFullName` are the same claim
 * from two places. Property is the listing's own record and is what every other
 * surface already renders, so the contacts section keeps its value and the
 * Member row contributes only what Property does not carry — the direct phone,
 * the licence, the website. Overwriting a stronger source with a weaker one is
 * the exact failure the brief names, so the merge is deliberately one-way.
 */
final class MlsRelatedResources
{
    /**
     * @param array<string,mixed>            $member
     * @param array<string,mixed>            $office
     * @param array<string,mixed>            $coMember
     * @param array<string,mixed>            $coOffice
     * @param list<array<string,mixed>>      $openHouses
     */
    public function __construct(
        public readonly array $member = [],
        public readonly array $office = [],
        public readonly array $coMember = [],
        public readonly array $coOffice = [],
        public readonly array $openHouses = [],
    ) {}

    public static function none(): self
    {
        return new self();
    }

    /**
     * Fetch everything the feed can tell us about the people behind this
     * listing, from the keys the Property record already supplies.
     *
     * No searching, no guessing: `ListAgentKey`, `ListOfficeKey`,
     * `CoListAgentMlsId` and `CoListOfficeMlsId` are all on Property, so each
     * lookup is a key equality. A record missing a key simply contributes no
     * section.
     *
     * @param array<string,mixed> $raw
     */
    public static function fetch(array $raw, BridgeRelatedResourceService $service): self
    {
        if (! $service->enabled()) {
            return self::none();
        }

        return new self(
            member:   $service->member(
                self::text($raw['ListAgentKey'] ?? null),
                self::text($raw['ListAgentMlsId'] ?? null),
            ),
            office:   $service->office(
                self::text($raw['ListOfficeKey'] ?? null),
                self::text($raw['ListOfficeMlsId'] ?? null),
            ),
            // Co-list agent and office have NO key on Property in this feed —
            // only `CoListAgentMlsId` / `CoListOfficeMlsId` — which is precisely
            // why the identity resolver falls back to the MLS id rather than
            // requiring a key.
            coMember: $service->member(
                self::text($raw['CoListAgentKey'] ?? null),
                self::text($raw['CoListAgentMlsId'] ?? null),
            ),
            coOffice: $service->office(
                self::text($raw['CoListOfficeKey'] ?? null),
                self::text($raw['CoListOfficeMlsId'] ?? null),
            ),
            openHouses: $service->openHouses(self::text($raw['ListingKey'] ?? null)),
        );
    }

    public function isEmpty(): bool
    {
        return $this->member === []
            && $this->office === []
            && $this->coMember === []
            && $this->coOffice === []
            && $this->openHouses === [];
    }

    /**
     * The sections this enrichment contributes, in reading order.
     *
     * @param  array<string,string> $alreadyShown  label => value already rendered
     *                                             from the Property record, so a
     *                                             weaker duplicate is dropped
     * @return array<string, list<array{key:string,label:string,value:string,link:?string,url:?string}>>
     */
    public function sections(array $alreadyShown = []): array
    {
        $out = [];

        $agent = $this->rowsFrom($this->member, MlsFieldCatalog::MEMBER_FIELDS, $alreadyShown);

        if ($agent !== []) {
            $out['Listing Agent Contact'] = $agent;
        }

        $office = $this->rowsFrom($this->office, MlsFieldCatalog::OFFICE_FIELDS, $alreadyShown);

        if ($office !== []) {
            $out['Brokerage Contact'] = $office;
        }

        $coAgent = $this->rowsFrom($this->coMember, MlsFieldCatalog::MEMBER_FIELDS, $alreadyShown, 'Co-Listing ');

        if ($coAgent !== []) {
            $out['Co-Listing Agent Contact'] = $coAgent;
        }

        $coOffice = $this->rowsFrom($this->coOffice, MlsFieldCatalog::OFFICE_FIELDS, $alreadyShown, 'Co-Listing ');

        if ($coOffice !== []) {
            $out['Co-Listing Brokerage Contact'] = $coOffice;
        }

        $openHouses = $this->openHouseRows();

        if ($openHouses !== []) {
            $out['Open Houses'] = $openHouses;
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $record
     * @param  array<string,string> $allowList
     * @param  array<string,string> $alreadyShown
     * @return list<array{key:string,label:string,value:string,link:?string,url:?string}>
     */
    private function rowsFrom(array $record, array $allowList, array $alreadyShown, string $prefix = ''): array
    {
        if ($record === []) {
            return [];
        }

        $rows = [];

        foreach ($allowList as $field => $label) {
            $value = MlsValueFormatter::format($record[$field] ?? null);

            if ($value === null) {
                continue;
            }

            $label = $prefix . $label;

            // Property already said this, from the listing's own record. Showing
            // it again under a second heading reads as two sources disagreeing
            // even when they agree.
            if (($alreadyShown[$label] ?? null) === $value) {
                continue;
            }

            $rows[] = [
                'key'   => $field,
                'label' => $label,
                'value' => $value,
                'link'  => $this->link($field, $value),
                'url'   => null,
            ];
        }

        return $rows;
    }

    /**
     * One row per open house, rendered as a single readable line.
     *
     * Collapsed rather than exploded into ten rows each: "Sat 12 Oct, 1:00 PM –
     * 3:00 PM" is what a reader wants, and a Date/Starts/Ends triple repeated
     * per event is a table nobody reads.
     *
     * @return list<array{key:string,label:string,value:string,link:?string,url:?string}>
     */
    private function openHouseRows(): array
    {
        $rows = [];

        foreach ($this->openHouses as $index => $house) {
            if (! is_array($house)) {
                continue;
            }

            $date  = MlsValueFormatter::format($house['OpenHouseDate'] ?? null);
            $start = MlsValueFormatter::format($house['OpenHouseStartTime'] ?? null);
            $end   = MlsValueFormatter::format($house['OpenHouseEndTime'] ?? null);

            $parts = array_values(array_filter([
                $date !== null ? $this->formatDate($date) : null,
                $this->formatTimeRange($start, $end),
                MlsValueFormatter::format($house['OpenHouseType'] ?? null),
                MlsValueFormatter::format($house['OpenHouseMethod'] ?? null),
                MlsValueFormatter::format($house['OpenHouseRemarks'] ?? null),
            ]));

            if ($parts === []) {
                continue;
            }

            if (MlsValueFormatter::format($house['AppointmentRequiredYN'] ?? null) === 'Yes') {
                $parts[] = 'Appointment required';
            }

            $url = null;

            foreach (MlsFieldCatalog::OPEN_HOUSE_URL_FIELDS as $field) {
                $candidate = MlsValueFormatter::format($house[$field] ?? null);

                if ($candidate !== null && str_starts_with(strtolower($candidate), 'https://')) {
                    $url = $candidate;
                    break;
                }
            }

            $rows[] = [
                'key'   => 'OpenHouse' . $index,
                'label' => 'Open House',
                'value' => implode(' · ', $parts),
                'link'  => null,
                'url'   => $url,
            ];
        }

        return $rows;
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);

        // Shown exactly as the feed sent it when it cannot be parsed, rather
        // than as a guess or as an epoch date.
        return $timestamp === false ? $value : date('D j M Y', $timestamp);
    }

    private function formatTimeRange(?string $start, ?string $end): ?string
    {
        $format = static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $timestamp = strtotime($value);

            return $timestamp === false ? $value : date('g:i A', $timestamp);
        };

        $start = $format($start);
        $end   = $format($end);

        if ($start === null && $end === null) {
            return null;
        }

        return $end === null ? $start : trim(($start ?? '') . ' – ' . $end);
    }

    private function link(string $field, string $value): ?string
    {
        if (str_contains(strtolower($field), 'email')) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $value : null;
        }

        if ($field !== 'SocialMediaWebsiteUrlOrId') {
            return null;
        }

        $url = str_starts_with(strtolower($value), 'http') ? $value : 'https://' . $value;

        if (! str_starts_with(strtolower($url), 'https://')) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $url : null;
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
