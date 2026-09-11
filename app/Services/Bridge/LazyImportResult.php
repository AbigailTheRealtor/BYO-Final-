<?php

namespace App\Services\Bridge;

class LazyImportResult
{
    public const STATUS_CACHED = 'cached';
    public const STATUS_FETCHED = 'fetched';
    public const STATUS_FAILED = 'failed';

    private function __construct(
        public readonly string  $status,
        public readonly int     $recordCount,
        public readonly bool    $fromCache,
        public readonly bool    $wasPartial,
        public readonly ?string $criteriaHash = null,

        /**
         * Provider pages this cycle actually SENT.
         *
         * Attempts, not successes: a page that was dispatched and came back a
         * failure consumed exactly as much of the provider's capacity — and of
         * any bill — as one that worked, so a caller rationing provider traffic
         * must count it. Counting only successes is how a failing integration
         * retries its way through a ceiling that looks like it is holding.
         *
         * Zero on a cache hit, which is the whole reason the cache is worth
         * having: a warm tile spends nothing and must be charged nothing.
         */
        public readonly int $pagesAttempted = 0,
    ) {}

    /**
     * Cache hit — no API call was made. Record count reflects the previously
     * stored value from the cache row so callers know the inventory size.
     */
    public static function cached(int $count = 0, ?string $hash = null): self
    {
        return new self(
            status: self::STATUS_CACHED,
            recordCount: $count,
            fromCache: true,
            wasPartial: false,
            criteriaHash: $hash,
            pagesAttempted: 0,
        );
    }

    /**
     * Fresh import completed.
     *
     * @param  int         $count      Total records upserted this cycle.
     * @param  bool        $wasPartial True when a max-pages or max-records cap was
     *                                 reached before the feed was fully consumed.
     * @param  string|null $hash       SHA-256 criteria hash used for this import.
     */
    public static function fetched(
        int $count,
        bool $wasPartial = false,
        ?string $hash = null,
        int $pagesAttempted = 0,
    ): self {
        return new self(
            status: self::STATUS_FETCHED,
            recordCount: $count,
            fromCache: false,
            wasPartial: $wasPartial,
            criteriaHash: $hash,
            pagesAttempted: $pagesAttempted,
        );
    }

    /**
     * API call failed. Caller should continue with existing local data.
     * No cache row was written.
     *
     * @param  string|null $hash  SHA-256 criteria hash that was attempted (for logging).
     */
    public static function failed(?string $hash = null, int $pagesAttempted = 0): self
    {
        return new self(
            status: self::STATUS_FAILED,
            recordCount: 0,
            fromCache: false,
            wasPartial: false,
            criteriaHash: $hash,
            // A failed cycle still SENT the pages it got through before the
            // fault. They are reported so a budget-aware caller charges for
            // provider capacity that was genuinely consumed.
            pagesAttempted: $pagesAttempted,
        );
    }

    public function isCached(): bool
    {
        return $this->status === self::STATUS_CACHED;
    }

    public function isFetched(): bool
    {
        return $this->status === self::STATUS_FETCHED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * True when a pagination cap was hit during a fetched import — the local
     * bridge_properties table may not reflect the full remote feed for this
     * criteria. The cache TTL is shortened automatically to allow a retry sooner.
     */
    public function isPartial(): bool
    {
        return $this->wasPartial;
    }
}
