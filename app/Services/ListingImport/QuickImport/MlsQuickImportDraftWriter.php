<?php

namespace App\Services\ListingImport\QuickImport;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Services\ListingImport\Media\MlsListingGallerySync;
use App\Services\ListingImport\MlsFieldMap;
use App\Support\Listing\ListingPhotoEntry;
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
     * Owner-scoped first, MLS-key second. Drafts only: a published listing is
     * never returned, so no caller can accidentally be handed one to overwrite.
     * The newest is preferred when a user somehow accumulated several.
     */
    public function findOwnedDraft(string $role, int $userId, string $listingKey): ?object
    {
        $modelClass = self::modelClassFor($role);

        if ($modelClass === null || $listingKey === '') {
            return null;
        }

        $candidates = $modelClass::query()
            ->where('user_id', $userId)
            ->where('is_draft', true)
            ->latest('id')
            ->get();

        foreach ($candidates as $candidate) {
            if ((string) $candidate->info(self::META_LISTING_KEY) === $listingKey) {
                return $candidate;
            }
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

            $auction->save();
        }

        $this->writeFacts($auction, $role, $result);
        $this->writeGallery($auction, $result);
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

            $auction->saveMeta(
                $metaKey,
                $isArray ? array_values(array_filter(array_map('trim', explode(',', (string) $value)))) : $value,
            );
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
