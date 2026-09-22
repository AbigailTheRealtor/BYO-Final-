<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * One validated chain entry. Immutable; built only by {@see ChainRegistry}. Every list is sorted
 * and every map key-sorted at construction, so nothing downstream can depend on the order the
 * config file happened to be written in.
 */
final class ChainDefinition
{
    /**
     * @param list<array{value: string, match: string}> $aliases        names.primary, normalised
     * @param list<string>                              $brandAliases   brand.names.primary, normalised, exact only
     * @param list<string>                              $ownWikidataIds positive evidence only, never required
     * @param list<string>                              $departmentWikidataIds identity that can only ever be a department
     * @param list<string>                              $exclusionWikidataIds  chain-local, adds to the global list
     * @param array<string, string>                     $exclusionNamePatterns reason => regex, chain-local
     * @param list<string>                              $fuelBrands     expected co-located fuel brand keys (diagnostic)
     * @param array<string, string>                     $allowedCategories category_key => role
     * @param list<string>                              $excludedCategories chain-local; the global list also applies
     * @param array<string, ChainFormat>                $formats
     * @param array{store_with_fuel: bool, fuel_only: bool}|null $fuelSites
     * @param array<string, list<string>>               $coBrands       partner brand_key => compound names
     */
    public function __construct(
        public readonly string $key,
        public readonly string $displayName,
        public readonly array $aliases,
        public readonly array $brandAliases,
        public readonly array $ownWikidataIds,
        public readonly array $departmentWikidataIds,
        public readonly array $exclusionWikidataIds,
        public readonly array $exclusionNamePatterns,
        public readonly array $fuelBrands,
        public readonly array $allowedCategories,
        public readonly array $excludedCategories,
        public readonly array $formats,
        public readonly string $defaultFormat,
        public readonly ?array $fuelSites,
        public readonly array $coBrands,
        public readonly string $validationStatus,
    ) {
    }

    public function format(string $formatKey): ChainFormat
    {
        if (! isset($this->formats[$formatKey])) {
            throw new \OutOfBoundsException("chain {$this->key} has no format {$formatKey}");
        }

        return $this->formats[$formatKey];
    }

    public function hasOwnWikidata(string $qid): bool
    {
        return in_array($qid, $this->ownWikidataIds, true);
    }

    public function hasBrandAlias(string $normalizedBrand): bool
    {
        return in_array($normalizedBrand, $this->brandAliases, true);
    }

    public function isCoBrandPartner(string $brandKey): bool
    {
        return isset($this->coBrands[$brandKey]);
    }
}
