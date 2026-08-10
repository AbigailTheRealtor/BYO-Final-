<?php

namespace App\Console\Commands;

use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\Exceptions\CoordinateProviderUnavailable;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Console\Command;

/**
 * A single, deliberate, human-initiated call to the live US Census Geocoder.
 *
 * WHY THIS EXISTS
 * ---------------
 * The Census contract was verified directly — endpoint, benchmark, response
 * shape, the no-match shape, the error shapes, the 100-character limit, the
 * multi-match case. But that verification was done with curl, and every
 * automated test of the adapter runs against mocked HTTP. So there is exactly
 * one claim the entire test suite cannot make: that *this application's code*,
 * with its real config, its real HTTP client, its real timeout and its real
 * TLS stack, can successfully call the real service.
 *
 * That gap has to be closed by an actual request, and an actual request has to
 * be made by a person who meant to make it. Hence a command rather than a test:
 * a test would run in CI, on every branch, forever, quietly generating traffic
 * against a public service to re-prove something that changes about once a
 * decade.
 *
 * SAFETY POSTURE
 * --------------
 * Dry run is the default and the only mode that exists today. The command
 * resolves an address, prints what came back, and writes nothing anywhere — no
 * listing row, no property_location_dna record, no side effect beyond the
 * provider's own cache entry and a telemetry line. Persisting a probe result
 * would mean this command could silently become a way to populate production
 * data, which is a different feature with a different review.
 *
 * The `--force-probe` acknowledgement is required whenever the adapter is
 * disabled, which it is by default. That is the point: the flag being off is
 * the production posture, and proving the integration works must not require
 * turning it on for everybody first. The acknowledgement makes the operator
 * state, in the command line itself, that they intend to send real traffic.
 *
 * NEVER RUNS ITSELF
 * -----------------
 * Not scheduled, not dispatched, not called from application code. The only way
 * it runs is somebody typing it.
 *
 * Usage:
 *   php artisan location:probe-census-address "315 E Madison St" \
 *       --city=Tampa --state=FL --zip=33602 --force-probe
 */
class ProbeCensusAddress extends Command
{
    protected $signature = 'location:probe-census-address
        {address : Street line, e.g. "315 E Madison St"}
        {--unit= : Unit/apt/suite — excluded from the lookup, shown for context}
        {--city= : City}
        {--state= : Two-letter state or full name}
        {--zip= : ZIP or ZIP+4}
        {--force-probe : Acknowledge sending real traffic while the adapter is disabled}
        {--write : RESERVED — not implemented; persistence is a separate decision}';

    protected $description = 'Send ONE live request to the US Census Geocoder and print the result (dry run; never persists)';

    public function handle(): int
    {
        if ($this->option('write')) {
            $this->error('--write is not implemented.');
            $this->line('  This command is dry-run only. Persisting a probe result would let it');
            $this->line('  become a way to populate production data, which needs its own review.');

            return self::FAILURE;
        }

        $address = new PropertyAddress(
            address:     (string) $this->argument('address'),
            unitAddress: (string) ($this->option('unit') ?? ''),
            city:        (string) ($this->option('city') ?? ''),
            state:       (string) ($this->option('state') ?? ''),
            zip:         (string) ($this->option('zip') ?? ''),
        );

        if (! $address->hasMinimumForLookup()) {
            $this->error('Not enough address to look up.');
            $this->line('  A street line plus either a ZIP or a city and state is the minimum.');

            return self::FAILURE;
        }

        $enabled = (bool) config('census_geocoder.enabled', false);

        if (! $enabled && ! $this->option('force-probe')) {
            $this->error('The Census geocoder is disabled (CENSUS_GEOCODER_ENABLED=false).');
            $this->line('  That is the intended production posture, and proving the integration');
            $this->line('  works must not require enabling it for everybody first.');
            $this->newLine();
            $this->line('  Re-run with --force-probe to send one real request anyway.');

            return self::FAILURE;
        }

        $this->info('Live US Census Geocoder probe — DRY RUN, nothing will be persisted');
        $this->newLine();

        $this->line('  <fg=gray>Submitted (normalized)</> ' . $address->coordinateLookupLine());

        if ($address->hasUnit()) {
            $this->line('  <fg=gray>Unit (excluded)      </> ' . $address->propertyIdentityLine());
        }

        $this->line('  <fg=gray>Endpoint             </> ' . config('census_geocoder.base_url'));
        $this->line('  <fg=gray>Benchmark            </> ' . config('census_geocoder.benchmark'));
        $this->line('  <fg=gray>Flag                 </> ' . ($enabled ? 'enabled' : 'disabled (forced probe)'));
        $this->newLine();

        // The adapter reads the flag through isAvailable(); this probe calls
        // resolve() directly, which is what --force-probe means in practice. The
        // caps and the breaker still apply — a probe is real traffic and is
        // counted and refused like any other.
        $startedAt = microtime(true);

        try {
            $result = (new CensusGeocoderAdapter())->resolve($address);
        } catch (CoordinateProviderUnavailable $e) {
            $this->error('Provider unavailable');
            $this->line('  <fg=gray>Kind    </> ' . $e->kind);
            $this->line('  <fg=gray>Reason  </> ' . $e->reason);
            $this->line('  <fg=gray>Detail  </> ' . $e->getMessage());
            $this->line('  <fg=gray>Latency </> ' . $this->elapsed($startedAt));

            return self::FAILURE;
        }

        $this->report($result, $startedAt);

        return $result->isResolved() ? self::SUCCESS : self::FAILURE;
    }

    private function report(PropertyCoordinateResult $result, float $startedAt): void
    {
        if (! $result->isResolved()) {
            $this->warn('No coordinate');
            $this->line('  <fg=gray>Status  </> unresolved');
            $this->line('  <fg=gray>Reason  </> ' . ($result->reason ?? '—'));
            $this->line('  <fg=gray>Latency </> ' . $this->elapsed($startedAt));

            return;
        }

        $this->info('Resolved');
        $this->line('  <fg=gray>Status          </> resolved');
        $this->line('  <fg=gray>Matched address </> ' . ($result->normalizedAddress ?? '—'));
        $this->line('  <fg=gray>Latitude        </> ' . $result->latitude);
        $this->line('  <fg=gray>Longitude       </> ' . $result->longitude);
        $this->line('  <fg=gray>Precision       </> ' . $result->precision->value . '  (' . $result->precision->label() . ')');
        $this->line('  <fg=gray>Provider        </> ' . ($result->provider ?? '—'));
        $this->line('  <fg=gray>Source          </> ' . ($result->source?->value ?? '—'));
        $this->line('  <fg=gray>Usable for DNA  </> ' . ($result->isUsableForLocationDna() ? 'yes' : 'no'));
        $this->line('  <fg=gray>Latency         </> ' . $this->elapsed($startedAt));
        $this->newLine();
        $this->line('  <fg=gray>Nothing was written. This was a dry run.</>');
    }

    private function elapsed(float $startedAt): string
    {
        return (int) round((microtime(true) - $startedAt) * 1000) . ' ms';
    }
}
