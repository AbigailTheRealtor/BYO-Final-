<?php

namespace App\Services\LocationDna\Provenance;

use App\Services\LocationDna\Contract\Dimension;

/**
 * LocationDnaProvenanceMap — an immutable, sparse, per-dimension provenance record.
 *
 * G1e inert provenance model. INERT: no database, no model, no column, no observer, no event.
 *
 * SPARSE BY DESIGN
 * ----------------
 * Not every dimension carries provenance, and requiring one per dimension would be the mistake
 * settled decision 6 forbids: a parallel structure that must answer for every dimension starts
 * answering the presence question. An ABSENT dimension has no provenance at all — absence is not an
 * origin — so a lookup simply returns null. That is the model's compatibility with the G1c three
 * states: `absent` has no entry, `cleared` has an `OwnerCleared` entry, and `authored` has an
 * `OwnerAuthored` entry.
 *
 * IMMUTABLE
 * ---------
 * `readonly`, PHP array-by-value semantics, and every accessor returns a copy — so neither the array
 * a caller passed in nor the array a caller receives back can reach the stored state. `with*` methods
 * return new instances.
 *
 * SERIALISATION IS PRIVATE AND VERSIONED
 * --------------------------------------
 * {@see self::toInternalArray()} exists for tests and for future persistence *planning* only. It is
 * not wired to anything, introduces no column, and does not touch the G1c document serializer, the
 * public projection or the revision token. It carries an explicit version so a future shape change
 * is introducible safely, and {@see self::fromInternalArray()} refuses an unsupported version rather
 * than guessing — the same posture G1c takes for `schema_version`.
 */
final class LocationDnaProvenanceMap
{
    /** Internal serialisation version. Bump when the shape changes. */
    public const INTERNAL_VERSION = 1;

    /** @param array<string, LocationDnaProvenanceKind> $entries dimension value => kind */
    private function __construct(private readonly array $entries)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Build from a dimension-keyed map of kinds.
     *
     * @param  array<string, LocationDnaProvenanceKind>  $entries
     *
     * @throws LocationDnaProvenanceException on a key that is not a canonical dimension
     */
    public static function fromKinds(array $entries): self
    {
        $clean = [];

        foreach ($entries as $key => $kind) {
            $dimension = Dimension::tryFromKey((string) $key);

            if ($dimension === null) {
                throw LocationDnaProvenanceException::malformed(
                    "`{$key}` is not a canonical dimension; provenance cannot be recorded for it.",
                );
            }

            if (! $kind instanceof LocationDnaProvenanceKind) {
                throw LocationDnaProvenanceException::malformed(
                    "provenance for `{$dimension->value}` must be a LocationDnaProvenanceKind, got "
                    .get_debug_type($kind).'.',
                );
            }

            $clean[$dimension->value] = $kind;
        }

        ksort($clean);

        return new self($clean);
    }

    // ── lookup ───────────────────────────────────────────────────────────────

    /** The recorded kind, or null when this dimension has no provenance entry. */
    public function kindFor(Dimension $dimension): ?LocationDnaProvenanceKind
    {
        return $this->entries[$dimension->value] ?? null;
    }

    /** The full record, or null when absent. */
    public function for(Dimension $dimension): ?DimensionProvenance
    {
        $kind = $this->kindFor($dimension);

        return $kind === null ? null : DimensionProvenance::of($dimension, $kind);
    }

    public function has(Dimension $dimension): bool
    {
        return array_key_exists($dimension->value, $this->entries);
    }

    /**
     * The authority for a dimension, or null when it has no provenance.
     *
     * Null is NOT an authority — a caller must decide what an unrecorded origin means for it, rather
     * than being handed a default that looks like a decision.
     */
    public function authorityFor(Dimension $dimension): ?ProvenanceAuthority
    {
        return $this->kindFor($dimension)?->authority();
    }

    public function isAuthoritative(Dimension $dimension): bool
    {
        return $this->kindFor($dimension)?->isAuthoritative() ?? false;
    }

    /** True when this dimension carries the owner's explicit clear. */
    public function blocksFallbackResurrection(Dimension $dimension): bool
    {
        return $this->kindFor($dimension)?->blocksFallbackResurrection() ?? false;
    }

    /** @return list<string> dimension keys carrying provenance, deterministically ordered */
    public function recordedDimensionKeys(): array
    {
        return array_keys($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    // ── derivation ───────────────────────────────────────────────────────────

    /**
     * A copy with one dimension's provenance set.
     *
     * No transition check is applied here: this is the raw recording operation, and legality is
     * {@see ProvenanceTransition}'s question. {@see self::withTransition()} is the checked path.
     */
    public function with(Dimension $dimension, LocationDnaProvenanceKind $kind): self
    {
        $next                      = $this->entries;
        $next[$dimension->value]   = $kind;
        ksort($next);

        return new self($next);
    }

    /** A copy with one dimension's provenance removed entirely. */
    public function without(Dimension $dimension): self
    {
        $next = $this->entries;
        unset($next[$dimension->value]);

        return new self($next);
    }

    /**
     * A copy with one dimension's provenance changed, but only if the transition is permitted.
     *
     * The current kind is the `from`; a dimension with no entry transitions from
     * {@see LocationDnaProvenanceKind::Unknown}, which is default-safe — so an unrecorded origin
     * cannot be automatically promoted, only explicitly authored.
     *
     * @throws LocationDnaProvenanceException when the transition is refused
     */
    public function withTransition(
        Dimension $dimension,
        LocationDnaProvenanceKind $to,
        ProvenanceActor $actor,
    ): self {
        $from = $this->kindFor($dimension) ?? LocationDnaProvenanceKind::Unknown;

        ProvenanceTransition::of($from, $to, $actor)->assertAllowed();

        return $this->with($dimension, $to);
    }

    /** True when the transition would be permitted, without performing it. */
    public function allowsTransition(
        Dimension $dimension,
        LocationDnaProvenanceKind $to,
        ProvenanceActor $actor,
    ): bool {
        $from = $this->kindFor($dimension) ?? LocationDnaProvenanceKind::Unknown;

        return ProvenanceTransition::of($from, $to, $actor)->isAllowed();
    }

    // ── private, versioned serialisation ─────────────────────────────────────

    /**
     * Internal shape, for tests and future planning only.
     *
     * @return array{version: int, dimensions: array<string, string>}
     */
    public function toInternalArray(): array
    {
        $dimensions = [];

        foreach ($this->entries as $key => $kind) {
            $dimensions[$key] = $kind->value;
        }

        ksort($dimensions);

        return ['version' => self::INTERNAL_VERSION, 'dimensions' => $dimensions];
    }

    /**
     * Read the internal shape back.
     *
     * A malformed record is rejected rather than becoming trusted provenance, and an unsupported
     * newer version is refused rather than guessed at. An unrecognised kind string degrades to
     * {@see LocationDnaProvenanceKind::Unknown} — default-safe, never to an authoritative kind.
     *
     * @throws LocationDnaProvenanceException
     */
    public static function fromInternalArray(mixed $raw): self
    {
        if (! is_array($raw)) {
            throw LocationDnaProvenanceException::malformed(
                'expected an array, got '.get_debug_type($raw).'.',
            );
        }

        if (! array_key_exists('version', $raw) || ! is_int($raw['version'])) {
            throw LocationDnaProvenanceException::malformed('the record carries no integer version.');
        }

        if ($raw['version'] > self::INTERNAL_VERSION) {
            throw LocationDnaProvenanceException::unsupportedVersion($raw['version'], self::INTERNAL_VERSION);
        }

        if ($raw['version'] < 1) {
            throw LocationDnaProvenanceException::malformed("version {$raw['version']} is not valid.");
        }

        $dimensions = $raw['dimensions'] ?? null;

        if (! is_array($dimensions)) {
            throw LocationDnaProvenanceException::malformed('`dimensions` must be an array.');
        }

        $entries = [];

        foreach ($dimensions as $key => $value) {
            $dimension = Dimension::tryFromKey((string) $key);

            if ($dimension === null) {
                throw LocationDnaProvenanceException::malformed(
                    "`{$key}` is not a canonical dimension.",
                );
            }

            // An unreadable kind becomes Unknown, never something authoritative.
            $entries[$dimension->value] = LocationDnaProvenanceKind::fromNameOrUnknown(
                is_string($value) ? $value : null,
            );
        }

        ksort($entries);

        return new self($entries);
    }
}
