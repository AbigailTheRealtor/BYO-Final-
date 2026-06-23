<?php

namespace App\Services\Bridge;

class LazyImportResult
{
    public const STATUS_CACHED = 'cached';
    public const STATUS_FETCHED = 'fetched';
    public const STATUS_FAILED = 'failed';

    private function __construct(
        public readonly string $status,
        public readonly int $recordCount,
        public readonly bool $fromCache,
        public readonly bool $wasPartial,
    ) {}

    /**
     * Cache hit — no API call was made. Record count reflects the previously
     * stored value from the cache row so callers know the inventory size.
     */
    public static function cached(int $count = 0): self
    {
        return new self(
            status: self::STATUS_CACHED,
            recordCount: $count,
            fromCache: true,
            wasPartial: false,
        );
    }

    /**
     * Fresh import completed.
     *
     * @param  int   $count      Total records upserted this cycle.
     * @param  bool  $wasPartial True when a max-pages or max-records cap was
     *                           reached before the feed was fully consumed.
     *                           Callers may use this to decide whether to re-fetch
     *                           sooner or display a "partial results" notice.
     */
    public static function fetched(int $count, bool $wasPartial = false): self
    {
        return new self(
            status: self::STATUS_FETCHED,
            recordCount: $count,
            fromCache: false,
            wasPartial: $wasPartial,
        );
    }

    /**
     * API call failed. Caller should continue with existing local data.
     * No cache row was written.
     */
    public static function failed(): self
    {
        return new self(
            status: self::STATUS_FAILED,
            recordCount: 0,
            fromCache: false,
            wasPartial: false,
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
