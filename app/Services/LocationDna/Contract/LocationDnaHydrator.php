<?php

namespace App\Services\LocationDna\Contract;

/**
 * LocationDnaHydrator — the single, version-aware entry point from untrusted input.
 *
 * G1c contract core. INERT. Approved by D-G1-1 (option 1-B).
 *
 * §5.5: "the hydrator is the only reader of `schema_version`." §5.4 rule 1: mode is read
 * once, at hydration, and no consumer re-derives it. This class is that single reader.
 *
 * WHAT IT ACCEPTS
 * ---------------
 * A JSON string, an already-decoded array, null, or anything else. It is a boundary, so it
 * assumes nothing about its input.
 *
 * WHAT IT DOES NOT DO
 * -------------------
 *   - no database access, no Eloquent, no meta reads
 *   - no legacy-mirror merge — that is LegacyMirrorAdapter's, NOT created in this increment
 *     (D-G1-5). This is the deliberate difference from `HasSearchAreas::loadSearchAreas()`,
 *     whose mirror merge at line 48 is what G1a proved resurrects cleared values.
 *   - no persistence repair
 *   - no write of any kind
 *
 * FAILURE IS NOT AN EMPTY DOCUMENT
 * --------------------------------
 * Every non-hydratable input returns a distinct {@see HydrationOutcome}. G1a proved the
 * current code silently converts a corrupt blob into an empty record
 * (test_s3_corrupt_blob_is_silently_treated_as_an_empty_record); this hydrator refuses to.
 */
final class LocationDnaHydrator
{
    public function __construct(private readonly LocationDnaNormalizer $normalizer = new LocationDnaNormalizer())
    {
    }

    /**
     * Hydrate untrusted input into a result.
     *
     * The input is never mutated: arrays are taken by value, and the raw value retained on a
     * malformed result is the value as supplied.
     */
    public function hydrate(mixed $input): HydrationResult
    {
        // ── absent ───────────────────────────────────────────────────────────
        // null, false (what `info()` returns for an unwritten meta key — the boolean G1a
        // pinned in test_s3_absent_blob_yields_an_empty_record_with_mirrors_as_the_only_source),
        // and the empty string all mean "no document exists".
        if ($input === null || $input === false || $input === '') {
            return HydrationResult::absent();
        }

        // ── decode ───────────────────────────────────────────────────────────
        $decoded = $input;

        if (is_string($input)) {
            $decoded = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return HydrationResult::malformed(
                    'The stored document is not valid JSON: '.json_last_error_msg().'.',
                    $input,
                );
            }
        }

        // ── malformed top level ──────────────────────────────────────────────
        // A decoded scalar is the specific hazard G1a recorded: json_decode("0") is integer 0,
        // perfectly valid JSON, and an array-offset read on it is a worse failure than a
        // clean null. Rejected here rather than absorbed.
        if (! is_array($decoded)) {
            return HydrationResult::malformed(
                'The stored document decoded to '.get_debug_type($decoded).', not an object.',
                $input,
            );
        }

        // A JSON list is not a canonical document either.
        if ($decoded !== [] && array_is_list($decoded)) {
            return HydrationResult::malformed('The stored document is a JSON list, not an object.', $input);
        }

        // ── schema version and interpretation mode (§5.4, §5.5) ──────────────
        $versionResult = $this->readSchemaVersion($decoded, $input);

        if ($versionResult instanceof HydrationResult) {
            return $versionResult;
        }

        [$schemaVersion, $mode] = $versionResult;

        // ── dimensions ───────────────────────────────────────────────────────
        $dimensions = [];
        $extensions = [];

        foreach ($decoded as $key => $value) {
            $key = (string) $key;

            if ($key === 'schema_version') {
                continue;
            }

            $dimension = Dimension::tryFromKey($key);

            if ($dimension === null) {
                // Unknown-future and withdrawn-but-present keys (e.g. `neighborhoods`, §18)
                // are retained verbatim and never interpreted. D-G1-1: preserved only through
                // version-aware hydration, and not interpreted by an older writer.
                $extensions[$key] = $value;

                continue;
            }

            if ($value === null) {
                // D-G1-1: null is not a valid authored dimension value. It is treated as
                // "not supplied", so the key simply does not become present — which is NOT
                // the same as clearing it.
                continue;
            }

            try {
                $dimensions[$dimension->value] = $this->normalizer->normalize($dimension, $value);
            } catch (LocationDnaContractException $e) {
                // A malformed KNOWN dimension quarantines the whole document rather than
                // being dropped, so a partial truth is never recorded (L5).
                return HydrationResult::malformed(
                    "Dimension `{$dimension->value}` is not valid canonical data: {$e->getMessage()}",
                    $input,
                );
            }
        }

        return HydrationResult::hydrated(
            LocationDnaDocument::fromCanonical($dimensions, $extensions, $mode, $schemaVersion),
        );
    }

    /**
     * Read `schema_version` and derive the interpretation mode.
     *
     * @return array{0: ?int, 1: InterpretationMode}|HydrationResult
     */
    private function readSchemaVersion(array $decoded, mixed $rawInput): array|HydrationResult
    {
        if (! array_key_exists('schema_version', $decoded)) {
            // §5.4 S1: absent stamp ⇒ legacy interpretation. A missing dimension key here is
            // indeterminate and must not later be claimed as a clear (§5.4 rule 2).
            return [null, InterpretationMode::Legacy];
        }

        $raw = $decoded['schema_version'];

        if (is_string($raw) && is_numeric($raw)) {
            $raw = (int) $raw;
        }

        if (! is_int($raw)) {
            return HydrationResult::malformed(
                'schema_version must be an integer, got '.get_debug_type($decoded['schema_version']).'.',
                $rawInput,
            );
        }

        if ($raw > LocationDnaDocument::CURRENT_SCHEMA_VERSION) {
            // §5.5: refuse to interpret. Do not guess, do not downgrade, do not write.
            return HydrationResult::unsupportedVersion($raw);
        }

        if ($raw < 1) {
            return HydrationResult::malformed("schema_version {$raw} is not a valid version.", $rawInput);
        }

        // Version 1 predates the canonical presence rule, so it is read with legacy
        // interpretation; version 2 is authoritative (§5.4 S2).
        return [$raw, $raw >= LocationDnaDocument::CURRENT_SCHEMA_VERSION
            ? InterpretationMode::Canonical
            : InterpretationMode::Legacy];
    }
}
