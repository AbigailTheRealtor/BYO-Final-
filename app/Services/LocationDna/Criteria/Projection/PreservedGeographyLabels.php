<?php

namespace App\Services\LocationDna\Criteria\Projection;

/**
 * Phase 1c — the stored labels the corpus could not resolve, kept so they survive an edit.
 *
 * WHY THIS TYPE EXISTS AT ALL
 * ---------------------------
 * Every record written before this phase carries free-text location labels: renamed places,
 * hand-typed values, formats from an earlier version of the editor, class-suffix variants the
 * reference tables spell differently. A cascade that can only represent what it can match would
 * quietly delete the rest the first time a user saved an unrelated field.
 *
 * So hydration splits its input in two — what became a selection, and what did not — and this
 * carries the second half all the way back out to the projector. The rule the two halves enforce
 * together is the approved one:
 *
 *     an unmatched label is preserved VERBATIM, never dropped and never guessed at.
 *
 * Verbatim is doing real work there. The label is carried out byte-identical to how it came in, so
 * a save that changes nothing writes back exactly what it read, and a record cannot drift a little
 * further from its original every time it is opened.
 *
 * IT IS NOT AN ERROR REPORT
 * -------------------------
 * A preserved label is not a validation failure and must not be rendered as one. It is history the
 * cascade cannot express — the surface shows it, lets the user remove it deliberately, and
 * otherwise leaves it alone. {@see \App\Services\LocationDna\Criteria\Rules\GeographyViolation} is
 * the type for things that are actually wrong.
 *
 * IMMUTABLE, and holds no state tier beyond the four the cascade knows about.
 */
final class PreservedGeographyLabels
{
    /**
     * @param  string|null   $state     an unresolvable state label; null when the state resolved
     * @param  list<string>  $counties
     * @param  list<string>  $cities
     * @param  list<string>  $zipCodes
     */
    public function __construct(
        public readonly ?string $state = null,
        public readonly array $counties = [],
        public readonly array $cities = [],
        public readonly array $zipCodes = [],
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /** Did everything resolve? */
    public function isEmpty(): bool
    {
        return $this->state === null
            && $this->counties === []
            && $this->cities === []
            && $this->zipCodes === [];
    }

    /** How many labels are being carried, across all tiers. Surfaces use this for their notice. */
    public function count(): int
    {
        return ($this->state === null ? 0 : 1)
            + count($this->counties)
            + count($this->cities)
            + count($this->zipCodes);
    }

    /** @return array<string, mixed> the shape a Livewire component can hold across a round trip */
    public function toArray(): array
    {
        return [
            'state'     => $this->state,
            'counties'  => $this->counties,
            'cities'    => $this->cities,
            'zip_codes' => $this->zipCodes,
        ];
    }

    /**
     * Rebuild from {@see self::toArray()}.
     *
     * A Livewire component cannot hold a value object across a request, so the trait keeps the
     * array form as a public property and rehydrates here. Defensive about every key: the array
     * has made a round trip through the browser and is untrusted on the way back.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::stringOrNull($data['state'] ?? null),
            self::stringList($data['counties'] ?? []),
            self::stringList($data['cities'] ?? []),
            self::stringList($data['zip_codes'] ?? []),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim($value) === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $out = [];

        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $out[] = $value;
            }
        }

        return array_values($out);
    }
}
