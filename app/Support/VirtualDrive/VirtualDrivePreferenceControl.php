<?php

namespace App\Support\VirtualDrive;

use App\Services\ListingPreferences\ListingPreferenceReader;
use App\Support\ListingPreferences\ListingPreferenceAvailability;
use App\Support\ListingPreferences\ListingPreferencePrefetch;
use App\Support\ListingPreferences\ListingPreferenceSurface;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Support\Facades\Blade;

/**
 * The one seam between the Virtual Drive and Listing Preferences.
 *
 * WHY A SEAM AT ALL. The Virtual Drive is a provider comparison whose actions
 * are deliberately inert descriptions — `{key, label, available, url, reason}`
 * and nothing more — and its JavaScript is meant to stay that way. Putting
 * preference logic into either would give this application a second place where
 * "what may a shopper do with a listing" is decided, which is the duplication
 * the whole subsystem exists to avoid.
 *
 * So the Virtual Drive asks this class two questions and asks nothing else:
 *
 *   • is there a reason Save cannot be offered for this listing?
 *   • what is the HTML for the shared control?
 *
 * Both answers come from the shared system. This class decides nothing: it
 * resolves the listing's context through the shared reader, asks
 * {@see ListingPreferenceAvailability} the same question every other surface
 * asks, and renders the same Blade component every other surface renders.
 *
 * RENDERED SERVER-SIDE ON PURPOSE. The page view renders every control hidden,
 * from the ids {@see pool()} decides, and the shell only MOVES the matching node
 * into the card — it never parses a string into markup. The Virtual Drive page emits
 * `<x-listing-preference.assets />` once so the shared delegated handler is
 * already listening when a control is revealed.
 *
 * THE ID IS THE CALLER'S TRUSTED ONE. It takes `bridge_properties.id` from a
 * caller that holds the model. It does not read the Explore projection, and the
 * projection is not widened to carry an id for its benefit.
 */
class VirtualDrivePreferenceControl
{
    public function __construct(
        private readonly ListingPreferenceReader $reader,
        private readonly ListingPreferencePrefetch $prefetch,
    ) {
    }

    /**
     * Whether Save can be offered, for MANY Bridge listings at once — and
     * nothing rendered.
     *
     * The listings API needs only this answer, for up to a page of nearby homes
     * that is re-asked as the shopper drives. Asking {@see forListing()} per
     * home cost a context query per home AND rendered a whole control only for
     * the API to discard it. This primes the shared prefetch once — the same
     * batching the result cards use — and decides from it.
     *
     * @param  list<int>  $bridgeRowIds
     * @return array<int, ?string> row id => refusal reason, or null when offered
     */
    public function reasonsFor(array $bridgeRowIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $bridgeRowIds), fn (int $id) => $id > 0)));

        if ($ids === []) {
            return [];
        }

        if (! ListingPreferenceAvailability::featureEnabled()) {
            return array_fill_keys($ids, 'Saving properties is not switched on in this environment.');
        }

        try {
            $this->primeFor($ids);

            $out = [];

            foreach ($ids as $id) {
                $availability = ListingPreferenceAvailability::for(
                    auth()->user(),
                    $this->contextOf(new SmartTagListingRef(SmartTagListingType::Bridge, $id)),
                );

                $out[$id] = $availability->shouldRender() ? null : $this->reasonFor($availability);
            }

            return $out;
        } catch (\Throwable $e) {
            // A Virtual Drive listing must never fail to load because a
            // preference answer could not be prepared.
            return array_fill_keys($ids, 'Saving properties is unavailable right now.');
        }
    }

    /**
     * Whether Save can be offered for one Bridge listing, and its control.
     *
     * @return array{reason: ?string, html: string}
     *         `reason` is null when the control is offered; the string is what
     *         the Virtual Drive's own unavailable-action list will display.
     */
    public function forListing(int $bridgeRowId): array
    {
        if ($bridgeRowId <= 0) {
            return ['reason' => 'This listing has no stable identity to save a preference against.', 'html' => ''];
        }

        if (! ListingPreferenceAvailability::featureEnabled()) {
            return ['reason' => 'Saving properties is not switched on in this environment.', 'html' => ''];
        }

        try {
            $ref = new SmartTagListingRef(SmartTagListingType::Bridge, $bridgeRowId);

            // The same context resolution every other surface performs, so the
            // Buyer = sale / Tenant = lease rule is applied here by the shared
            // policy rather than restated.
            $availability = ListingPreferenceAvailability::for(
                auth()->user(),
                $this->contextOf($ref),
            );

            if (! $availability->shouldRender()) {
                return ['reason' => $this->reasonFor($availability), 'html' => ''];
            }

            return [
                'reason' => null,
                'html'   => $this->render($bridgeRowId),
            ];
        } catch (\Throwable $e) {
            // A Virtual Drive listing must never fail to load because a
            // preference control could not be prepared.
            return ['reason' => 'Saving properties is unavailable right now.', 'html' => ''];
        }
    }

    /**
     * Which of the proof's listings get a control, keyed by MLS listing key,
     * with the TRUSTED `bridge_properties.id` each control must carry.
     *
     * WHY THE PAGE, NOT THE LISTINGS API.
     * -----------------------------------
     * The obvious shape is for the listings API to return the control's HTML
     * and for the shell to insert it. The proof forbids that, and rightly:
     * `VirtualDriveProviderIsolationTest` asserts that **no proof script writes
     * listing data as HTML**. So the controls are rendered with the page,
     * hidden, and the shell only reveals the one whose key matches the
     * selected home.
     *
     * WHY IDS, NOT PRE-RENDERED HTML. This returned rendered controls once, one
     * `Blade::render()` each — and a standalone render forgets `@once`, so
     * every control carried its own copy of the shared delegated script. A
     * click was then handled once per copy: one Done sent several reason
     * writes and appended several history events, and a late response closed
     * the tray the shopper had just reopened. The page view now renders the
     * shared component itself, in ONE pass, where `@once` holds.
     *
     * THE KEY IS A DOM SELECTOR, NOT AN IDENTITY. What the control carries is
     * the row id resolved here, server-side; the browser never supplies an
     * identity, and the write endpoint re-validates whatever it receives.
     * The shared prefetch is primed for every id, so each control rendered
     * afterwards finds its context and the viewer's state already waiting.
     *
     * @param  list<string> $listingKeys
     * @return array<string, int> listing key => bridge_properties.id
     */
    public function pool(array $listingKeys): array
    {
        $keys = array_values(array_filter(array_map(
            static fn ($k): string => is_string($k) ? trim($k) : '',
            $listingKeys,
        )));

        if ($keys === [] || ! ListingPreferenceAvailability::featureEnabled()) {
            return [];
        }

        try {
            // One query for the whole pool.
            $rows = \App\Models\BridgeProperty::query()
                ->whereIn('listing_key', $keys)
                ->get(['id', 'listing_key']);

            $idByKey = [];

            foreach ($rows as $row) {
                $id  = (int) $row->id;
                $key = (string) $row->listing_key;

                if ($id > 0 && $key !== '') {
                    $idByKey[$key] = $id;
                }
            }

            // One batch decides every listing — and primes the prefetch the
            // rendered controls will read.
            $reasons = $this->reasonsFor(array_values($idByKey));

            return array_filter(
                $idByKey,
                static fn (int $id): bool => array_key_exists($id, $reasons) && $reasons[$id] === null,
            );
        } catch (\Throwable $e) {
            // The proof page must still load.
            return [];
        }
    }

    /**
     * Prime the shared prefetch for these Bridge rows and this viewer.
     *
     * @param list<int> $ids
     */
    private function primeFor(array $ids): void
    {
        $user = auth()->user();
        $role = $user === null ? null : SeekerRole::forUserType(is_string($user->user_type ?? null) ? $user->user_type : null);

        $this->prefetch->prime(
            SmartTagListingType::Bridge,
            $ids,
            $user !== null && $role !== null ? (int) $user->getAuthIdentifier() : null,
            $role,
        );
    }

    /** The primed context when there is one; a single read otherwise. */
    private function contextOf(SmartTagListingRef $ref): ?SmartTagContext
    {
        return $this->prefetch->hasContext($ref)
            ? $this->prefetch->context($ref)
            : $this->reader->contextFor($ref);
    }

    /**
     * The shared component, in its compact layout, pointed at the Virtual
     * Drive's own write routes.
     *
     * The surface is carried by those ROUTES — it is a route default, not
     * something the browser sends — so an event recorded here says Virtual
     * Drive because that is where it happened.
     */
    private function render(int $bridgeRowId): string
    {
        return trim(Blade::render(
            '<x-listing-preference.control listing-type="bridge" :listing-id="$id" :compact="true" :surface="$surface" />',
            ['id' => $bridgeRowId, 'surface' => ListingPreferenceSurface::VIRTUAL_DRIVE],
        ));
    }

    /**
     * The Virtual Drive's own wording for a refusal.
     *
     * The proof's convention is that an unavailable action states why, in a
     * sentence a reader can act on. A guest is not refused here — the shared
     * control renders for them and routes them through the existing login flow
     * — so the only reasons reaching this method are the ones that no sign-in
     * would change.
     */
    private function reasonFor(ListingPreferenceAvailability $availability): string
    {
        if ($availability->isDisabled()) {
            return 'Saving properties is not switched on in this environment.';
        }

        return 'Saving properties is part of shopping as a buyer or a renter.';
    }
}
