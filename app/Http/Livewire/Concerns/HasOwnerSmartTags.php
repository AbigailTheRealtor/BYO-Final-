<?php

namespace App\Http\Livewire\Concerns;

use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagTelemetry;
use App\Support\SmartTags\OwnerSmartTagPanel;
use App\Support\SmartTags\OwnerSmartTagSelection;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The Seller/Landlord "Property Features" picker, as a Create/Edit wizard
 * concern.
 *
 * FOUR COMPONENTS, ONE IMPLEMENTATION. Seller Create, Seller Edit, Landlord
 * Create and Landlord Edit each add `use HasOwnerSmartTags`, one
 * {@see ownerSmartTagListingType()} and four one-line calls; everything else is
 * here. The alternative — the same twenty lines copied into four 4,000-line
 * components — is how the two wizards of one role come to disagree about what
 * they are saving, which is the defect `applicant-requirements.blade.php` exists
 * to prevent on the tab next door.
 *
 * WHAT IT TOUCHES, AND WHAT IT CANNOT. The selection is an ordinary listing
 * answer: one meta key, carried by the draft payload, the draft loader and
 * saveAllMetadata() beside `interior_features`. Evidence is written ONLY at
 * publish, ONLY through {@see SmartTagLifecycle}, and the trait holds no
 * reference to the writer, the resolver or any table — a guard test asserts
 * that, and it is why this concern cannot become a second manual-tag backend.
 *
 * NOTHING HERE CAN FAIL A SAVE. Every lifecycle call is one of the static shims,
 * which catch `Throwable` including an `app()` that cannot build the lifecycle.
 * The picker's own read is equally quiet: a fault returns an unavailable panel,
 * and the tab renders without it rather than 500ing a half-finished listing.
 */
trait HasOwnerSmartTags
{
    /**
     * Canonical Smart Tag keys the owner has ticked.
     *
     * A public Livewire property, so a client can set it to anything — which is
     * exactly why {@see SmartTagSelectionPolicy} runs at the write and not in
     * `rules()`. The same lesson as CompatibilityPreferencePolicy: validation
     * checks the paths it is told about and leaves the rest on the property.
     *
     * @var array<int, mixed>
     */
    public $smart_tag_selections = [];

    /** Memoised per request: the picker is asked once per render, not once per category. */
    private ?OwnerSmartTagPanel $ownerSmartTagPanelCache = null;

    /**
     * The listing the wizard last LOADED, for the render that follows.
     *
     * `$listingId` is the durable answer and what every later request uses, but
     * the create wizards' `loadDraft()` does not set it — mount() and saveDraft()
     * do — so on the first render after a draft load it can still be null while a
     * real listing is on screen. Reading the model's own key there is what lets
     * "already included from your listing details" be right on that first paint
     * instead of one request late.
     *
     * Private, so Livewire does not serialise it: by the next request `$listingId`
     * is populated and is the right source again.
     */
    private ?int $ownerSmartTagLoadedListingId = null;

    /** Which Smart Tag listing type this wizard writes. */
    abstract protected function ownerSmartTagListingType(): SmartTagListingType;

    /**
     * What the "Property Features" section renders.
     *
     * Reads the FORM's property type, so switching the listing from Residential
     * to Commercial re-offers the picker in the same request that changed it.
     */
    public function ownerSmartTagPanel(): OwnerSmartTagPanel
    {
        if ($this->ownerSmartTagPanelCache === null) {
            $this->ownerSmartTagPanelCache = SmartTagLifecycle::ownerPanel(
                $this->ownerSmartTagListingType(),
                is_string($this->property_type ?? null) ? $this->property_type : null,
                $this->ownerSmartTagLoadedListingId
                    ?? (is_numeric($this->listingId ?? null) ? (int) $this->listingId : null),
                OwnerSmartTagSelection::sanitize((array) $this->smart_tag_selections),
            );
        }

        return $this->ownerSmartTagPanelCache;
    }

    /**
     * Forget the memoised panel.
     *
     * Livewire keeps one component instance per request, so a `updatedX` hook
     * that changes the property type must invalidate the panel or the same
     * request would render the previous context's options.
     */
    public function refreshOwnerSmartTagPanel(): void
    {
        $this->ownerSmartTagPanelCache = null;
    }

    /**
     * The value for the draft payload and saveAllMetadata().
     *
     * Sanitised, not projected: a draft may have no property type yet, and
     * pruning against a context the owner has not chosen would silently discard
     * ticks they made before changing their mind. The contextual projection is
     * the writer's, against the STORED type.
     */
    protected function ownerSmartTagSelectionsForStorage(): string
    {
        return OwnerSmartTagSelection::encode((array) $this->smart_tag_selections);
    }

    /**
     * Restore the owner's ticks when a draft or a listing is loaded for editing.
     */
    protected function restoreOwnerSmartTagSelections($auction): void
    {
        $this->smart_tag_selections = OwnerSmartTagSelection::decode(
            $auction->get->{OwnerSmartTagSelection::META_KEY} ?? null
        );

        $key = $auction->getKey();
        $this->ownerSmartTagLoadedListingId = is_numeric($key) ? (int) $key : null;

        $this->refreshOwnerSmartTagPanel();
    }

    /**
     * Turn the owner's ticks into manual evidence. PUBLISH ONLY.
     *
     * Call AFTER saveAllMetadata() and AFTER the derivation call, for two
     * different reasons: the writer reads the listing's stored property type and
     * structured fields to decide what to prune, and the manual evidence must be
     * in place before the last projection of the listing's assignments — which
     * this call performs itself.
     *
     * NEVER on a draft save. See {@see SmartTagLifecycle::trySaveOwnerSelections()}.
     */
    protected function persistOwnerSmartTags(Model $auction): void
    {
        SmartTagLifecycle::trySaveOwnerSelections(
            $auction,
            OwnerSmartTagSelection::sanitize((array) $this->smart_tag_selections),
            Auth::user(),
            SmartTagTelemetry::ownerTagsEntryPoint($this->ownerSmartTagListingType()),
        );
    }
}
