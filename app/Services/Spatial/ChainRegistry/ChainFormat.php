<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * One row-level format of a chain (Walmart `supercenter`, 7-Eleven `fuel`, …). Immutable, and
 * only ever built by {@see ChainRegistry} after validation.
 *
 * A format with no name patterns is its categories' DEFAULT: the registry guarantees exactly one
 * per allowed category, and that its role equals the category's own role — so a category alone
 * can never upgrade a department into a storefront. Only a format with an explicit name pattern
 * may carry a different role (Walmart "Neighborhood Market" in grocery_store).
 */
final class ChainFormat
{
    /**
     * @param list<string> $categories
     * @param list<string> $namePatterns
     */
    public function __construct(
        public readonly string $key,
        public readonly array $categories,
        public readonly array $namePatterns,
        public readonly string $role,
        public readonly bool $userVisible,
        public readonly ?string $label,
    ) {
    }

    public function isDefault(): bool
    {
        return $this->namePatterns === [];
    }

    public function appliesTo(string $categoryKey): bool
    {
        return in_array($categoryKey, $this->categories, true);
    }
}
