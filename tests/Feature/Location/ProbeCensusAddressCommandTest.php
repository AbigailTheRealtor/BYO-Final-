<?php

namespace Tests\Feature\Location;

use App\Models\PropertyLocationDna;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The probe command exists to send real traffic, so its tests must not.
 *
 * Every case here mocks HTTP. The one thing the command is for — proving this
 * application's own code can reach the live service — is deliberately not
 * automated: a test asserting that would run in CI on every branch forever,
 * generating traffic against a public service to re-verify something that
 * changes about once a decade.
 *
 * No real address belongs in this file either. The fixtures below are the
 * Census Bureau's own headquarters and a deliberately non-existent street.
 */
class ProbeCensusAddressCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINT = 'geocoding.geo.census.gov/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Laravel's test case mocks console output by default, which makes
        // Artisan::output() come back empty. These tests assert on what the
        // operator is actually told, so the real buffered output is needed.
        $this->withoutMockingConsoleOutput();

        // The shipped posture. Individual tests opt in where that is the point.
        config()->set('census_geocoder.enabled', false);
    }

    private function matchBody(): array
    {
        return ['result' => ['addressMatches' => [[
            'coordinates'       => ['x' => -76.928365658124, 'y' => 38.845053106269],
            'addressComponents' => ['state' => 'DC', 'zip' => '20233'],
            'matchedAddress'    => '4600 SILVER HILL RD, WASHINGTON, DC, 20233',
        ]]]];
    }

    /**
     * Run the command and return its exit code plus everything it printed.
     *
     * Artisan::call rather than $this->artisan(): Laravel 8 has no
     * expectsOutputToContain(), and asserting on captured output is clearer
     * here anyway — several tests care about more than one line of it.
     *
     * @param  array<string, mixed> $extra
     * @return array{code: int, output: string}
     */
    private function probe(array $extra = []): array
    {
        $code = Artisan::call('location:probe-census-address', array_merge([
            'address'  => '4600 Silver Hill Rd',
            '--city'   => 'Washington',
            '--state'  => 'DC',
            '--zip'    => '20233',
        ], $extra));

        return ['code' => $code, 'output' => Artisan::output()];
    }

    /** @param array<string, mixed> $args */
    private function runProbe(array $args): array
    {
        $code = Artisan::call('location:probe-census-address', $args);

        return ['code' => $code, 'output' => Artisan::output()];
    }

    // ── refusal by default ──────────────────────────────────────────────────

    public function test_it_refuses_while_the_adapter_is_disabled(): void
    {
        Http::fake();

        $run = $this->probe();

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('CENSUS_GEOCODER_ENABLED=false', $run['output']);

        Http::assertNothingSent();
    }

    public function test_the_refusal_names_the_acknowledgement_that_lifts_it(): void
    {
        Http::fake();

        $run = $this->probe();

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('--force-probe', $run['output']);
    }

    public function test_force_probe_permits_a_run_while_disabled(): void
    {
        // The flag being off is the production posture; proving the integration
        // works must not require turning it on for everybody first.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->assertSame(0, $this->probe(['--force-probe' => true])['code']);

        Http::assertSentCount(1);
    }

    public function test_no_acknowledgement_is_needed_once_the_flag_is_on(): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $this->assertSame(0, $this->probe()['code']);
    }

    // ── output ──────────────────────────────────────────────────────────────

    public function test_a_successful_probe_reports_the_fields_that_matter(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $run = $this->probe(['--force-probe' => true]);

        $this->assertSame(0, $run['code']);

        foreach ([
            '4600 silver hill rd washington dc 20233', // submitted, normalized
            '38.845053106269',                         // latitude
            '-76.928365658124',                        // longitude
            'interpolated',                            // precision
            'us_census',                               // provider
        ] as $expected) {
            $this->assertStringContainsString($expected, $run['output']);
        }
    }

    public function test_a_no_match_is_reported_and_exits_non_zero(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['result' => ['addressMatches' => []]])]);

        $run = $this->runProbe([
            'address'       => '99999 Nonexistent Fake Rd',
            '--city'        => 'Tampa',
            '--state'       => 'FL',
            '--zip'         => '33602',
            '--force-probe' => true,
        ]);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('census_no_match', $run['output']);
    }

    public function test_a_provider_fault_is_reported_and_exits_non_zero(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 503)]);

        $run = $this->probe(['--force-probe' => true]);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('provider_http_503', $run['output']);
    }

    public function test_it_shows_the_endpoint_and_benchmark_it_will_use(): void
    {
        // A probe that does not say what it called proves less than it appears
        // to — a stale benchmark would be invisible in the result alone.
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $run = $this->probe(['--force-probe' => true]);

        $this->assertSame(0, $run['code']);
        $this->assertStringContainsString('geocoding.geo.census.gov', $run['output']);
        $this->assertStringContainsString('Public_AR_Current', $run['output']);
    }

    // ── dry run ─────────────────────────────────────────────────────────────

    public function test_a_successful_probe_persists_nothing(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->matchBody())]);

        $before = PropertyLocationDna::count();

        $this->assertSame(0, $this->probe(['--force-probe' => true])['code']);

        $this->assertSame(
            $before,
            PropertyLocationDna::count(),
            'A probe must never create listing or location rows'
        );
    }

    public function test_the_write_option_is_refused_rather_than_silently_ignored(): void
    {
        // Reserved in the signature so that somebody reaching for it is told it
        // is a separate decision, rather than discovering it does nothing.
        Http::fake();

        $run = $this->probe(['--force-probe' => true, '--write' => true]);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('not implemented', $run['output']);

        Http::assertNothingSent();
    }

    // ── input validation ────────────────────────────────────────────────────

    public function test_an_insufficient_address_is_refused_before_any_request(): void
    {
        Http::fake();

        $run = $this->runProbe([
            'address'       => '4600 Silver Hill Rd',
            '--force-probe' => true,
        ]);

        $this->assertSame(1, $run['code']);

        Http::assertNothingSent();
    }

    // ── it never runs itself ────────────────────────────────────────────────

    public function test_the_probe_is_not_scheduled(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        foreach ($schedule->events() as $event) {
            $this->assertStringNotContainsString(
                'probe-census-address',
                $event->command ?? '',
                'The probe must only ever run because somebody typed it'
            );
        }
    }

    public function test_no_application_code_invokes_the_probe(): void
    {
        $hits = [];

        foreach ([
            glob(base_path('app/Http/Livewire/**/*.php')) ?: [],
            glob(base_path('app/Jobs/*.php')) ?: [],
            glob(base_path('app/Observers/*.php')) ?: [],
            glob(base_path('app/Services/**/*.php')) ?: [],
        ] as $group) {
            foreach ($group as $file) {
                if (str_contains((string) file_get_contents($file), 'probe-census-address')) {
                    $hits[] = $file;
                }
            }
        }

        $this->assertSame([], $hits, 'Nothing may dispatch the probe programmatically');
    }
}
