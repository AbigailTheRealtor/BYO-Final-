<?php

namespace App\Services\AskAi;

/**
 * AskAiSourceResolver — Runtime field resolution from CANONICAL_SOURCE_MAP.
 *
 * Reads source specifications declared in AskAiContextBuilderService::CANONICAL_SOURCE_MAP
 * and resolves them against the listing model at runtime.  This eliminates the
 * duplication between the map (which declares WHAT source each field reads from)
 * and the old extractFactualFields() method (which re-declared those same sources
 * a second time in a hardcoded match block).
 *
 * Handles three source-type patterns:
 *
 *   bare EAV key string   'maximum_budget'
 *     → calls $infoGet('maximum_budget') → ?string
 *
 *   'native:column' prefix   'native:address'
 *     → calls $nativeGet('address') → ?string
 *
 *   array cascade   ['minimum_heated_square', 'heated_square_footage', 'heated_square']
 *     → iterates and returns the first non-empty value using ?: semantics (not ?? —
 *       empty-string EAV values are treated as absent and cause the cascade to
 *       advance to the next key).
 *
 *   'synthetic:*' prefix
 *     → returns null.  The caller (AskAiContextBuilderService) handles these fields
 *       manually because they require computation logic, not a source-map lookup.
 *
 * SCOPE BOUNDARY:
 *   This resolver handles source lookup ONLY.  Transformation logic
 *   (decodeJsonField, resolveOtherValue, summarizeUnitConfigurations, etc.)
 *   is intentionally excluded.  Fields requiring transformation are listed in
 *   AskAiContextBuilderService::extractManualFields() and override the resolver
 *   output via array_merge.
 */
class AskAiSourceResolver
{
    /**
     * Resolve a single CANONICAL_SOURCE_MAP field to its raw value.
     *
     * @param  string    $field      Context field name — used for cascade recursion only.
     * @param  mixed     $source     Source specification from CANONICAL_SOURCE_MAP entry.
     * @param  callable  $infoGet    EAV meta accessor:   fn(string $key) → ?string
     * @param  callable  $nativeGet  Native col accessor: fn(string $col) → ?string
     * @return mixed                 Raw resolved value, or null when absent.
     */
    public function resolveField(
        string $field,
        mixed $source,
        callable $infoGet,
        callable $nativeGet
    ): mixed {
        // ── Cascade array: first non-empty value wins (?:, not ??) ──────────────
        if (is_array($source)) {
            foreach ($source as $key) {
                $val = $this->resolveField($field, $key, $infoGet, $nativeGet);
                if ($val !== null && $val !== '' && $val !== false) {
                    return $val;
                }
            }
            return null;
        }

        $sourceStr = (string) $source;

        // ── Synthetic: caller handles manually ───────────────────────────────────
        if (str_starts_with($sourceStr, 'synthetic:')) {
            return null;
        }

        // ── Native column ────────────────────────────────────────────────────────
        if (str_starts_with($sourceStr, 'native:')) {
            return $nativeGet(substr($sourceStr, 7));
        }

        // ── Bare EAV key ─────────────────────────────────────────────────────────
        return $infoGet($sourceStr);
    }
}
