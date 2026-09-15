<?php

namespace App\Services\SmartTags;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Support\SmartTags\SmartTagListingRef;
use Illuminate\Support\Facades\DB;

/**
 * Removes one listing's Smart Tag evidence, assignments and derivation state.
 *
 * The Smart Tag tables have no foreign key on (listing_type, listing_id), by the
 * house convention for multi-source tables, so a deleted listing's rows must be
 * removed explicitly. Draft purges delete listings through the query builder and
 * fire no model events; the wiring phase calls this from that purge.
 *
 * Every delete is scoped to the one (listing_type, listing_id). The append-only
 * manual event audit is deliberately kept.
 */
class SmartTagAssignmentPurger
{
    /**
     * @return array{evidence: int, assignments: int, states: int}
     */
    public function forListing(SmartTagListingRef $listing): array
    {
        return DB::transaction(function () use ($listing) {
            $scope = static fn ($query) => $query
                ->where('listing_type', $listing->type->value)
                ->where('listing_id', $listing->id);

            return [
                'evidence'    => $scope(SmartTagEvidence::query())->delete(),
                'assignments' => $scope(SmartTagAssignment::query())->delete(),
                'states'      => $scope(SmartTagDerivationState::query())->delete(),
            ];
        });
    }
}
