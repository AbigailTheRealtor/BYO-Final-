<?php

namespace App\Models\Concerns;

/**
 * HasCompatibilityPreferences
 *
 * Provides internal-only persistence and rehydration for agent compatibility
 * response sections stored as dot-notation meta keys in the existing *Meta tables.
 *
 * Meta key format: compatibility_preferences.agent_response.{section}
 * Values are JSON-encoded arrays.
 *
 * This is an internal foundation layer — no compatibility data is exposed via
 * any Blade view, API response, or public-facing output.
 */
trait HasCompatibilityPreferences
{
    /**
     * The 7 canonical compatibility preference sections.
     */
    public static function compatibilitySections(): array
    {
        return [
            'communication_preferences',
            'negotiation_approach',
            'guidance_style',
            'collaboration_preferences',
            'transaction_strategy',
            'representation_philosophy',
            'representation_priorities',
        ];
    }

    /**
     * Meta key prefix for all compatibility preference sections.
     */
    protected static function compatibilityMetaPrefix(): string
    {
        return 'compatibility_preferences.agent_response.';
    }

    /**
     * Persist one or more compatibility preference sections to the meta table.
     *
     * @param  array  $sections  Associative array of section => data pairs.
     *                           Only keys listed in compatibilitySections() are stored.
     *                           Values must be arrays; null/non-array values are skipped.
     * @return void
     */
    public function saveCompatibilityPreferences(array $sections): void
    {
        $allowed = static::compatibilitySections();

        foreach ($sections as $section => $data) {
            if (!in_array($section, $allowed, true)) {
                continue;
            }

            if (!$this->isValidCompatibilitySection($data)) {
                continue;
            }

            $metaKey = static::compatibilityMetaPrefix() . $section;
            $this->saveMeta($metaKey, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Load all 7 compatibility preference sections from the meta table.
     *
     * Returns an associative array keyed by section name. Missing or malformed
     * sections are returned as null — never throws exceptions.
     *
     * @return array<string, array|null>
     */
    public function loadCompatibilityPreferences(): array
    {
        $result = [];

        foreach (static::compatibilitySections() as $section) {
            $metaKey = static::compatibilityMetaPrefix() . $section;
            $raw     = $this->info($metaKey);

            if ($raw === false || $raw === null || $raw === '') {
                $result[$section] = null;
                continue;
            }

            $decoded = json_decode((string) $raw, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $result[$section] = null;
                continue;
            }

            $result[$section] = $decoded;
        }

        return $result;
    }

    /**
     * Load a single compatibility preference section by name.
     *
     * Returns null if the section does not exist or is malformed — never throws.
     *
     * @param  string  $section  One of the keys from compatibilitySections().
     * @return array|null
     */
    public function loadCompatibilitySection(string $section): ?array
    {
        if (!in_array($section, static::compatibilitySections(), true)) {
            return null;
        }

        $metaKey = static::compatibilityMetaPrefix() . $section;
        $raw     = $this->info($metaKey);

        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }

    /**
     * Internal null-safe structural guard.
     *
     * Returns true only when the value is a non-empty associative array whose
     * values are recursively composed of only scalars or arrays (no objects,
     * resources, or closures). This prevents malformed payloads from being stored.
     *
     * No validation errors are surfaced publicly — this is an internal-only guard.
     *
     * @param  mixed  $data
     * @return bool
     */
    protected function isValidCompatibilitySection(mixed $data): bool
    {
        if (!is_array($data) || empty($data)) {
            return false;
        }

        // Must be an associative array (string keys), not a sequential list.
        if (array_is_list($data)) {
            return false;
        }

        return $this->compatibilitySectionValuesAreSafe($data);
    }

    /**
     * Recursively confirms that every value in a compatibility section array is
     * a scalar or a plain array — no objects, resources, or other non-serialisable
     * types. Returns false as soon as an unsafe value is found.
     *
     * Internal-only. Never surfaces errors publicly.
     *
     * @param  array  $data
     * @return bool
     */
    private function compatibilitySectionValuesAreSafe(array $data): bool
    {
        foreach ($data as $value) {
            if (is_null($value) || is_scalar($value)) {
                continue;
            }

            if (is_array($value)) {
                if (!$this->compatibilitySectionValuesAreSafe($value)) {
                    return false;
                }
                continue;
            }

            // objects, resources, closures — all rejected
            return false;
        }

        return true;
    }
}
