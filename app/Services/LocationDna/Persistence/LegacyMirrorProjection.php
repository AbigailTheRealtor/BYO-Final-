<?php

namespace App\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\LocationDnaDocument;

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
 * - **`zipCodes` is OUT OF SCOPE** for G1f-1 — D-G1F-4 (a). It is not in MANAGED_KEYS, is never
 *   emitted, and this class holds no ZIP logic at all.
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
     * Project the managed mirrors from the resulting canonical document.
     *
     * @return array<string, string> key => value; keys absent from the document are omitted
     */
    public function project(LocationDnaDocument $document): array
    {
        $mirrors = [];

        // cities / counties — collection dimensions, JSON-encoded, cleared as `[]`.
        foreach ([
            'cities'   => Dimension::Cities,
            'counties' => Dimension::Counties,
        ] as $key => $dimension) {
            if ($document->isAbsent($dimension)) {
                continue;
            }

            $value = $document->isCleared($dimension) ? [] : $document->value($dimension);

            $mirrors[$key] = (string) json_encode(array_values((array) $value));
        }

        // state — scalar dimension, RAW string (D-G1F-4 4S-i), cleared as the empty string.
        if (! $document->isAbsent(Dimension::State)) {
            $mirrors['state'] = $document->isCleared(Dimension::State)
                ? ''
                : (string) $document->value(Dimension::State);
        }

        return $mirrors;
    }
}
