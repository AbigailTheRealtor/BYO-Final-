<?php

namespace App\Services\SmartTags;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Services\SmartTags\Derivation\TagEvidence;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds one listing's canonical smart_tag_assignments from its evidence.
 *
 * Replace, not merge: in one transaction the listing's assignment rows are
 * deleted and the resolver's output inserted, so the unique
 * (listing_type, listing_id, tag_key) row can never be duplicated and a tag
 * whose evidence disappeared can never linger. A listing with no supported
 * context ends with no assignments.
 */
class SmartTagAssignmentProjector
{
    public function project(SmartTagListingRef $listing, ?SmartTagContext $context): SmartTagResolution
    {
        return DB::transaction(function () use ($listing, $context) {
            $resolution = SmartTagResolver::resolve($this->loadEvidence($listing), $context);

            SmartTagAssignment::query()
                ->where('listing_type', $listing->type->value)
                ->where('listing_id', $listing->id)
                ->delete();

            $now = now();
            foreach ($resolution->assignments as $resolved) {
                SmartTagAssignment::query()->create([
                    'listing_type'   => $listing->type->value,
                    'listing_id'     => $listing->id,
                    'tag_key'        => $resolved->tagKey,
                    'context'        => $resolved->context->value,
                    'state'          => $resolved->state->value,
                    'winning_source' => $resolved->winningSource->value,
                    'has_conflict'   => $resolved->hasConflict,
                    'conflict_tags'  => $resolved->conflictTags === [] ? null : $resolved->conflictTags,
                    'resolved_at'    => $now,
                ]);
            }

            return $resolution;
        });
    }

    /**
     * @return TagEvidence[]
     */
    private function loadEvidence(SmartTagListingRef $listing): array
    {
        $out = [];

        $rows = SmartTagEvidence::query()
            ->where('listing_type', $listing->type->value)
            ->where('listing_id', $listing->id)
            ->get();

        foreach ($rows as $row) {
            $context = SmartTagContext::tryFrom((string) $row->context);
            $source = SmartTagSource::tryFrom((string) $row->source);
            $state = SmartTagState::tryFrom((string) $row->state);

            // A row that no longer parses is ignored, never guessed at.
            if ($context === null || $source === null || $state === null || ! $listing->type->allowsSource($source)) {
                continue;
            }

            $out[] = new TagEvidence(
                tagKey: (string) $row->tag_key,
                state: $state,
                source: $source,
                context: $context,
                confidence: (int) $row->confidence,
                sourceField: $row->source_field,
                ruleId: $row->rule_id,
            );
        }

        return $out;
    }
}
