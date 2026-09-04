<?php

namespace App\Console\Commands;

use App\Services\Bridge\BridgeApiService;
use Illuminate\Console\Command;

/**
 * Ask this Bridge dataset which related resources it actually exposes.
 *
 * WHY A COMMAND RATHER THAN AN ENRICHMENT LAYER
 * ---------------------------------------------
 * The 2026-09-04 payload audit established what the Property resource does NOT
 * carry: no `OpenHouse`, `Rooms` or `Units` key on any of 1,224 cached records,
 * and no `ListAgentDirectPhone`, `ListAgentMobilePhone`, `ListAgentURL`,
 * `ListAgentStateLicense`, `ListOfficeURL`, `ListOfficeEmail`, `ListOfficeFax`
 * or `ListOffice` address columns either. Those are Member/Office/OpenHouse
 * resource fields, and this application has never issued a request to any
 * resource but `/Property`.
 *
 * Whether this dataset's licence exposes them is a question only Bridge can
 * answer, and it could not be asked while building this: the environment holds
 * no Bridge credentials. Writing a caching, back-off, N+1-avoiding enrichment
 * layer against resources nobody has confirmed exist would be building on a
 * guess — and the brief is explicit that missing contact information must not be
 * fabricated. So this command is the honest intermediate step: run it once with
 * credentials, and its output decides whether the enrichment layer is worth
 * building and against which fields.
 *
 * SAFETY POSTURE, mirroring `location:probe-census-address`
 * ---------------------------------------------------------
 *   · refuses to run without --force-probe;
 *   · reads one page of at most a few records per resource;
 *   · writes nothing — no cache, no upsert, no listing touched;
 *   · never scheduled, and called from no application code path;
 *   · prints field NAMES and counts, never field values, so an operator can
 *     paste the output into a ticket without pasting somebody's phone number.
 */
class ProbeBridgeResources extends Command
{
    protected $signature = 'mls:probe-resources
                            {--force-probe : Actually send the requests}
                            {--top=1 : Records to request per resource}';

    protected $description = 'Ask Bridge which related resources (Member, Office, OpenHouse, Room, Unit) this dataset exposes. Read-only.';

    /**
     * The resources a complete listing would want, and what each would answer.
     *
     * Ordered by how much the audit says we are missing without them.
     */
    private const RESOURCES = [
        'Member'    => 'agent direct/mobile phone, website, state licence — absent from Property',
        'Office'    => 'brokerage address, website, email, fax — absent from Property',
        'OpenHouse' => 'open house dates and times — STELLAR_OpenHouseCount is populated but the rows are not on Property',
        'Room'      => 'room-by-room dimensions — RoomsTotal is populated but no Rooms array is sent',
        'Unit'      => 'unit rosters for income properties — NumberOfUnitsTotal is populated but no Units array is sent',
        'Property'  => 'control: proves credentials and dataset are working before any absence is believed',
    ];

    public function handle(BridgeApiService $api): int
    {
        if (! $this->option('force-probe')) {
            $this->warn('Refusing to run without --force-probe.');
            $this->line('');
            $this->line('This sends live requests to the Bridge API using the configured credentials.');
            $this->line('It writes nothing and touches no listing, but it does spend real requests.');
            $this->line('');
            $this->line('  php artisan mls:probe-resources --force-probe');

            return self::SUCCESS;
        }

        if (empty(config('bridge.dataset')) || empty(config('bridge.token'))) {
            $this->error('BRIDGE_DATASET / BRIDGE_SERVER_TOKEN are not configured — nothing to probe.');

            return self::FAILURE;
        }

        $top  = max(1, (int) $this->option('top'));
        $rows = [];

        foreach (self::RESOURCES as $resource => $why) {
            $result = $api->probeResource($resource, $top);

            $rows[] = [
                $resource,
                $result['ok'] ? 'AVAILABLE' : 'NOT AVAILABLE',
                $result['status'] ?? '—',
                $result['ok'] ? (string) $result['count'] : '—',
                $result['ok'] ? (string) count($result['fields']) : '—',
                $result['error'] ?? '',
            ];

            if ($result['ok'] && $result['fields'] !== []) {
                $this->line("<info>{$resource}</info> — {$why}");
                $this->line('  fields: ' . implode(', ', $result['fields']));
                $this->line('');
            }
        }

        $this->table(['Resource', 'Status', 'HTTP', 'Rows', 'Fields', 'Error'], $rows);

        $this->line('');
        $this->line('Nothing was written. If Member/Office are AVAILABLE, the enrichment layer is');
        $this->line('worth building: resolve by the ListAgentKey / ListOfficeKey the Property record');
        $this->line('already supplies, cache per key, and never let a failed lookup fail an import.');
        $this->line('If they are NOT AVAILABLE, record that in the audit and stop — the deeper contact');
        $this->line('fields do not exist for us and must not be invented.');

        return self::SUCCESS;
    }
}
