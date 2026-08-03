<?php

namespace App\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionKind;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use InvalidArgumentException;

/**
 * G1f-1 — the pure write-side legacy mirror projection. D-G1F-2, option 2-A.
 *
 * NAMING, AND WHAT IS DELIBERATELY NOT HERE
 * -----------------------------------------
 * D-G1-5's approved text assigns legacy compatibility to `LegacyMirrorAdapter`. D-G1F-2 approved
 * splitting that into two responsibilities: this class, which is **write-side only**, and a future
 * `LegacyMirrorAdapter` carrying read / fallback / repair. **`LegacyMirrorAdapter` remains
 * uncreated and separately authorization-gated**, and the G1c and G1d inertness guards still
 * assert its absence.
 *
 * CONTRACT
 * --------
 * In:  the RESULTING canonical {@see LocationDnaDocument}, after commands have been applied.
 * Out: a map of managed mirror key => string value, ready to persist verbatim.
 *
 * It is pure and total: same document in, same map out, no I/O, no clock, no randomness, and the
 * input document is never mutated (it is immutable anyway).
 *
 * RULES, EACH LOAD-BEARING
 * ------------------------
 * - **Derived from canonical state only.** It never reads an existing mirror, so a stale mirror
 *   can never influence what is written — which is what makes the resurrection defect
 *   structurally impossible on the write path rather than merely guarded against.
 * - **Present-but-cleared produces a cleared mirror.** This is D-G1-4 4-A: a user's clear finally
 *   takes effect on `counties` and `state`, which today keep stale values.
 * - **Absent invents nothing.** An absent dimension is omitted from the map entirely, so the
 *   caller writes no key. This is what stops a no-op save from destroying a legacy-only mirror —
 *   the defect `test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror` pins today.
 * - **`state` is a raw string** — D-G1F-4 4S-i.
 * - **`zipCodes` is OPT-IN PER SURFACE** — D-G1F-4 (a), resolved by the G1f-4 prerequisite. It is
 *   NOT in {@see self::MANAGED_KEYS}, so the default projection is byte-for-byte what G1f-1, G1f-2
 *   and G1f-3 already produce and no migrated workflow starts emitting a mirror it never wrote. A
 *   surface that ALREADY writes the legacy `zipCodes` meta may opt in by naming it in the
 *   constructor; nothing else changes. See the constructor for why this is a parameter rather than
 *   a global change.
 * - **The plural `states` key is OUT OF SCOPE** and never emitted — report §17.5, legacy dead write.
 *
 * It does no repair, no persistence, no provenance and no snapshot work. It cannot: it has no
 * collaborators.
 */
final class LegacyMirrorProjection
{
    /**
     * The mirror keys G1f-1 manages, in deterministic order.
     *
     * Exactly the three `BuyerAgentAuction` already writes. `zipCodes` and the plural `states` are
     * deliberately absent per D-G1F-4 and §17.5; adding either is an authorization decision, not a
     * refactor.
     */
    public const MANAGED_KEYS = ['cities', 'counties', 'state'];

    /**
     * Every mirror key any surface is permitted to manage, key => canonical dimension.
     *
     * A key absent from here cannot be managed at all, so a typo or an invented key fails loudly at
     * construction instead of silently writing nothing. The plural `states` is deliberately not a
     * member — §17.5 keeps it a legacy dead write.
     */
    private const SUPPORTED_KEYS = ['cities', 'counties', 'state', 'zipCodes'];

    /** @var list<string> the keys THIS instance manages, in deterministic emission order */
    private readonly array $managedKeys;

    /**
     * @param  list<string>|null  $managedKeys  null keeps the default set
     *
     * WHY OPT-IN RATHER THAN A GLOBAL SET
     * -----------------------------------
     * Adding `zipCodes` to {@see self::MANAGED_KEYS} would have changed behaviour for workflows that
     * have never written that meta. The Buyer family in particular already carries a PRESENT but
     * empty `zip_codes` key in its canonical blob, and present-empty is CLEARED, not absent — so a
     * global set would have made `BuyerAgentAuction` (G1f-1) and `BuyerOfferListing`/`Edit` (G1f-3)
     * emit `zipCodes => '[]'` on their very next save. That is a new legacy mirror key appearing in
     * three already-migrated workflows, which is precisely the compatibility guarantee G1f is built
     * to keep.
     *
     * So the managed set is per-instance and the default is unchanged. Only a surface that ALREADY
     * writes the legacy key opts in, and it does so by naming the key — this class holds no
     * per-workflow knowledge and no Tenant-specific branch.
     */
    public function __construct(?array $managedKeys = null)
    {
        $keys = $managedKeys ?? self::MANAGED_KEYS;

        foreach ($keys as $key) {
            if (! in_array($key, self::SUPPORTED_KEYS, true)) {
                throw new InvalidArgumentException(
                    "`{$key}` is not a supported legacy mirror key; supported: "
                    .implode(', ', self::SUPPORTED_KEYS)
                );
            }
        }

        $this->managedKeys = array_values(array_unique($keys));
    }

    /**
     * Project the managed mirrors from the resulting canonical document.
     *
     * @return array<string, string> key => value; keys absent from the document are omitted
     */
    public function project(LocationDnaDocument $document): array
    {
        $mirrors = [];

        foreach ($this->managedKeys as $key) {
            $dimension = $this->dimensionFor($key);

            // Absent invents nothing — the caller writes no key at all.
            if ($document->isAbsent($dimension)) {
                continue;
            }

            // Scalar text dimensions mirror RAW (D-G1F-4 4S-i); collections mirror JSON-encoded.
            // Cleared is the canonical empty of the dimension's own kind in both cases.
            $mirrors[$key] = $dimension->kind() === DimensionKind::Text
                ? ($document->isCleared($dimension) ? '' : (string) $document->value($dimension))
                : (string) json_encode(array_values(
                    (array) ($document->isCleared($dimension) ? [] : $document->value($dimension))
                ));
        }

        return $mirrors;
    }

    /** The canonical dimension a supported mirror key projects from. */
    private function dimensionFor(string $key): Dimension
    {
        return match ($key) {
            'cities'   => Dimension::Cities,
            'counties' => Dimension::Counties,
            'state'    => Dimension::State,
            'zipCodes' => Dimension::ZipCodes,
        };
    }
}
