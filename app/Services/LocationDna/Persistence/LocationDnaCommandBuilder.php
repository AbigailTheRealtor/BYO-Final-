<?php

namespace App\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Contract\LocationDnaContractException;

/**
 * G1f-1 — builds explicit per-dimension commands from a submitted editor payload.
 *
 * THIS CLASS IS WHERE "ABSENCE MEANS NO OPERATION" IS ENFORCED
 * -----------------------------------------------------------
 * D-G1-2 option 2-A, approved: an unmounted field performs no operation, an omitted field performs
 * no operation, absence is not clear, and preserve is represented by the ABSENCE of a command
 * rather than a third operation. All four fall out of one rule here:
 *
 *   a dimension gets a command only if its key is PRESENT in the submitted payload.
 *
 * Presence is decided with `array_key_exists()`, never `empty()` — §5.2's mechanism, and the whole
 * defect class G1a characterised.
 *
 *   key absent          → no command      → preserve
 *   key present, value  → set             → authored
 *   key present, empty  → clear           → present-but-empty (S4)
 *
 * NO PAYLOAD AT ALL MEANS NO COMMANDS
 * -----------------------------------
 * `null`, `false` and `''` all yield an empty batch. `false` is not hypothetical: the EAV accessor
 * returns boolean `false` for an unwritten key and `HasSearchAreas:65` assigns it straight to the
 * bridged property, so a legacy-only record arrives here as `false`. An unparseable payload also
 * yields an empty batch — a transport failure is never an instruction (L5), and it must never
 * become a clear.
 *
 * WHY THIS IS NOT A FULL-DOCUMENT REWRITE
 * ---------------------------------------
 * The commands describe only what the SUBMITTED payload states. Nothing is read from stored
 * canonical state, and nothing is read from a legacy mirror, so no inherited, imported, derived or
 * legacy value can be promoted to owner-authored by this builder. That is the §10.2 constraint
 * D-G1F-1 made binding, expressed as code rather than as discipline.
 *
 * `subject_property` is never commanded: §17 G8 owns it and the G1d resolver grants no mutation
 * for it, so emitting a command would only produce a guaranteed capability rejection.
 */
final class LocationDnaCommandBuilder
{
    /**
     * Build the batch a submitted payload states.
     *
     * @param  mixed  $payload  the bridged editor value: JSON string, array, false, null or ''
     * @return list<DimensionCommand> empty when the payload states nothing
     */
    public function fromEditorPayload(mixed $payload): array
    {
        $decoded = $this->decode($payload);

        if ($decoded === null) {
            return [];
        }

        $commands = [];

        foreach (Dimension::all() as $dimension) {
            if ($dimension === Dimension::SubjectProperty) {
                continue;
            }

            // §5.2: presence, never empty().
            if (! array_key_exists($dimension->value, $decoded)) {
                continue;
            }

            $value = $decoded[$dimension->value];

            // D-G1-1: null is not a valid authored value and is not a clear. Not supplied.
            if ($value === null) {
                continue;
            }

            try {
                $commands[] = $dimension->isCanonicalEmpty($value)
                    ? DimensionCommand::clear($dimension)
                    : DimensionCommand::set($dimension, $value);
            } catch (LocationDnaContractException) {
                // A value the contract refuses is not silently coerced into a clear (L5). It is
                // simply not commanded, and the service's own validation surfaces the malformed
                // document if the stored state is the problem.
                continue;
            }
        }

        return $commands;
    }

    /**
     * Decode a submitted payload to an associative array, or null when it states nothing.
     *
     * @return array<string, mixed>|null
     */
    private function decode(mixed $payload): ?array
    {
        if ($payload === null || $payload === false || $payload === '') {
            return null;
        }

        if (is_array($payload)) {
            return array_is_list($payload) ? null : $payload;
        }

        if (! is_string($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }
}
