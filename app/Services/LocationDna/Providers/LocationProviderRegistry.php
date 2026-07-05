<?php

namespace App\Services\LocationDna\Providers;

/**
 * LocationProviderRegistry — declarative resolution of which providers serve a
 * canonical category, in what role, for a given region.
 *
 * Replaces the single-provider "if google … else stub" binding with a
 * config-driven registry so providers can be added / replaced / combined
 * without touching any intelligence engine (docs/location-provider-capability-map-proposal.md §3).
 *
 * STAGE B: this class is PURE and UNWIRED. It reads a plain config array
 * (injected, not fetched from the container) and returns provider *descriptors*.
 * It deliberately does NOT instantiate adapters — live instantiation and the
 * AppServiceProvider binding swap belong to a later stage. Nothing in the
 * runtime path consumes this yet.
 */
class LocationProviderRegistry
{
    public const ROLE_BASE     = 'base';
    public const ROLE_OVERLAY  = 'overlay';
    public const ROLE_FALLBACK = 'fallback';

    /** Fallback capability key inherited by any `poi.*` category with no explicit entry. */
    private const POI_DEFAULT_KEY = 'poi.default';

    /**
     * @param  array  $config  The `config/location_providers.php` array
     *                         ({ providers, capabilities, regional_overrides }).
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Resolve the ordered, ENABLED provider bindings for a canonical category.
     *
     * Resolution order:
     *   1. regional_overrides[$canonicalCategory][$region]  (exact region wins)
     *   2. capabilities[$canonicalCategory]                 (exact category)
     *   3. capabilities['poi.default']                      (only for `poi.*` categories)
     *
     * Disabled providers, and providers absent from the `providers` block, are
     * skipped — so a not-yet-implemented adapter class is never referenced.
     *
     * @return array<int, array{provider:string, role:string, descriptor:array}>
     *         Preserves the configured order. Empty if nothing is enabled/mapped.
     */
    public function resolve(string $canonicalCategory, string $region = '*'): array
    {
        $bindings = $this->rawBindingsFor($canonicalCategory, $region);

        $resolved = [];
        foreach ($bindings as $binding) {
            $providerId = $binding['provider'] ?? null;
            $role       = $binding['role'] ?? self::ROLE_BASE;

            if ($providerId === null) {
                continue;
            }

            $descriptor = $this->config['providers'][$providerId] ?? null;
            if ($descriptor === null || ($descriptor['enabled'] ?? false) !== true) {
                continue; // unknown or disabled provider — never instantiated/referenced
            }

            $resolved[] = [
                'provider'   => $providerId,
                'role'       => $role,
                'descriptor' => $descriptor,
            ];
        }

        return $resolved;
    }

    /**
     * The single provider that should supply the primary value for a category.
     *
     * The first binding explicitly marked `base` wins; if the configured base is
     * disabled (and thus filtered out), the highest-priority remaining enabled
     * binding is promoted to effective base (proposal §3). Null if none enabled.
     *
     * @return array{provider:string, role:string, descriptor:array}|null
     */
    public function effectiveBase(string $canonicalCategory, string $region = '*'): ?array
    {
        $resolved = $this->resolve($canonicalCategory, $region);
        if ($resolved === []) {
            return null;
        }

        foreach ($resolved as $binding) {
            if ($binding['role'] === self::ROLE_BASE) {
                return $binding;
            }
        }

        return $resolved[0]; // promote highest-priority survivor
    }

    /** Adapter FQCNs for the enabled bindings, in role order (for a later wiring stage). */
    public function adapterClassesFor(string $canonicalCategory, string $region = '*'): array
    {
        return array_values(array_filter(array_map(
            static fn (array $b) => $b['descriptor']['adapter'] ?? null,
            $this->resolve($canonicalCategory, $region)
        )));
    }

    /**
     * Stable hash of the ACTIVE provider surface — every enabled provider's
     * identity/tier/license plus the full capability + regional-override maps.
     *
     * This is the value that must be folded into `fetch_version`
     * (canonical-field-mapping-spec §7): changing which providers/roles are
     * active changes this hash, which invalidates cached candidates. Deterministic
     * — independent of PHP array/hash ordering — so identical config always yields
     * the identical hash.
     */
    public function capabilityHash(): string
    {
        $enabled = [];
        foreach (($this->config['providers'] ?? []) as $id => $d) {
            if (($d['enabled'] ?? false) !== true) {
                continue;
            }
            $enabled[$id] = [
                'tier'    => $d['tier'] ?? null,
                'license' => $d['license'] ?? null,
                'serves'  => $this->sortedCopy($d['serves'] ?? []),
            ];
        }
        ksort($enabled);

        $capabilities = $this->config['capabilities'] ?? [];
        ksort($capabilities);

        $overrides = $this->config['regional_overrides'] ?? [];
        ksort($overrides);

        return hash('sha256', json_encode([
            'providers'          => $enabled,
            'capabilities'       => $capabilities,
            'regional_overrides' => $overrides,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** True when a provider exists and is enabled. */
    public function isEnabled(string $providerId): bool
    {
        return (($this->config['providers'][$providerId]['enabled'] ?? false) === true);
    }

    /** Raw (pre-enabled-filter) bindings for a category, applying region + poi.default fallback. */
    private function rawBindingsFor(string $canonicalCategory, string $region): array
    {
        $override = $this->config['regional_overrides'][$canonicalCategory][$region] ?? null;
        if (is_array($override)) {
            return $override;
        }

        $capabilities = $this->config['capabilities'] ?? [];
        if (isset($capabilities[$canonicalCategory])) {
            return $capabilities[$canonicalCategory];
        }

        if (str_starts_with($canonicalCategory, 'poi.') && isset($capabilities[self::POI_DEFAULT_KEY])) {
            return $capabilities[self::POI_DEFAULT_KEY];
        }

        return [];
    }

    private function sortedCopy(array $values): array
    {
        $copy = $values;
        sort($copy);

        return $copy;
    }
}
