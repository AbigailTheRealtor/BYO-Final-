<?php

namespace App\Services\SmartTags\Seeker;

use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * How one listing compares with a seeker's selected Smart Tags.
 *
 * Carries canonical KEYS for the scorer and exposes only human LABELS for
 * presentation — no key, id or weight is meant to reach a page.
 */
final class SeekerSmartTagMatch
{
    /**
     * @param list<string> $selectedKeys
     * @param list<string> $matchedKeys   subset of $selectedKeys, same order
     * @param bool         $hasListingData whether the listing has ANY resolved present tag
     */
    public function __construct(
        public readonly array $selectedKeys,
        public readonly array $matchedKeys,
        public readonly bool $hasListingData,
    ) {
    }

    public function selectedCount(): int
    {
        return count($this->selectedKeys);
    }

    public function matchedCount(): int
    {
        return count($this->matchedKeys);
    }

    /** Fraction of the selection this listing has, 0.0–1.0. */
    public function share(): float
    {
        $selected = $this->selectedCount();

        return $selected === 0 ? 0.0 : $this->matchedCount() / $selected;
    }

    /** @return list<string> */
    public function matchedLabels(): array
    {
        return self::labels($this->matchedKeys);
    }

    /** @return list<string> */
    public function unmatchedLabels(): array
    {
        return self::labels(array_values(array_diff($this->selectedKeys, $this->matchedKeys)));
    }

    /**
     * @param  list<string> $keys
     * @return list<string>
     */
    private static function labels(array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $definition = SmartTagTaxonomy::get($key);

            if ($definition !== null && trim($definition->label) !== '') {
                $out[] = $definition->label;
            }
        }

        return $out;
    }
}
