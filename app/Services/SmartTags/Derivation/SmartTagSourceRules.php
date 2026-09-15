<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * Typed access to config/smart_tag_sources.php, plus the validation that keeps
 * every rule inside the canonical taxonomy.
 */
final class SmartTagSourceRules
{
    public const KINDS = ['boolean', 'equals', 'any', 'prefix', 'nonempty', 'vocab', 'number_gt', 'flag'];

    /** @var array<string, array<string, string>> */
    private static array $normalizedVocab = [];

    /** Test hook. */
    public static function flush(): void
    {
        self::$normalizedVocab = [];
    }

    /** @return array<string, mixed> */
    public static function raw(): array
    {
        return SmartTagConfig::sources();
    }

    /**
     * A vocabulary keyed by normalised option value.
     *
     * @return array<string, string> normalised value => tag key
     */
    public static function vocabulary(string $name): array
    {
        if (! array_key_exists($name, self::$normalizedVocab)) {
            $out = [];
            foreach ((array) (self::raw()['vocabularies'][$name] ?? []) as $value => $tag) {
                $out[NativeMetaValueReader::normalizeOption((string) $value)] = (string) $tag;
            }
            self::$normalizedVocab[$name] = $out;
        }

        return self::$normalizedVocab[$name];
    }

    /** @return array<int, array<string, mixed>> */
    public static function bridgeRules(): array
    {
        return array_values((array) (self::raw()['bridge']['rules'] ?? []));
    }

    /** @return array<int, array<string, mixed>> */
    public static function nativeRules(SmartTagListingType $type): array
    {
        if (! $type->isNative()) {
            return [];
        }

        return array_values(array_merge(
            (array) (self::raw()['native']['shared_rules'] ?? []),
            (array) (self::raw()['native'][$type->value]['rules'] ?? []),
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public static function structuredRules(SmartTagListingType $type): array
    {
        return $type === SmartTagListingType::Bridge ? self::bridgeRules() : self::nativeRules($type);
    }

    public static function nativePropertyTypeField(): string
    {
        return (string) (self::raw()['native']['property_type_field'] ?? 'property_type');
    }

    /** @return array<string, array<int, array<string, mixed>>> tag key => rules */
    public static function descriptionRules(): array
    {
        return (array) (self::raw()['description']['rules'] ?? []);
    }

    public static function descriptionSetting(string $key, mixed $default = null): mixed
    {
        return self::raw()['description'][$key] ?? $default;
    }

    /** @return array<string, mixed>|null */
    public static function descriptionField(SmartTagListingType $type): ?array
    {
        $field = self::raw()['description']['fields'][$type->value] ?? null;

        return is_array($field) ? $field : null;
    }

    public static function confidence(string $key, int $default): int
    {
        return (int) (self::raw()['confidence'][$key] ?? $default);
    }

    /** @return string[] */
    public static function forbiddenKeys(): array
    {
        return array_values((array) (self::raw()['forbidden_source_keys'] ?? []));
    }

    public static function isForbiddenKey(string $key): bool
    {
        if (in_array($key, self::forbiddenKeys(), true)) {
            return true;
        }

        foreach ((array) (self::raw()['forbidden_source_key_prefixes'] ?? []) as $prefix) {
            if ($prefix !== '' && str_starts_with($key, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every tag key a structured rule can emit.
     *
     * @param array<string, mixed> $rule
     * @return string[]
     */
    public static function tagsEmittableBy(array $rule): array
    {
        if (($rule['kind'] ?? null) === 'vocab') {
            return array_values(array_unique(array_values((array) (self::raw()['vocabularies'][$rule['vocab'] ?? ''] ?? []))));
        }

        return isset($rule['tag']) ? [(string) $rule['tag']] : [];
    }

    /**
     * The contexts in which a rule may emit a tag for a listing type.
     *
     * @param array<string, mixed> $rule
     * @return SmartTagContext[]
     */
    public static function ruleContexts(array $rule, string $tagKey, SmartTagListingType $type): array
    {
        $definition = SmartTagTaxonomy::get($tagKey);
        if ($definition === null) {
            return [];
        }

        $allowed = array_values(array_filter(
            $definition->contexts,
            static fn (SmartTagContext $c) => in_array($c, $type->possibleContexts(), true),
        ));

        if (isset($rule['contexts'])) {
            $declared = array_filter(array_map(static fn ($c) => SmartTagContext::tryFrom((string) $c), (array) $rule['contexts']));
            $allowed = array_values(array_filter($allowed, static fn (SmartTagContext $c) => in_array($c, $declared, true)));
        }

        return $allowed;
    }

    /**
     * Structural problems with the source rules. Empty means valid.
     *
     * @return string[]
     */
    public static function validationErrors(): array
    {
        $raw = self::raw();
        $errors = [];
        $ids = [];

        $check = static function (array $rule, string $where) use (&$errors, &$ids, $raw): void {
            $id = (string) ($rule['id'] ?? '');
            if ($id === '') {
                $errors[] = "{$where}: rule without id";
            } elseif (isset($ids[$id])) {
                $errors[] = "{$where}: duplicate rule id {$id}";
            }
            $ids[$id] = true;

            $kind = $rule['kind'] ?? null;
            if (! in_array($kind, self::KINDS, true)) {
                $errors[] = "{$id}: unknown kind " . var_export($kind, true);
                return;
            }

            if ($kind === 'vocab') {
                if (! isset($raw['vocabularies'][$rule['vocab'] ?? ''])) {
                    $errors[] = "{$id}: unknown vocabulary " . ($rule['vocab'] ?? 'null');
                }
            } elseif (! isset($rule['tag'])) {
                $errors[] = "{$id}: missing tag";
            }

            if (! isset($rule['field']) && ! isset($rule['column'])) {
                $errors[] = "{$id}: missing field";
            }

            foreach (self::tagsEmittableBy($rule) as $tag) {
                if (! SmartTagTaxonomy::has($tag)) {
                    $errors[] = "{$id}: emits unknown tag {$tag}";
                }
            }

            foreach ((array) ($rule['contexts'] ?? []) as $ctx) {
                if (SmartTagContext::tryFrom((string) $ctx) === null) {
                    $errors[] = "{$id}: invalid context {$ctx}";
                }
                foreach (self::tagsEmittableBy($rule) as $tag) {
                    $definition = SmartTagTaxonomy::get($tag);
                    $resolved = SmartTagContext::tryFrom((string) $ctx);
                    if ($definition !== null && $resolved !== null && ! $definition->appliesTo($resolved)) {
                        $errors[] = "{$id}: context {$ctx} is not allowed for tag {$tag}";
                    }
                }
            }
        };

        foreach (self::bridgeRules() as $rule) {
            $check($rule, 'bridge');
            if (isset($rule['flag']) || ($rule['kind'] ?? null) === 'flag') {
                $errors[] = ($rule['id'] ?? '?') . ': flag rules are not valid for Bridge records';
            }
        }

        foreach ([SmartTagListingType::SellerAgent, SmartTagListingType::LandlordAgent] as $type) {
            foreach ((array) ($raw['native'][$type->value]['rules'] ?? []) as $rule) {
                $check($rule, $type->value);
            }
        }
        foreach ((array) ($raw['native']['shared_rules'] ?? []) as $rule) {
            $check($rule, 'native.shared');
        }

        foreach ((array) ($raw['vocabularies'] ?? []) as $name => $vocab) {
            foreach ((array) $vocab as $value => $tag) {
                if (! SmartTagTaxonomy::has((string) $tag)) {
                    $errors[] = "vocabulary {$name}: value \"{$value}\" maps to unknown tag {$tag}";
                }
            }
        }

        foreach ((array) ($raw['native']['shared_rules'] ?? []) as $rule) {
            if (self::isForbiddenKey((string) ($rule['field'] ?? ''))) {
                $errors[] = ($rule['id'] ?? '?') . ': reads forbidden source key ' . $rule['field'];
            }
        }
        foreach ([SmartTagListingType::SellerAgent, SmartTagListingType::LandlordAgent] as $type) {
            foreach (self::nativeRules($type) as $rule) {
                if (self::isForbiddenKey((string) ($rule['field'] ?? ''))) {
                    $errors[] = ($rule['id'] ?? '?') . ': reads forbidden source key ' . $rule['field'];
                }
            }
            $descField = self::descriptionField($type);
            if ($descField !== null && self::isForbiddenKey((string) ($descField['meta_key'] ?? ''))) {
                $errors[] = "description field for {$type->value} is a forbidden source key";
            }
        }

        foreach (self::descriptionRules() as $tag => $rules) {
            if (! SmartTagTaxonomy::has((string) $tag)) {
                $errors[] = "description rules for unknown tag {$tag}";
                continue;
            }
            $definition = SmartTagTaxonomy::get((string) $tag);
            foreach ((array) $rules as $i => $rule) {
                $patterns = (array) ($rule['patterns'] ?? []);
                if ($patterns === []) {
                    $errors[] = "description.{$tag}[{$i}]: no patterns";
                }
                foreach ($patterns as $pattern) {
                    if (@preg_match(ListingDescriptionTagParser::compile((string) $pattern), '') === false) {
                        $errors[] = "description.{$tag}[{$i}]: invalid pattern {$pattern}";
                    }
                }
                foreach ((array) ($rule['contexts'] ?? []) as $ctx) {
                    $resolved = SmartTagContext::tryFrom((string) $ctx);
                    if ($resolved === null || ! $definition->appliesTo($resolved)) {
                        $errors[] = "description.{$tag}[{$i}]: context {$ctx} is not allowed for the tag";
                    }
                }
            }
        }

        foreach (SmartTagComplianceGuard::phraseViolations($raw) as $violation) {
            $errors[] = $violation;
        }

        return $errors;
    }
}
