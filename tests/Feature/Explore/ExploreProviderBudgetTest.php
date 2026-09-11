<?php

namespace Tests\Feature\Explore;

use App\Models\BridgeCriteriaFetchCache;
use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeApiService;
use App\Services\Explore\Guards\ExploreProviderBudget;
use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\Feature\Explore\Support\FakeBridgeApi;
use Tests\TestCase;

/**
 * Explore cannot spend unbounded provider traffic — §19 A through L.
 *
 * WHAT THESE ACTUALLY PROVE
 * -------------------------
 * Every assertion is about the count of requests that reached the provider
 * double. That is the only thing a bill responds to: a budget that returns the
 * right booleans while the fetch still goes out has protected nothing, so the
 * tests count `providerRequestCount()` rather than inspecting the guard.
 *
 * The seam is the network boundary and nothing above it — FakeBridgeApi
 * subclasses the real BridgeApiService, so the importer, the normalizer, the
 * fetch cache and the advisory lock are all the production ones.
 *
 * ZERO live Bridge requests. ZERO live Google requests.
 */
class ExploreProviderBudgetTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private FakeBridgeApi $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'explore.enabled'                        => true,
            'explore.discovery.enabled'              => true,
            'explore.google.browser_key'             => null,
            'mls_media.enabled'                      => true,
            'mls_media.license_acknowledged'         => true,
            'explore.provider_budget.enabled'        => true,
            'explore.provider_budget.kill_switch'    => false,
            'explore.provider_budget.global_hourly'  => 1000,
            'explore.provider_budget.global_daily'   => 5000,
            'explore.provider_budget.actor_hourly'   => 1000,
            'explore.provider_budget.actor_daily'    => 5000,
        ]);

        // The budget counts through the cache; each test starts from zero.
        Cache::flush();

        $this->provider = new FakeBridgeApi();
        $this->app->instance(BridgeApiService::class, $this->provider);
    }

    private function listings(array $query = []): array
    {
        $query = array_merge(['bbox' => $this->bboxAroundDefault()], $query);

        return $this->getJson('/api/explore/listings?' . http_build_query($query))->json();
    }

    /** Move a viewport far enough that it lands in a different snapped tile. */
    private function tileAt(int $offset): string
    {
        $lat = self::LAT + ($offset * 0.2);
        $lng = self::LNG + ($offset * 0.2);

        return implode(',', [$lat - 0.01, $lng - 0.01, $lat + 0.01, $lng + 0.01]);
    }

    /* ── A — the happy path still works ─────────────────────────────────── */

    /** @test */
    public function a_cold_tile_is_allowed_while_budget_remains(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'ALLOWED-1'])];

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(['ALLOWED-1'], array_column($payload['listings'], 'id'));
        $this->assertSame(1, $this->provider->providerRequestCount());
        $this->assertSame('fetched', $payload['discovery']['status']);
    }

    /* ── B / C / D — exhaustion ─────────────────────────────────────────── */

    /**
     * §19-B. The assertion that matters: when the budget is spent, NOTHING GOES
     * OUT. Not "the response says blocked while the fetch still happened".
     *
     * @test
     */
    public function an_exhausted_budget_sends_no_provider_request(): void
    {
        config(['explore.provider_budget.global_hourly' => 1]);

        $this->provider->records = [$this->providerRecord(['listing_key' => 'FIRST'])];

        // Spend the single allowed request.
        $this->listings(['transaction_type' => 'sale', 'bbox' => $this->tileAt(0)]);
        $this->assertSame(1, $this->provider->providerRequestCount());

        // A different cold tile, which would otherwise fetch.
        $this->listings(['transaction_type' => 'sale', 'bbox' => $this->tileAt(1)]);

        $this->assertSame(1, $this->provider->providerRequestCount(), 'no second request may be sent');
    }

    /**
     * §19-C. Exhausted, with usable local inventory: the map keeps its
     * listings and the response says the answer is degraded.
     *
     * @test
     */
    public function exhaustion_with_local_inventory_degrades_rather_than_emptying(): void
    {
        $known = $this->makeListing(['listing_key' => 'LOCALLY-KNOWN']);
        $known->forceFill(['imported_at' => now()->subDays(30)])->save();

        config(['explore.provider_budget.global_hourly' => 1, 'explore.provider_budget.global_daily' => 1]);
        (new ExploreProviderBudget())->record(null, 1); // pre-spend the ceiling

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(0, $this->provider->providerRequestCount());
        $this->assertSame('budget_limited', $payload['discovery']['status']);
        $this->assertTrue($payload['discovery']['degraded']);

        // complete=false suppresses the withhold-unconfirmed rule, so the row
        // survives. A budget ceiling must not look like a delisting.
        $this->assertFalse($payload['discovery']['complete']);
        $this->assertSame(['LOCALLY-KNOWN'], array_column($payload['listings'], 'id'));
    }

    /**
     * §19-D. Exhausted with nothing local: a deliberate temporary-unavailable
     * answer, NEVER a false "there are no homes here".
     *
     * @test
     */
    public function exhaustion_with_no_local_inventory_is_temporary_not_empty(): void
    {
        config(['explore.provider_budget.global_hourly' => 1, 'explore.provider_budget.global_daily' => 1]);
        (new ExploreProviderBudget())->record(null, 1);

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(0, $this->provider->providerRequestCount());
        $this->assertSame(0, $payload['count']);

        // The two flags the surface reads to choose its wording. `degraded`
        // true is what makes it say "temporarily unavailable" instead of
        // "no listings in this view".
        $this->assertTrue($payload['discovery']['degraded']);
        $this->assertFalse($payload['discovery']['complete']);
    }

    /* ── E — cache hits are free ────────────────────────────────────────── */

    /**
     * §19-E. A warm tile sends nothing, so it must be charged nothing —
     * rationing our own memory would defeat the cache the ceiling relies on.
     *
     * @test
     */
    public function a_warm_cache_hit_spends_no_budget(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'WARM-1'])];

        $this->listings(['transaction_type' => 'sale']);

        $budget = new ExploreProviderBudget();
        $after  = $budget->spent(null)['global']['hourly'];

        $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(1, $this->provider->providerRequestCount(), 'the warm tile sent nothing');
        $this->assertSame($after, $budget->spent(null)['global']['hourly'], 'and was charged nothing');
    }

    /* ── F — pagination is charged per page ─────────────────────────────── */

    /**
     * §19-F. A five-page pass is five provider requests and must be charged as
     * five. Charging a multi-page crawl as "one discovery" is how a ceiling
     * that reads as 600 turns into 3,000 outbound requests.
     *
     * @test
     */
    public function pagination_is_charged_per_page_not_per_pass(): void
    {
        // page_size 1 with 3 records forces three pages plus the short final one.
        config(['bridge.lazy_page_size' => 1, 'explore.discovery.max_pages' => 10]);

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'P-1']),
            $this->providerRecord(['listing_key' => 'P-2']),
            $this->providerRecord(['listing_key' => 'P-3']),
        ];

        $this->listings(['transaction_type' => 'sale']);

        $pages = count($this->provider->paginatedCalls);

        $this->assertGreaterThan(1, $pages, 'precondition: the pass really paginated');
        $this->assertSame(
            $pages,
            (new ExploreProviderBudget())->spent(null)['global']['hourly'],
            'every dispatched page is charged'
        );
    }

    /* ── G — retries cannot bypass ──────────────────────────────────────── */

    /**
     * §19-G. A caller that retries after exhaustion is refused again. The check
     * is at the start of every attempt, so there is no "second try" path that
     * skips it.
     *
     * @test
     */
    public function retrying_cannot_bypass_the_budget(): void
    {
        config(['explore.provider_budget.global_hourly' => 1, 'explore.provider_budget.global_daily' => 1]);
        (new ExploreProviderBudget())->record(null, 1);

        for ($i = 0; $i < 5; $i++) {
            $this->listings(['transaction_type' => 'sale', 'bbox' => $this->tileAt($i)]);
        }

        $this->assertSame(0, $this->provider->providerRequestCount());
    }

    /**
     * A provider FAILURE is charged, and the retry after it is charged too —
     * until the ceiling stops it. Counting only successes is how a failing
     * integration retries its way through a ceiling that looks like it holds.
     *
     * @test
     */
    public function a_failed_provider_request_still_consumes_budget(): void
    {
        $this->provider->shouldFail = true;

        $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(
            1,
            (new ExploreProviderBudget())->spent(null)['global']['hourly'],
            'the page was dispatched and failed; it consumed provider capacity'
        );
    }

    /* ── H — cold-tile fan-out ──────────────────────────────────────────── */

    /**
     * §19-H. Repeated requests for the SAME cold tile produce one fetch: the
     * fetch cache answers the second, so the advisory lock's serialisation is
     * never even needed. Charged once, because sent once.
     *
     * @test
     */
    public function repeated_requests_for_one_cold_tile_fetch_once(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'SAME-TILE'])];

        for ($i = 0; $i < 6; $i++) {
            $this->listings(['transaction_type' => 'sale']);
        }

        $this->assertSame(1, $this->provider->providerRequestCount());
        $this->assertSame(1, (new ExploreProviderBudget())->spent(null)['global']['hourly']);
    }

    /* ── I / J — actor vs global ────────────────────────────────────────── */

    /**
     * §19-I. The gap the HTTP throttle cannot close: one actor traversing many
     * DISTINCT cold tiles. Repeat visits were already free; distinct ones were
     * not bounded at all.
     *
     * @test
     */
    public function one_actor_traversing_many_distinct_cold_tiles_is_bounded(): void
    {
        config([
            'explore.provider_budget.actor_hourly'  => 3,
            'explore.provider_budget.global_hourly' => 1000,
        ]);

        $this->provider->records = [$this->providerRecord()];

        for ($i = 0; $i < 10; $i++) {
            $this->listings(['transaction_type' => 'sale', 'bbox' => $this->tileAt($i)]);
        }

        $this->assertSame(3, $this->provider->providerRequestCount(), 'the actor ceiling held');
    }

    /**
     * §19-J. And the ceiling the actor limit cannot see — one caller arriving
     * from many addresses, or simply many callers. This is the ceiling that
     * would actually have caught the incident behind this work.
     *
     * @test
     */
    public function the_global_ceiling_stops_traffic_no_actor_limit_would_see(): void
    {
        config([
            'explore.provider_budget.global_hourly' => 2,
            'explore.provider_budget.actor_hourly'  => 1000,
        ]);

        $this->provider->records = [$this->providerRecord()];

        // Each request presents a different actor identity.
        for ($i = 0; $i < 8; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.' . $i])
                ->getJson('/api/explore/listings?' . http_build_query([
                    'bbox'             => $this->tileAt($i),
                    'transaction_type' => 'sale',
                ]));
        }

        $this->assertSame(2, $this->provider->providerRequestCount());
    }

    /** Two different actors do not share one actor bucket. @test */
    public function actor_budgets_are_scoped_per_actor(): void
    {
        config([
            'explore.provider_budget.actor_hourly'  => 1,
            'explore.provider_budget.global_hourly' => 1000,
        ]);

        $this->provider->records = [$this->providerRecord()];

        foreach (['198.51.100.7', '198.51.100.8'] as $i => $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/explore/listings?' . http_build_query([
                    'bbox'             => $this->tileAt($i + 20),
                    'transaction_type' => 'sale',
                ]));
        }

        $this->assertSame(2, $this->provider->providerRequestCount(), 'one each, not one between them');
    }

    /* ── K / L — determinism and the off switches ───────────────────────── */

    /** §19-K. @test */
    public function provider_failure_accounting_is_deterministic(): void
    {
        $this->provider->shouldFail = true;

        for ($i = 0; $i < 3; $i++) {
            $this->listings(['transaction_type' => 'sale', 'bbox' => $this->tileAt($i + 40)]);
        }

        $this->assertSame(3, $this->provider->providerRequestCount());
        $this->assertSame(3, (new ExploreProviderBudget())->spent(null)['global']['hourly']);
    }

    /** §19-L. @test */
    public function discovery_disabled_sends_nothing(): void
    {
        config(['explore.discovery.enabled' => false]);

        $this->provider->records = [$this->providerRecord()];

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(0, $this->provider->providerRequestCount());
        $this->assertSame('disabled', $payload['discovery']['status']);
        $this->assertSame(0, (new ExploreProviderBudget())->spent(null)['global']['hourly']);
    }

    /**
     * The emergency stop: Stellar traffic ceases, Explore keeps serving.
     *
     * @test
     */
    public function the_kill_switch_stops_provider_traffic_without_stopping_explore(): void
    {
        config(['explore.provider_budget.kill_switch' => true]);

        $known = $this->makeListing(['listing_key' => 'STILL-SERVED']);
        $known->forceFill(['imported_at' => now()->subDays(30)])->save();

        $this->provider->records = [$this->providerRecord()];

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(0, $this->provider->providerRequestCount());
        $this->assertSame('budget_limited', $payload['discovery']['status']);
        $this->assertSame(['STILL-SERVED'], array_column($payload['listings'], 'id'));
    }

    /**
     * Switching the GUARD off must not unleash traffic. A disabled guard is
     * treated as "do not call the provider", so this flag is a second way to
     * stop spending and never a way to start it.
     *
     * @test
     */
    public function disabling_the_guard_stops_spending_rather_than_unleashing_it(): void
    {
        config(['explore.provider_budget.enabled' => false]);

        $this->provider->records = [$this->providerRecord()];

        $this->listings(['transaction_type' => 'sale']);

        $this->assertSame(0, $this->provider->providerRequestCount());
    }

    /* ── the panel path spends from the same ceilings ───────────────────── */

    /** @test */
    public function the_property_panel_refresh_is_charged_and_can_be_refused(): void
    {
        $stale = $this->makeListing(['listing_key' => 'PANEL-BUDGET']);
        $stale->forceFill(['imported_at' => now()->subDays(30)])->save();

        $this->provider->records = [$this->providerRecord(['listing_key' => 'PANEL-BUDGET'])];

        $this->getJson('/api/explore/listings/PANEL-BUDGET')->assertOk();
        $this->assertSame(1, $this->provider->providerRequestCount());
        $this->assertSame(1, (new ExploreProviderBudget())->spent(null)['global']['hourly']);

        config(['explore.provider_budget.kill_switch' => true]);

        $stale->fresh()->forceFill(['imported_at' => now()->subDays(30)])->save();
        $this->getJson('/api/explore/listings/PANEL-BUDGET')->assertOk();

        $this->assertSame(1, $this->provider->providerRequestCount(), 'refused, and the panel still served');
    }

    /* ── hard ceilings — the configured number is the maximum, exactly ─── */

    /** @return array<string, array{0:string, 1:int, 2:bool}> */
    public function exactCeilings(): array
    {
        return [
            'actor hourly 60'   => ['actor_hourly', 60, true],
            'actor daily 300'   => ['actor_daily', 300, true],
            'global hourly 300' => ['global_hourly', 300, false],
            'global daily 2000' => ['global_daily', 2000, false],
        ];
    }

    /**
     * Each ceiling on its own, at its shipped value: the unit that reaches the
     * limit is admitted, and the one after it is refused BEFORE anything is
     * sent. Charged to the limit and not one unit beyond.
     *
     * @test
     * @dataProvider exactCeilings
     */
    public function each_configured_ceiling_is_exact(string $ceiling, int $limit, bool $actorScoped): void
    {
        // Every other ceiling well out of the way, so only the one under test
        // can refuse.
        config([
            'explore.provider_budget.global_hourly' => 100_000,
            'explore.provider_budget.global_daily'  => 100_000,
            'explore.provider_budget.actor_hourly'  => 100_000,
            'explore.provider_budget.actor_daily'   => 100_000,
            "explore.provider_budget.{$ceiling}"    => $limit,
        ]);

        // The actor every request in this test arrives as.
        $actor = ExploreProviderBudget::actorKey(null, '127.0.0.1');
        (new ExploreProviderBudget())->record($actorScoped ? $actor : null, $limit - 1);

        $this->provider->records = [$this->providerRecord()];

        $this->listings(['transaction_type' => 'sale', 'bbox' => $this->tileAt(0)]);
        $this->assertSame(1, $this->provider->providerRequestCount(), "unit {$limit} of {$limit} is admitted and sent");

        $payload = $this->listings(['transaction_type' => 'sale', 'bbox' => $this->tileAt(1)]);
        $this->assertSame(1, $this->provider->providerRequestCount(), 'unit ' . ($limit + 1) . ' is never sent');
        $this->assertSame('budget_limited', $payload['discovery']['status']);

        $spent  = (new ExploreProviderBudget())->spent($actor);
        $scope  = $actorScoped ? $spent['actor'] : $spent['global'];
        $window = str_ends_with($ceiling, 'hourly') ? 'hourly' : 'daily';

        $this->assertSame($limit, $scope[$window], 'charged to the limit and not one unit beyond');
    }

    /**
     * The case check-then-charge got wrong: budget that runs out PART-WAY
     * through a pass. Three units remain and the provider has more pages than
     * that — exactly three pages go out, page 4 never does, and the answer is
     * degraded rather than a false empty market.
     *
     * @test
     */
    public function budget_running_out_mid_pagination_stops_before_the_next_page(): void
    {
        config([
            'bridge.lazy_page_size'                 => 1,
            'explore.discovery.max_pages'           => 10,
            'explore.provider_budget.global_hourly' => 3,
        ]);

        $known = $this->makeListing(['listing_key' => 'KNOWN-BEFORE']);
        $known->forceFill(['imported_at' => now()->subDays(30)])->save();

        $this->provider->records = array_map(
            fn (int $i): array => $this->providerRecord(['listing_key' => "MID-{$i}"]),
            range(1, 8),
        );

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertCount(3, $this->provider->paginatedCalls, 'exactly the three admitted pages were sent');
        $this->assertSame(3, (new ExploreProviderBudget())->spent(null)['global']['hourly']);

        $this->assertSame('budget_limited', $payload['discovery']['status']);
        $this->assertTrue($payload['discovery']['degraded']);
        $this->assertFalse($payload['discovery']['complete']);

        // What was fetched is shown, and what was known before is NOT withheld
        // on the strength of pages the pass never reached.
        $ids = array_column($payload['listings'], 'id');

        foreach (['MID-1', 'MID-2', 'MID-3', 'KNOWN-BEFORE'] as $expected) {
            $this->assertContains($expected, $ids);
        }

        $this->assertNotContains('MID-4', $ids, 'page 4 was never fetched');

        // No warm-tile cache row: a truncated pass must not later be served as
        // a complete answer.
        $this->assertSame(0, BridgeCriteriaFetchCache::query()->count());
    }

    /**
     * An unfiltered viewport runs a sale pass and then a rent pass. When the
     * sale pass takes the last unit, the rent pass is refused before its first
     * page, and the response says the answer is budget-limited — with the sale
     * rows it did get — rather than presenting half the market as the whole.
     *
     * @test
     */
    public function running_out_between_the_sale_and_rent_passes_is_degraded_not_half_a_map(): void
    {
        config(['explore.provider_budget.global_hourly' => 1]);

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'SALE-ONLY']),
            $this->providerRentalRecord(['listing_key' => 'RENT-NEVER-ASKED']),
        ];

        $payload = $this->listings();

        $this->assertSame(1, $this->provider->providerRequestCount(), 'the rent pass sent nothing');
        $this->assertSame('budget_limited', $payload['discovery']['status']);
        $this->assertTrue($payload['discovery']['degraded']);
        $this->assertFalse($payload['discovery']['complete']);
        $this->assertContains('SALE-ONLY', array_column($payload['listings'], 'id'));
    }

    /**
     * Two actors, one unit left in the global ceiling. Neither actor ceiling is
     * anywhere near its limit, so only the global one decides — and it admits
     * exactly one of them.
     *
     * @test
     */
    public function two_actors_cannot_both_take_the_last_global_unit(): void
    {
        config(['explore.provider_budget.global_hourly' => 1]);

        $this->provider->records = [$this->providerRecord()];

        foreach (['198.51.100.21', '198.51.100.22'] as $i => $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/explore/listings?' . http_build_query([
                    'bbox'             => $this->tileAt($i + 60),
                    'transaction_type' => 'sale',
                ]));
        }

        $this->assertSame(1, $this->provider->providerRequestCount());
        $this->assertSame(1, (new ExploreProviderBudget())->spent(null)['global']['hourly']);
    }

    /**
     * Admission that cannot be decided is a refusal. With the admission lock
     * held elsewhere nothing is admitted and nothing is charged; once the turn
     * is free, admission resumes.
     *
     * @test
     */
    public function admission_fails_closed_when_it_cannot_take_its_turn(): void
    {
        $budget = new ProviderRequestBudget('explore_lock_held', 100, 100);
        $held   = Cache::lock(ProviderRequestBudget::ADMISSION_LOCK, 30);

        $this->assertTrue($held->get());

        try {
            $this->assertSame(
                ProviderRequestBudget::REASON_ADMISSION_UNAVAILABLE,
                ProviderRequestBudget::admit(['global' => $budget], 0)
            );
            $this->assertSame(0, $budget->spent()['hourly'], 'a refusal charges nothing');
        } finally {
            $held->release();
        }

        $this->assertNull(ProviderRequestBudget::admit(['global' => $budget], 0));
        $this->assertSame(1, $budget->spent()['hourly']);
    }

    /**
     * The final-unit race with REAL concurrency: several PHP processes, the
     * real file cache store this deployment uses, all admitting against one
     * ceiling at once. However they interleave, exactly the ceiling is admitted
     * and exactly the ceiling is charged — not one unit more.
     *
     * Check-then-charge could not promise this: two processes can both read 19
     * of 20 and both proceed, and on the file driver increment() is itself a
     * read-modify-write that can lose an update.
     *
     * @test
     */
    public function concurrent_processes_cannot_race_past_the_ceiling(): void
    {
        $dir = sys_get_temp_dir() . '/explore-admission-race-' . bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);

        $script = $dir . '/racer.php';
        file_put_contents($script, <<<'PHP'
<?php
[, $base, $cacheDir, $mode, $cap, $attempts] = $argv;
require $base . '/vendor/autoload.php';

use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;

$container = new Illuminate\Container\Container();
$container->instance('cache', new Illuminate\Cache\Repository(
    new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem(), $cacheDir)
));
Illuminate\Support\Facades\Facade::setFacadeApplication($container);

$budget = new ProviderRequestBudget('explore_race', null, (int) $cap);

if ($mode === 'spent') {
    echo $budget->spent()['daily'];
    exit(0);
}

$admitted = 0;
for ($i = 0; $i < (int) $attempts; $i++) {
    if (ProviderRequestBudget::admit(['global' => $budget], 10) === null) {
        $admitted++;
    }
}
echo $admitted;
PHP);

        $php    = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $cap    = 20;
        $racers = [];

        try {
            // Four processes × 15 attempts = 60 attempts against a ceiling of 20.
            for ($i = 0; $i < 4; $i++) {
                $process = new Process([$php, $script, base_path(), $dir, 'race', (string) $cap, '15']);
                $process->setTimeout(120);
                $process->start();
                $racers[] = $process;
            }

            $admitted = 0;

            foreach ($racers as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $admitted += (int) trim($process->getOutput());
            }

            $spent = new Process([$php, $script, base_path(), $dir, 'spent', (string) $cap, '0']);
            $spent->mustRun();

            $this->assertSame($cap, $admitted, 'sixty racing attempts admit exactly the ceiling');
            $this->assertSame($cap, (int) trim($spent->getOutput()), 'and charge exactly the ceiling');
        } finally {
            (new Filesystem())->deleteDirectory($dir);
        }
    }

    /* ── no second budget system ────────────────────────────────────────── */

    /**
     * The guard COMPOSES the shared component; it does not reimplement one.
     * Two mechanisms counting "a request" would eventually disagree about what
     * one is, and the disagreement would surface as an unexplained bill.
     *
     * @test
     */
    public function the_guard_delegates_all_accounting_to_the_shared_budget(): void
    {
        $source = (string) file_get_contents(app_path('Services/Explore/Guards/ExploreProviderBudget.php'));

        $this->assertStringContainsString('use App\Services\Location\Coordinates\Guards\ProviderRequestBudget;', $source);

        // No storage, no counters, no windows of its own.
        foreach (['Cache::', 'increment(', 'gmdate(', 'RateLimiter'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, "the guard must not implement its own accounting ({$forbidden})");
        }

        // And no rival budget class was introduced anywhere.
        foreach (glob(app_path('Services/Explore/**/*.php')) ?: [] as $path) {
            $this->assertStringNotContainsString('class BridgeRequestBudget', (string) file_get_contents($path));
            $this->assertStringNotContainsString('class GoogleRequestBudget', (string) file_get_contents($path));
        }

        $this->assertTrue(class_exists(ProviderRequestBudget::class));
    }
}
