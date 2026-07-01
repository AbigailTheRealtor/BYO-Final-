<?php

namespace App\Services\AskAi;

use App\Models\AiFaqAnswer;
use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\TenantCriteriaAuction;

/**
 * AskAiFaqEnrichmentService
 *
 * Syncs listing FAQ answers from the raw listing_ai_faq JSON blob into the
 * structured ai_faq_answers table, resolving question_group,
 * intelligence_category, and question_label from the appropriate config file.
 *
 * Supported listing types: seller, buyer, landlord, tenant.
 * Config sources:
 *   seller   → config/ai_faq_seller.php
 *   landlord → config/ai_faq_landlord.php
 *   buyer    → config/ai_faq_buyer.php
 *   tenant   → config/tenant_ai_faq.php
 *
 * answer_normalized JSON column stores question_label and config_key, which
 * are not native columns on ai_faq_answers.
 */
class AskAiFaqEnrichmentService
{
    private const CONFIG_MAP = [
        'seller'   => 'ai_faq_seller',
        'landlord' => 'ai_faq_landlord',
        'buyer'    => 'ai_faq_buyer',
        'tenant'   => 'tenant_ai_faq',
    ];

    /**
     * Sync FAQ answers for the given listing into ai_faq_answers.
     *
     * Returns a summary of synced and skipped keys.
     *
     * @param  string $listingType  Canonical listing type (seller, buyer, landlord, tenant).
     * @param  int    $listingId    Primary key of the listing record.
     * @return array{synced: string[], skipped: string[], error: string|null}
     */
    public function sync(string $listingType, int $listingId): array
    {
        $listing = $this->findListing($listingType, $listingId);

        if ($listing === null) {
            return [
                'synced'  => [],
                'skipped' => [],
                'error'   => "Listing not found: {$listingType} #{$listingId}",
            ];
        }

        $raw = $this->loadFaqJson($listing, $listingType);

        if (empty($raw)) {
            return [
                'synced'  => [],
                'skipped' => [],
                'error'   => null,
            ];
        }

        $index   = static::buildConfigIndex($listingType);
        $synced  = [];
        $skipped = [];

        foreach ($raw as $configKey => $answerText) {
            $configKey  = (string) $configKey;
            $answerText = ($answerText !== null && $answerText !== false) ? (string) $answerText : null;

            if ($answerText === null || $answerText === '') {
                $skipped[] = $configKey;
                continue;
            }

            $meta = $index[$configKey] ?? [
                'question_group'        => null,
                'question_label'        => null,
                'intelligence_category' => null,
            ];

            AiFaqAnswer::updateOrCreate(
                [
                    'listing_type' => $listingType,
                    'listing_id'   => $listingId,
                    'question_key' => $configKey,
                ],
                [
                    'question_group'        => $meta['question_group'],
                    'intelligence_category' => $meta['intelligence_category'],
                    'answer_text'           => $answerText,
                    'answer_normalized'     => [
                        'config_key'     => $configKey,
                        'question_label' => $meta['question_label'],
                    ],
                ]
            );

            $synced[] = $configKey;
        }

        return [
            'synced'  => $synced,
            'skipped' => $skipped,
            'error'   => null,
        ];
    }

    /**
     * Build a flat config index for the given listing type.
     *
     * Returns an array keyed by question config_key, where each value is:
     *   question_group        — the group/category the question belongs to
     *   question_label        — the human-readable question label
     *   intelligence_category — snake_case category derived from question_group
     *
     * @param  string $listingType  One of 'seller', 'buyer', 'landlord', 'tenant'.
     * @return array<string, array{question_group: string|null, question_label: string|null, intelligence_category: string|null}>
     */
    public static function buildConfigIndex(string $listingType): array
    {
        $configKey = self::CONFIG_MAP[$listingType] ?? null;

        if ($configKey === null) {
            return [];
        }

        // Configs use the two-axis groups/gating shape; AskAiFaqConfigService flattens it
        // into a uniform category→key→entry map for all roles (seller/buyer/landlord/tenant).
        try {
            $byCategory = AskAiFaqConfigService::questionsByCategory($configKey);
        } catch (\Throwable) {
            return [];
        }

        $index = [];
        foreach ($byCategory as $group => $questions) {
            foreach ($questions as $key => $def) {
                $index[(string) $key] = [
                    'question_group'        => (string) $group,
                    'question_label'        => $def['label'] ?? null,
                    'intelligence_category' => static::groupToCategory((string) $group),
                ];
            }
        }

        return $index;
    }

    /**
     * Build the index for seller/landlord/buyer configs (grouped question structure).
     *
     * Structure:
     *   questions => [ 'Group Name' => [ 'key' => ['label' => '...', ...] ] ]
     *   addons    => [ 'addon_id' => [ 'questions' => [ 'key' => [...] ] ] ]
     */
    private static function indexGroupedConfig(array $config): array
    {
        $index = [];

        foreach (($config['questions'] ?? []) as $group => $questions) {
            if (!is_array($questions)) {
                continue;
            }
            foreach ($questions as $key => $def) {
                $index[(string) $key] = [
                    'question_group'        => (string) $group,
                    'question_label'        => $def['label'] ?? null,
                    'intelligence_category' => static::groupToCategory((string) $group),
                ];
            }
        }

        foreach (($config['addons'] ?? []) as $addon) {
            if (!is_array($addon) || !isset($addon['questions'])) {
                continue;
            }
            $addonLabel = $addon['label'] ?? null;
            foreach ($addon['questions'] as $key => $def) {
                $group = $addonLabel ?? $addon['label'] ?? 'Addon';
                $index[(string) $key] = [
                    'question_group'        => (string) $group,
                    'question_label'        => $def['label'] ?? null,
                    'intelligence_category' => static::groupToCategory((string) $group),
                ];
            }
        }

        return $index;
    }

    /**
     * Build the index for the tenant config (flat array of question objects).
     *
     * Structure:
     *   questions => [ ['key' => '...', 'label' => '...', 'category' => '...', ...] ]
     */
    private static function indexTenantConfig(array $config): array
    {
        $index = [];

        foreach (($config['questions'] ?? []) as $entry) {
            if (!is_array($entry) || !isset($entry['key'])) {
                continue;
            }
            $key   = (string) $entry['key'];
            $group = $entry['category'] ?? null;
            $index[$key] = [
                'question_group'        => $group,
                'question_label'        => $entry['label'] ?? null,
                'intelligence_category' => $group ? static::groupToCategory($group) : null,
            ];
        }

        return $index;
    }

    /**
     * Load the raw PHP config array for the given config key.
     *
     * Uses the Laravel config() helper when the application is bootstrapped,
     * and falls back to a direct require of the config file otherwise (e.g.
     * in pure-PHPUnit test environments where the container is not booted).
     *
     * @param  string $configKey  Config file key, e.g. 'ai_faq_seller'.
     * @return array
     * @throws \RuntimeException  When the config file cannot be found or read.
     */
    private static function loadConfigArray(string $configKey): array
    {
        if (function_exists('config') && app()->bound('config')) {
            $result = config($configKey);
            if (is_array($result)) {
                return $result;
            }
        }

        $file = static::configFilePath($configKey);

        if (!file_exists($file)) {
            return [];
        }

        $result = require $file;
        return is_array($result) ? $result : [];
    }

    /**
     * Resolve the absolute path to a config file by key.
     * Works from the known location of this service file.
     * Service is at: app/Services/AskAi/AskAiFaqEnrichmentService.php
     * Config is at:  config/<key>.php  (project root / config)
     */
    private static function configFilePath(string $configKey): string
    {
        return dirname(__DIR__, 3) . '/config/' . $configKey . '.php';
    }

    /**
     * Derive a snake_case intelligence_category from a human-readable group name.
     *
     * Examples:
     *   'Property Condition & Maintenance' → 'property_condition_maintenance'
     *   'Financial & Utility Insights'     → 'financial_utility_insights'
     *   'Commercial – Business Use'        → 'commercial_business_use'
     */
    public static function groupToCategory(string $group): string
    {
        $normalized = strtolower($group);
        $normalized = preg_replace('/[&\-–—\/+\'"]/', ' ', $normalized);
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', '_', trim($normalized));
        return $normalized;
    }

    /**
     * Resolve the primary listing model for the given canonical type and ID.
     */
    private function findListing(string $listingType, int $listingId): ?object
    {
        return match ($listingType) {
            'seller'   => PropertyAuction::find($listingId),
            'buyer'    => BuyerCriteriaAuction::find($listingId),
            'landlord' => LandlordAuction::find($listingId),
            'tenant'   => TenantCriteriaAuction::find($listingId),
            default    => null,
        };
    }

    /**
     * Load the raw listing_ai_faq JSON from the listing model.
     * Returns a decoded array or null when unavailable.
     */
    private function loadFaqJson(object $listing, string $listingType): ?array
    {
        if ($listingType === 'tenant') {
            $col = $listing->listing_ai_faq ?? null;
            if ($col !== null) {
                $decoded = is_array($col) ? $col : json_decode((string) $col, true);
                return is_array($decoded) ? $decoded : null;
            }
            return null;
        }

        if (method_exists($listing, 'info')) {
            $meta = $listing->info('listing_ai_faq');
            if ($meta !== null && $meta !== false && $meta !== '') {
                $decoded = is_array($meta) ? $meta : json_decode((string) $meta, true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }
}
