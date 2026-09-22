<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * One row's membership of one chain. A row may hold several (a declared, evidenced co-brand).
 *
 * `role` is the FORMAT's role. A department membership is credible identity but never a
 * confirmed storefront: {@see storefrontStatus()} reports it as `storefront_unconfirmed`, and
 * nothing in this layer upgrades it. Whether a site is a storefront is decided over all of its
 * members by {@see ChainSiteClassifier}.
 */
final class ChainMembership
{
    public const METHOD_OWN_WIKIDATA = 'own_wikidata';
    public const METHOD_BRAND_ALIAS = 'brand_alias';
    public const METHOD_NAME_ALIAS = 'name_alias';
    public const METHOD_CO_BRAND_COMPOUND = 'co_brand_compound';
    public const METHOD_DEPARTMENT_WIKIDATA = 'department_wikidata';

    /**
     * @param list<string> $coBrandWith other chains this same row is a member of (sorted)
     */
    public function __construct(
        public readonly string $brandKey,
        public readonly string $role,
        public readonly string $formatKey,
        public readonly string $matchMethod,
        public readonly array $coBrandWith = [],
    ) {
        if (! in_array($role, ChainRole::all(), true)) {
            throw new \InvalidArgumentException("unknown chain role: {$role}");
        }
    }

    public function storefrontStatus(): string
    {
        return ChainRole::storefrontStatus($this->role);
    }

    public function isStorefront(): bool
    {
        return $this->role === ChainRole::STOREFRONT;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'brand_key' => $this->brandKey,
            'role' => $this->role,
            'format_key' => $this->formatKey,
            'match_method' => $this->matchMethod,
            'storefront_status' => $this->storefrontStatus(),
            'co_brand_with' => $this->coBrandWith,
        ];
    }
}
