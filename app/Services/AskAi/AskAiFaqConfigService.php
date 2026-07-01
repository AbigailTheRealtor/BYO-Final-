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
