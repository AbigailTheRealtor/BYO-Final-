<?php

namespace App\Services\SmartTags;

use App\Services\SmartTags\Derivation\NativeListingTagDeriver;
use App\Services\SmartTags\Derivation\TagEvidence;
use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\OwnerSmartTagPanel;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagDefinition;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * Builds the Seller/Landlord "Property Features" picker from the canonical
 * taxonomy and the listing's own structured answers.
 *
 * READ ONLY. Nothing here writes a row, and the panel it returns is display
 * state; {@see ManualSmartTagWriter} remains the only thing that persists an
 * owner's selection. Reached only through {@see SmartTagLifecycle}, like every
 * other Smart Tag entry point.
 *
 * THE OPTIONS ARE PROJECTED, NEVER LISTED. A duplicate list of "features a
 * seller can tick" is exactly the parallel vocabulary the governance forbids, so
 * the options are {@see SmartTagTaxonomy::forContext()} narrowed to the owner
 * surface, minus the keys the listing's own fields already answer. Adding a tag
 * to config/smart_tags.php puts it on this form; there is nothing else to edit.
 *
 * THE TWO SETS IT SUBTRACTS ARE NOT THE SAME SET, and conflating them would be a
 * bug in both directions:
 *
 *   • ANSWERED — an authoritative Yes/No field has spoken, either way. Pool = No
 *     answers `private_pool` just as firmly as Pool = Yes does, and neither may
 *     be contradicted by a tick. These are removed from the options, and the
 *     writer refuses them again on arrival.
 *   • DETECTED — a structured field already establishes the tag as PRESENT.
 *     These are shown, read-only, as "already included", because a feature the
 *     owner can see is on their listing needs no second checkbox and its absence
 *     from the picker would otherwise read as an omission.
 *
 * An answered-ABSENT tag is in the first set and not the second: "No pool" is not
 * a feature, and printing it under a heading of things the listing HAS would be
 * the plainest possible lie.
 */
class OwnerSmartTagPanelBuilder
{
    public function __construct(private readonly NativeListingTagDeriver $deriver)
    {
    }

    /**
     * @param string[] $selected the keys the wizard currently holds
     */
    public function build(
        SmartTagListingType $type,
        ?string $propertyType,
        ?int $listingId,
        array $selected,
    ): OwnerSmartTagPanel {
        if (! $type->isNative()) {
            return OwnerSmartTagPanel::unavailable(OwnerSmartTagPanel::REASON_UNSUPPORTED_LISTING);
        }

        // The FORM's property type, not the stored one: the owner may have just
        // changed it, and offering commercial tags on a listing they have already
        // switched to Residential would be a picker that argues with the form it
        // sits in. The stored type governs the WRITE, which is the half that
        // matters, and the writer prunes anything this disagreed with.
        $context = SmartTagContextResolver::forListingType($type, $propertyType);

        if ($context === null) {
            return OwnerSmartTagPanel::unavailable(OwnerSmartTagPanel::REASON_NO_CONTEXT);
        }

        $structured = $this->structuredEvidence($type, $listingId, $context);

        $answered = [];
        $detected = [];
        foreach ($structured as $evidence) {
            if ($evidence->authoritative) {
                $answered[] = $evidence->tagKey;
            }
            if ($evidence->state === SmartTagState::Present) {
                $detected[] = $evidence->tagKey;
            }
        }

        $definitions = SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_OWNER);

        // The same projection the write performs, so what is offered and what
        // would be accepted cannot drift apart on screen.
        $accepted = SmartTagSelectionPolicy::project(
            $selected,
            $context,
            SmartTagTaxonomy::SURFACE_OWNER,
            $answered,
        )->accepted;

        $groups = $this->groups($definitions, $answered, $accepted);

        if ($groups === []) {
            return OwnerSmartTagPanel::unavailable(OwnerSmartTagPanel::REASON_NO_OPTIONS, $context);
        }

        return OwnerSmartTagPanel::of($context, $groups, $accepted, $this->detectedRows($detected, $context));
    }

    /**
     * What this listing's own structured fields say right now.
     *
     * An unsaved listing has no stored answers, so it has none: the picker then
     * offers every owner-selectable tag for the chosen context and the writer
     * prunes the ones the finished form turns out to answer, recording
     * `pruned_answered_by_property_details` for each. That is the honest order —
     * the form's answers are not knowable before the form is saved.
     *
     * @return TagEvidence[]
     */
    private function structuredEvidence(SmartTagListingType $type, ?int $listingId, SmartTagContext $context): array
    {
        if ($listingId === null || $listingId <= 0) {
            return [];
        }

        $class = $type->modelClass();
        $model = $class::query()->find($listingId);

        if ($model === null) {
            return [];
        }

        $meta = NativeMetaValueReader::fromMetaRows($model->meta()->get(['meta_key', 'meta_value']));

        // Deliberately the context the PANEL is in, not the stored one: a rule
        // that only fires for commercial must not contribute "already included"
        // rows to a form the owner has switched to residential.
        return $this->deriver->derive($type, $meta, $context);
    }

    /**
     * @param array<string, SmartTagDefinition> $definitions in display order
     * @param string[]                          $answered
     * @param string[]                          $accepted
     * @return array<int, array{key: string, label: string, options: array<int, array{key: string, label: string, description: string, selected: bool}>}>
     */
    private function groups(array $definitions, array $answered, array $accepted): array
    {
        $categories = SmartTagTaxonomy::categories();
        $buckets = [];

        foreach ($definitions as $key => $definition) {
            if (in_array($key, $answered, true)) {
                continue;
            }

            $buckets[$definition->category][] = [
                'key'         => $key,
                'label'       => $definition->label,
                'description' => $definition->description,
                'selected'    => in_array($key, $accepted, true),
            ];
        }

        $groups = [];
        foreach ($categories as $category => $meta) {
            if (! isset($buckets[$category])) {
                continue;
            }

            $groups[] = [
                'key'     => (string) $category,
                'label'   => (string) ($meta['label'] ?? $category),
                'options' => $buckets[$category],
            ];
            unset($buckets[$category]);
        }

        // A category the taxonomy declares on a tag but not in its category list
        // is a config error the validator already reports; it is still rendered
        // rather than dropped, so the tag cannot vanish silently.
        foreach ($buckets as $category => $options) {
            $groups[] = ['key' => (string) $category, 'label' => (string) $category, 'options' => $options];
        }

        return $groups;
    }

    /**
     * @param string[] $detected
     * @return array<int, array{key: string, label: string, category: string}>
     */
    private function detectedRows(array $detected, SmartTagContext $context): array
    {
        $categories = SmartTagTaxonomy::categories();
        $rows = [];

        foreach ($detected as $key) {
            $definition = SmartTagTaxonomy::get($key);

            // Only tags the taxonomy would let this listing publish at all. A
            // derived key that is retired, out of context or pending compliance
            // review is not shown to the owner as something their listing says.
            if ($definition === null || ! $definition->isActive() || ! $definition->appliesTo($context) || $definition->isPendingReview()) {
                continue;
            }

            $rows[] = [
                'key'      => $key,
                'label'    => $definition->label,
                'category' => (string) ($categories[$definition->category]['label'] ?? $definition->category),
                'order'    => $definition->displayOrder,
            ];
        }

        usort($rows, static fn (array $a, array $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        return array_map(
            static fn (array $row) => ['key' => $row['key'], 'label' => $row['label'], 'category' => $row['category']],
            $rows,
        );
    }
}
