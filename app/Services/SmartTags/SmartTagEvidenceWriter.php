<?php

namespace App\Services\SmartTags;

use App\Models\SmartTagEvidence;
use App\Services\SmartTags\Derivation\TagEvidence;
use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Support\Facades\DB;

/**
 * Persists DERIVED evidence (structured and description sources) for one listing.
 *
 * Replaces exactly one source's rows for one listing, so re-deriving structured
 * fields can never erase an owner's manual selections or a description's
 * evidence. Manual selections go through ManualSmartTagWriter instead.
 *
 * MLS REMARKS ARE REFUSED. Bridge PublicRemarks is licence-RESTRICTED
 * (MlsFieldCatalog), and processing it for stored or displayed tags has not been
 * approved. The constant below is the gate, deliberately in code rather than an
 * environment flag: turning it on is a reviewed change, not a config edit.
 *
 * Every evidence item is re-validated against the closed taxonomy before it is
 * written — an undeclared key, a retired key, a wrong-context key, an "absent"
 * from a source that cannot assert absence, or a prohibited concept is rejected.
 */
class SmartTagEvidenceWriter
{
    /** Licensing/use approval for storing remarks-derived tags. Not granted. */
    public const MLS_REMARKS_PERSISTENCE_APPROVED = false;

    public const REJECT_SOURCE_MISMATCH = 'evidence_source_mismatch';
    public const REJECT_CONTEXT_MISMATCH = 'evidence_context_mismatch';
    public const REJECT_UNKNOWN_TAG = 'unknown_tag';
    public const REJECT_RETIRED_TAG = 'retired_tag';
    public const REJECT_NOT_APPLICABLE = 'not_applicable_to_context';
    public const REJECT_ABSENT_NOT_ALLOWED = 'absent_not_allowed_for_source_or_tag';
    public const REJECT_PROHIBITED = 'prohibited_concept';
    public const REJECT_DUPLICATE = 'duplicate_tag_in_batch';

    public function __construct(private readonly SmartTagAssignmentProjector $projector)
    {
    }

    /**
     * @param TagEvidence[] $evidence
     */
    public function replaceDerived(
        SmartTagListingRef $listing,
        SmartTagContext $context,
        SmartTagSource $source,
        array $evidence,
        string $taggerVersion,
        bool $project = true,
    ): EvidenceWriteResult {
        $this->assertWritable($listing, $context, $source);

        $accepted = [];
        $rejected = [];

        foreach ($evidence as $item) {
            $reason = $this->rejectionReason($item, $context, $source);
            if ($reason === null && isset($accepted[$item->tagKey])) {
                $reason = self::REJECT_DUPLICATE;
            }
            if ($reason !== null) {
                $rejected[$item->tagKey] = $reason;
                continue;
            }
            $accepted[$item->tagKey] = $item;
        }

        $resolution = DB::transaction(function () use ($listing, $context, $source, $accepted, $taggerVersion, $project) {
            SmartTagEvidence::query()
                ->where('listing_type', $listing->type->value)
                ->where('listing_id', $listing->id)
                ->where('source', $source->value)
                ->delete();

            foreach ($accepted as $item) {
                SmartTagEvidence::query()->create([
                    'listing_type'   => $listing->type->value,
                    'listing_id'     => $listing->id,
                    'tag_key'        => $item->tagKey,
                    'context'        => $context->value,
                    'source'         => $source->value,
                    'state'          => $item->state->value,
                    'confidence'     => max(0, min(100, $item->confidence)),
                    'source_field'   => $item->sourceField,
                    'rule_id'        => $item->ruleId,
                    'set_by_user_id' => null,
                    'tagger_version' => $taggerVersion,
                ]);
            }

            return $project ? $this->projector->project($listing, $context) : null;
        });

        ksort($rejected);

        return new EvidenceWriteResult(array_keys($accepted), $rejected, $resolution);
    }

    private function assertWritable(SmartTagListingRef $listing, SmartTagContext $context, SmartTagSource $source): void
    {
        if (! $source->isDerived()) {
            throw new SmartTagWriteRefused('manual_source_requires_manual_writer',
                'Manual owner selections are written only by ManualSmartTagWriter.');
        }

        if ($source === SmartTagSource::MlsRemarks && ! self::MLS_REMARKS_PERSISTENCE_APPROVED) {
            throw new SmartTagWriteRefused('mls_remarks_not_approved',
                'Storing Smart Tags derived from MLS PublicRemarks has not been approved.');
        }

        if (! $listing->type->allowsSource($source)) {
            throw new SmartTagWriteRefused('source_not_allowed_for_listing_type',
                "{$source->value} evidence cannot be attached to a {$listing->type->value} listing.");
        }

        if (! in_array($context, $listing->type->possibleContexts(), true)) {
            throw new SmartTagWriteRefused('context_not_possible_for_listing_type',
                "A {$listing->type->value} listing cannot be in context {$context->value}.");
        }
    }

    private function rejectionReason(TagEvidence $item, SmartTagContext $context, SmartTagSource $source): ?string
    {
        if ($item->source !== $source) {
            return self::REJECT_SOURCE_MISMATCH;
        }
        if ($item->context !== $context) {
            return self::REJECT_CONTEXT_MISMATCH;
        }
        if (! SmartTagComplianceGuard::keyIsClean($item->tagKey)) {
            return self::REJECT_PROHIBITED;
        }

        $definition = SmartTagTaxonomy::get($item->tagKey);
        if ($definition === null) {
            return self::REJECT_UNKNOWN_TAG;
        }
        if (! $definition->isActive()) {
            return self::REJECT_RETIRED_TAG;
        }
        if (! $definition->appliesTo($context)) {
            return self::REJECT_NOT_APPLICABLE;
        }
        if ($item->state === SmartTagState::Absent && (! $source->canAssertAbsent() || ! $definition->negatable)) {
            return self::REJECT_ABSENT_NOT_ALLOWED;
        }

        return null;
    }
}
