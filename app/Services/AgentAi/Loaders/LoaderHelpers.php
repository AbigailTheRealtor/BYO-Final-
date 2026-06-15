<?php

namespace App\Services\AgentAi\Loaders;

/**
 * LoaderHelpers
 *
 * Shared utility methods used by all AgentAi V2 context loaders.
 *
 * GOVERNANCE: All methods are pure data transformers — no DB writes, no HTTP
 * calls, no invention of missing data.
 */
trait LoaderHelpers
{
    /**
     * Decode a JSON-encoded field into a comma-separated string.
     *
     * Filters the literal string "Other" (case-insensitive) from decoded arrays,
     * consistent with the V1 AskAiContextBuilderService::decodeJsonField() pattern.
     *
     * @param  string|null $value  Raw meta value (JSON array or plain string).
     * @return string|null         Comma-separated string, or null when empty.
     */
    protected static function decodeJsonField(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $filtered = array_filter(
                $decoded,
                fn ($v) => strtolower(trim((string) $v)) !== 'other'
            );
            $result = implode(', ', array_values($filtered));
            return $result !== '' ? $result : null;
        }
        return $value;
    }

    /**
     * Resolve a field that may carry a sentinel placeholder ("Other", "See Remarks",
     * "TBD", etc.) by trying ordered fallback meta keys.
     *
     * Mirrors AskAiContextBuilderService::resolveOtherValue() exactly so loader
     * output is consistent with the V1 context builder for the same fields.
     *
     * @param  string|null $primaryValue   Raw primary field value.
     * @param  callable    $infoGet        EAV accessor: fn(string $key): ?string.
     * @param  string      ...$fallbackKeys Meta keys to try when primary is a sentinel.
     * @return string|null
     */
    protected static function resolveOtherValue(
        ?string $primaryValue,
        callable $infoGet,
        string ...$fallbackKeys
    ): ?string {
        if ($primaryValue === null || $primaryValue === '') {
            return null;
        }

        $normalized = strtolower(trim($primaryValue));

        if (in_array($normalized, ['tbd', 't.b.d.', 'n/a', 'na', 'none', 'unknown', 'not applicable', 'not available'], true)) {
            return null;
        }

        if (in_array($normalized, ['other', 'see remarks', 'see private remarks', 'per remarks'], true)) {
            foreach ($fallbackKeys as $key) {
                $val = $infoGet($key);
                if ($val !== null && $val !== '' && $val !== false) {
                    return (string) $val;
                }
            }
            return null;
        }

        return $primaryValue;
    }

    /**
     * Estimate the token count for a content array.
     *
     * Uses the GPT-4o approximation of 4 characters per token after JSON
     * serialization. This is consistent with the approach documented in the
     * AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md audit.
     *
     * @param  array $content
     * @return int
     */
    protected static function tokenEstimate(array $content): int
    {
        return (int) ceil(strlen(json_encode($content)) / 4);
    }

    /**
     * Build a complete context fragment conforming to the V2 fragment contract.
     *
     * Automatically strips null and empty values from $content before calculating
     * the token estimate, per audit section 11.1 (flatten null values).
     *
     * @param  string   $sourceKey    Unique source identifier (e.g. 'listing_core').
     * @param  int      $priority     Loader registration priority (echoed back).
     * @param  array    $content      Raw key-value content (may contain nulls).
     * @param  bool     $publicAllowed Whether fragment is safe for unauthenticated users.
     * @param  string[] $roleScope    Which roles this fragment applies to.
     * @param  int|null $cacheTtl     Cache TTL in seconds; null = no cache.
     * @return array
     */
    protected static function makeFragment(
        string $sourceKey,
        int $priority,
        array $content,
        bool $publicAllowed = true,
        array $roleScope = [],
        ?int $cacheTtl = null
    ): array {
        $clean = array_filter(
            $content,
            fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== false
        );

        return [
            'source_key'     => $sourceKey,
            'priority'       => $priority,
            'content'        => $clean,
            'token_estimate' => self::tokenEstimate($clean),
            'public_allowed' => $publicAllowed,
            'role_scope'     => $roleScope,
            'cache_ttl'      => $cacheTtl,
            'loaded_at'      => now()->toISOString(),
        ];
    }

    /**
     * Truncate a free-text string to $maxChars characters.
     *
     * Per audit section 11.1 rule 5: description, bio, marketing plan, and FAQ
     * answer fields should be capped at 300 characters in the serialized context.
     *
     * @param  string|null $text
     * @param  int         $maxChars
     * @return string|null
     */
    protected static function truncateText(?string $text, int $maxChars = 300): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        if (strlen($text) <= $maxChars) {
            return $text;
        }
        return mb_substr($text, 0, $maxChars - 3) . '...';
    }

    /**
     * Build an EAV accessor closure from a listing model's info() method.
     *
     * Returns a callable that accepts a meta key and returns either the string
     * value or null (never false, so callers can use ?? safely).
     *
     * @param  object $listing  Any model that has an info() method.
     * @return callable
     */
    protected static function makeInfoGet(object $listing): callable
    {
        return function (string $key) use ($listing): ?string {
            if (!method_exists($listing, 'info')) {
                return null;
            }
            $val = $listing->info($key);
            return ($val !== false && $val !== null) ? (string) $val : null;
        };
    }

    /**
     * Build a native-column accessor closure from a listing model.
     *
     * @param  object $listing
     * @return callable
     */
    protected static function makeNativeGet(object $listing): callable
    {
        return function (string $key) use ($listing): ?string {
            return isset($listing->{$key}) && $listing->{$key} !== null
                ? (string) $listing->{$key}
                : null;
        };
    }
}
