<?php

namespace Tests\Feature\Stellar\Matching\Parity;

use App\Models\SmartTagAssignment;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B2 — the parity command writes nothing, calls nothing, dispatches nothing.
 *
 * Driven through the real command over rows that produce every kind of finding (allowed,
 * undeclared, unresolvable, error parity), with the post-attachment tag level both off and on,
 * so every branch that could write, fetch or dispatch is actually reached.
 */
class CanonicalMatchingParityReadOnlyTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    private const ISOLATE = ['PoolPrivateYN' => true, 'GarageYN' => true, 'WaterfrontYN' => true];

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase migrates the first database test of a process through $this->artisan(),
        // which binds OutputStyle to a mock for that application; Kernel::call() output would
        // then go to the mock instead of the buffer these assertions read.
        unset($this->app[OutputStyle::class]);
    }

    /** @dataProvider smartTagPostures */
    public function test_a_full_run_is_select_only_and_changes_no_row(bool $smartTags): void
    {
        config()->set('smart_tags_wiring.seeker_preferences_enabled', $smartTags);
        config()->set('smart_tags_wiring.seeker_matching_enabled', $smartTags);

        $this->seedEveryFindingKind();
        $before = $this->tableCounts();

        Http::fake();
        Queue::fake();
        Bus::fake();

        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });

        $out  = new BufferedOutput();
        $code = $this->app[Kernel::class]->call('matching:canonical-parity', ['--json' => true], $out);
        $report = json_decode($out->fetch(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains($code, [4, 5], 'the seeded undeclared difference is reported');
        $this->assertSame($smartTags ? 'EXERCISED' : 'NOT_EXERCISED', $report['result']['smart_tags']['state']);

        $this->assertNotEmpty($sql);
        foreach ($sql as $statement) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $statement, "a non-read statement ran: {$statement}");
        }

        Http::assertNothingSent();
        Queue::assertNothingPushed();
        Bus::assertNothingDispatched();

        $this->assertSame($before, $this->tableCounts(), 'no row was added or removed anywhere');
        $this->assertSame($smartTags, config('smart_tags_wiring.seeker_matching_enabled'), 'the run changes no setting');
    }

    public static function smartTagPostures(): array
    {
        return ['smart tags off' => [false], 'smart tags on' => [true]];
    }

    public function test_no_report_file_is_written_unless_one_is_named(): void
    {
        $this->storeAllBaselineFixtures();
        $before = $this->filesUnder([storage_path('app'), base_path('bootstrap/cache')]);

        $this->app[Kernel::class]->call('matching:canonical-parity', [], new BufferedOutput());
        $this->app[Kernel::class]->call('matching:canonical-parity', ['--json' => true], new BufferedOutput());

        $this->assertSame($before, $this->filesUnder([storage_path('app'), base_path('bootstrap/cache')]));
    }

    private function seedEveryFindingKind(): void
    {
        $this->storeAllBaselineFixtures();                                                               // AD-1 / exact
        $this->storeBaselineFixture('residential', self::ISOLATE, 'ro_ad2', ['latitude' => 0, 'longitude' => 0]);
        $this->storeBaselineFixture('residential', self::ISOLATE, 'ro_und', ['living_area' => 1500.5]);   // undeclared
        $this->storeBaselineFixture('residential', self::ISOLATE, 'ro_unres', ['provider' => 'some_other_mls']);
        $this->storeBaselineFixture('residential', self::ISOLATE, 'ro_err', ['latitude' => 'not-a-number']);
        $tagged = $this->storeBaselineFixture('residential', self::ISOLATE, 'ro_tag');

        SmartTagAssignment::create([
            'listing_type' => 'bridge', 'listing_id' => $tagged->id, 'tag_key' => 'updated_kitchen',
            'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_mls',
        ]);
    }

    /** @return array<string,int> every application table's row count */
    private function tableCounts(): array
    {
        $tables = array_column(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    /** @return list<string> */
    private function filesUnder(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $files[] = $f->getPathname() . '@' . $f->getMTime();
            }
        }
        sort($files);

        return $files;
    }
}
