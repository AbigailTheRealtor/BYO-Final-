<?php

namespace Tests\Unit\ListingImport;

use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\Sync\MlsSyncFreshness;
use Tests\TestCase;

/**
 * WHEN IS A LISTING DUE?
 *
 * The scheduled sweep and the sync service both ask this class, and their
 * answers have to be the same one. If the sweep's idea of "due" were looser
 * than the service's idea of "fresh", every run would select a batch, be told
 * FRESH for all of it, send nothing, and report success — a broken sync that
 * looks exactly like a healthy idle one.
 *
 * No database and no HTTP: this is a pure function of stored meta and config.
 */
class MlsSyncFreshnessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_sync.freshness_minutes'          => 60,
            'mls_sync.terminal_freshness_minutes' => 1440,
            'mls_sync.retry_after_minutes'        => 30,
        ]);
    }

    /** Meta for a listing last synced $minutes ago with the given status. */
    private function meta(int $minutesAgo, ?string $standardStatus = 'Active', array $extra = []): array
    {
        return array_merge([
            Meta::META_SYNCED_AT       => now()->subMinutes($minutesAgo)->toIso8601String(),
            Meta::META_STANDARD_STATUS => $standardStatus,
        ], $extra);
    }

    // =====================================================================
    // The live window
    // =====================================================================

    /** @test */
    public function a_live_listing_inside_the_hour_is_fresh_and_outside_it_is_not(): void
    {
        $this->assertTrue(MlsSyncFreshness::isFresh($this->meta(30)));
        $this->assertFalse(MlsSyncFreshness::isFresh($this->meta(90)));

        $this->assertSame(MlsSyncFreshness::REASON_LIVE, MlsSyncFreshness::reason($this->meta(30)));
        $this->assertSame(60, MlsSyncFreshness::windowMinutes($this->meta(30)));
    }

    /** @test */
    public function a_listing_that_has_never_synced_is_due_immediately(): void
    {
        $meta = [Meta::META_LISTING_KEY => 'K-1'];

        $this->assertFalse(MlsSyncFreshness::isFresh($meta));
        $this->assertSame(MlsSyncFreshness::REASON_NEVER, MlsSyncFreshness::reason($meta));
        $this->assertNull(MlsSyncFreshness::dueAt($meta));
    }

    // =====================================================================
    // The terminal window — the lifecycle reduction
    // =====================================================================

    /**
     * @test
     * @dataProvider terminalStatuses
     */
    public function an_off_market_listing_drops_to_the_daily_window(string $status): void
    {
        $meta = $this->meta(90, $status);

        $this->assertSame(MlsSyncFreshness::REASON_TERMINAL, MlsSyncFreshness::reason($meta));
        $this->assertSame(1440, MlsSyncFreshness::windowMinutes($meta));

        // Ninety minutes stale would be due on the live window; on the terminal
        // one it is not. That difference IS the reduction in polling.
        $this->assertTrue(MlsSyncFreshness::isFresh($meta));
    }

    public static function terminalStatuses(): array
    {
        return [
            ['Closed'], ['Expired'], ['Withdrawn'],
            ['Canceled'], ['Cancelled'], ['Temporarily Off Market'],
        ];
    }

    /** @test */
    public function a_terminal_listing_is_still_checked_daily_rather_than_abandoned(): void
    {
        // The reduction is a reduction, never a stop: a day later it is due
        // again, so a status Stellar reverses is picked up within a day.
        $this->assertFalse(MlsSyncFreshness::isFresh($this->meta(1500, 'Closed')));
    }

    /**
     * @test
     * @dataProvider liveStatuses
     */
    public function every_live_status_keeps_the_short_window(string $status): void
    {
        $this->assertSame(MlsSyncFreshness::REASON_LIVE, MlsSyncFreshness::reason($this->meta(90, $status)));
        $this->assertFalse(MlsSyncFreshness::isFresh($this->meta(90, $status)));
    }

    public static function liveStatuses(): array
    {
        return [['Active'], ['Pending'], ['Active Under Contract'], ['Coming Soon']];
    }

    /**
     * @test
     *
     * The safe direction, and the reason MlsSourceStatus::isOffMarket() answers
     * false for a word it does not know. Reading an unfamiliar status as "off
     * market" would drop a live listing to daily polling, and it would go stale
     * for exactly the reason nobody would think to look for.
     */
    public function an_unrecognised_status_keeps_the_live_window_rather_than_being_assumed_terminal(): void
    {
        $meta = $this->meta(90, 'Auction Pending Release');

        $this->assertSame(MlsSyncFreshness::REASON_LIVE, MlsSyncFreshness::reason($meta));
        $this->assertFalse(MlsSyncFreshness::isFresh($meta));
    }

    /** @test */
    public function status_falls_back_to_mls_status_when_standard_status_is_absent(): void
    {
        $meta = $this->meta(90, null, [Meta::META_SOURCE_STATUS => 'Closed']);

        $this->assertSame(MlsSyncFreshness::REASON_TERMINAL, MlsSyncFreshness::reason($meta));
    }

    // =====================================================================
    // The error window
    // =====================================================================

    /** @test */
    public function a_failed_listing_retries_on_the_shorter_backoff(): void
    {
        $meta = [
            Meta::META_SYNCED_AT         => now()->subMinutes(5)->toIso8601String(),
            Meta::META_SYNC_ATTEMPTED_AT => now()->subMinutes(45)->toIso8601String(),
            Meta::META_SYNC_ERROR        => 'unavailable',
            Meta::META_STANDARD_STATUS   => 'Active',
        ];

        $this->assertSame(MlsSyncFreshness::REASON_ERROR, MlsSyncFreshness::reason($meta));
        $this->assertSame(30, MlsSyncFreshness::windowMinutes($meta));

        // Due, even though the last SUCCESS was only five minutes ago — the
        // error clock runs from the attempt.
        $this->assertFalse(MlsSyncFreshness::isFresh($meta));
    }

    /**
     * @test
     *
     * The backoff must measure from the ATTEMPT. Measuring from the last success
     * would push the retry further away on every failure, and a listing failing
     * repeatedly would retry in a tight loop forever.
     */
    public function the_error_backoff_measures_from_the_attempt_not_the_last_success(): void
    {
        $meta = [
            Meta::META_SYNCED_AT         => now()->subDays(30)->toIso8601String(),
            Meta::META_SYNC_ATTEMPTED_AT => now()->subMinutes(2)->toIso8601String(),
            Meta::META_SYNC_ERROR        => 'unavailable',
        ];

        $this->assertTrue(
            MlsSyncFreshness::isFresh($meta),
            'A listing that failed two minutes ago must wait out its backoff, not retry immediately'
        );
    }

    /** @test */
    public function an_error_outranks_a_terminal_status(): void
    {
        // A Closed listing whose last attempt failed still retries on the short
        // backoff: we do not actually know the stored status is current.
        $meta = [
            Meta::META_SYNC_ATTEMPTED_AT => now()->subMinutes(45)->toIso8601String(),
            Meta::META_SYNCED_AT         => now()->subMinutes(45)->toIso8601String(),
            Meta::META_SYNC_ERROR        => 'unavailable',
            Meta::META_STANDARD_STATUS   => 'Closed',
        ];

        $this->assertSame(MlsSyncFreshness::REASON_ERROR, MlsSyncFreshness::reason($meta));
        $this->assertFalse(MlsSyncFreshness::isFresh($meta));
    }

    // =====================================================================
    // Corrupt input
    // =====================================================================

    /**
     * @test
     *
     * Stale is the safe reading. Refusing to sync because a timestamp is corrupt
     * would let one bad row freeze a listing's price permanently, which is the
     * worse of the two failures.
     */
    public function an_unparseable_or_empty_stamp_reads_as_stale(): void
    {
        foreach (['', '   ', 'null', 'not-a-date'] as $stamp) {
            $this->assertFalse(
                MlsSyncFreshness::isFresh([Meta::META_SYNCED_AT => $stamp]),
                "Stamp [{$stamp}] should read as stale"
            );
        }
    }

    /** @test */
    public function a_zero_window_makes_everything_due(): void
    {
        config(['mls_sync.freshness_minutes' => 0]);

        $this->assertFalse(MlsSyncFreshness::isFresh($this->meta(0)));
    }
}
