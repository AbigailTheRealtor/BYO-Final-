<?php

namespace App\Services\SmartTags;

use App\Models\BridgeProperty;
use App\Models\SmartTagDerivationState;
use App\Services\Listing\ListingWorkflowResolver;
use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\BridgeStructuredTagDeriver;
use App\Services\SmartTags\Derivation\ListingDescriptionTagParser;
use App\Services\SmartTags\Derivation\NativeListingDescriptionReader;
use App\Services\SmartTags\Derivation\NativeListingTagDeriver;
use App\Support\Listing\ListingFlag;
use App\Support\Listing\ListingWorkflow;
use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Derives and stores one listing's Smart Tags from its legitimate sources.
 *
 * INERT IN PHASE 1: nothing in the application calls this class. No import
 * hook, no sync hook, no save hook, no command. It exists so the derivation
 * contract is built and tested before anything is wired to it.
 *
 * Reprocessing is controlled by smart_tag_derivation_states: a source is
 * re-derived only when its own hash, the tagger version or the listing's
 * context changed. Unchanged prose is never reparsed.
 *
 * MLS remarks are never parsed here while MLS_REMARKS_PROCESSING_APPROVED is
 * false — and the evidence writer would refuse them anyway.
 */
class SmartTagDerivationService
{
    /** Licensing/use approval for parsing Bridge PublicRemarks. Not granted. */
    public const MLS_REMARKS_PROCESSING_APPROVED = false;

    public function __construct(
        private readonly BridgeStructuredTagDeriver $bridgeDeriver,
        private readonly NativeListingTagDeriver $nativeDeriver,
        private readonly NativeListingDescriptionReader $descriptionReader,
        private readonly ListingDescriptionTagParser $parser,
        private readonly SmartTagEvidenceWriter $writer,
        private readonly SmartTagAssignmentProjector $projector,
        private readonly ListingWorkflowResolver $workflows,
    ) {
    }

    public function deriveBridge(BridgeProperty $property): DerivationOutcome
    {
        $listing = SmartTagListingRef::fromModel($property);
        $record = BridgeRecordAccessor::fromModel($property);
        $context = $this->bridgeDeriver->contextFor($record);

        if ($context === null) {
            return DerivationOutcome::skipped(DerivationOutcome::NO_CONTEXT, null, $this->projector->project($listing, null));
        }

        $version = SmartTagVersion::taggerVersion();
        $structuredHash = SmartTagVersion::structuredInputsHash($this->bridgeDeriver->structuredInputs($record));
        $state = $this->state($listing);

        $structuredStale = $this->isStale($state, $version, $context, 'structured_inputs_hash', $structuredHash);

        return DB::transaction(function () use ($listing, $record, $context, $version, $structuredHash, $structuredStale) {
            if ($structuredStale) {
                $this->writer->replaceDerived(
                    $listing, $context, SmartTagSource::StructuredMls,
                    array_values($this->bridgeDeriver->derive($record, $context)),
                    $version, project: false,
                );
            }

            $this->saveState($listing, $context, $version, [
                'structured_inputs_hash' => $structuredHash,
                // Remarks are not processed, so no remarks hash is recorded as seen.
                'mls_remarks_hash'       => null,
            ]);

            $resolution = $structuredStale ? $this->projector->project($listing, $context) : null;

            return new DerivationOutcome(true, null, $context, $structuredStale, false,
                [DerivationOutcome::MLS_REMARKS_NOT_APPROVED], $resolution);
        });
    }

    public function deriveNative(Model $listingModel): DerivationOutcome
    {
        $listing = SmartTagListingRef::fromModel($listingModel);
        if (! $listing->type->isNative()) {
            throw new InvalidArgumentException('deriveNative() only accepts native Offer Listings.');
        }

        if (! $this->workflows->matches($listingModel, ListingWorkflow::OFFER_LISTING)) {
            return DerivationOutcome::skipped(DerivationOutcome::NOT_AN_OFFER_LISTING);
        }
        if (ListingFlag::isTrue($listingModel->getAttribute('is_archived'))) {
            return DerivationOutcome::skipped(DerivationOutcome::ARCHIVED);
        }

        $meta = NativeMetaValueReader::fromMetaRows($listingModel->meta()->get(['meta_key', 'meta_value']));
        $context = $this->nativeDeriver->contextFor($listing->type, $meta);

        if ($context === null) {
            return DerivationOutcome::skipped(DerivationOutcome::NO_CONTEXT, null, $this->projector->project($listing, null));
        }

        $version = SmartTagVersion::taggerVersion();
        $structuredHash = SmartTagVersion::structuredInputsHash($this->nativeDeriver->structuredInputs($listing->type, $meta));
        $described = $this->descriptionReader->describe($listing->type, $meta);
        $description = $described->text;
        $descriptionHash = SmartTagVersion::descriptionHash($description);
        $state = $this->state($listing);

        $structuredStale = $this->isStale($state, $version, $context, 'structured_inputs_hash', $structuredHash);
        $descriptionStale = $this->isStale($state, $version, $context, 'native_description_hash', $descriptionHash);

        return DB::transaction(function () use ($listing, $meta, $context, $version, $structuredHash, $structuredStale, $description, $descriptionHash, $descriptionStale, $described) {
            if ($structuredStale) {
                $this->writer->replaceDerived(
                    $listing, $context, SmartTagSource::StructuredNativeListing,
                    array_values($this->nativeDeriver->derive($listing->type, $meta, $context)),
                    $version, project: false,
                );
            }

            if ($descriptionStale) {
                $evidence = $descriptionHash === null
                    ? []
                    : array_values($this->parser->parse($description, $context, SmartTagSource::NativeListingDescription));

                $this->writer->replaceDerived(
                    $listing, $context, SmartTagSource::NativeListingDescription,
                    $evidence, $version, project: false,
                );
            }

            $this->saveState($listing, $context, $version, [
                'structured_inputs_hash'  => $structuredHash,
                'native_description_hash' => $descriptionHash,
            ]);

            $resolution = ($structuredStale || $descriptionStale) ? $this->projector->project($listing, $context) : null;

            $notes = $described->suppressedByPolicy ? [DerivationOutcome::DESCRIPTION_SUPPRESSED] : [];

            return new DerivationOutcome(true, null, $context, $structuredStale, $descriptionStale && $descriptionHash !== null, $notes, $resolution);
        });
    }

    private function state(SmartTagListingRef $listing): ?SmartTagDerivationState
    {
        return SmartTagDerivationState::query()
            ->where('listing_type', $listing->type->value)
            ->where('listing_id', $listing->id)
            ->first();
    }

    private function isStale(?SmartTagDerivationState $state, string $version, SmartTagContext $context, string $hashColumn, ?string $hash): bool
    {
        return $state === null
            || $state->tagger_version !== $version
            || $state->context !== $context->value
            || $state->getAttribute($hashColumn) !== $hash;
    }

    /**
     * @param array<string, string|null> $hashes
     */
    private function saveState(SmartTagListingRef $listing, SmartTagContext $context, string $version, array $hashes): void
    {
        SmartTagDerivationState::query()->updateOrCreate(
            ['listing_type' => $listing->type->value, 'listing_id' => $listing->id],
            array_merge($hashes, [
                'context'        => $context->value,
                'tagger_version' => $version,
                'derived_at'     => now(),
            ]),
        );
    }
}
