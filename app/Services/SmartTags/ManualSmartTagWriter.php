<?php

namespace App\Services\SmartTags;

use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Models\User;
use App\Services\SmartTags\Derivation\NativeListingTagDeriver;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagSelectionResult;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Support\Facades\DB;

/**
 * Persists a Seller/Landlord owner's manual Smart Tag selections.
 *
 * NOT WIRED TO ANY UI IN PHASE 1. This is the write path a future "Property
 * Features" picker will call.
 *
 *   1. Authorize — owner of a non-archived native Offer Listing only.
 *   2. Context from the STORED property type, never a submitted one.
 *   3. Project through SmartTagSelectionPolicy — canonical, active, applicable,
 *      owner-selectable keys only; keys the listing's own Yes/No fields already
 *      answer are refused (the owner edits Property Details instead).
 *   4. Diff against the owner's current manual evidence and, in one transaction,
 *      add and remove manual_listing_owner rows, append audit events, and
 *      re-resolve the listing's canonical assignments.
 *
 * Deselecting deletes the owner's evidence row: the tag becomes UNKNOWN (unless
 * another source still supports it). It never records "absent".
 */
class ManualSmartTagWriter
{
    public const ACTOR_ROLE = 'listing_owner';

    public function __construct(
        private readonly SmartTagListingAuthorizer $authorizer,
        private readonly NativeListingTagDeriver $nativeDeriver,
        private readonly SmartTagAssignmentProjector $projector,
    ) {
    }

    /**
     * @param array<int, mixed> $requestedKeys
     */
    public function replaceSelections(SmartTagListingRef $listing, array $requestedKeys, ?User $actor): ManualSelectionResult
    {
        $decision = $this->authorizer->authorize($actor, $listing);
        if (! $decision->allowed) {
            return ManualSelectionResult::refused((string) $decision->reason);
        }

        $model = $decision->listing;
        $meta = NativeMetaValueReader::fromMetaRows($model->meta()->get(['meta_key', 'meta_value']));
        $context = SmartTagContextResolver::forListingType($listing->type, $meta->scalar(SmartTagSourceRules::nativePropertyTypeField()));

        if ($context === null) {
            // No supported property type yet (e.g. an early draft). Change nothing:
            // pruning here would silently discard the owner's earlier selections.
            return ManualSelectionResult::refused(SmartTagSelectionResult::REASON_NO_CONTEXT);
        }

        $answered = $this->nativeDeriver->answeredKeys($listing->type, $meta, $context);
        $projection = SmartTagSelectionPolicy::project($requestedKeys, $context, SmartTagTaxonomy::SURFACE_OWNER, $answered);

        $existing = SmartTagEvidence::query()
            ->where('listing_type', $listing->type->value)
            ->where('listing_id', $listing->id)
            ->where('source', SmartTagSource::ManualListingOwner->value)
            ->pluck('tag_key')
            ->all();

        $toAdd = array_values(array_diff($projection->accepted, $existing));
        $toRemove = array_values(array_diff($existing, $projection->accepted));

        $actorId = (int) $actor->getKey();

        $resolution = DB::transaction(function () use ($listing, $context, $toAdd, $toRemove, $projection, $answered, $actorId) {
            if ($toRemove !== []) {
                SmartTagEvidence::query()
                    ->where('listing_type', $listing->type->value)
                    ->where('listing_id', $listing->id)
                    ->where('source', SmartTagSource::ManualListingOwner->value)
                    ->whereIn('tag_key', $toRemove)
                    ->delete();
            }

            $now = now();
            $confidence = SmartTagSourceRules::confidence('manual', 80);

            foreach ($toAdd as $key) {
                SmartTagEvidence::query()->create([
                    'listing_type'   => $listing->type->value,
                    'listing_id'     => $listing->id,
                    'tag_key'        => $key,
                    'context'        => $context->value,
                    'source'         => SmartTagSource::ManualListingOwner->value,
                    'state'          => SmartTagState::Present->value,
                    'confidence'     => $confidence,
                    'source_field'   => null,
                    'rule_id'        => 'manual',
                    'set_by_user_id' => $actorId,
                    'tagger_version' => null,
                ]);

                $this->event($listing, $key, SmartTagManualEvent::ACTION_SELECTED, $actorId, $now);
            }

            foreach ($toRemove as $key) {
                $reason = $projection->rejected[$key] ?? null;
                $action = match (true) {
                    $reason === null                                                   => SmartTagManualEvent::ACTION_DESELECTED,
                    in_array($key, $answered, true)                                    => SmartTagManualEvent::ACTION_PRUNED_ANSWERED_BY_STRUCTURE,
                    default                                                            => SmartTagManualEvent::ACTION_PRUNED_NOT_APPLICABLE,
                };
                $this->event($listing, $key, $action, $actorId, $now);
            }

            return $this->projector->project($listing, $context);
        });

        return ManualSelectionResult::saved(
            selected: array_values($projection->accepted),
            added: $toAdd,
            removed: $toRemove,
            rejected: $projection->rejected,
            resolution: $resolution,
        );
    }

    private function event(SmartTagListingRef $listing, string $key, string $action, int $actorId, $at): void
    {
        SmartTagManualEvent::query()->create([
            'listing_type'  => $listing->type->value,
            'listing_id'    => $listing->id,
            'tag_key'       => $key,
            'action'        => $action,
            'actor_user_id' => $actorId,
            'actor_role'    => self::ACTOR_ROLE,
            'created_at'    => $at,
        ]);
    }
}
