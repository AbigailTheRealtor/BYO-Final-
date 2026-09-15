<?php

namespace App\Support\SmartTags;

use App\Services\SmartTags\Derivation\DescriptionTextNormalizer;

/**
 * Deterministic version and change-detection hashes for Smart Tag derivation.
 *
 * Four independent stamps, modelled on LocationDnaVersionService:
 *
 *   tagger version        — the taxonomy + every source rule + the engine
 *                           version below. Any change makes every listing stale.
 *   structured inputs     — only the fields the structured rules read, for one
 *                           listing. A price change does not re-derive tags.
 *   MLS remarks           — the NORMALISED PublicRemarks text.
 *   native description    — the NORMALISED listing description text.
 *
 * Description hashes are taken over normalised text so whitespace and curly
 * quotes do not trigger a reparse, and are null when there is no text.
 */
final class SmartTagVersion
{
    /**
     * Bump when the derivation/parsing CODE changes meaning (a new rule kind, a
     * different negation window). Config changes are already in the hash.
     */
    public const ENGINE_VERSION = 'smart-tags-engine-2026-09-15.1';

    public static function taggerVersion(): string
    {
        return hash('sha256', self::canonicalJson([
            'engine'   => self::ENGINE_VERSION,
            'taxonomy' => SmartTagConfig::taxonomy(),
            'sources'  => SmartTagConfig::sources(),
        ]));
    }

    /**
     * @param array<string, mixed> $inputs field => value, only the fields rules read
     */
    public static function structuredInputsHash(array $inputs): string
    {
        return hash('sha256', self::canonicalJson($inputs));
    }

    public static function descriptionHash(?string $text): ?string
    {
        $normalized = DescriptionTextNormalizer::normalize($text);

        return $normalized === '' ? null : hash('sha256', $normalized);
    }

    /**
     * Key-sorted JSON so array ordering never changes a hash.
     */
    public static function canonicalJson(mixed $value): string
    {
        return (string) json_encode(self::sortKeys($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $k => $v) {
            $value[$k] = self::sortKeys($v);
        }

        return $value;
    }
}
