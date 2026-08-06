<?php

namespace App\Services\LocationDna\Criteria\Rules;

/**
 * Phase 1b — one selection the resolver dropped, and the rule that justified dropping it.
 *
 * WHY THE RESOLVER REPORTS INSTEAD OF JUST DELETING
 * ------------------------------------------------
 * Clearing a dependent selection is data loss from the user's point of view. A cascade that removes
 * eleven ZIP codes because a county was deselected, and says nothing, looks like a bug to the person
 * it happens to. Phase 1c needs to be able to say "11 ZIP codes were cleared because you removed
 * Suffolk County" — which needs the ids and the reason, not just the surviving set.
 *
 * `reason` reuses {@see GeographyRule} rather than introducing a parallel vocabulary: the resolver
 * clears exactly what the validator would flag, so the two speak the same language by construction.
 */
final class ClearedSelection
{
    public function __construct(
        public readonly GeographyTier $tier,
        public readonly string $id,
        public readonly GeographyRule $reason,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'tier'   => $this->tier->value,
            'id'     => $this->id,
            'reason' => $this->reason->value,
        ];
    }
}
