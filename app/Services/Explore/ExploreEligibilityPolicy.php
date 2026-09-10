<?php

namespace App\Services\Explore;

use App\Models\BridgeProperty;
use App\Services\ListingImport\Mls\MlsDisplayPermissions;

/**
 * The server-side answer to "may this record appear on Explore, and for whom?"
 *
 * THE BROWSER IS NEVER THE COMPLIANCE LAYER.
 * ------------------------------------------
 * Every exclusion below happens before a record becomes a projection, so an
 * ineligible listing is not serialised, not sent, and not hidden with CSS. A
 * consumer inspecting the network response finds nothing to reveal.
 *
 * THE GATES, AND WHY EACH IS SEPARATE
 * -----------------------------------
 *  1. FEED PERMISSION — {@see MlsDisplayPermissions::listingDisplayable()},
 *     which is IDXParticipationYN AND InternetEntireListingDisplayYN, either
 *     false being decisive. This is the existing authority and Explore
 *     delegates to it rather than reimplementing it. NOTE that this is stricter
 *     than {@see \App\Services\Stellar\MatchCheck\ListingVisibilityGate}, which
 *     reads only IDXParticipationYN; where the two differ, the stricter one is
 *     the correct reading and is what a public surface must use.
 *
 *     A malformed or absent raw_json resolves to `denyAll()`. That is a
 *     deliberate DIFFERENCE from MlsDisplayPermissions' own posture on a
 *     missing FLAG: an absent flag inside a record we can read means "this
 *     record predates the column"; a record we cannot read at all means we do
 *     not know what its owner permitted, and a public map is not the place to
 *     resolve that ambiguity as permission.
 *
 *  2. MARKET STATUS — StandardStatus must be in `explore.public_statuses`
 *     ('Active'). Compared against StandardStatus only. MlsStatus is a second
 *     vocabulary that disagrees with it on the same record ('Closed' ↔ 'Sold'),
 *     and treating them as interchangeable is how a sold house stays on a map.
 *
 *     The COLUMN is preferred and raw_json is the fallback, in that order: the
 *     column is what the bbox query filtered on, so trusting the blob over it
 *     would let a row pass a filter it did not actually satisfy.
 *
 *  3. TRANSACTION TYPE — must classify to SALE or RENT. An unclassified
 *     PropertyType is excluded rather than defaulted, because its ListPrice
 *     cannot be labelled.
 *
 *  4. COORDINATES — must be present, finite, non-null-island and inside the
 *     requested viewport. Explore places markers from MLS coordinates and
 *     nothing else; a record without them has no place on a map and must not
 *     be geocoded onto one.
 *
 * WHAT THIS POLICY DOES NOT DO
 * ----------------------------
 * It does not decide whether the ADDRESS may be shown. That is a third,
 * narrower permission and a listing whose address is withheld is still a
 * listing that may be shown — the projection suppresses the address line and
 * keeps the marker. 71 of 1,203 live records are in exactly that state.
 */
class ExploreEligibilityPolicy
{
    /**
     * @param array<string,mixed>|null $raw  Pre-decoded raw_json, when the caller already has it.
     */
    public function decide(
        BridgeProperty $listing,
        ExploreAccessTier $tier,
        ?ExploreViewport $viewport = null,
        ?array $raw = null,
    ): ExploreEligibilityDecision {
        // Explore serves one tier today. A VOW tier arriving here without its
        // own projection would inherit the public one, which is the failure
        // this refuses rather than tolerates.
        if (! $tier->isPublic()) {
            return ExploreEligibilityDecision::excluded('tier_not_served');
        }

        $raw ??= $this->decodeRaw($listing);

        if ($raw === null) {
            return ExploreEligibilityDecision::excluded('raw_record_unreadable');
        }

        if (! $this->permissions($raw)->listingDisplayable()) {
            return ExploreEligibilityDecision::excluded('feed_display_permission_denied');
        }

        if (! $this->statusIsPublic($listing, $raw)) {
            return ExploreEligibilityDecision::excluded('status_not_public');
        }

        $type = ExploreTransactionType::fromPropertyType(
            $listing->property_type ?? ($raw['PropertyType'] ?? null)
        );

        if ($type === null) {
            return ExploreEligibilityDecision::excluded('transaction_type_unclassified');
        }

        $coordinates = $this->coordinates($listing);

        if ($coordinates === null) {
            return ExploreEligibilityDecision::excluded('coordinates_unusable');
        }

        if ($viewport !== null && ! $viewport->contains($coordinates[0], $coordinates[1])) {
            return ExploreEligibilityDecision::excluded('outside_viewport');
        }

        return ExploreEligibilityDecision::eligible($type);
    }

    /**
     * The feed permissions for a record, denying everything when the record
     * itself could not be read.
     *
     * @param array<string,mixed>|null $raw
     */
    public function permissions(?array $raw): MlsDisplayPermissions
    {
        return $raw === null
            ? MlsDisplayPermissions::denyAll()
            : MlsDisplayPermissions::fromRecord($raw);
    }

    /**
     * raw_json as an array, or null when it is absent or not a JSON object.
     *
     * @return array<string,mixed>|null
     */
    public function decodeRaw(BridgeProperty $listing): ?array
    {
        $json = $listing->raw_json;

        if (! is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * A usable MLS coordinate pair, or null.
     *
     * (0, 0) is rejected explicitly. It is a valid coordinate in the Gulf of
     * Guinea and an extremely common representation of "this field was never
     * populated"; a Florida dataset produces the second, and a marker there is
     * a property placed 5,000 miles from itself.
     *
     * @return array{0:float,1:float}|null
     */
    public function coordinates(BridgeProperty $listing): ?array
    {
        $lat = $listing->latitude;
        $lng = $listing->longitude;

        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
            return null;
        }

        return [$lat, $lng];
    }

    /**
     * @param array<string,mixed> $raw
     */
    private function statusIsPublic(BridgeProperty $listing, array $raw): bool
    {
        $allowed = array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            (array) config('explore.public_statuses', ['Active'])
        )));

        if ($allowed === []) {
            // An empty allow-list is indistinguishable from a config that did
            // not load. Publishing everything is not a safe reading of that.
            return false;
        }

        $status = $listing->standard_status;

        if (! is_string($status) || trim($status) === '') {
            $status = $raw['StandardStatus'] ?? null;
        }

        return is_string($status) && in_array(trim($status), $allowed, true);
    }
}
