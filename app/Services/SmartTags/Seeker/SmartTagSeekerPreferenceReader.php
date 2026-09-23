<?php

namespace App\Services\SmartTags\Seeker;

use App\Models\SmartTagSeekerPreference;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagDefinition;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * THE read path for seeker Smart Tag preferences.
 *
 * Built so a future matcher can ask "what canonical Smart Tag keys has this
 * Buyer/Tenant selected?" without knowing the table, the form, or that criteria
 * records store property type as EAV meta. It returns KEYS — matching compares
 * keys against `smart_tag_assignments.tag_key`, and a label would not join.
 *
 * NOT WIRED INTO SCORING. This phase supplies the preferences; consuming them is
 * the next one, and doing both at once would put an unproven signal into a
 * scorer whose weights must sum to 100.
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
