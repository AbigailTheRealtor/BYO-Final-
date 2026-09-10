<?php

namespace App\Console\Commands;

use App\Services\Bridge\BridgeApiService;
use Illuminate\Console\Command;

/**
 * Ask this Bridge dataset two questions the live-sync design depends on, and
 * that nothing in the repository can answer.
 *
 * WHY THIS EXISTS
 * ---------------
 * The MLS live-sync contract says the Stellar listing's own lifecycle governs an
 * MLS-linked BidYourOffer listing. Building that requires knowing two things
 * about the feed, and the repository is not a witness to either:
 *
 *   1. Does Stellar actually publish `ExpirationDate`? `MlsFieldCatalog` gives
 *      it a disposition ('Listing Expires', LISTING_CONTEXT), so it would be
 *      preserved if it arrived — but it is absent from all seven per-type
 *      fixtures, including the four cut from real cached records. "Absent from
 *      seven records" is not "the feed does not send it", and the sync must not
 *      invent an expiration it cannot source.
 *
 *   2. Which `StandardStatus` / `MlsStatus` strings does this dataset really
 *      emit? Every fixture is `Active`, and the only status literal anywhere in
 *      the codebase is the `StandardStatus eq 'Active'` search clause. A status
 *      map written from memory would be a map of RESO's vocabulary, not
 *      Stellar's.
 *
 * SAFETY POSTURE, mirroring `mls:probe-resources` and `location:probe-census-address`
 * -----------------------------------------------------------------------------------
 *   · refuses to run without --force-probe;
 *   · issues one small page for the corpus sample, then at most one $top=1
 *     request per candidate status — a fixed, tiny number of narrow requests,
 *     never a crawl;
 *   · writes NOTHING — no upsert, no cache, no listing touched. It calls
 *     BridgeApiService directly and deliberately not BridgeListingLookupService,
 *     whose lookups cache what they find;
 *   · never scheduled, and called from no application code path;
 *   · prints dates, statuses and counts — lifecycle metadata, not anybody's
 *     contact details. No remarks, no agent fields, no addresses.
 */
class ProbeBridgeLifecycle extends Command
{
    protected $signature = 'mls:probe-lifecycle
                            {--force-probe : Actually send the requests}
                            {--top=25 : Records to sample for field presence}
                            {--mls= : Also probe one specific MLS number (ListingId)}';

    protected $description = 'Ask Bridge whether Stellar publishes ExpirationDate and which status strings this dataset emits. Read-only.';

    /**
     * The lifecycle fields the sync would want to read.
     *
     * Probed for presence and population separately: a key that is present and
     * null on every record is a different fact from a key the feed omits, and
     * the two lead to different designs.
     */
    private const LIFECYCLE_FIELDS = [
        'ExpirationDate',
        'STELLAR_ExpireRenewalDate',
        'OffMarketDate',
        'OffMarketTimestamp',
        'ContractStatusChangeDate',
        'ModificationTimestamp',
        'StatusChangeTimestamp',
        'PriceChangeTimestamp',
        'PhotosChangeTimestamp',
        'BridgeModificationTimestamp',
        'StandardStatus',
        'MlsStatus',
        'ListPrice',
    ];

    /**
     * Status strings to test for existence, one narrow request each.
     *
     * This list is the OWNER-SUPPLIED transition vocabulary plus the two RESO
     * spellings of "cancelled". It is a list of things to ASK ABOUT, not a list
     * of things assumed to exist — a status that returns nothing here is
     * reported as unconfirmed and must not end up in a mapping table.
     */
    private const CANDIDATE_STATUSES = [
        'Active',
        'Pending',
        'Closed',
        'Expired',
        'Withdrawn',
        'Canceled',
        'Cancelled',
        'Temporarily Off Market',
        'Active Under Contract',
        'Coming Soon',
    ];

    public function handle(BridgeApiService $api): int
    {
        if (! $this->option('force-probe')) {
            $this->warn('Refusing to run without --force-probe. This command sends live Bridge requests.');
            $this->line('It writes nothing: no upsert, no cache, no listing is touched.');

            return self::SUCCESS;
        }

        if (empty(config('bridge.dataset')) || empty(config('bridge.token'))) {
            $this->error('Bridge credentials are not configured. Nothing was sent.');

            return self::FAILURE;
        }

        $top = max(1, (int) $this->option('top'));

        $this->line('');
        $this->info('=== 1. Lifecycle field presence, sampled over ' . $top . ' record(s) ===');

        $records = $api->fetchProperties($top);

        if ($records === []) {
            $this->error('No records returned (' . ($api->lastFailure() ?? 'empty result') . '). Nothing further probed.');

            return self::FAILURE;
        }

        $this->reportFieldPresence($records);

        $this->line('');
        $this->info('=== 2. Which status strings does this dataset emit? ===');
        $this->reportStatusVocabulary($api);

        $mls = trim((string) $this->option('mls'));

        if ($mls !== '') {
            $this->line('');
            $this->info('=== 3. Single record: ListingId ' . $mls . ' ===');
            $this->reportSingleRecord($api, $mls);
        }

        $this->line('');
        $this->info('Probe complete. Nothing was written.');

        return self::SUCCESS;
    }

    /**
     * For each lifecycle field: on how many sampled records is the key present,
     * and on how many is it actually populated?
     *
     * @param  list<array<string,mixed>>  $records
     */
    private function reportFieldPresence(array $records): void
    {
        $total = count($records);
        $rows  = [];

        foreach (self::LIFECYCLE_FIELDS as $field) {
            $present   = 0;
            $populated = 0;
            $sample    = null;

            foreach ($records as $record) {
                if (! array_key_exists($field, $record)) {
                    continue;
                }

                $present++;
                $value = $record[$field];

                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                $populated++;
                $sample ??= is_scalar($value) ? (string) $value : gettype($value);
            }

            $rows[] = [
                $field,
                $present . '/' . $total,
                $populated . '/' . $total,
                $sample === null ? '—' : \Illuminate\Support\Str::limit($sample, 30),
            ];
        }

        $this->table(['Field', 'Key present', 'Populated', 'Example value'], $rows);
    }

    /**
     * One narrow request per candidate status. A status that returns a record
     * exists in this dataset; one that returns nothing is UNCONFIRMED — which is
     * not the same as "does not exist", and is reported as such.
     */
    private function reportStatusVocabulary(BridgeApiService $api): void
    {
        $rows = [];

        foreach (self::CANDIDATE_STATUSES as $status) {
            $escaped = str_replace("'", "''", $status);
            $records = $api->fetchProperties(1, "StandardStatus eq '{$escaped}'");
            $failure = $api->lastFailure();

            if ($failure !== null) {
                $rows[] = [$status, 'REQUEST FAILED (' . $failure . ')', '—', '—'];

                continue;
            }

            if ($records === []) {
                $rows[] = [$status, 'unconfirmed (no record)', '—', '—'];

                continue;
            }

            $record  = $records[0];
            $mlsStat = $record['MlsStatus'] ?? null;
            $expiry  = array_key_exists('ExpirationDate', $record)
                ? ($record['ExpirationDate'] ?? 'null')
                : '(key absent)';

            $rows[] = [
                $status,
                'CONFIRMED',
                is_scalar($mlsStat) ? (string) $mlsStat : '—',
                is_scalar($expiry) ? (string) $expiry : '—',
            ];
        }

        $this->table(
            ['StandardStatus probed', 'Result', 'MlsStatus on that record', 'ExpirationDate on that record'],
            $rows
        );
    }

    /**
     * The lifecycle fields of one named listing, for spot-checking a record an
     * operator is actually looking at.
     */
    private function reportSingleRecord(BridgeApiService $api, string $mlsNumber): void
    {
        $escaped = str_replace("'", "''", $mlsNumber);
        $records = $api->fetchProperties(1, "ListingId eq '{$escaped}'");

        if ($records === []) {
            $this->warn('No record for that ListingId (' . ($api->lastFailure() ?? 'not found') . ').');

            return;
        }

        $record = $records[0];
        $rows   = [];

        foreach (self::LIFECYCLE_FIELDS as $field) {
            $rows[] = [
                $field,
                array_key_exists($field, $record) ? 'yes' : 'NO',
                array_key_exists($field, $record) && is_scalar($record[$field])
                    ? (string) ($record[$field] ?? 'null')
                    : (array_key_exists($field, $record) ? gettype($record[$field]) : '—'),
            ];
        }

        $this->table(['Field', 'Key present', 'Value'], $rows);
    }
}
