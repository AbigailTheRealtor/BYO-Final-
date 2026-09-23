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
 *
 * `hostChains` (chain-registry-v2, decision 7) names chains whose brand identity on a row does NOT
 * conflict with this chain when the row names this chain AND this format covers its category —
 * CVS `store_in_target` with host `target`. It is an exception to one conflict rule for one
 * format, never a general truce between two chains. `hostCategories` narrows it further: the
 * categories of this format in which the host's identity is excused (all of them unless the
 * registry declares a subset). A format may therefore be SELECTED by its name pattern in a
 * category where the host exception does not apply.
 */
final class ChainFormat
{
    /**
     * @param list<string> $categories
     * @param list<string> $namePatterns
     * @param list<string> $hostChains     chain keys; empty for almost every format
     * @param list<string> $hostCategories where the host exception applies; empty without hosts
     */
    public function __construct(
        public readonly string $key,
        public readonly array $categories,
        public readonly array $namePatterns,
        public readonly string $role,
        public readonly bool $userVisible,
        public readonly ?string $label,
        public readonly array $hostChains = [],
        public readonly array $hostCategories = [],
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

    /** Whether a declared host chain's identity is excused on a row of this category. */
    public function hostsIn(string $categoryKey): bool
    {
        return $this->hostChains !== [] && in_array($categoryKey, $this->hostCategories, true);
    }
}
