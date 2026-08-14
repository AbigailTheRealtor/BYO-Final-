<?php

namespace App\Http\Livewire\OfferListing\QuickImport;

use App\Http\Livewire\Concerns\ResolvesOwnedAuction;
use App\Http\Livewire\OfferListing\Concerns\StampsBiddingActivation;
use App\Services\ListingImport\Media\MlsListingGallerySync;
use App\Services\ListingImport\MlsPropertyDetailsPresenter;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportResult;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Support\Listing\ListingGalleryView;
use App\Support\Listing\ListingPhotoEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * The shortened MLS creation path, shared by Seller and Landlord.
 *
 * "Give us the MLS number and we'll build the property portion of the listing
 * for you. You only tell BidYourOffer how you want the transaction handled."
 *
 *     MLS #  →  confirm the property  →  Traditional or Bidding Period
 *            →  transaction terms  →  review  →  publish
 *
 * WHAT THIS IS NOT
 * ----------------
 * It is not a second listing system. Every write lands in the same EAV meta keys
 * the tabbed wizard writes, through the same {@see \App\Services\ListingImport\MlsFieldMap},
 * onto the same SellerAgentAuction / LandlordAgentAuction rows. The listing it
 * produces is an ordinary BidYourOffer listing — searchable, matchable and
 * viewable by every existing consumer with no knowledge that it arrived this
 * way. The manual wizard is untouched and remains the path for anyone without
 * an MLS number.
 *
 * It is also not a shortcut past the property form for the sake of it. The
 * point is that re-typing facts the MLS already published is work with no
 * output: the user cannot improve on the feed's bedroom count, and asking them
 * to confirm it 100 fields at a time is the thing this feature exists to
 * remove.
 *
 * AUTHORIZATION, AND WHY hydrate() IS NOT ENOUGH ON ITS OWN
 * ---------------------------------------------------------
 * `$listingId` is a public Livewire property and therefore client input.
 * Livewire 2 applies the client's `syncInput` updates AFTER the hydration hooks
 * run, so a value tampered between requests reaches an action having already
 * passed hydrate(). Both layers are therefore present and both are required:
 * hydrate() refuses a foreign id at the request boundary, and every action that
 * reads or writes re-resolves the draft owner-scoped through
 * {@see resolveOwnedDraft()} and fails closed with a 403.
 *
 * This mirrors the S5/S6 pattern already established on the Offer Listing
 * components; it is deliberately not a new mechanism.
 *
 * An MLS number is public, so "find the listing for this MLS number" is the
 * obvious ownership-takeover shape here. No query in this flow ever resolves a
 * listing by MLS key alone — see {@see MlsQuickImportDraftWriter}, which scopes
 * every lookup to the authenticated user first.
 */
abstract class MlsQuickImportComponent extends Component
{
    use ResolvesOwnedAuction;
    use StampsBiddingActivation;

    public const STEP_LOOKUP  = 'lookup';
    public const STEP_CONFIRM = 'confirm';
    public const STEP_METHOD  = 'method';
    public const STEP_TERMS   = 'terms';
    public const STEP_REVIEW  = 'review';

    public string $step = self::STEP_LOOKUP;

    public string $mlsNumber = '';

    public string $errorMessage = '';

    /** The owned draft this flow is building. Null until the import is accepted. */
    public $listingId = null;

    // ── BidYourOffer answers ────────────────────────────────────────────────

    /** 'Traditional' | 'Bidding Period' — the platform's canonical terminology. */
    public string $auction_type = '';

    /** Bidding Period duration; read only when auction_type is a bidding format. */
    public string $auction_time = '';

    /** Role-specific transaction answers, keyed by the schema below. */
    public array $terms = [];

    /** Multi-select answers (financing, lease length), keyed by field name. */
    public array $multiTerms = [];

    // ── Display state, rebuilt from the owned record on every render ────────

    public array $headline = [];
    public int   $photoCount = 0;

    /**
     * The role this component serves. Not a property: a public property would be
     * client-writable, and the role decides which model is resolved and which
     * questions are asked.
     */
    abstract public function role(): string;

    /**
     * The BidYourOffer questions this role asks after the property is imported.
     *
     * Every entry names a field that ALREADY EXISTS on the corresponding wizard
     * component and is stored under the same meta key — this flow introduces no
     * new transaction vocabulary and no parallel data model. Seller asks about
     * financing and sale terms; Landlord asks about lease terms and never sees
     * the seller financing questions, which do not belong to it.
     *
     * @return array<string, array{label: string, type: string, options?: list<string>, help?: string, section: string, when?: string}>
     */
    abstract public function questionSchema(): array;

    /** Price meta key for this role: what the user is asking for. */
    abstract public function priceField(): string;

    /**
     * The MLS-derived value that may pre-fill this role's price input, or null.
     *
     * Default: the record's list price. That is correct for a Seller — a sale
     * record's ListPrice is the asking price, the same quantity the seller is
     * about to state.
     *
     * It is NOT automatically correct for a Landlord, which is why this is a
     * method rather than a fixed read of `facts['price']`. On a lease record
     * ListPrice is the monthly rent; on a sale record it is the sale price, and
     * pre-filling a rent box with a sale price is how a $100,000 condo came to be
     * advertised at $100,000 per month. Returning null leaves the input empty,
     * and because the rent question is required the flow then cannot reach
     * publish until the landlord states a figure themselves.
     */
    protected function seededPrice(MlsQuickImportResult $result): ?string
    {
        $price = $result->facts['price'] ?? null;

        return ($price === null || $price === '') ? null : (string) $price;
    }

    // ─── Lifecycle ───────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (! $this->quickImport()->availableForRole($this->role())) {
            abort(404);
        }
    }

    /**
     * Owner-only guard on every subsequent request. `$listingId` is null before
     * an import is accepted (allowed) and set only to a draft this user owns.
     */
    public function hydrate(): void
    {
        $this->assertCanManageAuction(
            MlsQuickImportDraftWriter::modelClassFor($this->role()),
            $this->listingId,
            null,
        );
    }

    // ─── Step 1 — find the listing ───────────────────────────────────────────

    /**
     * Look the MLS number up and stage the property for confirmation.
     *
     * Nothing is written here. The number is easy to mistype and a wrong one
     * that silently created a listing would be a listing for somebody else's
     * house — so the user sees the property first and accepts it explicitly.
     */
    public function findListing(): void
    {
        $this->errorMessage = '';

        $result = $this->quickImport()->lookup($this->mlsNumber, $this->role());

        if (! $result->isFound()) {
            $this->errorMessage = $result->message();

            return;
        }

        $this->stashResult($result);

        $this->headline   = $result->headline;
        $this->photoCount = $result->photoCount();
        $this->step       = self::STEP_CONFIRM;
    }

    // ─── Step 2 — accept the property ────────────────────────────────────────

    /**
     * Accept the looked-up property and materialise the owned draft.
     *
     * The lookup is re-run rather than trusting the staged payload: the staged
     * copy round-tripped through the client, and the facts and media that reach
     * a listing must come from the provider on this request, not from whatever
     * came back in the request body.
     */
    public function acceptProperty(): void
    {
        $this->errorMessage = '';

        $result = $this->quickImport()->lookup($this->mlsNumber, $this->role());

        if (! $result->isFound()) {
            $this->errorMessage = $result->message();
            $this->step         = self::STEP_LOOKUP;

            return;
        }

        $auction = $this->draftWriter()->materialise($this->role(), (int) Auth::id(), $result);

        if ($auction === null) {
            $this->errorMessage = 'We could not start a listing from that MLS record. Please try again.';

            return;
        }

        $this->listingId  = $auction->id;
        $this->headline   = $result->headline;
        $this->photoCount = $result->photoCount();

        // Seed the price the user is asking, as a starting point they can change.
        // What may legitimately be seeded is role-specific — see seededPrice() —
        // because the MLS list price means different things on a sale record and
        // a lease record, and guessing wrong publishes a wrong number.
        $seed = $this->seededPrice($result);

        if ($seed !== null
            && (! isset($this->terms[$this->priceField()]) || $this->terms[$this->priceField()] === '')) {
            $this->terms[$this->priceField()] = $seed;
        }

        $this->step = self::STEP_METHOD;
    }

    public function backToLookup(): void
    {
        $this->step         = self::STEP_LOOKUP;
        $this->errorMessage = '';
    }

    // ─── Step 3 — listing method ─────────────────────────────────────────────

    /**
     * Choose Traditional or Bidding Period.
     *
     * The value is validated against the platform's own canonical vocabulary
     * rather than accepted as free text, and Bidding Period is refused outright
     * when the feature flag that hides it is off — a hidden option is not a
     * closed door, and this flow must not be a way to set a listing format the
     * wizard would not allow.
     */
    public function chooseMethod(string $method): void
    {
        $this->errorMessage = '';

        if (! in_array($method, $this->availableMethods(), true)) {
            $this->errorMessage = 'Please choose how you would like to sell.';

            return;
        }

        $this->auction_type = $method;

        if ($method !== 'Bidding Period') {
            $this->auction_time = '';
        }
    }

    public function continueToTerms(): void
    {
        if ($this->auction_type === '') {
            $this->errorMessage = 'Please choose a listing method to continue.';

            return;
        }

        if ($this->auction_type === 'Bidding Period' && trim($this->auction_time) === '') {
            $this->errorMessage = 'Please choose how long the bidding period should run.';

            return;
        }

        $this->errorMessage = '';
        $this->step         = self::STEP_TERMS;
    }

    /**
     * The listing methods this environment actually offers.
     *
     * Bidding Period is gated by config/bya_beta.php exactly as the wizard gates
     * it. Reading the same flag here is what stops this flow becoming a way in
     * to a format the rest of the product is not showing yet.
     */
    public function availableMethods(): array
    {
        $methods = ['Traditional'];

        if (config('bya_beta.bidding_period_enabled')) {
            $methods[] = 'Bidding Period';
        }

        return $methods;
    }

    public function backToMethod(): void
    {
        $this->step         = self::STEP_METHOD;
        $this->errorMessage = '';
    }

    // ─── Step 4 — transaction terms ──────────────────────────────────────────

    public function continueToReview(): void
    {
        $this->errorMessage = '';

        $missing = $this->missingRequiredTerms();

        if ($missing !== []) {
            $this->errorMessage = 'Please complete: ' . implode(', ', $missing) . '.';

            return;
        }

        $auction = $this->resolveOwnedDraft();

        if ($auction === null) {
            $this->errorMessage = 'Your imported listing could not be found. Please start again.';
            $this->step         = self::STEP_LOOKUP;

            return;
        }

        $this->persistAnswers($auction);

        $this->step = self::STEP_REVIEW;
    }

    public function backToTerms(): void
    {
        $this->step         = self::STEP_TERMS;
        $this->errorMessage = '';
    }

    // ─── Photo control ───────────────────────────────────────────────────────

    /**
     * Make one of the listing's own photographs the cover.
     *
     * The selector says WHICH photo; the owned record decides whether that photo
     * is a member of this listing's gallery at all. A selector that addresses
     * nothing is refused rather than ignored, which is what stops a crafted
     * request pointing a listing's cover at media it does not hold.
     *
     * MLS-sourced images are not altered by this — the cover flag is a property
     * of our gallery, not of the provider's photograph.
     */
    public function setCoverPhoto(string $selector): void
    {
        $auction = $this->resolveOwnedDraft();

        if ($auction === null) {
            return;
        }

        $entries = ListingPhotoEntry::collection($auction->info('property_photos'));
        $updated = app(MlsListingGallerySync::class)->chooseCover($entries, $selector);

        if ($updated === null) {
            Log::info('[MLS QUICK IMPORT] refused a cover selector absent from the owned gallery', [
                'listing_id' => $auction->id,
            ]);

            return;
        }

        $auction->saveMeta('property_photos', ListingPhotoEntry::toStorageCollection($updated));
    }

    /**
     * Apply an owner-chosen gallery order.
     *
     * The client array is a SELECTOR over the persisted collection, never a
     * replacement for it: entries it does not mention keep their relative order
     * at the end, and names that are not members are dropped. That is the same
     * rule {@see \App\Http\Livewire\OfferListing\Seller\SellerOfferListing::applyPhotoOrder()}
     * enforces, and it is what stops a partial drag payload silently deleting
     * photographs.
     *
     * Reordering marks the gallery as owner-arranged, which is what makes the
     * arrangement survive every later MLS refresh.
     */
    public function reorderGallery(array $orderedKeys): void
    {
        $auction = $this->resolveOwnedDraft();

        if ($auction === null) {
            return;
        }

        $entries = ListingPhotoEntry::collection($auction->info('property_photos'));

        $ordered = [];
        $taken   = [];

        foreach ($orderedKeys as $selector) {
            foreach ($entries as $index => $entry) {
                if (isset($taken[$index]) || ! $entry->matchesSelector($selector)) {
                    continue;
                }
                $taken[$index] = true;
                $ordered[]     = $entry;
                break;
            }
        }

        foreach ($entries as $index => $entry) {
            if (! isset($taken[$index])) {
                $ordered[] = $entry;
            }
        }

        $auction->saveMeta('property_photos', ListingPhotoEntry::toStorageCollection($ordered));
        $auction->saveMeta(MlsQuickImportDraftWriter::META_ORDER_CUSTOM, '1');
    }

    // ─── Step 5 — publish ────────────────────────────────────────────────────

    /**
     * Publish the reviewed listing through the same transition the wizard uses.
     *
     * Publication is never automatic. A wrong MLS number, stale feed data or an
     * unintended transaction setting all become public the moment this runs, so
     * it is reachable only from the review step and only by an explicit action.
     */
    public function publish()
    {
        $this->errorMessage = '';

        if ($this->step !== self::STEP_REVIEW) {
            return null;
        }

        $auction = $this->resolveOwnedDraft();

        if ($auction === null) {
            $this->errorMessage = 'Your imported listing could not be found. Please start again.';
            $this->step         = self::STEP_LOOKUP;

            return null;
        }

        $missing = $this->missingRequiredTerms();

        if ($missing !== []) {
            $this->errorMessage = 'Please complete: ' . implode(', ', $missing) . '.';
            $this->step         = self::STEP_TERMS;

            return null;
        }

        $this->persistAnswers($auction);

        $auction->is_draft    = 0;
        $auction->is_approved = true;
        $auction->save();

        $auction->saveMeta('listing_status', 'Active');

        // Start the bidding clock — once, never restarted. Same call the wizard
        // makes, so a quick-imported Bidding Period listing has exactly the same
        // window bookkeeping as a manually created one.
        $this->stampBiddingActivation($auction, $this->role());

        $this->enrichLocation($auction);

        return redirect()->route(
            $this->role() === 'seller' ? 'offer.listing.seller.view' : 'offer.listing.landlord.view',
            ['id' => $auction->id],
        );
    }

    // ─── Rendering ───────────────────────────────────────────────────────────

    public function render()
    {
        $auction = $this->listingId !== null ? $this->resolveOwnedDraft() : null;

        // Re-derived from the OWNED record on every render, through the same
        // resolver the published Seller and Landlord pages use.
        //
        // ListingGalleryView re-applies the media policy itself, so the flag is
        // still checked here as well as at extraction and at write — three
        // independent times, because a flag can change between an import and a
        // page view and the answer that governs a render is the one that holds
        // when the render happens. What has gone is this component's PRIVATE copy
        // of that filter: a second implementation of the same gate is a second
        // thing to keep correct, and the review screen disagreeing with the
        // published page about a photograph is the exact defect being closed.
        $gallery = $auction !== null
            ? ListingGalleryView::forRole($auction->info('property_photos'), $this->role())->photos()
            : [];

        // Only once a draft exists does the record become the authority on the
        // count. Before that there is no gallery to count, and overwriting the
        // figure here would replace the confirmation screen's "24 MLS photos
        // will be imported" with a zero on the very step whose whole job is to
        // state it.
        if ($auction !== null) {
            $this->photoCount = count($gallery);
        }

        return view('livewire.offer-listing.quick-import.mls-quick-import', [
            'role'        => $this->role(),
            'gallery'     => $gallery,
            'schema'      => $this->questionSchema(),
            'methods'     => $this->availableMethods(),
            'mlsDetails'  => $this->mlsDetailsFor($auction),
            'priceField'  => $this->priceField(),
        ])->layout('layouts.app');
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    /**
     * The draft this flow is building, re-resolved owner-scoped, or null.
     *
     * Resolved at the ACTION boundary rather than inherited from hydrate(),
     * because Livewire applies the client's property updates after the hydration
     * hooks have already run. Aborts 403 on a foreign id rather than returning
     * null, so a tampered value cannot be mistaken for "no draft yet".
     */
    protected function resolveOwnedDraft(): ?object
    {
        if (empty($this->listingId)) {
            return null;
        }

        $modelClass = MlsQuickImportDraftWriter::modelClassFor($this->role());

        if ($modelClass === null) {
            return null;
        }

        $auction = $modelClass::query()
            ->where('id', $this->listingId)
            ->where('user_id', Auth::id())
            ->first();

        abort_if($auction === null, 403, 'You are not authorized to access this listing.');

        return $auction;
    }

    /**
     * Write the BidYourOffer answers onto the owned draft.
     *
     * Answers are written with `saveMeta` under the SAME keys the wizard uses,
     * which is what makes the resulting listing indistinguishable downstream.
     * Values are taken from the schema, so a key that is not a declared question
     * for this role cannot be written by an arbitrary `terms` payload.
     */
    protected function persistAnswers(object $auction): void
    {
        $auction->saveMeta('auction_type', $this->auction_type);
        $auction->saveMeta(
            'auction_time',
            $this->auction_type === 'Bidding Period' ? $this->auction_time : '',
        );

        foreach ($this->questionSchema() as $field => $spec) {
            if (($spec['type'] ?? 'text') === 'multiselect') {
                $selected = $this->multiTerms[$field] ?? [];
                $allowed  = $spec['options'] ?? [];

                // Only declared options may be stored. Without this an arbitrary
                // multiTerms payload would write free text into a field the
                // review screen presents as a fixed vocabulary.
                $clean = array_values(array_intersect(
                    array_map('strval', is_array($selected) ? $selected : []),
                    $allowed,
                ));

                $auction->saveMeta($field, $clean);

                continue;
            }

            if (! array_key_exists($field, $this->terms)) {
                continue;
            }

            $value = $this->terms[$field];
            $auction->saveMeta($field, is_scalar($value) ? (string) $value : '');
        }
    }

    /**
     * Declared questions the user has left blank.
     *
     * @return list<string>
     */
    protected function missingRequiredTerms(): array
    {
        $missing = [];

        foreach ($this->questionSchema() as $field => $spec) {
            if (empty($spec['required'])) {
                continue;
            }

            if (($spec['type'] ?? 'text') === 'multiselect') {
                if (empty($this->multiTerms[$field])) {
                    $missing[] = $spec['label'];
                }

                continue;
            }

            if (trim((string) ($this->terms[$field] ?? '')) === '') {
                $missing[] = $spec['label'];
            }
        }

        return $missing;
    }

    /**
     * Display-only MLS attributes for the review screen.
     *
     * Read from the cached feed record through the display allow-list. The raw
     * record is never handed to a view.
     */
    protected function mlsDetailsFor(?object $auction): array
    {
        if ($auction === null) {
            return [];
        }

        $listingKey = (string) $auction->info(MlsQuickImportDraftWriter::META_LISTING_KEY);

        if ($listingKey === '') {
            return [];
        }

        $property = \App\Models\BridgeProperty::where('listing_key', $listingKey)->first();

        if ($property === null || empty($property->raw_json)) {
            return [];
        }

        $raw = json_decode($property->raw_json, true);

        return is_array($raw) ? app(MlsPropertyDetailsPresenter::class)->present($raw) : [];
    }

    /**
     * Prime Location DNA for the published listing.
     *
     * Best-effort and never allowed to fail a publish: a listing that is live
     * with enrichment still queued is a far better outcome than one that refused
     * to publish because a background pipeline was unavailable. Mirrors the
     * wizard's own try/catch around the same dispatch.
     */
    protected function enrichLocation(object $auction): void
    {
        try {
            \App\Jobs\ComputeLocationDna::dispatch(
                $this->role() === 'seller' ? 'seller_agent' : 'landlord_agent',
                $auction->id,
            );
        } catch (\Throwable $e) {
            Log::warning('[MLS QUICK IMPORT] Location DNA dispatch failed', [
                'listing_id' => $auction->id,
                'reason'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Keep the confirmation payload out of client-writable state.
     *
     * Only the MLS number survives the round trip; the facts and media are
     * re-fetched from the provider when the user accepts. A staged payload that
     * came back from the browser is not evidence of what the MLS said.
     */
    protected function stashResult(MlsQuickImportResult $result): void
    {
        // Intentionally a no-op beyond the display fields already assigned by
        // the caller. Documented as a method so the absence is deliberate rather
        // than an omission somebody later "fixes" by persisting the payload.
    }

    protected function quickImport(): MlsQuickImportService
    {
        return app(MlsQuickImportService::class);
    }

    protected function draftWriter(): MlsQuickImportDraftWriter
    {
        return app(MlsQuickImportDraftWriter::class);
    }
}
