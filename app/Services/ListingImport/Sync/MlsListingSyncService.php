<?php

namespace App\Services\ListingImport\Sync;

use App\Services\ListingImport\Media\MlsListingGallerySync;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\QuickImport\MlsQuickImportResult;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Support\Listing\ListingPhotoEntry;
use App\Support\Listing\MlsSourceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles one MLS-linked listing with its current Stellar source record.
 *
 * THE CONTRACT THIS IMPLEMENTS
 * ----------------------------
 * Stellar is authoritative for MLS-sourced FACTS — price, status, beds, baths,
 * square footage, year built, property type, features, HOA, taxes, agent and
 * brokerage attribution, photographs. The listing follows the feed with no
 * manual re-import.
 *
 * BidYourOffer is authoritative for TERMS — Listing Method, Your Terms, bidding
 * configuration, conditional answers, offer requirements, platform settings,
 * bids, offers, counters, history, and anything the user uploaded. Sync never
 * writes any of it. {@see MlsSyncFieldPolicy} is where that boundary is decided,
 * as an intersection, so a field reaches a listing by being named and never by
 * escaping a list.
 *
 * WHAT THIS CLASS DELIBERATELY DOES NOT OWN
 * -----------------------------------------
 * It maps nothing. The canonical-fact-to-meta-field translation is
 * {@see MlsFactProjection}, shared with the import path; the gallery rules are
 * {@see MlsListingGallerySync}, unchanged; the supplemental payload is
 * {@see MlsSupplementalDetails}, unchanged; the allow-lists are the import
 * path's. This class decides WHEN to sync and WHAT is allowed to move, and
 * delegates every question of HOW.
 *
 * FRESHNESS IS CHECKED BEFORE ANYTHING IS SENT
 * --------------------------------------------
 * Two gates stand in front of Bridge, in this order, and both are local:
 *
 *   1. The freshness window — a stored timestamp comparison. Inside it, no
 *      request is made at all. This is what stops a page render becoming a
 *      request, and it costs nothing.
 *   2. The per-listing lock — two viewers arriving together produce one fetch,
 *      not two, and cannot both rewrite the gallery.
 *
 * Only past both does a request happen; then the source's own
 * ModificationTimestamp decides whether anything is written. An unchanged record
 * writes NOTHING but the attempt timestamp, so a listing's updated_at does not
 * churn and neither do its meta rows.
 *
 * FAILURE NEVER DESTROYS ANYTHING
 * -------------------------------
 * A provider that cannot be reached is recorded and the listing is left exactly
 * as it was — last-known-good data intact, terms intact, photographs intact.
 * "Bridge did not answer" is never allowed to become "this listing is gone":
 * UNAVAILABLE and NOT_FOUND are distinct outcomes and only the first is retried
 * sooner. Even NOT_FOUND deletes nothing; it records the fact and leaves the
 * listing standing.
 */
class MlsListingSyncService
{
    public function __construct(
        private readonly MlsQuickImportService $quickImport,
        private readonly MlsListingGallerySync $gallerySync,
        private readonly MlsFactProjection $projection,
    ) {}

    /**
     * Bring one listing into line with its source.
     *
     * @param  object  $listing  a SellerAgentAuction or LandlordAgentAuction
     * @param  bool    $force    skip the freshness window and the unchanged-source
     *                           short-circuit; still takes the lock, still never
     *                           writes a protected field
     */
    public function sync(object $listing, string $role, bool $force = false): MlsSyncOutcome
    {
        if (! (bool) config('mls_sync.enabled', false)) {
            return MlsSyncOutcome::disabled();
        }

        // Two role checks, and neither is redundant. The policy's list is the
        // structural fact — only Seller and Landlord listings describe one
        // property and so have a source record to be reconciled against. The
        // config list is operational and may only narrow that. A config file
        // cannot widen sync to a role that has nothing to sync.
        if (! in_array($role, MlsSyncFieldPolicy::SYNCABLE_ROLES, true)
            || ! in_array($role, (array) config('mls_sync.roles', []), true)) {
            return MlsSyncOutcome::unsupported();
        }

        $meta       = $this->meta($listing);
        $listingKey = trim((string) ($meta[Meta::META_LISTING_KEY] ?? ''));
        $mlsNumber  = trim((string) ($meta[Meta::META_MLS_NUMBER] ?? ''));

        // A manual listing has no source record and must never be touched. This
        // is the first thing checked after the gates precisely because every
        // later step assumes a source exists.
        if ($listingKey === '' && $mlsNumber === '') {
            return MlsSyncOutcome::notMlsLinked();
        }

        if (! $force && $this->isFresh($meta)) {
            return MlsSyncOutcome::fresh();
        }

        $lock = Cache::lock(
            'mls-sync:' . ($listingKey !== '' ? $listingKey : $mlsNumber),
            max(1, (int) config('mls_sync.lock_seconds', 30))
        );

        if (! $lock->block(max(0, (int) config('mls_sync.lock_wait_seconds', 5)), fn () => true)) {
            return MlsSyncOutcome::locked();
        }

        try {
            // Re-read inside the lock. The holder we waited behind may have just
            // finished this exact work, and repeating their fetch would spend a
            // request to learn what their write already recorded.
            $meta = $this->meta($listing->fresh());

            if (! $force && $this->isFresh($meta)) {
                return MlsSyncOutcome::fresh();
            }

            return $this->performSync($listing, $role, $meta, $listingKey, $mlsNumber, $force);
        } finally {
            $lock->release();
        }
    }

    /**
     * The fetch-compare-write cycle, already inside the lock.
     *
     * @param  array<string,mixed>  $meta
     */
    private function performSync(
        object $listing,
        string $role,
        array $meta,
        string $listingKey,
        string $mlsNumber,
        bool $force,
    ): MlsSyncOutcome {
        $this->write($listing, [Meta::META_SYNC_ATTEMPTED_AT => $this->now()]);

        $result = $this->quickImport->refresh($listingKey, $mlsNumber, $role);

        if ($result->status === MlsQuickImportResult::STATUS_UNAVAILABLE) {
            // The one branch that must change nothing about the listing's data.
            // Last-known-good stays; the failure is recorded so the backstop can
            // retry sooner than the freshness window would allow.
            $this->write($listing, [Meta::META_SYNC_ERROR => MlsSyncOutcome::UNAVAILABLE]);

            Log::warning('[MLS SYNC] source unavailable; listing left unchanged', [
                'listing_id'  => $listing->id,
                'role'        => $role,
                'listing_key' => $listingKey,
            ]);

            return MlsSyncOutcome::unavailable();
        }

        if (! $result->isFound()) {
            // Recorded, never acted on. A record absent from the feed today may
            // be one whose status we are not licensed to see, and unpublishing a
            // seller's listing on that inference is not a decision this class is
            // entitled to make.
            $this->write($listing, [Meta::META_SYNC_ERROR => $result->status]);

            Log::info('[MLS SYNC] source record not returned; listing left standing', [
                'listing_id' => $listing->id,
                'role'       => $role,
                'status'     => $result->status,
            ]);

            return MlsSyncOutcome::notFound();
        }

        $sourceModified = $result->modificationTimestamp;
        $storedModified = (string) ($meta[Meta::META_SOURCE_MODIFIED_AT] ?? '');

        if (! $force && $this->sourceUnchanged($sourceModified, $storedModified)) {
            // Nothing about the listing moves. Only the bookkeeping that proves
            // we asked, so the freshness window restarts and the next viewer
            // does not spend another request.
            $this->write($listing, [
                Meta::META_SYNCED_AT  => $this->now(),
                Meta::META_SYNC_ERROR => null,
            ]);

            return MlsSyncOutcome::unchanged($sourceModified);
        }

        return $this->applyChanges($listing, $role, $meta, $result, $sourceModified);
    }

    /**
     * Write the MLS-owned values, leaving everything else alone.
     *
     * @param  array<string,mixed>  $meta
     */
    private function applyChanges(
        object $listing,
        string $role,
        array $meta,
        MlsQuickImportResult $result,
        ?string $sourceModified,
    ): MlsSyncOutcome {
        $writes  = [];
        $journal = [];

        // ── 1. The mapped Tier-1 facts ──────────────────────────────────────
        //
        // Same projector, same MlsFieldMap, same vocabulary translation the
        // import uses — only the precedence differs. MODE_SYNC lets Stellar win
        // for the facts MlsSyncFieldPolicy says it owns, and the policy has
        // already removed everything it does not.
        $projected = $this->projection->project(
            role:               $role,
            facts:              $result->facts,
            existing:           $meta,
            mode:               MlsFactProjection::MODE_SYNC,
            sourcePropertyType: $result->sourcePropertyType,
        );

        foreach ($projected as $metaKey => $value) {
            if ($this->sameValue($meta[$metaKey] ?? null, $value)) {
                continue;
            }

            // Sync is allowed to overwrite a fact somebody typed — that is the
            // owner's decision and the point of the feature. It is not allowed
            // to make that irreversible, so the previous value is journalled in
            // the same pass.
            $journal[$metaKey] = $meta[$metaKey] ?? null;
            $writes[$metaKey]  = $value;
        }

        // ── 2. The authoritative MLS list price ─────────────────────────────
        //
        // Stored under its OWN key as well as flowing through the mapped price
        // field above. The mapped field (`maximum_budget` on seller,
        // `desired_rental_amount` on landlord) is a form input a user can edit;
        // this key is the MLS's own figure and is never edited. Keeping both
        // means a calculator or a hero can ask for "the MLS price" and get the
        // MLS price, rather than whatever the price input currently holds.
        if ($result->listPrice !== null
            && MlsSyncFieldPolicy::allowsPriceSync($role, $result->sourcePropertyType)) {
            $price = (string) $result->listPrice;

            if (! $this->sameValue($meta[Meta::META_LIST_PRICE] ?? null, $price)) {
                $writes[Meta::META_LIST_PRICE] = $price;
            }
        }

        // ── 3. Status, verbatim, both fields ────────────────────────────────
        $writes += $this->statusWrites($meta, $result);

        // ── 4. Change markers ───────────────────────────────────────────────
        $writes[Meta::META_SOURCE_MODIFIED_AT]        = $sourceModified;
        $writes[Meta::META_SOURCE_STATUS_CHANGED_AT]  = $result->statusChangeTimestamp;
        $writes[Meta::META_SOURCE_PRICE_CHANGED_AT]   = $result->priceChangeTimestamp;
        $writes[Meta::META_SOURCE_PHOTOS_CHANGED_AT]  = $result->photosChangeTimestamp;

        if ($result->sourcePropertyType !== null && $result->sourcePropertyType !== '') {
            $writes[Meta::META_SOURCE_PTYPE] = $result->sourcePropertyType;
        }

        // ── 5. Supplemental MLS payload — the feed wins, wholesale ──────────
        //
        // Contains nothing a user authored, so it is replaced rather than
        // merged: a merge would leave rows for facts the MLS has since
        // retracted, and a listing asserting a retracted fact is worse than one
        // missing it. This is also where agent and brokerage attribution and the
        // related-resource sections live, so they refresh with it.
        $details = $result->details;

        if ($details instanceof MlsSupplementalDetails) {
            $writes[Meta::META_DISPLAY_PERMISSIONS] = $details->permissions;

            if (! $details->isEmpty()) {
                $writes[Meta::META_PROPERTY_DETAILS] = $details->toArray();
            }
        }

        // ── 6. Photographs ──────────────────────────────────────────────────
        $media = $this->syncGallery($listing, $meta, $result);

        // ── 7. Bookkeeping ──────────────────────────────────────────────────
        $writes[Meta::META_SYNCED_AT]    = $this->now();
        $writes[Meta::META_REFRESHED_AT] = $this->now();
        $writes[Meta::META_SYNC_ERROR]   = null;

        if ($journal !== []) {
            $writes[Meta::META_SYNC_JOURNAL] = $this->appendJournal($meta, $journal);
        }

        $this->write($listing, $writes);

        $status = MlsSourceStatus::normalize($result->standardStatus ?? $result->mlsStatus);

        Log::info('[MLS SYNC] listing reconciled with source', [
            'listing_id'    => $listing->id,
            'role'          => $role,
            'changed_facts' => array_keys($journal),
            'source_status' => $status,
            'media'         => $media,
        ]);

        return MlsSyncOutcome::synced(
            changedKeys:         array_keys($journal),
            sourceStatus:        $status,
            sourceModifiedAt:    $sourceModified,
            statusUnrecognised:  $status !== null && ! MlsSourceStatus::isRecognised($status),
            mediaAdded:          $media['added'],
            mediaUpdated:        $media['updated'],
            mediaRemoved:        $media['removed'],
            userPhotosPreserved: $media['user_photos_kept'],
        );
    }

    /**
     * The two status fields, kept apart.
     *
     * `StandardStatus` is the RESO-normalised value and the authoritative market
     * status; `MlsStatus` is Stellar's own wording. The feed disagrees with
     * itself across them on real records — 'Closed' against 'Sold', 'Active
     * Under Contract' against 'Pending' — so neither is derived from the other
     * and neither is a fallback for the other.
     *
     * An unrecognised status is stored exactly as it arrived and flagged, never
     * dropped and never replaced by a BidYourOffer status. A status we do not
     * recognise is still what the MLS says.
     *
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    private function statusWrites(array $meta, MlsQuickImportResult $result): array
    {
        $writes = [];

        $standard = MlsSourceStatus::normalize($result->standardStatus);
        $mls      = MlsSourceStatus::normalize($result->mlsStatus);

        if ($standard !== null) {
            $writes[Meta::META_STANDARD_STATUS] = $standard;
        }

        if ($mls !== null) {
            $writes[Meta::META_SOURCE_STATUS] = $mls;
        }

        $authoritative = $standard ?? $mls;

        if ($authoritative !== null && ! MlsSourceStatus::isRecognised($authoritative)) {
            $writes[Meta::META_STATUS_UNRECOGNISED] = '1';

            Log::warning('[MLS SYNC] unrecognised MLS status preserved verbatim', [
                'status'    => $authoritative,
                'confirmed' => MlsSourceStatus::PROBE_CONFIRMED,
            ]);
        } elseif (($meta[Meta::META_STATUS_UNRECOGNISED] ?? null) !== null) {
            // A previously-unrecognised listing that now reports a status we do
            // know must not keep carrying the flag.
            $writes[Meta::META_STATUS_UNRECOGNISED] = null;
        }

        return $writes;
    }

    /**
     * Reconcile the gallery, or leave it entirely alone.
     *
     * Every rule — media-key identity, user uploads never touched, owner
     * ordering preserved, an empty incoming set removing nothing — belongs to
     * {@see MlsListingGallerySync} and is not re-decided here. This method's
     * only jobs are to pass the owner's ordering flag through and to write the
     * result back.
     *
     * `detachAll()` is deliberately NOT called for off-market statuses. Whether
     * a closed or withdrawn listing may keep displaying MLS photographs is a
     * licensing question about republication, and it has not been answered. An
     * unreviewed status string must not be what silently deletes a gallery.
     *
     * @param  array<string,mixed>  $meta
     * @return array{added:int,updated:int,removed:int,user_photos_kept:int}
     */
    private function syncGallery(object $listing, array $meta, MlsQuickImportResult $result): array
    {
        $empty = ['added' => 0, 'updated' => 0, 'removed' => 0, 'user_photos_kept' => 0];

        if ($result->media === []) {
            return $empty;
        }

        $sync = $this->gallerySync->sync(
            storedPhotos:    $meta['property_photos'] ?? null,
            incoming:        $result->media,
            orderCustomized: (bool) ($meta[Meta::META_ORDER_CUSTOM] ?? false),
        );

        $listing->saveMeta('property_photos', ListingPhotoEntry::toStorageCollection($sync->entries));

        return [
            'added'            => $sync->added,
            'updated'          => $sync->updated,
            'removed'          => $sync->removed,
            'user_photos_kept' => $sync->userPhotosPreserved,
        ];
    }

    /**
     * Append this run's overwritten values to the rollback journal.
     *
     * Bounded: only the most recent runs are kept, because the journal is a
     * safety net for "sync just changed something I typed", not an audit log.
     * An unbounded blob on a listing synced every six hours would grow without
     * limit for a question nobody asks about last year.
     *
     * @param  array<string,mixed>  $meta
     * @param  array<string,mixed>  $journal
     */
    private function appendJournal(array $meta, array $journal): array
    {
        $existing = $meta[Meta::META_SYNC_JOURNAL] ?? [];

        if (! is_array($existing)) {
            $existing = [];
        }

        $existing[] = [
            'at'       => $this->now(),
            'previous' => $journal,
        ];

        return array_slice($existing, -10);
    }

    /**
     * Is the last successful sync still inside the applicable window?
     *
     * Delegated to {@see MlsSyncFreshness} rather than decided here, because the
     * scheduled sweep must select exactly the listings this method would accept.
     * When the two disagreed, every sweep selected a batch, called this, and was
     * told FRESH for all of them — a run that costs a query per listing, sends
     * nothing, and reports success. That class carries the reasoning; this is
     * one of its two readers.
     */
    private function isFresh(array $meta): bool
    {
        return MlsSyncFreshness::isFresh($meta);
    }

    /**
     * Has the source record stood still since the last successful sync?
     *
     * Compared as instants, not strings: the feed's own formatting is not a
     * stable contract, and a purely textual comparison would report a change
     * every time Bridge altered its fractional-second precision.
     *
     * A missing source timestamp reads as CHANGED. We cannot prove it stood
     * still, and the cost of being wrong is one unnecessary write rather than a
     * listing frozen at a stale price.
     */
    private function sourceUnchanged(?string $source, string $stored): bool
    {
        if ($source === null || $source === '' || $stored === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($source)->equalTo(CarbonImmutable::parse($stored));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Persist a set of meta writes.
     *
     * A null value clears the key rather than storing the string "null" — the
     * error and flag keys are cleared on success, and a listing carrying the
     * literal 'null' would read as still-failing forever.
     *
     * @param  array<string,mixed>  $writes
     */
    private function write(object $listing, array $writes): void
    {
        foreach ($writes as $key => $value) {
            // The last line of defence. Nothing above should ever produce a
            // protected key, and if a future change does, it stops here rather
            // than reaching a seller's terms.
            //
            // No exception for `property_photos`: the gallery is written by
            // syncGallery() through MlsListingGallerySync, which owns the
            // user-upload rule, and it deliberately does not come through here.
            if (MlsSyncFieldPolicy::isProtectedMetaKey($key)) {
                Log::error('[MLS SYNC] refused to write a protected key', ['key' => $key]);

                continue;
            }

            $listing->saveMeta($key, $value);
        }
    }

    /** The listing's meta as a flat array. */
    private function meta(object $listing): array
    {
        return $listing->get->toArray();
    }

    /** Loose equality that treats 1 and '1' as the same stored value. */
    private function sameValue(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return is_array($a) && is_array($b) && $a == $b;
        }

        return (string) ($a ?? '') === (string) ($b ?? '');
    }

    private function now(): string
    {
        return now()->toIso8601String();
    }
}
