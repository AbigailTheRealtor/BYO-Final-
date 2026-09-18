<?php

namespace App\Support\SmartTags;

/**
 * Everything the Seller/Landlord "Property Features" picker renders, and nothing
 * it has to work out for itself.
 *
 * A VIEW MODEL, NOT A WRITE BOUNDARY. What this object offers is already the
 * result of {@see SmartTagSelectionPolicy}, so the checkboxes a listing owner
 * sees are exactly the keys their save would accept — but the panel is not what
 * makes that true. {@see \App\Services\SmartTags\ManualSmartTagWriter} projects
 * the submitted keys again, against the STORED property type, and a key that
 * survives here and not there is refused there. Hiding a choice is a courtesy;
 * the server-side projection is the rule.
 *
 * Immutable, and carries no listing prose: tag keys, taxonomy labels and
 * taxonomy descriptions only.
 */
final class OwnerSmartTagPanel
{
    /** The activation gate is closed. Nothing renders, and nothing would be written. */
    public const REASON_DISABLED = 'feature_disabled';

    /** Smart Tags do not attach to this kind of listing at all. */
    public const REASON_UNSUPPORTED_LISTING = 'unsupported_listing_type';

    /**
     * The listing has no property type yet, or one that resolves to none of the
     * seven contexts. Fail-closed: no context, no options. The wizard asks for a
     * property type on an earlier tab, so this is the ordinary state of a brand
     * new listing rather than an error.
     */
    public const REASON_NO_CONTEXT = 'no_property_type_selected';

    /** A valid context whose owner-selectable set is empty. */
    public const REASON_NO_OPTIONS = 'no_options_for_context';

    /** Something threw. The panel is withheld rather than half-rendered. */
    public const REASON_ERROR = 'error';

    /**
     * @param array<int, array{key: string, label: string, options: array<int, array{key: string, label: string, description: string, selected: bool}>}> $groups
     * @param string[]                                              $selected canonical keys, already projected
     * @param array<int, array{key: string, label: string, category: string}> $detected tags the listing's own fields already establish
     */
    private function __construct(
        public readonly bool $available,
        public readonly ?string $unavailableReason,
        public readonly ?SmartTagContext $context,
        public readonly array $groups,
        public readonly array $selected,
        public readonly array $detected,
    ) {
    }

    public static function unavailable(string $reason, ?SmartTagContext $context = null): self
    {
        return new self(false, $reason, $context, [], [], []);
    }

    /**
     * @param array<int, array{key: string, label: string, options: array<int, array{key: string, label: string, description: string, selected: bool}>}> $groups
     * @param string[] $selected
     * @param array<int, array{key: string, label: string, category: string}> $detected
     */
    public static function of(SmartTagContext $context, array $groups, array $selected, array $detected): self
    {
        return new self(true, null, $context, $groups, $selected, $detected);
    }

    public function optionCount(): int
    {
        $count = 0;
        foreach ($this->groups as $group) {
            $count += count($group['options']);
        }

        return $count;
    }

    public function selectedCount(): int
    {
        return count($this->selected);
    }

    public function hasDetected(): bool
    {
        return $this->detected !== [];
    }

    public function isSelected(string $key): bool
    {
        return in_array($key, $this->selected, true);
    }
}
