<?php

namespace App\Services\ListingPreferences\Taste;

use App\Models\BridgeProperty;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteDnaAvailability;
use App\Support\ListingPreferences\Taste\TasteDnaReranker;
use App\Support\ListingPreferences\Taste\TasteRerankCandidate;
use App\Support\ListingPreferences\Taste\TasteRerankExplanation;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Phase 5's ONE consumer of Taste DNA: the Stellar Buyer / Tenant results page,
 * under Best Match only.
 *
 * It decides WHETHER to personalize, loads everything the pure reranker needs
 * in batch, calls it once, and puts the page's own cards back in the order it
 * returns. It never touches a score, never filters, never paginates — the
 * caller hands it the WHOLE scored list before slicing a page, which is what
 * makes page 1 genuinely the top of the personalized order and keeps every
 * listing on exactly one page.
 *
 * WHO IS PERSONALIZED. The signed-in account's own Taste profile, for the seeker
 * role its user_type names, and only when that role is the side of the market
 * the results are for. An agent viewing a client's criteria resolves to no
 * seeker role and gets the standard order — an agent's own history must never
 * reorder a client's search, and the client's must never be read on the agent's
 * behalf.
 *
 * EXPLICIT CRITERIA OUTRANK LEARNED TASTE. A seeker who picked Smart Tags on
 * the criteria being searched has told us what they want TODAY. Those picks are
 * stored and shown, but nothing in the matcher filters or scores them yet — so
 * learned taste reordering the results could put a listing matching a PAST
 * pattern above one matching a CURRENT request. Until the picks have
 * authoritative matcher semantics, such a search gets the standard Best Match
 * order. A criteria type this service cannot check is treated the same way:
 * unconfirmed is not "none".
 *
 * QUERY SHAPE. With any gate closed: none. Otherwise the Phase 4 profile read
 * (one event query plus the facts of the homes in the customer's own history —
 * bounded by THEIR history, not by the candidates) and one Smart Tag query for
 * all candidates, whose rows the matcher already loaded. The count does not grow
 * with the number of results.
 */
class TasteRerankingService
{
    /** Request parameter the page uses for "show the standard Best Match order". */
    public const OPT_OUT_PARAM = 'taste';
    public const OPT_OUT_VALUE = 'off';

    /**
     * The sort values that MEAN Best Match. Stellar results offer no other order
     * today (no sort control exists); any other value a request carries is
     * treated as an explicit sort and left untouched rather than guessed at.
     */
    public const BEST_MATCH_SORTS = ['', 'best_match'];

    /** The Stellar results page's criteria tokens, and the seeker-tag subject each one is. */
    private const CRITERIA_SUBJECTS = [
        'buyer_offer'  => SmartTagSeekerSubjectType::BuyerOfferListing,
        'tenant_offer' => SmartTagSeekerSubjectType::TenantOfferListing,
    ];

    public function __construct(
        private readonly TasteDnaService $taste,
        private readonly TasteListingFactsReader $facts,
        private readonly SmartTagSeekerPreferenceReader $seekerTags,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $cards   mapped result cards, in Best Match order, each
     *                                            carrying `bridge_property_id` and `total_score`
     * @param iterable<BridgeProperty>   $rows    the same candidates' rows, already loaded
     * @param SeekerRole                 $market  the side of the market these results are for
     * @param string                     $criteriaType the page's criteria token (`buyer_offer` / `tenant_offer`)
     * @param int                        $criteriaId   the criteria record being searched
     */
    public function rerankStellarResults(
        ?Authenticatable $user,
        SeekerRole $market,
        array $cards,
        iterable $rows,
        ?string $sort,
        ?string $tasteParam,
        string $criteriaType,
        int $criteriaId,
    ): TasteRerankOutcome {
        $cards = array_values($cards);

        if (! TasteDnaAvailability::rerankingEnabled() || $user === null) {
            return new TasteRerankOutcome(TasteRerankOutcome::INACTIVE, $cards);
        }

        $role = SeekerRole::forUserType(is_string($user->user_type ?? null) ? $user->user_type : null);

        if ($role !== $market) {
            return new TasteRerankOutcome(TasteRerankOutcome::INACTIVE, $cards);
        }

        if (! in_array((string) $sort, self::BEST_MATCH_SORTS, true)) {
            return new TasteRerankOutcome(TasteRerankOutcome::EXPLICIT_SORT, $cards);
        }

        if ($tasteParam === self::OPT_OUT_VALUE) {
            return new TasteRerankOutcome(TasteRerankOutcome::OPTED_OUT, $cards);
        }

        if ($this->hasExplicitSeekerTags($criteriaType, $criteriaId)) {
            return new TasteRerankOutcome(TasteRerankOutcome::EXPLICIT_CRITERIA, $cards);
        }

        $profile = $this->taste->profileFor((int) $user->getAuthIdentifier(), $role);

        if (TasteDnaReranker::rankingSignals($profile) === []) {
            return new TasteRerankOutcome(TasteRerankOutcome::NO_SIGNALS, $cards);
        }

        $facts = $this->facts->forBridgeRows($rows);

        $candidates = [];

        foreach ($cards as $index => $card) {
            $id = isset($card['bridge_property_id']) ? (int) $card['bridge_property_id'] : 0;

            $candidates[] = new TasteRerankCandidate(
                key:       (string) $index,
                baseScore: (int) ($card['total_score'] ?? 0),
                facts:     $id > 0 ? ($facts[SmartTagListingType::Bridge->value . ':' . $id] ?? null) : null,
            );
        }

        $result = TasteDnaReranker::rerank($profile, $candidates);

        $ordered = [];

        foreach ($result->orderedKeys as $key) {
            $card = $cards[(int) $key];

            // Presentation only: worded sentences, or nothing. No number from
            // the reranker is put on the card.
            $card['taste_explanation'] = TasteRerankExplanation::for($result->influence($key));

            $ordered[] = $card;
        }

        return new TasteRerankOutcome(TasteRerankOutcome::PERSONALIZED, $ordered);
    }

    /**
     * Whether the searched criteria carry explicit Smart Tag picks — ANY stored
     * pick, whether or not the seeker-preference picker is switched on now: a
     * request the customer made is still their request while the control is
     * hidden. An unrecognised criteria type answers true (fail closed).
     */
    private function hasExplicitSeekerTags(string $criteriaType, int $criteriaId): bool
    {
        $subject = self::CRITERIA_SUBJECTS[$criteriaType] ?? null;

        if ($subject === null || $criteriaId <= 0) {
            return true;
        }

        return $this->seekerTags->keysForSubject($subject, $criteriaId) !== [];
    }
}
