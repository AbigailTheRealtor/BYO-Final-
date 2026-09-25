<?php

namespace App\Services\SmartTags\Seeker;

use App\Models\SmartTagSeekerPreference;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagDefinition;
use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Support\Facades\Log;

/**
 * THE read path for seeker Smart Tag preferences.
 *
 * Built so a future matcher can ask "what canonical Smart Tag keys has this
 * Buyer/Tenant selected?" without knowing the table, the form, or that criteria
 * records store property type as EAV meta. It returns KEYS — matching compares
 * keys against `smart_tag_assignments.tag_key`, and a label would not join.
 *
 * READ BY MATCHING THROUGH ONE METHOD. {@see matchingKeysFor()} is what the four
 * Stellar criteria loaders hand the matcher; BuyerMatchScorer scores the picks
 * inside its existing Amenities category (see SeekerSmartTagMatcher). They score,
 * they never select: no pick filters a result.
 */
class SmartTagSeekerPreferenceReader
{
    /**
     * Canonical keys this criteria record has selected, in taxonomy display
     * order so two callers never disagree about ordering.
     *
     * @return string[]
     */
    public function keysFor(object $subject): array
    {
        $type = SmartTagSeekerSubjectType::forModel($subject);
        $id   = (int) ($subject->id ?? 0);

        if ($type === null || $id <= 0) {
            return [];
        }

        return $this->keysForSubject($type, $id);
    }

    /** @return string[] */
    public function keysForSubject(SmartTagSeekerSubjectType $type, int $subjectId): array
    {
        $stored = SmartTagSeekerPreference::query()
            ->where('subject_type', $type->value)
            ->where('subject_id', $subjectId)
            ->pluck('tag_key')
            ->all();

        if ($stored === []) {
            return [];
        }

        $ordered = [];
        foreach (SmartTagTaxonomy::all() as $key => $_definition) {
            if (in_array($key, $stored, true)) {
                $ordered[] = $key;
            }
        }

        // A key that is no longer in the taxonomy (retired between write and
        // read) is dropped rather than returned: a matcher must never be handed
        // a key it cannot resolve. The row is left alone — removing stored data
        // on a read is not this class's business.
        return $ordered;
    }

    /**
     * The same selection, still current for the record's CURRENT context.
     *
     * A criteria record's property type can be edited after tags were picked, so
     * a stored key may no longer be applicable. Matching should use this, not
     * {@see keysFor()}: scoring a buyer against "loading dock" because they once
     * searched Commercial and then switched to Residential would be wrong.
     *
     * @return string[]
     */
    public function currentKeysFor(object $subject): array
    {
        $context = $this->contextFor($subject);

        if ($context === null) {
            return [];
        }

        $applicable = array_keys(
            SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_SEEKER)
        );

        return array_values(array_intersect($this->keysFor($subject), $applicable));
    }

    /**
     * The picks MATCHING may use for this record, right now — or none.
     *
     * Three rules, each fail-closed:
     *
     *   • The picker gate AND the separate matching gate must be ON
     *     ({@see SmartTagSeekerPreferenceGate::matchingEnabled()}). With the
     *     picker off a stored pick is one the customer can neither see nor edit;
     *     with matching off, picks are saved but not yet scored. Either way the
     *     answer is no picks, which is exactly the pre-feature score.
     *   • The record's context must also be activated for matching
     *     ({@see SmartTagSeekerPreferenceGate::matchingEnabledFor()}), one
     *     context at a time as its Bridge coverage is verified.
     *   • Every key is re-projected through SmartTagSelectionPolicy on
     *     SURFACE_SEEKER against the record's CURRENT context: the same
     *     intersection the write used, asked again at read time, so a tag that
     *     has since been retired, put under compliance review, made
     *     non-seeker-selectable or made inapplicable by a property-type edit
     *     stops contributing on the next search without anyone rewriting rows.
     *   • No context, no picks.
     *
     * NEVER THROWS. A search must not fail because a preference could not be
     * read; a fault is logged (class only) and reads as no picks, which is the
     * pre-feature score.
     *
     * @return list<string> canonical keys, in taxonomy display order
     */
    public function matchingKeysFor(object $subject): array
    {
        try {
            if (! SmartTagSeekerPreferenceGate::matchingEnabled()) {
                return [];
            }

            $context = $this->contextFor($subject);

            // Per-context activation: a context whose Bridge coverage has not been
            // verified scores no picks — the pre-feature score, never a penalty.
            if (! SmartTagSeekerPreferenceGate::matchingEnabledFor($context)) {
                return [];
            }

            return array_values(SmartTagSelectionPolicy::project(
                $this->keysFor($subject),
                $context,
                SmartTagTaxonomy::SURFACE_SEEKER,
            )->accepted);
        } catch (\Throwable $e) {
            Log::warning('smart_tag_seeker_preferences matching read failed', [
                'exception' => $e::class,
            ]);

            return [];
        }
    }

    /**
     * Selections as taxonomy definitions, for display.
     *
     * @return array<string, SmartTagDefinition>
     */
    public function definitionsFor(object $subject): array
    {
        $out = [];
        foreach ($this->keysFor($subject) as $key) {
            $definition = SmartTagTaxonomy::get($key);
            if ($definition !== null) {
                $out[$key] = $definition;
            }
        }

        return $out;
    }

    public function contextFor(object $subject): ?SmartTagContext
    {
        $type = SmartTagSeekerSubjectType::forModel($subject);

        if ($type === null) {
            return null;
        }

        $propertyType = $subject->get->property_type ?? null;

        return SmartTagContextResolver::forSeekerSubject(
            $type,
            is_string($propertyType) ? $propertyType : null,
        );
    }

    /**
     * The tags this criteria record may choose from, grouped by taxonomy
     * category for rendering. Categories preserve config order; tags preserve
     * display order. Empty categories are dropped.
     *
     * This is the ONLY source a picker may render from — there is no hard-coded
     * list in Blade or JS.
     *
     * @return array<string, array{label: string, tags: array<string, SmartTagDefinition>}>
     */
    public function selectableGroupedFor(object $subject): array
    {
        $context = $this->contextFor($subject);

        return $context === null ? [] : $this->selectableGroupedForContext($context);
    }

    /**
     * @return array<string, array{label: string, tags: array<string, SmartTagDefinition>}>
     */
    public function selectableGroupedForContext(SmartTagContext $context): array
    {
        $tags = SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_SEEKER);

        $grouped = [];
        foreach (SmartTagTaxonomy::categories() as $slug => $category) {
            $inCategory = array_filter(
                $tags,
                static fn (SmartTagDefinition $d): bool => $d->category === $slug
            );

            if ($inCategory !== []) {
                $grouped[$slug] = [
                    'label' => (string) ($category['label'] ?? $slug),
                    'tags'  => $inCategory,
                ];
            }
        }

        return $grouped;
    }
}
