<?php

namespace App\Services\Location\Coordinates;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The outcome of resolving one property address to a coordinate.
 *
 * Immutable, provider-neutral, and the single type every adapter returns — so a
 * caller handles a US Census result, an MLS feed coordinate and a ZIP centroid
 * the same way, and swapping providers changes nothing above this line.
 *
 * THE SAFETY PROPERTY THIS TYPE EXISTS FOR
 * ----------------------------------------
 * A ZIP centroid is the middle of a postal area that can span several miles. It
 * is a perfectly good thing to point a map at and a completely wrong thing to
 * measure a school-commute from. Both are "a latitude and a longitude", so
 * nothing about the numbers themselves stops the second use.
 *
 * `isUsableForLocationDna()` is that stop, and it is deliberately a method on
 * the type rather than a rule in a docblock: the previous generation of this
 * code relied on naming conventions (`zip_centroid` in the source string) and a
 * comment asking callers to be careful. Callers are not careful. A type is.
 *
 * Unresolved results answer `false` to it as well, so a caller that forgets to
 * check `isResolved()` still cannot feed a null island into a distance sum.
 *
 * @see CoordinatePrecision for where the exact/coarse line sits and why
 */
final class PropertyCoordinateResult
{
    private function __construct(
        public readonly bool $resolved,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly CoordinatePrecision $precision,
        public readonly ?CoordinateSource $source,
        public readonly ?string $provider,
        public readonly ?string $normalizedAddress,
        public readonly ?float $confidence,
        public readonly ?DateTimeImmutable $resolvedAt,
        public readonly ?string $reason,
        /**
         * The upstream record this coordinate came from, where the source
         * publishes a stable identifier — a NENA SITEADDID, a NAD UUID, an MLS
         * listing key.
         *
         * Deliberately one opaque string and not a per-source field. The point of
         * keeping it is to be able to ask, later, "which row in which import
         * produced this point" without this type having to know what any source's
         * identifiers look like. Null wherever a source publishes none, which is
         * most of them.
         */
        public readonly ?string $sourceRef,
        /**
         * Who decided this coordinate, when a person did.
         *
         * Null for every automatic resolution, and that is the honest value —
         * nobody decided a Census interpolation. See {@see self::manual()} for
         * why this may not be filled in on an automatic result.
         */
        public readonly ?int $actorId,
        /**
         * Why a person overrode the automatic answer.
         *
         * Distinct from {@see self::$reason}, which records why a resolution
         * FAILED. The two would be confusing to merge and impossible to tell
         * apart once stored: one is a machine-readable failure code on an
         * unresolved result, the other is a human justification on a resolved
         * one.
         */
        public readonly ?string $overrideReason,
    ) {
    }

    /**
     * A resolved coordinate.
     *
     * @param string|null $provider  who supplied it ('us_census', 'bridge_mls', …).
     *                               Free-form on purpose: the set of providers must
     *                               be able to grow without editing this class.
     * @param float|null  $confidence provider-reported 0..1 where available; many
     *                               providers report nothing, hence nullable.
     */
    public static function resolved(
        float $latitude,
        float $longitude,
        CoordinatePrecision $precision,
        CoordinateSource $source,
        ?string $provider = null,
        ?string $normalizedAddress = null,
        ?float $confidence = null,
        ?DateTimeImmutable $resolvedAt = null,
        ?string $sourceRef = null,
    ): self {
        return new self(
            resolved:          true,
            latitude:          $latitude,
            longitude:         $longitude,
            precision:         $precision,
            source:            $source,
            provider:          $provider,
            normalizedAddress: $normalizedAddress,
            confidence:        $confidence,
            resolvedAt:        $resolvedAt ?? new DateTimeImmutable(),
            reason:            null,
            sourceRef:         $sourceRef,
            // An automatic resolution has no actor and no justification. Left
            // null rather than defaulted to a system user id, because "the
            // platform resolved this" and "a person chose this" are the exact
            // two things a manual-override audit has to be able to separate.
            actorId:           null,
            overrideReason:    null,
        );
    }

    /**
     * A coordinate a person set deliberately, overriding what the ladder found.
     *
     * NOT IMPLEMENTED AS A FEATURE YET — this is the storage contract only. No
     * UI, no route and no component produces one of these. It exists so that
     * when the override is built, the shape it must record is already decided
     * and already enforced here rather than at whatever call site gets written
     * first.
     *
     * WHY EVERY ARGUMENT IS REQUIRED
     * ------------------------------
     * The failure this guards against is a coordinate acquiring the authority of
     * a human decision without a human having made one. Today the browser
     * supplies `property_lat`/`property_lng` through `fillFromResolvedAddress()`
     * as unvalidated strings; if "manual" were merely a source name that any
     * writer could stamp, an autocomplete pick would be indistinguishable from a
     * surveyed correction, and it would outrank the address corpus while looking
     * accountable.
     *
     * So a manual result cannot be constructed without an actor and a stated
     * reason. There is no default and no nullable shortcut: an override nobody
     * signed is not an override, and the type refuses to represent one.
     *
     * The precision is the caller's explicit claim about what they placed —
     * a rooftop pin and a "somewhere on this parcel" pin are different
     * assertions, and the person making one is the only party who knows which.
     *
     * @param int    $actorId        the user making the decision
     * @param string $overrideReason why the automatic answer was wrong
     */
    public static function manual(
        float $latitude,
        float $longitude,
        CoordinatePrecision $precision,
        int $actorId,
        string $overrideReason,
        ?string $normalizedAddress = null,
        ?DateTimeImmutable $resolvedAt = null,
    ): self {
        $overrideReason = trim($overrideReason);

        if ($overrideReason === '') {
            throw new InvalidArgumentException(
                'A manual coordinate override requires a stated reason.'
            );
        }

        if ($actorId <= 0) {
            throw new InvalidArgumentException(
                'A manual coordinate override requires the id of the user making it.'
            );
        }

        return new self(
            resolved:          true,
            latitude:          $latitude,
            longitude:         $longitude,
            precision:         $precision,
            source:            CoordinateSource::Manual,
            // The provider is the person, expressed the same way every other
            // provider is: a stable string a consumer can group by. It is not a
            // rung, and no ladder produces it.
            provider:          'manual_override',
            normalizedAddress: $normalizedAddress,
            confidence:        null,
            resolvedAt:        $resolvedAt ?? new DateTimeImmutable(),
            reason:            null,
            sourceRef:         null,
            actorId:           $actorId,
            overrideReason:    $overrideReason,
        );
    }

    /**
     * A coordinate obtained from a unit-stripped lookup — i.e. the building, not
     * the unit.
     *
     * Exists so that "we looked up 123 Main St because we could not look up Unit
     * 4A" cannot be recorded as Rooftop. It caps precision at Parcel regardless
     * of what the provider claimed, because the provider answered a question
     * about the building and we asked about the unit.
     */
    public static function forBuilding(
        float $latitude,
        float $longitude,
        CoordinateSource $source,
        ?string $provider = null,
        ?string $normalizedAddress = null,
        ?float $confidence = null,
        ?DateTimeImmutable $resolvedAt = null,
        ?string $sourceRef = null,
    ): self {
        return self::resolved(
            latitude:          $latitude,
            longitude:         $longitude,
            precision:         CoordinatePrecision::Parcel,
            source:            $source,
            provider:          $provider,
            normalizedAddress: $normalizedAddress,
            confidence:        $confidence,
            resolvedAt:        $resolvedAt,
            sourceRef:         $sourceRef,
        );
    }

    /**
     * No coordinate. Carries the reason so the caller can tell "we have not
     * tried" from "we tried and the address does not exist" — the distinction
     * the merged Google fail-closed work established with
     * `non_google_geocoder_unavailable`, preserved here.
     */
    public static function unresolved(string $reason, ?string $normalizedAddress = null): self
    {
        return new self(
            resolved:          false,
            latitude:          null,
            longitude:         null,
            precision:         CoordinatePrecision::Unknown,
            source:            null,
            provider:          null,
            normalizedAddress: $normalizedAddress,
            confidence:        null,
            resolvedAt:        null,
            reason:            $reason,
            sourceRef:         null,
            actorId:           null,
            overrideReason:    null,
        );
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * True only when this coordinate identifies the property closely enough to
     * drive distance, travel-time and boundary work.
     *
     * The one gate Location DNA must consult. Coarse tiers and unresolved
     * results both answer false.
     */
    public function isUsableForLocationDna(): bool
    {
        return $this->resolved && $this->precision->isExact();
    }

    /**
     * True when there is a point that may be drawn on a map but must be labelled
     * approximate and never measured from.
     */
    public function isCoarseDisplayOnly(): bool
    {
        return $this->resolved && $this->precision->isCoarse();
    }

    /**
     * Coordinates for measurement, or null when this result is not entitled to
     * be measured from.
     *
     * The accessor Location DNA should use. Reaching for `->latitude` directly
     * bypasses the gate; this cannot.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function exactCoordinates(): ?array
    {
        if (! $this->isUsableForLocationDna()) {
            return null;
        }

        return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude];
    }

    /**
     * Coordinates for map framing regardless of precision, or null when
     * unresolved. Always pair with {@see CoordinatePrecision::label()} in the UI
     * so an approximate point is never shown as if it were the property.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function displayCoordinates(): ?array
    {
        if (! $this->resolved) {
            return null;
        }

        return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resolved'           => $this->resolved,
            'latitude'           => $this->latitude,
            'longitude'          => $this->longitude,
            'precision'          => $this->precision->value,
            'source'             => $this->source?->value,
            'provider'           => $this->provider,
            'normalized_address' => $this->normalizedAddress,
            'confidence'         => $this->confidence,
            'resolved_at'        => $this->resolvedAt?->format(DATE_ATOM),
            'reason'             => $this->reason,
            'usable_for_dna'     => $this->isUsableForLocationDna(),
        ];
    }
}
