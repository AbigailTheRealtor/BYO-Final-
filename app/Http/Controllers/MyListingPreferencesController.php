<?php

namespace App\Http\Controllers;

use App\Services\ListingPreferences\ListingPreferenceHistoryReader;
use App\Services\ListingPreferences\ListingPreferenceListingHydrator;
use App\Services\ListingPreferences\ListingPreferenceListReader;
use App\Services\ListingPreferences\Taste\TasteDnaService;
use App\Support\ListingPreferences\ListingPreferenceAvailability;
use App\Support\ListingPreferences\ListingPreferencePrefetch;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteDnaAvailability;
use App\Support\ListingPreferences\Taste\TasteObservationPresenter;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's own Saved / Maybe / Passed area, and their history.
 *
 * DELIBERATELY NOT ON /author/{id}.
 * ---------------------------------
 * That page reads like a "my stuff" area and is not one: it carries only `web`
 * middleware and takes ANY user's id, so it is a PUBLIC profile. A customer's
 * Save / Maybe / Pass is private — it is what they think of somebody else's
 * house — and publishing it under a profile id would expose every customer's
 * opinions to every visitor. These routes are `auth`-gated and read the
 * signed-in user; there is no id in the URL to tamper with.
 *
 * THE VIEWER IS THE SUBJECT, ALWAYS. `user_id` and `seeker_role` are taken from
 * the session, never from the request, so there is no parameter that could ask
 * for somebody else's list. The readers require both, so widening the scope
 * would mean writing a different query rather than passing a different value.
 *
 * NOTHING HERE WRITES. Changing a preference from this page posts to the same
 * shared endpoints every other surface uses — recorded under the `account`
 * surface because the route says so, not because the browser claimed it. The
 * listing itself is never touched.
 *
 * A NON-SEEKER GETS AN HONEST EMPTY PAGE rather than a 403: an agent or a
 * seller has no Save/Maybe/Pass history because the feature is not theirs, and
 * saying so is kinder and more accurate than refusing the route.
 */
class MyListingPreferencesController extends Controller
{
    /** What the tabs are, in the order a customer reads them. */
    private const TABS = ['save', 'maybe', 'pass'];

    public function __construct(
        private readonly ListingPreferenceListReader $list,
        private readonly ListingPreferenceListingHydrator $hydrator,
        private readonly ListingPreferenceHistoryReader $history,
        private readonly ListingPreferencePrefetch $prefetch,
        private readonly TasteDnaService $taste,
    ) {
    }

    /** Saved | Maybe | Passed. */
    public function index(Request $request): View
    {
        $role = $this->roleFor($request);

        if ($role === null) {
            return view('listing-preferences.mine.index', $this->emptyPage());
        }

        $userId = (int) $request->user()->getAuthIdentifier();

        $state = ListingPreferenceState::tryFrom((string) $request->query('state', 'save'));
        $state ??= ListingPreferenceState::Save;

        $page = $this->list->page($userId, $role, $state);
        $refs = $this->list->referencesFor($page->items());

        // BATCHED, exactly as the Phase 3A card pages are: the preference state
        // and the listing context for the whole page are resolved once, so each
        // control below finds its answer already waiting instead of asking.
        $this->primeControls($refs, $userId, $role);

        return view('listing-preferences.mine.index', [
            'role'      => $role,
            'available' => true,
            'state'     => $state,
            'tabs'      => $this->tabs(),
            'counts'    => $this->list->counts($userId, $role),
            'page'      => $page,
            'cards'     => $this->hydrator->hydrate($refs, $userId),
            'reasons'   => $this->list->reasonLabelsFor($page->items()),
            'tasteLink' => TasteDnaAvailability::enabled(),
        ]);
    }

    /**
     * "Your Home Taste" — patterns in the customer's OWN Save / Maybe / Pass
     * choices (Phase 4).
     *
     * Computed on request from their own history and nobody else's; nothing is
     * stored and nothing else reads it. The view receives worded observations
     * only — never a score, a key, an id or a subject — because the presenter is
     * the one path from a signal to a page.
     */
    public function taste(Request $request): View
    {
        $role = $this->roleFor($request);

        if ($role === null) {
            return view('listing-preferences.mine.taste', ['available' => false, 'incomplete' => false, 'groups' => [], 'labels' => []]);
        }

        $profile = $this->taste->profileFor((int) $request->user()->getAuthIdentifier(), $role);

        return view('listing-preferences.mine.taste', [
            'available'  => true,
            'incomplete' => ! $profile->complete,
            'groups'     => TasteObservationPresenter::grouped(TasteObservationPresenter::present($profile)),
            'labels'     => TasteObservationPresenter::GROUP_LABELS,
        ]);
    }

    /** A compact timeline of the customer's own decisions. */
    public function history(Request $request): View
    {
        $role = $this->roleFor($request);

        if ($role === null) {
            return view('listing-preferences.mine.history', ['available' => false, 'role' => null, 'events' => null, 'rows' => [], 'cards' => []]);
        }

        $userId = (int) $request->user()->getAuthIdentifier();

        $events = $this->history->page($userId, $role);
        $refs   = $this->history->referencesFor($events->items());

        $rows = [];
        foreach ($events->items() as $event) {
            $rows[] = $this->history->present($event);
        }

        return view('listing-preferences.mine.history', [
            'available' => true,
            'role'      => $role,
            'events'    => $events,
            'rows'      => $rows,
            'cards'     => $this->hydrator->hydrate($refs, $userId),
        ]);
    }

    /**
     * The seeker role for this viewer, or null when they are not a seeker.
     *
     * Read from the account, never from the request. `ListingPreferenceState`
     * rows are keyed on this, so a wrong answer would show one market's
     * decisions under the other's.
     */
    private function roleFor(Request $request): ?SeekerRole
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return SeekerRole::forUserType(is_string($user->user_type ?? null) ? $user->user_type : null);
    }

    /**
     * @param  list<\App\Support\SmartTags\SmartTagListingRef> $refs
     */
    private function primeControls(array $refs, int $userId, SeekerRole $role): void
    {
        if ($refs === [] || ! ListingPreferenceAvailability::featureEnabled()) {
            return;
        }

        /** @var array<string, list<int>> $byType */
        $byType = [];

        foreach ($refs as $ref) {
            $byType[$ref->type->value][] = $ref->id;
        }

        foreach ($byType as $typeValue => $ids) {
            $type = SmartTagListingType::tryFrom($typeValue);

            if ($type !== null) {
                $this->prefetch->prime($type, $ids, $userId, $role);
            }
        }
    }

    /** @return array<string, mixed> */
    private function emptyPage(): array
    {
        return [
            'role'      => null,
            'available' => false,
            'state'     => ListingPreferenceState::Save,
            'tabs'      => $this->tabs(),
            'counts'    => array_fill_keys(self::TABS, 0),
            'page'      => null,
            'cards'     => [],
            'reasons'   => [],
            'tasteLink' => false,
        ];
    }

    /**
     * The tab vocabulary, in the CUSTOMER's words.
     *
     * `save` / `maybe` / `pass` are storage; Saved, Maybe and Passed are what a
     * person is shown. The mapping lives with the history reader so one
     * vocabulary serves the whole surface.
     *
     * @return array<string, string> state value => label
     */
    private function tabs(): array
    {
        $tabs = [];

        foreach (self::TABS as $value) {
            $state = ListingPreferenceState::from($value);
            $tabs[$value] = ListingPreferenceHistoryReader::stateLabel($state);
        }

        return $tabs;
    }
}
