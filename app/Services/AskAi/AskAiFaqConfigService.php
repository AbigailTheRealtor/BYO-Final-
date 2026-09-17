<?php

namespace App\Services\AskAi;

/**
 * AskAiFaqConfigService — accessor/compatibility layer for the listing-AI knowledge-base
 * config files (config/ai_faq_seller.php, ai_faq_buyer.php, ai_faq_landlord.php,
 * tenant_ai_faq.php).
 *
 * Those files moved to the two-axis `groups` + `gating` shape
 * (docs/ask-ai-kb-replacement-spec.md Part A). This service flattens that shape back into
 * the views older consumers expect (a category→key map, a flat list with a 'key' field,
 * and a flat key list), so the new architecture does not break the snapshot/enrichment/
 * normalizer/controller code that reads these configs.
 *
 * GOVERNANCE: pure, read-only config access. No LLM/HTTP/DB calls, no AI text generation.
 */
class AskAiFaqConfigService
{
    /** Role → config key. */
    public const CONFIG_MAP = [
        'seller'   => 'ai_faq_seller',
        'buyer'    => 'ai_faq_buyer',
        'landlord' => 'ai_faq_landlord',
        'tenant'   => 'tenant_ai_faq',
    ];

    /**
     * Flatten every group into a category→key→entry map (gating is ignored — this is the
     * full set of questions across all property types). Mirrors the legacy
     * `config('<key>.questions')` nested shape.
     *
     * @param  string $configKey  e.g. 'ai_faq_seller' or 'tenant_ai_faq'.
     * @return array<string, array<string, array>>
     */
    public static function questionsByCategory(string $configKey): array
    {
        $config = static::loadConfig($configKey);
        $out    = [];

        foreach (($config['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $category => $questions) {
                if (!is_array($questions)) {
                    continue;
                }
                foreach ($questions as $key => $entry) {
                    if (is_array($entry)) {
                        $out[(string) $category][(string) $key] = $entry;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * The questions that ACTUALLY render for one role and one property type —
     * the 'universal' group plus the single group the config's 'gating' map
     * names for that type (docs/ask-ai-kb-replacement-spec.md Part A).
     *
     * THIS IS THE GATING DEFINITION. There is exactly one, and it has two
     * readers: ai-questions-input.blade.php, which renders these questions, and
     * MlsQuickImportComponent, which admits answers to them. That pairing is the
     * point. The gating logic lived only inside the blade until MLS Quick Import
     * needed to decide which keys a client may write — and a second copy of
     * "which questions belong to a Seller Commercial listing" is a copy that
     * drifts, leaving the form asking one set and the persist accepting another.
     *
     * Distinct from {@see questionsByCategory()}, which deliberately IGNORES
     * gating and returns every question a role defines. That wider set is what
     * the Ask AI admission boundary and the enrichment service intersect
     * against, because a listing's property type can be edited after answers
     * were stored and a stored answer must not be orphaned by it. This narrower
     * set is what a FORM may write on a single request.
     *
     * Fail-safe, in the same two directions the blade fails safe:
     *   - no property type yet          → universal only, so a property-type
     *                                     interview is never revealed early;
     *   - unrecognised property type    → universal only, never a residential
     *                                     default that would leak questions.
     *
     * @param  string $role          'seller' | 'buyer' | 'landlord' | 'tenant'.
     * @param  string $propertyType  As STORED on the listing — short forms
     *                               (Seller/Buyer) and long forms
     *                               (Landlord/Tenant) both resolve, via the
     *                               config/property_types.php alias map.
     * @return array<string, array<string, array>>  category => key => entry.
     */
    public static function gatedQuestions(string $role, string $propertyType): array
    {
        $configKey = self::CONFIG_MAP[$role] ?? null;

        if ($configKey === null) {
            return [];
        }

        $config = static::loadConfig($configKey);
        $groups = $config['groups'] ?? [];
        $gating = $config['gating'] ?? [];

        // Seller/Buyer store SHORT property-type values and Landlord/Tenant LONG
        // ones; the gating maps are keyed by the long forms. One alias map
        // bridges the two vocabularies. An already-long value passes through.
        $aliases    = static::loadConfig('property_types')['ai_faq_gating_aliases'] ?? [];
        $gatingKey  = $aliases[$propertyType] ?? $propertyType;

        $activeGroups = ($gatingKey === '' || $gatingKey === null)
            ? ['universal']
            : ($gating[$gatingKey] ?? ['universal']);

        $out = [];

        foreach ($activeGroups as $groupName) {
            foreach (($groups[$groupName] ?? []) as $category => $questions) {
                if (! is_array($questions)) {
                    continue;
                }
                foreach ($questions as $key => $entry) {
                    if (is_array($entry)) {
                        $out[(string) $category][(string) $key] = $entry;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * The flat key list behind {@see gatedQuestions()} — the admission set for a
     * role + property type.
     *
     * @param  string $role
     * @param  string $propertyType
     * @return string[]
     */
    public static function gatedKeys(string $role, string $propertyType): array
    {
        $keys = [];

        foreach (static::gatedQuestions($role, $propertyType) as $questions) {
            foreach ($questions as $key => $entry) {
                $keys[] = (string) $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Every question key defined across all groups (deduplicated).
     *
     * @param  string $configKey
     * @return string[]
     */
    public static function allKeys(string $configKey): array
    {
        $keys = [];
        foreach (static::questionsByCategory($configKey) as $questions) {
            foreach ($questions as $key => $entry) {
                $keys[] = (string) $key;
            }
        }
        return array_values(array_unique($keys));
    }

    /**
     * Flat list of entries, each carrying its 'key' and 'category' — compatible with the
     * legacy tenant shape consumed via array_column(..., 'key').
     *
     * @param  string $configKey
     * @return array<int, array>
     */
    public static function flatList(string $configKey): array
    {
        $list = [];
        foreach (static::questionsByCategory($configKey) as $category => $questions) {
            foreach ($questions as $key => $entry) {
                $list[] = array_merge(['key' => (string) $key, 'category' => (string) $category], $entry);
            }
        }
        return $list;
    }

    /**
     * The raw config array for one knowledge base.
     *
     * A read-only passthrough to the same container-optional loader every other accessor
     * here uses, exposed for callers that need the `groups`/`gating` shape itself rather
     * than one of the flattened views — the public-question service reads it to verify
     * that an allowlisted key still sits in the group it was approved under.
     *
     * @return array
     */
    public static function rawConfig(string $configKey): array
    {
        return static::loadConfig($configKey);
    }

    /**
     * Load the raw config array, using config() when the container is booted and falling
     * back to a direct require otherwise (e.g. pure-PHPUnit runs without a booted app).
     *
     * @param  string $configKey
     * @return array
     */
    private static function loadConfig(string $configKey): array
    {
        try {
            if (function_exists('config') && function_exists('app') && app()->bound('config')) {
                $result = config($configKey);
                if (is_array($result)) {
                    return $result;
                }
            }
        } catch (\Throwable) {
            // fall through to file require
        }

        // Not booted (e.g. pure-PHPUnit / raw scripts): resolve the config file relative
        // to this class (app/Services/AskAi → repo root → config/). Avoids base_path(),
        // which requires a fully-bound container.
        $file = __DIR__ . '/../../../config/' . $configKey . '.php';

        if (is_file($file)) {
            $loaded = require $file;
            return is_array($loaded) ? $loaded : [];
        }

        return [];
    }
}
