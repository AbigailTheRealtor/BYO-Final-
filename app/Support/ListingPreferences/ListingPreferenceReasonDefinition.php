<?php

namespace App\Support\ListingPreferences;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagDefinition;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * One reason chip, resolved from config.
 *
 * A reason NEVER carries its own copy of a Smart Tag's properties — label,
 * contexts and selectability are asked of the tag every time. Two independent
 * records of the same fact is how they come to disagree, and here a stale copy
 * would mean a tag retired for Fair Housing reasons kept being offered as a
 * chip.
 */
final class ListingPreferenceReasonDefinition
{
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_RETIRED = 'retired';

    /**
     * @param list<string> $states  ListingPreferenceState values this reason may be offered for
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ListingPreferenceReasonDimension $dimension,
        public readonly ?string $smartTagKey,
        public readonly ?string $criteriaDimension,
        public readonly array $states,
        public readonly string $status,
        public readonly int $displayOrder,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** May this reason be offered alongside the given state? */
    public function appliesToState(ListingPreferenceState $state): bool
    {
        return in_array($state->value, $this->states, true);
    }

    /**
     * May this reason ever contribute to a learned signal?
     *
     * The dimension decides. A retired reason never does, whatever its
     * dimension, because it is no longer offered and its stored rows describe a
     * question customers were asked differently.
     */
    public function isLearnable(): bool
    {
        return $this->isActive() && $this->dimension->isLearnable();
    }

    /**
     * The canonical Smart Tag behind this reason, or null.
     *
     * Resolved live from the taxonomy, so a tag that is retired or loses
     * seeker-selectability stops backing its reason immediately.
     */
    public function smartTag(): ?SmartTagDefinition
    {
        return $this->smartTagKey === null ? null : SmartTagTaxonomy::get($this->smartTagKey);
    }

    /**
     * Whether this reason is usable at all right now.
     *
     * For a tag-backed reason that means the tag is still canonical, active,
     * seeker-selectable and past compliance review — the single gate that keeps
     * accessible_features and playground out of the chip list without this file
     * holding a second exclusion list.
     */
    public function isOfferable(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if (! $this->dimension->requiresSmartTag()) {
            return true;
        }

        $tag = $this->smartTag();

        return $tag !== null && $tag->isSeekerSelectable();
    }

    /**
     * The listing contexts this reason applies to.
     *
     * A tag-backed reason inherits its tag's contexts rather than restating
     * them; everything else applies wherever a customer can express a
     * preference. Deriving rather than duplicating is why a context added to a
     * tag cannot drift out of sync with its chip.
     *
     * @return list<SmartTagContext>
     */
    public function contexts(): array
    {
        $tag = $this->smartTag();

        if ($tag === null) {
            return SmartTagContext::cases();
        }

        return array_values(array_filter(
            SmartTagContext::cases(),
            static fn (SmartTagContext $c): bool => $tag->appliesTo($c),
        ));
    }

    public function appliesToContext(SmartTagContext $context): bool
    {
        $tag = $this->smartTag();

        return $tag === null || $tag->appliesTo($context);
    }
}
