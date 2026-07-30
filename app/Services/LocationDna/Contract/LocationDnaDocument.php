<?php

namespace App\Services\LocationDna\Contract;

/**
 * LocationDnaDocument — the immutable canonical PRIVATE Location DNA document.
 *
 * G1c contract core. INERT: not referenced by any existing production workflow.
 * Approved by D-G1-1 (option 1-B) in
 * docs/architecture/LOCATION-DNA-ENGINE-V1.2-G1C-DECISION-PACKAGE.md.
 *
 * WHAT THIS IS
 * ------------
 * Canonical STATE, never patch intent. A document says what the record currently asserts;
 * it has no notion of "unchanged" or "preserve". Those belong to {@see DimensionCommand}.
 *
 * PRESENCE IS STRUCTURAL
 * ----------------------
 * §5.2 and settled decision 5: presence is decided by key presence, never by `empty()`.
 * Three states per dimension, and they are three, not two:
 *
 *   absent   — never authored. Legacy fallback MAY apply (subject to interpretation mode).
 *   cleared  — present and equal to the canonical empty. Intentional. No fallback.
 *   authored — present and non-empty.
 *
 * D-G1-1 approved clarifications enforced here: `null` is never an authored value; an
 * empty array is the canonical cleared value for collection dimensions; present-but-cleared
 * is authoritative; and malformed values cannot masquerade as valid canonical values —
 * this class only accepts values already validated by {@see LocationDnaNormalizer}, and
 * its named constructors reject `null` outright.
 *
 * WHAT IT DOES NOT DO
 * -------------------
 * No Eloquent, no Livewire, no HTTP, no database, no legacy-mirror reads. §6: the domain
 * core must not depend on a UI framework or transport. Mirror fallback is
 * `LegacyMirrorAdapter`'s job and is NOT created in this increment (D-G1-5). Public
 * redaction remains `PublicGeometryProjection`, unchanged and separate.
 *
 * IMMUTABILITY
 * ------------
 * `readonly` properties, and every accessor that returns an array returns a value copy —
 * PHP copies arrays by value, so a caller cannot reach in and mutate stored state. The
 * `with*` helpers return new instances.
 */
final class LocationDnaDocument
{
    /** The schema version every v1.2 writer stamps (§5.5). */
    public const CURRENT_SCHEMA_VERSION = 2;

    /** The canonical meta key this document is stored under (§5.1). */
    public const CANONICAL_META_KEY = 'location_dna_preferences';

    /**
     * @param  array<string, mixed>  $dimensions  present dimensions only, keyed by Dimension->value
     * @param  array<string, mixed>  $extensions  unknown-future and withdrawn-but-present keys, never interpreted
     */
    private function __construct(
        private readonly array $dimensions,
        private readonly array $extensions,
        private readonly InterpretationMode $mode,
        private readonly ?int $schemaVersion,
    ) {
    }

    /**
     * A document with no dimension present at all.
     *
     * Distinct from a cleared document: nothing here was ever authored.
     */
    public static function emptyDocument(
        InterpretationMode $mode = InterpretationMode::Canonical,
        ?int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ): self {
        return new self([], [], $mode, $schemaVersion);
    }

    /**
     * Build from already-validated canonical values.
     *
     * Intended for {@see LocationDnaHydrator} and {@see LocationDnaNormalizer}, which own
     * validation. Callers passing raw untrusted input should go through the hydrator.
     *
     * @param  array<string, mixed>  $dimensions
     * @param  array<string, mixed>  $extensions
     *
     * @throws LocationDnaContractException on an unrecognised key or an authored null
     */
    public static function fromCanonical(
        array $dimensions,
        array $extensions = [],
        InterpretationMode $mode = InterpretationMode::Canonical,
        ?int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ): self {
        $clean = [];

        foreach ($dimensions as $key => $value) {
            $dimension = Dimension::tryFromKey((string) $key);

            if ($dimension === null) {
                throw LocationDnaContractException::invalidDimensionValue(
                    (string) $key,
                    'not a canonical dimension; unknown keys belong in the extension bag.',
                );
            }

            if ($value === null) {
                throw LocationDnaContractException::authoredNull($dimension->value);
            }

            $clean[$dimension->value] = $value;
        }

        // Deterministic key order so serialisation and tokenisation are stable.
        ksort($clean);
        ksort($extensions);

        return new self($clean, $extensions, $mode, $schemaVersion);
    }

    // ── presence ─────────────────────────────────────────────────────────────

    public function isAbsent(Dimension $dimension): bool
    {
        return ! array_key_exists($dimension->value, $this->dimensions);
    }

    public function isPresent(Dimension $dimension): bool
    {
        return array_key_exists($dimension->value, $this->dimensions);
    }

    /** Present and equal to the canonical empty — an intentional clear (§5.4 S4). */
    public function isCleared(Dimension $dimension): bool
    {
        return $this->isPresent($dimension)
            && $dimension->isCanonicalEmpty($this->dimensions[$dimension->value]);
    }

    /** Present and carrying a non-empty value. */
    public function isAuthored(Dimension $dimension): bool
    {
        return $this->isPresent($dimension) && ! $this->isCleared($dimension);
    }

    /** @return list<string> canonical keys actually present, in deterministic order */
    public function presenceSet(): array
    {
        return array_keys($this->dimensions);
    }

    // ── values ───────────────────────────────────────────────────────────────

    /** The stored value, or null when the dimension is absent. Arrays are returned by copy. */
    public function value(Dimension $dimension): mixed
    {
        return $this->dimensions[$dimension->value] ?? null;
    }

    /** The stored value, falling back to this dimension's canonical empty when absent. */
    public function valueOrCanonicalEmpty(Dimension $dimension): mixed
    {
        return $this->isPresent($dimension)
            ? $this->dimensions[$dimension->value]
            : $dimension->canonicalEmpty();
    }

    /** @return array<string, mixed> a copy of the present dimensions */
    public function toDimensionArray(): array
    {
        return $this->dimensions;
    }

    // ── extensions: unknown-future and withdrawn-but-present keys ────────────

    /**
     * Opaque retained keys.
     *
     * Holds two categories, neither interpreted: keys this reader does not recognise, and
     * keys §18 withdrew but kept read-tolerant (`neighborhoods`). D-G1-1 approved that
     * unknown future keys are preserved only through version-aware hydration and are not
     * interpreted by an older writer — this bag is that mechanism, and nothing in this
     * namespace reads its contents.
     *
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    public function hasExtension(string $key): bool
    {
        return array_key_exists($key, $this->extensions);
    }

    // ── interpretation ───────────────────────────────────────────────────────

    public function interpretationMode(): InterpretationMode
    {
        return $this->mode;
    }

    /** The stamped version, or null for a legacy record that carried none (§5.4 S1). */
    public function schemaVersion(): ?int
    {
        return $this->schemaVersion;
    }

    // ── derivation (returns new instances; never mutates) ────────────────────

    /**
     * A copy with one dimension set to an already-validated value.
     *
     * @throws LocationDnaContractException when $value is null
     */
    public function withDimension(Dimension $dimension, mixed $value): self
    {
        if ($value === null) {
            throw LocationDnaContractException::authoredNull($dimension->value);
        }

        $next                      = $this->dimensions;
        $next[$dimension->value]   = $value;
        ksort($next);

        return new self($next, $this->extensions, $this->mode, $this->schemaVersion);
    }

    /** A copy with one dimension present and set to its canonical empty — an intentional clear. */
    public function withClearedDimension(Dimension $dimension): self
    {
        $next                    = $this->dimensions;
        $next[$dimension->value] = $dimension->canonicalEmpty();
        ksort($next);

        return new self($next, $this->extensions, $this->mode, $this->schemaVersion);
    }

    /**
     * A copy stamped at the current schema version, in canonical interpretation mode.
     *
     * This is the §5.5 lazy upgrade: stamp the version and record the observed presence
     * set, changing no values. Because {@see LocationDnaRevisionToken} deliberately
     * excludes the version and the mode from its input, such an upgrade does not move the
     * token — which is exactly what D-G1-3's approved clarification requires.
     */
    public function withLazyUpgrade(): self
    {
        return new self(
            $this->dimensions,
            $this->extensions,
            InterpretationMode::Canonical,
            self::CURRENT_SCHEMA_VERSION,
        );
    }
}
