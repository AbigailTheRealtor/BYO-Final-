<?php

namespace App\Services\Location\Coordinates\Adapters;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\Exceptions\CoordinateProviderUnavailable;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rung 3 of the ladder, and the first one that leaves this machine: the US
 * Census Bureau's public geocoder.
 *
 * WHY THIS PROVIDER FIRST
 * -----------------------
 * It is free, it needs no API key, its output is public domain, and it is the
 * only geocoder in the plan whose licence does not restrict what we may store.
 * Google's terms forbid persisting coordinates; Geoapify's restrict
 * redistribution. Census data has neither constraint, which makes it the one
 * provider whose results can simply be written to our own tables and kept —
 * the property that makes a coordinate cache worth building at all.
 *
 * It is also the weakest of the three, and this class does not pretend
 * otherwise. See PRECISION below.
 *
 * THE CONTRACT, AS VERIFIED RATHER THAN ASSUMED (2026-08-10)
 * ---------------------------------------------------------
 * Every claim below was checked against the live service before this adapter
 * was written, because the published PDF specification is not machine-readable
 * and a geocoder's failure modes are exactly where documentation goes stale.
 *
 *   GET /geocoder/locations/onelineaddress
 *       ?address=…&benchmark=Public_AR_Current&format=json
 *
 *   Match (HTTP 200):
 *     result.addressMatches[] each carrying
 *       coordinates.x        LONGITUDE
 *       coordinates.y        LATITUDE
 *       matchedAddress       "4600 SILVER HILL RD, WASHINGTON, DC, 20233"
 *       tigerLine.tigerLineId / .side
 *       addressComponents.fromAddress / .toAddress   the block's number range
 *
 *   No match (HTTP 200):  result.addressMatches == []   ← not an error
 *   Bad benchmark (400):  {"errors":["Invalid benchmark in request"]}
 *   Over-long address (400):
 *     {"errors":["Address cannot be empty and cannot exceed 100 characters"]}
 *
 * No authentication, no API key, no published rate limit. US, Puerto Rico and
 * the US Island Areas only.
 *
 * X IS LONGITUDE, Y IS LATITUDE
 * -----------------------------
 * Worth its own heading because it is the single most common way to break a
 * geocoder integration, and because the failure is silent: swap them and the
 * property moves to the far side of the planet while the code reports complete
 * success.
 *
 * Note that {@see CoordinateValidator} does NOT catch this on its own, which is
 * the trap. It rejects out-of-range values, and a transposed US coordinate is
 * usually still in range: Tampa's longitude is -82.46, and -82.46 is a
 * perfectly legal latitude in the Southern Ocean. Range validation passes and
 * the property is placed near Antarctica.
 *
 * So the axis mapping is written exactly once, below, with the names spelled
 * out — and {@see self::isWithinServiceArea()} then checks the pair against the
 * only places this provider serves. A transposition fails that check even when
 * both numbers are individually legal, which is what makes it detectable.
 *
 * PRECISION: ALWAYS INTERPOLATED, NEVER ROOFTOP
 * ---------------------------------------------
 * The Census geocoder does not know where buildings are. It knows that one side
 * of one street segment runs from number 301 to number 399, and it places the
 * house number proportionally along that line — which is why every match
 * returns `fromAddress`/`toAddress` alongside the point. The result is
 * typically within a house or two and occasionally much further out on long
 * rural segments.
 *
 * {@see CoordinatePrecision::Interpolated} is precisely that tier, and it is
 * hardcoded rather than inferred: the service reports no quality, accuracy or
 * confidence field of any kind, so there is nothing to infer from, and every
 * successful match is produced the same way. Grading any of them Rooftop or
 * Parcel would be a claim the provider never made.
 *
 * WHY forBuilding() IS DELIBERATELY NOT USED
 * ------------------------------------------
 * {@see PropertyCoordinateResult::forBuilding()} exists to stop a unit-stripped
 * condo lookup being recorded as Rooftop, and it does that by capping precision
 * at Parcel. Applying it here would *raise* an Interpolated result to Parcel —
 * the opposite of a cap, and a fabricated upgrade. A street-range interpolation
 * is no more precise for having had a unit number removed from the query, so
 * the tier stays Interpolated whether or not the address had a unit. The cap is
 * for providers that over-claim; this one under-claims by construction.
 *
 * AMBIGUITY IS REFUSED, NOT GUESSED
 * ---------------------------------
 * `addressMatches` is an array and really can hold more than one entry — "1
 * Broadway, New York, NY" returns two, in ZIP 10004 and ZIP 10006, about 130
 * metres apart (verified live). Taking the first would attach one of two
 * genuinely different postal locations to the property and report complete
 * success, which is the same guessing {@see BridgeMlsCoordinatesAdapter}
 * refuses when it declines to match a feed record by address similarity.
 *
 * So: one match is used; several matches are used only when they agree to
 * within {@see self::AMBIGUITY_TOLERANCE_METRES}, which is the case where the
 * service has returned the same place twice rather than two places. Genuine
 * disagreement returns `census_ambiguous_match` and lets the next rung try.
 * In practice this is rare, because {@see PropertyAddress::hasMinimumForLookup()}
 * already requires a ZIP or a city+state, and a ZIP disambiguates almost
 * everything.
 *
 * GEOGRAPHY AGREEMENT (added in G4)
 * ---------------------------------
 * A coordinate is not evidence that the provider found the right property. The
 * geocoder answers with whatever its corpus considered the best match for the
 * string it was given, and a street name that exists in two states will
 * cheerfully resolve to the wrong one — returning a valid, in-range, plausible
 * coordinate for a property several hundred miles from the one being listed.
 * Nothing about the numbers reveals that.
 *
 * So every match is checked against what was actually asked: if the caller
 * supplied a state, the match must be in that state; if the caller supplied a
 * ZIP, the match must be in that ZIP5. Both sides are normalized through
 * {@see PropertyAddress}, so "FL"/"Florida" agree and a requested
 * "33602-1234" agrees with a returned "33602" — but genuinely different
 * geography does not. Exact equality only; there is no fuzzy fallback, because
 * a rule that sometimes accepts the wrong county is not a rule.
 *
 * This runs per match rather than over the response as a whole, which also
 * disambiguates: where the provider offers several candidates and only one is
 * in the requested ZIP, the requested ZIP is evidence, and the others are
 * dropped. That is not the same as taking the first result — it is using
 * information the caller supplied instead of guessing.
 *
 * A component the caller did NOT supply is not checked. The caller made no
 * claim about it, so the provider cannot contradict one.
 *
 * WHEN THE PROVIDER OMITS A COMPONENT WE ASKED ABOUT
 * --------------------------------------------------
 * Rejected, as `census_match_components_missing`.
 *
 * The policy is deliberate and was decided on evidence rather than taste. Ten
 * successful live matches were sampled across FL, DC, MA, NY, IL, CA and WA,
 * including three queries that supplied no ZIP at all; every one returned a
 * populated `state` and `zip`. That the service populates other components with
 * empty strings — `preType`, `preDirection`, `suffixQualifier` are routinely
 * '' — makes the consistency of these two more meaningful, not less: empties
 * are clearly available to this API and these fields never used them.
 *
 * So a missing component is not the normal case degrading gracefully; it is the
 * provider behaving in a way it has not been observed to behave, and a guard
 * that waves through the one response shape it cannot verify is not a guard.
 * It carries its own reason rather than reusing `census_zip_mismatch` so that
 * if the service ever does change shape, telemetry shows an anomaly appearing
 * at volume instead of a sudden epidemic of apparently wrong addresses.
 *
 * CACHING: OUTCOMES YES, FAULTS NEVER
 * -----------------------------------
 * Keyed on {@see PropertyAddress::coordinateCacheKeyInput()} — the unit-free
 * line — so every unit in a building shares one entry and one provider call.
 * Matches and definitive misses are both stored: an address the corpus does not
 * contain will not appear in it an hour later.
 *
 * Faults are never stored, following {@see \App\Services\LocationDna\CensusTigerBoundaryAdapter}:
 * caching a 502 would let one bad minute teach the cache that thousands of real
 * addresses do not exist, and it would persist long after the service recovered.
 * A fault raises {@see CoordinateProviderUnavailable} instead, which is the
 * signal G4's breaker and per-provider budget will consume.
 *
 * G3 SCOPE
 * --------
 * This class is the whole of G3. It is not on {@see LocalCoordinateLadder} —
 * that ladder is the local rungs and stays local — it is bound in no container,
 * referenced by no listing flow, and dispatches no Location DNA work. It also
 * ships disabled: with no API key to be missing, `census_geocoder.enabled` is
 * the only thing keeping it quiet, so it defaults to false. Assembling a ladder
 * that includes it is G4/G5.
 */
final class CensusGeocoderAdapter implements CoordinateProviderAdapterInterface
{
    /**
     * How far apart two matches may sit and still be treated as the same place.
     *
     * 25 metres is comfortably inside one parcel and comfortably outside the
     * ~130 m spread of the 1 Broadway case that motivated this rule. It
     * separates "the corpus lists this address twice" from "the corpus lists
     * two different addresses"; it is not an accuracy claim about the point.
     */
    private const AMBIGUITY_TOLERANCE_METRES = 25.0;

    /** Metres per degree of latitude. Constant enough at this tolerance. */
    private const METRES_PER_DEGREE = 111_320.0;

    private const CACHE_PREFIX = 'census_geocode_v1_';

    public function providerId(): string
    {
        return 'us_census';
    }

    public function source(): CoordinateSource
    {
        return CoordinateSource::Geocoder;
    }

    public function requiresNetwork(): bool
    {
        return true;
    }

    /**
     * The feature flag, and nothing else.
     *
     * There are no credentials to check — the service is unauthenticated — so
     * this is the entire availability question. Answered without a network
     * call, as the interface requires: an unavailable adapter must be skipped,
     * never awaited.
     */
    public function isAvailable(): bool
    {
        return (bool) config('census_geocoder.enabled', false);
    }

    public function resolve(PropertyAddress $address): PropertyCoordinateResult
    {
        $line = $address->coordinateLookupLine();

        // Defence in depth. The resolver already short-circuits on this, but an
        // adapter that is correct only when called through one particular
        // caller is not correct.
        if (! $address->hasMinimumForLookup()) {
            return PropertyCoordinateResult::unresolved(
                'insufficient_address',
                $line !== '' ? $line : null
            );
        }

        // Declined before it is spent rather than after: the service would
        // answer this with a 400 indistinguishable from a bad benchmark.
        $maxLength = (int) config('census_geocoder.max_address_length', 100);

        if (mb_strlen($line) > $maxLength) {
            return PropertyCoordinateResult::unresolved('address_exceeds_provider_limit', $line);
        }

        $cacheKey = self::CACHE_PREFIX . md5($address->coordinateCacheKeyInput());
        $outcome  = Cache::get($cacheKey);

        if (! is_array($outcome)) {
            $outcome = $this->lookup($line, $address);

            // Only definitive outcomes reach this line — a fault has already
            // thrown, so there is no path by which one is cached.
            Cache::put($cacheKey, $outcome, (int) config('census_geocoder.cache_ttl', 2592000));
        }

        return $this->toResult($outcome, $line);
    }

    /**
     * One request, reduced to a small cacheable outcome array.
     *
     * Returns a plain array rather than a {@see PropertyCoordinateResult}
     * because the result object carries a resolution timestamp: caching one
     * would replay a month-old `resolvedAt` as if the lookup had just happened.
     * The array holds the provider's answer; the timestamp is stamped fresh
     * each time the answer is turned back into a result.
     *
     * @return array{hit: bool, lat?: float, lng?: float, matched?: string, reason?: string}
     *
     * @throws CoordinateProviderUnavailable when the provider is at fault
     */
    private function lookup(string $line, PropertyAddress $address): array
    {
        $response = $this->request($line);

        // 5xx and 429 are the service's problem and may pass; every other 4xx
        // means it rejected this particular request, which is ours.
        if ($response->serverError() || $response->status() === 429) {
            throw new CoordinateProviderUnavailable(
                $this->providerId(),
                "US Census geocoder returned HTTP {$response->status()}"
            );
        }

        if ($response->clientError()) {
            Log::warning('CensusGeocoderAdapter: request rejected', [
                'status'  => $response->status(),
                'address' => $line,
                'errors'  => $response->json('errors'),
            ]);

            return ['hit' => false, 'reason' => 'census_request_rejected'];
        }

        $matches = $response->json('result.addressMatches');

        // A 200 that is not the documented shape is a fault, not a miss. The
        // difference matters: an empty list is a real answer worth caching for a
        // month, whereas an error page served with a 200 is not an answer at
        // all, and caching it as one would be indistinguishable from the
        // address genuinely not existing.
        if (! is_array($matches)) {
            throw new CoordinateProviderUnavailable(
                $this->providerId(),
                'US Census geocoder returned a body without result.addressMatches'
            );
        }

        return $this->interpret($matches, $address);
    }

    /**
     * The HTTP call, with transport failures translated into the fault type.
     *
     * @throws CoordinateProviderUnavailable
     */
    private function request(string $line): \Illuminate\Http\Client\Response
    {
        try {
            return Http::timeout((int) config('census_geocoder.timeout', 10))
                ->acceptJson()
                ->get((string) config('census_geocoder.base_url'), [
                    'address'   => $line,
                    'benchmark' => (string) config('census_geocoder.benchmark'),
                    'format'    => 'json',
                ]);
        } catch (CoordinateProviderUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            // Connection refused, DNS failure, timeout. Transient by nature and
            // never cached.
            throw new CoordinateProviderUnavailable(
                $this->providerId(),
                'US Census geocoder unreachable: ' . $e->getMessage()
            );
        }
    }

    /**
     * Turn the provider's match list into an outcome.
     *
     * @param  array<int, mixed> $matches
     * @return array{hit: bool, lat?: float, lng?: float, matched?: string, reason?: string}
     */
    private function interpret(array $matches, PropertyAddress $address): array
    {
        $points     = [];
        $rejections = [];

        foreach ($matches as $match) {
            if (! is_array($match)) {
                continue;
            }

            // x is longitude, y is latitude. See the class docblock.
            $longitude = CoordinateValidator::toFloat($match['coordinates']['x'] ?? null);
            $latitude  = CoordinateValidator::toFloat($match['coordinates']['y'] ?? null);

            if (! CoordinateValidator::isValidPair($latitude, $longitude)) {
                $rejections[] = 'census_coordinates_invalid';
                continue;
            }

            // In range but not anywhere this provider serves — which is what a
            // transposed pair looks like. See the axis heading in the docblock.
            if (! $this->isWithinServiceArea($latitude, $longitude)) {
                $rejections[] = 'census_coordinates_invalid';
                continue;
            }

            // Does the provider's answer describe the place we asked about?
            // See GEOGRAPHY AGREEMENT in the class docblock.
            $disagreement = $this->geographyDisagreement($match, $address);

            if ($disagreement !== null) {
                $rejections[] = $disagreement;
                continue;
            }

            $points[] = [
                'lat'     => $latitude,
                'lng'     => $longitude,
                'matched' => is_string($match['matchedAddress'] ?? null)
                    ? $match['matchedAddress']
                    : null,
            ];
        }

        if ($points === []) {
            // Either the corpus has no such address, or every match it returned
            // was rejected. Both are final answers about this address rather
            // than statements about the service, so both cache.
            //
            // The first rejection is reported rather than a generic one: with a
            // single match — overwhelmingly the common case — it is exactly the
            // reason, and it keeps "matched the wrong state" from being filed
            // under the same label as "the coordinate was corrupt".
            return [
                'hit'    => false,
                'reason' => $matches === []
                    ? 'census_no_match'
                    : ($rejections[0] ?? 'census_no_match'),
            ];
        }

        if (! $this->pointsAgree($points)) {
            return ['hit' => false, 'reason' => 'census_ambiguous_match'];
        }

        $chosen = $points[0];

        return [
            'hit'     => true,
            'lat'     => $chosen['lat'],
            'lng'     => $chosen['lng'],
            'matched' => $chosen['matched'],
        ];
    }

    /**
     * Why this match does not describe the place that was asked about, or null
     * when it does.
     *
     * Exact equality on normalized values only — no fuzzy matching, no distance
     * fallback, no "close enough". Both sides go through {@see PropertyAddress}
     * so that "FL", "Fl" and "Florida" compare equal and a requested
     * "33602-1234" compares equal to a returned "33602", while genuinely
     * different geography compares unequal.
     *
     * A component that was not supplied is not checked: the caller made no
     * claim about it, so the provider cannot contradict one. A component that
     * was supplied but comes back empty is a different matter — see
     * `census_match_components_missing` in the docblock.
     */
    private function geographyDisagreement(array $match, PropertyAddress $address): ?string
    {
        $components = is_array($match['addressComponents'] ?? null)
            ? $match['addressComponents']
            : [];

        $requestedState = $address->normalizedState();
        $requestedZip   = $address->normalizedZip5();

        // Normalized through the same rules as the request, so the comparison
        // is between like and like.
        $returned = new PropertyAddress(
            state: is_string($components['state'] ?? null) ? $components['state'] : '',
            zip:   is_string($components['zip'] ?? null)   ? $components['zip']   : '',
        );

        if ($requestedState !== '') {
            if ($returned->normalizedState() === '') {
                return 'census_match_components_missing';
            }

            if ($returned->normalizedState() !== $requestedState) {
                return 'census_state_mismatch';
            }
        }

        if ($requestedZip !== '') {
            if ($returned->normalizedZip5() === '') {
                return 'census_match_components_missing';
            }

            if ($returned->normalizedZip5() !== $requestedZip) {
                return 'census_zip_mismatch';
            }
        }

        return null;
    }

    /**
     * True when a coordinate falls somewhere this provider actually covers.
     *
     * The US Census Geocoder geocodes the United States, Puerto Rico and the US
     * Island Areas, and nothing else. A point outside that envelope is not a
     * result this service could have produced, so it is evidence of a bug —
     * overwhelmingly a transposed x/y — rather than a property.
     *
     * The envelope is deliberately generous, because a false rejection here is
     * worse than a missed transposition: it would silently drop a legitimate
     * property. It stretches from American Samoa (14.6S) past the north slope of
     * Alaska (71.5N), and spans both sides of the antimeridian to take in Guam
     * and the Northern Marianas to the west and the US Virgin Islands to the
     * east. It is a plausibility check, not a boundary test — deciding which
     * jurisdiction a point sits in is the boundary services' job.
     */
    private function isWithinServiceArea(float $latitude, float $longitude): bool
    {
        if ($latitude < -15.0 || $latitude > 72.0) {
            return false;
        }

        // The Americas, from the Aleutians east to the US Virgin Islands …
        if ($longitude >= -180.0 && $longitude <= -64.0) {
            return true;
        }

        // … and the western Pacific territories on the far side of the line.
        return $longitude >= 144.0 && $longitude <= 180.0;
    }

    /**
     * True when every match describes the same place.
     *
     * Compared against the first point rather than pairwise: with the tolerance
     * this small the distinction cannot change the verdict, and "all within 25 m
     * of the point we would return" is the question actually being asked.
     *
     * @param list<array{lat: float, lng: float, matched: string|null}> $points
     */
    private function pointsAgree(array $points): bool
    {
        $first = $points[0];

        foreach (array_slice($points, 1) as $point) {
            if ($this->metresBetween($first, $point) > self::AMBIGUITY_TOLERANCE_METRES) {
                return false;
            }
        }

        return true;
    }

    /**
     * Equirectangular approximation. At tens of metres its error is far below
     * the tolerance it is compared against, and unlike a haversine it cannot be
     * mistaken for a distance the application reports to anyone.
     *
     * @param array{lat: float, lng: float, matched?: string|null} $a
     * @param array{lat: float, lng: float, matched?: string|null} $b
     */
    private function metresBetween(array $a, array $b): float
    {
        $latitudeMetres  = ($a['lat'] - $b['lat']) * self::METRES_PER_DEGREE;
        $longitudeMetres = ($a['lng'] - $b['lng']) * self::METRES_PER_DEGREE
            * cos(deg2rad(($a['lat'] + $b['lat']) / 2));

        return sqrt(($latitudeMetres ** 2) + ($longitudeMetres ** 2));
    }

    /**
     * Rebuild a result from a cached or fresh outcome.
     *
     * @param array{hit: bool, lat?: float, lng?: float, matched?: string|null, reason?: string} $outcome
     */
    private function toResult(array $outcome, string $line): PropertyCoordinateResult
    {
        if (($outcome['hit'] ?? false) !== true) {
            return PropertyCoordinateResult::unresolved(
                is_string($outcome['reason'] ?? null) ? $outcome['reason'] : 'census_no_match',
                $line
            );
        }

        return PropertyCoordinateResult::resolved(
            latitude:  (float) $outcome['lat'],
            longitude: (float) $outcome['lng'],
            // Never anything else — see PRECISION in the class docblock.
            precision: CoordinatePrecision::Interpolated,
            source:    CoordinateSource::Geocoder,
            provider:  $this->providerId(),
            // What the provider says it matched, folded through the same rules
            // as every other address here so it is comparable to them. The
            // caller's line is already known to the caller; the useful thing to
            // record is what the coordinate actually describes.
            normalizedAddress: $this->normalizeMatched($outcome['matched'] ?? null, $line),
            // The service reports no confidence, accuracy or quality field. Null
            // is the honest value; a fabricated 1.0 would read as certainty
            // nobody asserted.
            confidence: null,
        );
    }

    /**
     * The provider's matched address, normalized through {@see PropertyAddress}
     * so it can be compared with any other address in this namespace. Falls
     * back to the line we sent when the provider returned none.
     */
    private function normalizeMatched(?string $matched, string $line): string
    {
        if ($matched === null || trim($matched) === '') {
            return $line;
        }

        $normalized = (new PropertyAddress(address: $matched))->coordinateLookupLine();

        return $normalized !== '' ? $normalized : $line;
    }
}
