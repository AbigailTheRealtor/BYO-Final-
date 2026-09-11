<?php

namespace App\Services\Bridge;

use App\Models\BridgeProperty;
use App\Models\PropertyLocationDna;
use App\Services\LocationDna\LocationDnaGeocodeService;
use Illuminate\Support\Facades\Cache;

/**
 * Should a Bridge row that was just upserted have Location DNA scheduled?
 *
 * The normalizer answers half of it: a NEW row, or one whose address or
 * coordinates changed, needs DNA. That was the whole rule while every importer
 * dispatched. It stopped being enough once a caller could upsert WITHOUT
 * dispatching — Explore does, so that a map pass never fans out into Google
 * Places through Location DNA. A row Explore imported first is no longer new,
 * and no longer re-addressed, by the time a normal import reaches it; on the
 * normalizer's rule alone it would have been left without DNA for good.
 *
 * The other half is read from state that already exists: the
 * `property_location_dna` row. The pipeline writes that row — with the address
 * it was computed for — at the start of every run that passes its required-field
 * check, before any provider is called and whatever the run then does. So "no
 * row for this address" means DNA was never requested for the address the
 * listing has NOW, and that is when a normal caller schedules it. No marker, no
 * schema, no second record of the same fact.
 *
 * WHAT KEEPS THIS FROM BECOMING A STORM
 * -------------------------------------
 *  · Once per row and address. The run writes the row, so the next import finds
 *    it and dispatches nothing — after a failed or skipped run too, exactly as
 *    before: an import never retried a failed DNA run.
 *  · A row missing an address field the geocoder requires is never backfilled.
 *    That run is skipped WITHOUT writing a row, so dispatching it would repeat
 *    on every import.
 *  · Price, status and every other non-address change leaves the row current,
 *    so it dispatches nothing.
 *  · EVERY dispatch decided here — the normalizer's own included — takes a short
 *    claim on the row, and the backfill honours it. Between a job being
 *    scheduled and it writing its row (a queued job, or two imports racing),
 *    the row has no DNA record yet; without the claim the next import would read
 *    that as "never requested" and schedule a duplicate.
 *
 * Callers still decide WHETHER they dispatch at all. This is consulted only by a
 * caller that has not opted out (`dispatchDna: false`), so Explore never reaches
 * it and never pays for the query.
 */
final class BridgeLocationDnaState
{
    /** The listing type the pipeline records Bridge rows under. */
    public const LISTING_TYPE = 'bridge';

    /**
     * How long one claim stands. On the sync queue the job runs inline and
     * writes its row at once; the window covers racing imports and a job that
     * is queued rather than run.
     */
    private const CLAIM_SECONDS = 900;

    private const CLAIM_PREFIX = 'bridge_location_dna_backfill_';

    /**
     * The dispatch decision for a caller that has not opted out.
     *
     * The normalizer's rule is kept verbatim and still dispatches
     * unconditionally. The backfill can only ever ADD a dispatch, and only for a
     * row that has never had DNA requested for its current address and is not
     * already claimed.
     */
    public static function shouldDispatch(UpsertResult $result): bool
    {
        $id = (int) $result->model->id;

        if ($result->shouldDispatchDna()) {
            // Taken so a backfill cannot schedule a second job for this row
            // before this one has run and written its record.
            self::claim($id);

            return true;
        }

        return self::missingForCurrentAddress($result->model) && self::claim($id);
    }

    /**
     * True when no Location DNA has been requested for this row's CURRENT
     * address — and a run would actually record one if asked.
     *
     * The comparison is the geocode step's own: the same five fields, trimmed
     * the same way, against what the pipeline stored. The pipeline copies them
     * verbatim from this row (LocationDnaPipelineRunner::resolveBridgeAddress()),
     * so a row whose DNA was requested always matches itself.
     */
    public static function missingForCurrentAddress(BridgeProperty $row): bool
    {
        $address = [
            'address' => trim((string) $row->unparsed_address),
            'city'    => trim((string) $row->city),
            'state'   => trim((string) $row->state_or_province),
            'county'  => trim((string) $row->county_or_parish),
            'zip'     => trim((string) $row->postal_code),
        ];

        foreach (LocationDnaGeocodeService::REQUIRED_ADDRESS_FIELDS as $field) {
            if ($address[$field] === '') {
                return false;
            }
        }

        $dna = PropertyLocationDna::query()
            ->where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', $row->id)
            ->first(['source_address', 'source_city', 'source_state', 'source_county', 'source_zip']);

        if ($dna === null) {
            return true;
        }

        return (string) $dna->source_address !== $address['address']
            || (string) $dna->source_city !== $address['city']
            || (string) $dna->source_state !== $address['state']
            || (string) ($dna->source_county ?? '') !== $address['county']
            || (string) ($dna->source_zip ?? '') !== $address['zip'];
    }

    /** Atomically claim this row's pending dispatch; false when already claimed. */
    private static function claim(int $bridgePropertyId): bool
    {
        return Cache::add(self::CLAIM_PREFIX . $bridgePropertyId, true, self::CLAIM_SECONDS);
    }
}
