<?php

namespace App\Services\Location\Coordinates;

use DateTimeImmutable;

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
