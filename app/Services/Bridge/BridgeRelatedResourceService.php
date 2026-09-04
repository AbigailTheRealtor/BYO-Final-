<?php

namespace App\Services\Bridge;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The Member, Office and OpenHouse rows that go with a Property record.
 *
 * WHY THESE THREE AND NOT MORE
 * ----------------------------
 * `php artisan mls:probe-resources --force-probe`, run against this dataset on
 * 2026-09-04, answered the question the payload audit could not: Member (79
 * fields), Office (55) and OpenHouse (36) all return HTTP 200; Room and Unit
 * both return HTTP 404. So three are implemented and two are recorded as
 * unavailable in {@see \App\Services\ListingImport\Mls\MlsFieldCatalog::UNAVAILABLE_RESOURCES}.
 * Nothing here synthesises the missing two from Property columns — `RoomsTotal`
 * is a count, and a count is not a roster.
 *
 * WHAT THIS BUYS
 * --------------
 * Fields that are ABSENT from Property on all 1,224 cached records, not merely
 * null: the agent's direct and mobile phone, their email as Member states it,
 * their state licence, the brokerage's address, website, email and fax, and
 * every open-house row.
 *
 * AVOIDING N+1 IS THE WHOLE DESIGN
 * --------------------------------
 * The cache is keyed on the MEMBER or OFFICE key, never on the listing. One
 * brokerage lists hundreds of properties; the second and every subsequent
 * import from that office costs zero requests. Open houses are keyed on the
 * listing because that is genuinely what varies.
 *
 * A per-import ceiling sits on top, counted after the cache, so a future caller
 * that loops cannot turn one user action into hundreds of requests. That is the
 * same reasoning as the Census provider budget, and for the same reason: a free
 * or cheap provider produces no bill and no signal until it stops answering.
 *
 * FAILURE IS ALWAYS SILENT AND ALWAYS PARTIAL
 * -------------------------------------------
 * Every method returns an empty array on any failure. A brokerage phone number
 * that cannot be fetched must cost that phone number and nothing else — never
 * the import, never the facts, never the photographs. {@see BridgeApiService::fetchRelated()}
 * swallows its own errors for the same reason; this class adds the cache and
 * the budget and keeps the posture.
 */
class BridgeRelatedResourceService
{
    /** Requests actually sent during this instance's lifetime. */
    private int $requestsMade = 0;

    public function __construct(
        private readonly BridgeApiService $api,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('mls_related_resources.enabled', true);
    }

    /**
     * One agent's Member row, or [].
     *
     * Resolved by `MemberKey` when the Property record supplies one, falling
     * back to `MemberMlsId`. Both are on Property for every cached record
     * (`ListAgentKey`, `ListAgentMlsId`), so the lookup never needs a search.
     *
     * @return array<string,mixed>
     */
    public function member(?string $memberKey, ?string $memberMlsId = null): array
    {
        if (! $this->enabled() || ! config('mls_related_resources.member', true)) {
            return [];
        }

        [$field, $value] = $this->identity($memberKey, $memberMlsId, 'MemberKey', 'MemberMlsId');

        if ($value === null) {
            return [];
        }

        return $this->remember(
            "bridge:member:{$field}:{$value}",
            (int) config('mls_related_resources.member_ttl_minutes', 1440),
            fn () => $this->firstRow('Member', "{$field} eq '" . $this->escape($value) . "'")
        );
    }

    /**
     * One brokerage's Office row, or [].
     *
     * @return array<string,mixed>
     */
    public function office(?string $officeKey, ?string $officeMlsId = null): array
    {
        if (! $this->enabled() || ! config('mls_related_resources.office', true)) {
            return [];
        }

        [$field, $value] = $this->identity($officeKey, $officeMlsId, 'OfficeKey', 'OfficeMlsId');

        if ($value === null) {
            return [];
        }

        return $this->remember(
            "bridge:office:{$field}:{$value}",
            (int) config('mls_related_resources.office_ttl_minutes', 1440),
            fn () => $this->firstRow('Office', "{$field} eq '" . $this->escape($value) . "'")
        );
    }

    /**
     * A listing's open houses, newest declaration first as the feed returns them.
     *
     * Cached briefly: these are dated events, and a stale one is a listing
     * inviting somebody to a house on a day nobody will be there.
     *
     * @return list<array<string,mixed>>
     */
    public function openHouses(?string $listingKey): array
    {
        if (! $this->enabled() || ! config('mls_related_resources.open_house', true)) {
            return [];
        }

        $listingKey = trim((string) $listingKey);

        if ($listingKey === '') {
            return [];
        }

        $max = max(1, (int) config('mls_related_resources.max_open_houses', 10));

        return $this->remember(
            "bridge:openhouse:{$listingKey}",
            (int) config('mls_related_resources.open_house_ttl_minutes', 60),
            fn () => $this->rows('OpenHouse', "ListingKey eq '" . $this->escape($listingKey) . "'", $max)
        );
    }

    /** How many live requests this instance has actually sent. */
    public function requestsMade(): int
    {
        return $this->requestsMade;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Prefer the globally-unique key; fall back to the MLS id.
     *
     * @return array{0: string, 1: string|null}
     */
    private function identity(?string $key, ?string $mlsId, string $keyField, string $mlsIdField): array
    {
        $key = trim((string) $key);

        if ($key !== '') {
            return [$keyField, $key];
        }

        $mlsId = trim((string) $mlsId);

        return [$mlsIdField, $mlsId !== '' ? $mlsId : null];
    }

    /**
     * Cache-through, with the per-import ceiling applied to the MISS path only.
     *
     * A cache hit costs nothing and is never counted, which is what lets a
     * brokerage's hundredth listing enrich for free while a genuine burst of
     * distinct lookups still stops at the ceiling.
     *
     * The cache stores the empty result too. A listing whose agent genuinely has
     * no Member row must not re-ask on every render — an empty answer is an
     * answer, and re-asking for it is the N+1 this class exists to prevent.
     */
    private function remember(string $key, int $ttlMinutes, callable $fetch): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $ceiling = max(0, (int) config('mls_related_resources.max_requests_per_import', 6));

        if ($this->requestsMade >= $ceiling) {
            Log::warning('[MLS RELATED] per-import request ceiling reached; skipping enrichment', [
                'ceiling' => $ceiling,
                'key'     => $key,
            ]);

            return [];
        }

        $this->requestsMade++;

        $value = $fetch();

        Cache::put($key, $value, now()->addMinutes(max(1, $ttlMinutes)));

        return $value;
    }

    /** @return array<string,mixed> */
    private function firstRow(string $resource, string $filter): array
    {
        $rows = $this->api->fetchRelated($resource, $filter, 1);

        return $rows[0] ?? [];
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $resource, string $filter, int $top): array
    {
        return $this->api->fetchRelated($resource, $filter, $top);
    }

    /** Escape a value for an OData single-quoted literal (double the quote). */
    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
