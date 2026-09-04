<?php

namespace App\Services\ListingImport\QuickImport;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Services\ListingImport\Media\MlsListingGallerySync;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\Listing\ListingWorkflowResolver;
use App\Support\Listing\ListingPhotoEntry;
use App\Support\Listing\ListingWorkflow;
use App\Support\Listing\MlsFactVocabulary;
use App\Support\Listing\PropertyTypeVocabulary;
use Illuminate\Support\Facades\Log;

/**
 * Materialises an MLS quick-import into a DRAFT listing the current user owns.
 *
 * EVERY RESOLUTION HERE IS SCOPED TO ONE USER. THAT IS THE WHOLE DESIGN.
 * ---------------------------------------------------------------------
 * An MLS number is public. Anyone can type any listing's number, which makes
 * "find the BidYourOffer listing for this MLS number" the most obvious
 * ownership-takeover shape this feature could have: import 123 Main Street,
 * land on somebody else's listing, and start writing to it. So no query in this
 * class ever looks up a listing by MLS key alone. Every one of them is
 * `where('user_id', $userId)` first, and the MLS key only narrows within what
 * that user already owns.
 *
 * The consequence is deliberate and worth stating plainly: two different users
 * importing the same MLS number get two independent drafts, and neither can see
 * or affect the other's. That is not a bug to be deduplicated later. The
 * alternative — one BYO listing per MLS number, globally — means the first
 * person to type a number owns it, and anyone else who types it is either
 * blocked or handed a stranger's listing. Both are worse than a duplicate.
 *
 * PUBLISHED LISTINGS ARE NEVER MUTATED BY AN IMPORT.
 * A re-import resumes the user's own DRAFT for that MLS key, or starts a new
 * one. It will not reach into a listing that is already live and rewrite its
 * price, its photographs or its terms because somebody typed a number into a
 * search box. Refreshing a published listing is a separate, explicit act.
 *
 * The writer performs the ownership scoping itself rather than trusting a
 * caller-supplied model, because the caller is a Livewire component whose
 * public properties are client input.
 *
 * EVERY DRAFT LEAVES HERE STAMPED AS AN OFFER LISTING.
 * ---------------------------------------------------
 * This class is reachable only from Create Offer Listing, so every row it creates is
 * an Offer Listing — but it used to say so nowhere. It set `user_id`, `is_draft`,
 * `title` and (on seller) `address`, and no product identity at all, because the only
 * thing writing `workflow_type` was the wizards' `saveAllMetadata()`, which a quick
 * import never runs. The four `*_agent_auctions` tables hold BOTH products, so a draft
 * with no identity was a draft that belonged to whichever screen asked for it first —
 * which is how an imported Offer Listing draft came to be listed, opened and rendered
 * by the Hire Seller's Agent wizard.
 *
 * The stamp is written through {@see ListingWorkflow::stamp()} so the native column and
 * the legacy EAV key are always set together. Writing one without the other would
 * manufacture exactly the native/EAV disagreement the resolver must fail closed on.
 */
class MlsQuickImportDraftWriter
{
    /** Meta keys carrying import provenance. Read by the refresh path. */
    public const META_LISTING_KEY   = 'mls_listing_key';
    public const META_MLS_NUMBER    = 'mls_number';
    public const META_PROVIDER      = 'mls_provider';
    public const META_IMPORTED_AT   = 'mls_imported_at';
    public const META_REFRESHED_AT  = 'mls_refreshed_at';
    public const META_SOURCE_STATUS = 'mls_source_status';
    public const META_ORDER_CUSTOM  = 'property_photos_order_customized';
    public const META_QUICK_IMPORT  = 'mls_quick_import';
    public const META_SOURCE_PTYPE  = 'mls_source_property_type';

    /**
     * The supplemental MLS payload — every legitimate fact the feed supplied
     * that has no editable Create Offer field, already filtered through the
     * display allow-lists and already stripped of empty values.
     *
     * ONE meta key holding one JSON document, not sixty keys. The listing views
     * read it, `MlsSupplementalDetails::fromStored()` validates it, and a
     * re-import overwrites it wholesale — which is what makes a refresh
     * idempotent instead of leaving orphaned rows from a previous shape.
     */
    public const META_PROPERTY_DETAILS = 'mls_property_details';

    /**
     * The feed's own display permissions for this listing, as they stood at
     * import. Read by the listing views through {@see MlsDisplayPermissions}
     * before anything MLS-sourced — including the address — is printed.
     *
     * Stored separately from the details blob even though the blob also carries
     * a copy: the address gate has to work for a listing whose details blob is
     * missing, malformed, or written by a version this code does not know.
     */
    public const META_DISPLAY_PERMISSIONS = 'mls_display_permissions';

    public function __construct(
        private readonly MlsListingGallerySync $gallerySync,
    ) {}

    /**
     * The model class backing a role's Offer Listing, or null for a role this
     * feature is not built for.
     *
     * A match with an explicit null default rather than a lookup array, so an
     * unrecognised role cannot resolve to a model by accident.
     *
     * @return class-string|null
     */
    public static function modelClassFor(string $role): ?string
    {
        return match ($role) {
            'seller'   => SellerAgentAuction::class,
            'landlord' => LandlordAgentAuction::class,
            default    => null,
        };
    }

    /**
     * The user's own draft for this MLS listing, or null when they have none.
     *
     * Owner-scoped first, product second, MLS-key third. Drafts only: a published
     * listing is never returned, so no caller can accidentally be handed one to
     * overwrite. The newest is preferred when a user somehow accumulated several.
     *
     * The product filter matters even though only this class writes the MLS key: a
     * draft that was created here and then resumed and re-saved by a Hire wizard comes
     * back carrying both products' fingerprints, and the resolver reports that as
     * conflicting. Such a row must not be silently re-adopted and written to as though
     * it were a clean Offer Listing draft — a fresh draft is created instead, and the
     * damaged row is left intact for the inventory to surface.
     */
    public function findOwnedDraft(string $role, int $userId, string $listingKey): ?object
    {
        $modelClass = self::modelClassFor($role);

        if ($modelClass === null || $listingKey === '') {
            return null;
        }

        $resolver = app(ListingWorkflowResolver::class);

        $candidates = $modelClass::query()
            ->where('user_id', $userId)
            ->where('is_draft', true)
            ->forWorkflow(ListingWorkflow::OFFER_LISTING)
            ->latest('id')
            ->get();

        foreach ($candidates as $candidate) {
            if ((string) $candidate->info(self::META_LISTING_KEY) !== $listingKey) {
                continue;
            }

            if (! $resolver->matches($candidate, ListingWorkflow::OFFER_LISTING)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * Create or resume the current user's draft for an imported MLS listing.
     *
     * Returns the owned draft with the MLS facts, gallery and provenance written.
     * Never returns a listing belonging to anybody else, and never a published one.
     */
    public function materialise(string $role, int $userId, MlsQuickImportResult $result): ?object
    {
        if (! $result->isFound()) {
            return null;
        }

        $modelClass = self::modelClassFor($role);

        if ($modelClass === null) {
            return null;
        }

        $listingKey = (string) ($result->listingKey ?? '');

        $auction = $listingKey !== ''
            ? $this->findOwnedDraft($role, $userId, $listingKey)
            : null;

        $isNew = $auction === null;

        if ($isNew) {
            $auction = new $modelClass();
            $auction->user_id  = $userId;
            $auction->is_draft = true;

            // The listing rows carry NOT NULL columns the wizard normally fills in.
            // Seeded from the imported address so a draft is never created in a state
            // the schema rejects.
            //
            // The two tables are NOT symmetrical here: seller_agent_auctions has a
            // native `address` column and landlord_agent_auctions does not — the
            // landlord side keeps its address in EAV meta. Writing the column
            // unconditionally throws on landlord, so it is set only where it exists.
            // See the schema-asymmetry note in CLAUDE.md.
            $auction->title = $this->draftTitle($result);

            if ($this->tableHasColumn($auction, 'address')) {
                $auction->address = $this->draftTitle($result);
            }

            // Product identity travels in the INSERT, not in a follow-up UPDATE. A row
            // that exists for even one statement without it is a row another screen's
            // picker could enumerate, and "briefly unidentified" is the state this whole
            // change exists to abolish.
            if ($this->tableHasColumn($auction, ListingWorkflow::COLUMN)) {
                $auction->setAttribute(ListingWorkflow::COLUMN, ListingWorkflow::OFFER_LISTING);
            }

            $auction->save();
        }

        // Idempotent, and applied on resume as well as creation: a draft imported before
        // this fix shipped carries no stamp, and re-importing it is the natural moment to
        // give it one. Writes the native column and the legacy EAV key together.
        ListingWorkflow::stamp($auction, ListingWorkflow::OFFER_LISTING);

        $this->writeFacts($auction, $role, $result);
        $this->writeGallery($auction, $result);
        $this->writeSupplementalDetails($auction, $result);
        $this->writeProvenance($auction, $result, $isNew);

        return $auction->fresh();
    }

    /**
     * Write the imported facts into the listing's meta.
     *
     * Routed through {@see MlsFieldMap} so a quick import lands in exactly the
     * same meta keys the tabbed wizard writes. That is what makes an imported
     * listing an ordinary BidYourOffer listing rather than a second kind of
     * listing that every downstream reader would have to learn about.
     *
     * Existing values are NOT overwritten. On a resumed draft the user may
     * already have corrected something the feed got wrong, and a re-import that
     * silently reverted their correction would be worse than not refreshing.
     */
    private function writeFacts(object $auction, string $role, MlsQuickImportResult $result): void
    {
        $map      = MlsFieldMap::forRole($role);
        $existing = $auction->get;

        foreach ($result->facts as $canonicalKey => $value) {
            // ── Furnished is a MERGE, not a copy, and only on Seller ─────────
            //
            // It does not land in a "furnished" field: it contributes at most one
            // label to building_features, a list the user also edits. So it is
            // handled before the overwrite guard below, because the guard would
            // skip an already-populated array and merging into one is the whole
            // point. Landlord has no entry for this key in its map, so a landlord
            // import never reaches here.
            if ($canonicalKey === 'furnished') {
                $this->mergeFurnished($auction, $map, $existing, (string) $value);

                continue;
            }

            $target = $map[$canonicalKey] ?? null;

            if ($target === null) {
                continue;
            }

            // A leading '*' marks a target the wizard stores as an array.
            $isArray  = str_starts_with($target, '*');
            $metaKey  = ltrim($target, '*');

            $current = $existing->{$metaKey} ?? null;
            $hasValue = is_array($current) ? $current !== [] : ($current !== null && $current !== '');

            if ($hasValue) {
                continue;
            }

            $stored = $isArray
                ? array_values(array_filter(array_map('trim', explode(',', (string) $value))))
                : $value;

            // Stored in BYO vocabulary, exactly as the manual flow stores it, so
            // a quick-imported listing drives the same conditionals everywhere
            // downstream — the terms step, the Edit tabs and the published page.
            // The feed's own wording is preserved separately as provenance.
            if ($canonicalKey === 'property_type') {
                $stored = PropertyTypeVocabulary::forRole((string) $stored, $role);
            }

            // Flooring lands in a fixed 26-option multi-select. A feed value
            // outside that list would store fine and then never render as
            // chosen, so it is dropped rather than written.
            if ($canonicalKey === 'flooring') {
                $stored = MlsFactVocabulary::filterFloorCoverings((array) $stored);

                if ($stored === []) {
                    continue;
                }
            }

            // Every other destination whose control speaks its own vocabulary —
            // an acreage band, a square-footage source, a fee frequency, a
            // business type. The rule lives in MlsFactVocabulary because the
            // wizard's own apply path needs the identical answer; null means the
            // feed's value has no option on this form, so the field is left for
            // the user rather than filled with something that would never render
            // as chosen. The fact is still shown under MLS Details.
            $stored = MlsFactVocabulary::toFormValue($canonicalKey, $stored);

            if ($stored === null || $stored === '' || $stored === []) {
                continue;
            }

            $auction->saveMeta($metaKey, $stored);
        }

        // Carried as meta rather than as a form field: there is no input for
        // either, and they are what lets the coordinate ladder's Bridge rung and
        // the refresh path find this property's feed record later.
        if ($result->listingKey !== null) {
            $auction->saveMeta(self::META_LISTING_KEY, $result->listingKey);
        }
        if ($result->mlsNumber !== null) {
            $auction->saveMeta(self::META_MLS_NUMBER, $result->mlsNumber);
        }
    }

    /**
     * Add the furnishing label to building_features without disturbing the rest.
     *
     * The one import on this path that merges rather than replaces. Existing
     * selections are preserved, at most one entry is added, nothing is removed,
     * and a second import of the same record changes nothing — the rule lives in
     * {@see MlsFactVocabulary} so this writer and the URL/text importer apply the
     * identical behaviour instead of two lookalike copies.
     *
     * "Unfurnished" contributes nothing: absence of a furnishing label already
     * means unfurnished, and listing it as a building FEATURE would read as the
     * opposite of what it says.
     *
     * @param  array<string,string>  $map
     */
    private function mergeFurnished(object $auction, array $map, object $existing, string $value): void
    {
        $metaKey = ltrim((string) ($map['furnished'] ?? ''), '*');

        // ONLY building_features, which is Seller's target.
        //
        // The landlord map also carries a `furnished` entry, pointing at
        // `tenant_require` — a SINGLE-SELECT "Furnishings" control, not a feature
        // list. Merging a label into it is meaningless, and its blade currently
        // binds the same variable it iterates for its options, so a written value
        // would not render as chosen anyway. The landlord map entry is left alone
        // because the URL/text importer has always used it; this WRITE path
        // simply declines to act on it.
        if ($metaKey !== 'building_features') {
            return;
        }
        $merged  = MlsFactVocabulary::mergeFurnishedFeature($existing->{$metaKey} ?? null, $value);

        // Nothing to add and nothing already there — leave the key unwritten
        // rather than storing an empty array over an absent one.
        if ($merged === []) {
            return;
        }

        $auction->saveMeta($metaKey, $merged);
    }

    /**
     * Reconcile the listing's gallery against the imported media.
     *
     * Delegates every decision to {@see MlsListingGallerySync} — idempotence,
     * ordering, cover selection and the never-touch-user-uploads rule all live
     * there. This method's only job is to hand it the persisted collection and
     * write the result back.
     *
     * An import that carries no media leaves the gallery entirely alone. That is
     * the sync's empty-set rule, and it is what stops a media-disabled
     * environment, or one bad response, from emptying a listing that already has
     * photographs.
     */
    private function writeGallery(object $auction, MlsQuickImportResult $result): void
    {
        if ($result->media === []) {
            return;
        }

        $sync = $this->gallerySync->sync(
            storedPhotos:    $auction->info('property_photos'),
            incoming:        $result->media,
            orderCustomized: (bool) $auction->info(self::META_ORDER_CUSTOM),
        );

        $auction->saveMeta('property_photos', ListingPhotoEntry::toStorageCollection($sync->entries));

        Log::info('[MLS QUICK IMPORT] gallery synced', [
            'listing_id'          => $auction->id,
            'added'               => $sync->added,
            'updated'             => $sync->updated,
            'removed'             => $sync->removed,
            'user_photos_kept'    => $sync->userPhotosPreserved,
        ]);
    }

    /**
     * Persist the supplemental MLS facts, contacts and listing context.
     *
     * THIS IS THE FIX AT THE CENTRE OF THE WHOLE CHANGE.
     * The lookup service has always built these; the writer has always ignored
     * them. `$result->facts` was persisted and `$result->details` was shown once
     * on the review screen and dropped, which is why an imported listing carried
     * 41 facts while the MLS had supplied several hundred.
     *
     * Written wholesale rather than merged. The blob is derived entirely from
     * the feed and contains nothing a user authored, so a refresh should replace
     * it completely — a merge would leave rows from a previous import for fields
     * the MLS has since cleared, and a listing asserting a fact the MLS has
     * retracted is worse than one missing it. User-entered fields are a
     * different store entirely and are never touched here.
     *
     * An import that produced no supplemental detail leaves whatever is already
     * stored alone, for the same reason the gallery does: one thin response must
     * not empty a listing that was previously complete.
     */
    private function writeSupplementalDetails(object $auction, MlsQuickImportResult $result): void
    {
        $details = $result->details;

        if (! $details instanceof MlsSupplementalDetails) {
            return;
        }

        // Permissions are written even when there is nothing else to write:
        // "the MLS forbids showing this address" is exactly the case where the
        // details blob may be empty, and it is the case where the gate matters
        // most.
        $auction->saveMeta(self::META_DISPLAY_PERMISSIONS, $details->permissions);

        if ($details->isEmpty()) {
            return;
        }

        $auction->saveMeta(self::META_PROPERTY_DETAILS, $details->toArray());

        Log::info('[MLS QUICK IMPORT] supplemental MLS details written', [
            'listing_id' => $auction->id,
            'sections'   => count($details->sections),
            'rows'       => $details->rowCount(),
        ]);
    }

    /**
     * Stamp where this listing's property data came from and when.
     *
     * `imported_at` is written once and never rewritten — it records the first
     * import, which is the answer to "when did this listing come from the MLS?".
     * `refreshed_at` moves on every subsequent pass. Collapsing the two into one
     * column would lose whichever question is asked second.
     */
    private function writeProvenance(object $auction, MlsQuickImportResult $result, bool $isNew): void
    {
        $auction->saveMeta(self::META_PROVIDER, 'bridge');
        $auction->saveMeta(self::META_QUICK_IMPORT, '1');

        if ($result->mlsStatus !== null) {
            $auction->saveMeta(self::META_SOURCE_STATUS, $result->mlsStatus);
        }

        // The feed's own property-type wording, kept because the editable field
        // now holds the BYO translation of it. Normalising the form value must
        // not cost us the ability to say what the MLS actually called this
        // property — "Residential Lease" and "Residential Income" both become
        // "Residential Property" on a landlord listing, and that distinction is
        // worth keeping somewhere.
        $sourceType = (string) ($result->facts['property_type'] ?? '');

        if ($sourceType !== '') {
            $auction->saveMeta(self::META_SOURCE_PTYPE, $sourceType);
        }

        $now = now()->toIso8601String();

        if ($isNew || $auction->info(self::META_IMPORTED_AT) === false) {
            $auction->saveMeta(self::META_IMPORTED_AT, $now);
        } else {
            $auction->saveMeta(self::META_REFRESHED_AT, $now);
        }
    }

    /**
     * Does this model's table actually carry the given column?
     *
     * Asked rather than assumed because the Seller and Landlord tables are
     * deliberately asymmetrical — seller stores several fields in native columns
     * that landlord keeps in EAV meta. Hardcoding either shape here would work
     * for one role and throw for the other.
     *
     * Answers are memoised per table for the life of the request: this runs once
     * per new draft, and the schema does not change underneath it.
     *
     * @var array<string, array<string, bool>>
     */
    private static array $columnCache = [];

    private function tableHasColumn(object $model, string $column): bool
    {
        $table = $model->getTable();

        if (! isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = array_flip(
                $model->getConnection()->getSchemaBuilder()->getColumnListing($table)
            );
        }

        return isset(self::$columnCache[$table][$column]);
    }

    /**
     * A NOT NULL seed for a brand-new draft row.
     *
     * The address if the feed gave one, otherwise a neutral placeholder. Never
     * blank: the column rejects it, and a draft that cannot be saved is worse
     * than one with a dull title the user is about to replace anyway.
     */
    private function draftTitle(MlsQuickImportResult $result): string
    {
        $address = trim((string) ($result->headline['address'] ?? ''));

        if ($address !== '') {
            return mb_substr($address, 0, 190);
        }

        $mlsNumber = trim((string) ($result->mlsNumber ?? ''));

        return $mlsNumber !== '' ? "MLS #{$mlsNumber}" : 'Imported MLS Listing';
    }
}
