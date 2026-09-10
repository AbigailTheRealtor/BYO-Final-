<?php

namespace App\Services\Explore;

use InvalidArgumentException;

/**
 * A bounding box the server has agreed to answer for.
 *
 * WHY A REFUSAL AND NOT A CLAMP
 * -----------------------------
 * An over-large bbox is refused with an exception, never silently shrunk. A
 * clamped box returns markers for somewhere the consumer is not looking at:
 * the map draws houses outside the frame, or — worse — draws a plausible
 * handful and the consumer reads an empty neighbourhood as "nothing for sale
 * here". An error is legible; a quiet substitution is not.
 *
 * The span ceiling is a scrape guard, not a performance tuning knob. Explore
 * publishes eligible IDX records a viewport at a time; a request covering a
 * whole state is a request for the dataset, and this is where that is refused.
 *
 * Latitude ordering is enforced rather than assumed. Longitude is NOT allowed
 * to wrap the antimeridian: this dataset is Florida, a wrapped box here means a
 * malformed client request rather than a Pacific viewport, and accepting one
 * would turn a bug into a full-table scan.
 */
final class ExploreViewport
{
    private function __construct(
        public readonly float $south,
        public readonly float $west,
        public readonly float $north,
        public readonly float $east,
    ) {}

    /**
     * Parse the wire format: "south,west,north,east" in decimal degrees.
     *
     * @throws InvalidArgumentException on anything this server will not answer.
     */
    public static function fromString(?string $bbox, ?float $maxSpanDegrees = null): self
    {
        $parts = array_map('trim', explode(',', (string) $bbox));

        if (count($parts) !== 4) {
            throw new InvalidArgumentException('bbox must be "south,west,north,east".');
        }

        foreach ($parts as $part) {
            // is_numeric rejects '', 'NaN', '1e999' handled below, and hex.
            if ($part === '' || ! is_numeric($part)) {
                throw new InvalidArgumentException('bbox values must be numeric.');
            }
        }

        [$south, $west, $north, $east] = array_map('floatval', $parts);

        foreach ([$south, $west, $north, $east] as $value) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('bbox values must be finite.');
            }
        }

        if ($south < -90.0 || $north > 90.0 || $west < -180.0 || $east > 180.0) {
            throw new InvalidArgumentException('bbox is outside the coordinate range.');
        }

        if ($south >= $north || $west >= $east) {
            throw new InvalidArgumentException('bbox must be ordered south,west,north,east.');
        }

        $viewport = new self($south, $west, $north, $east);

        $max = $maxSpanDegrees ?? (float) config('explore.viewport.max_span_degrees', 1.0);

        if ($max > 0.0 && ($viewport->latitudeSpan() > $max || $viewport->longitudeSpan() > $max)) {
            throw new InvalidArgumentException(
                'bbox is larger than this endpoint will answer. Zoom in and try again.'
            );
        }

        return $viewport;
    }

    public function latitudeSpan(): float
    {
        return $this->north - $this->south;
    }

    public function longitudeSpan(): float
    {
        return $this->east - $this->west;
    }

    public function contains(float $latitude, float $longitude): bool
    {
        return $latitude >= $this->south
            && $latitude <= $this->north
            && $longitude >= $this->west
            && $longitude <= $this->east;
    }

    /** @return array{south:float,west:float,north:float,east:float} */
    public function toArray(): array
    {
        return [
            'south' => $this->south,
            'west'  => $this->west,
            'north' => $this->north,
            'east'  => $this->east,
        ];
    }
}
