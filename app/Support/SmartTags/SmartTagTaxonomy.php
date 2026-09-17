<?php

namespace App\Support\SmartTags;

/**
 * The canonical Smart Tag taxonomy, read from config/smart_tags.php.
 *
 * Closed and fail-closed: a key that is not declared here does not exist, and
 * nothing — no form, no parser, no import — can add one at runtime.
 */
final class SmartTagTaxonomy
{
    public const SURFACE_OWNER  = 'owner';
    public const SURFACE_SEEKER = 'seeker';

    /** @var array<string, SmartTagDefinition>|null */
    private static ?array $definitions = null;

    /** Test hook. */
    public static function flush(): void
    {
        self::$definitions = null;
        SmartTagConfig::flush();
    }

    public static function version(): string
    {
        return (string) (SmartTagConfig::taxonomy()['version'] ?? '');
    }

    /**
     * @return array<string, array{label: string, display_order: int}>
     */
    public static function categories(): array
    {
        return (array) (SmartTagConfig::taxonomy()['categories'] ?? []);
    }

    /**
     * Every declared tag, including retired ones.
     *
     * @return array<string, SmartTagDefinition>
     */
    public static function all(): array
    {
        if (self::$definitions === null) {
            self::$definitions = self::build(SmartTagConfig::taxonomy());
        }

        return self::$definitions;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function get(string $key): ?SmartTagDefinition
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Active tags applicable to one context, optionally narrowed to a selection
     * surface, in display order. This is what a future picker renders — never the
     * whole taxonomy.
     *
     * @return array<string, SmartTagDefinition>
     */
    public static function forContext(SmartTagContext $context, ?string $surface = null): array
    {
        $out = array_filter(self::all(), static function (SmartTagDefinition $def) use ($context, $surface) {
            if (! $def->isActive() || ! $def->appliesTo($context)) {
                return false;
            }

            return match ($surface) {
                null                 => true,
                self::SURFACE_OWNER  => $def->isOwnerSelectable(),
                self::SURFACE_SEEKER => $def->isSeekerSelectable(),
                default              => false,
            };
        });

        uasort($out, static fn (SmartTagDefinition $a, SmartTagDefinition $b) => $a->displayOrder <=> $b->displayOrder);

        return $out;
    }

    /**
     * Structural problems with the taxonomy. Empty means valid. Tests assert it.
     *
     * @return string[]
     */
    public static function validationErrors(): array
    {
        $raw = SmartTagConfig::taxonomy();
        $errors = [];
        $categories = array_keys((array) ($raw['categories'] ?? []));
        $tags = (array) ($raw['tags'] ?? []);

        if (($raw['version'] ?? '') === '') {
            $errors[] = 'taxonomy version is missing';
        }

        foreach ($tags as $key => $tag) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{1,62}$/', $key)) {
                $errors[] = "invalid tag key: " . var_export($key, true);
                continue;
            }
            if (! in_array($tag['category'] ?? null, $categories, true)) {
                $errors[] = "{$key}: unknown category";
            }
            if (trim((string) ($tag['label'] ?? '')) === '') {
                $errors[] = "{$key}: missing label";
            }
            if (! in_array($tag['status'] ?? null, [SmartTagDefinition::STATUS_ACTIVE, SmartTagDefinition::STATUS_RETIRED], true)) {
                $errors[] = "{$key}: invalid status";
            }
            $contexts = (array) ($tag['contexts'] ?? []);
            if ($contexts === []) {
                $errors[] = "{$key}: no contexts";
            }
            foreach ($contexts as $ctx) {
                if (SmartTagContext::tryFrom((string) $ctx) === null) {
                    $errors[] = "{$key}: invalid context {$ctx}";
                }
            }
            if (count($contexts) !== count(array_unique($contexts))) {
                $errors[] = "{$key}: duplicate contexts";
            }
            foreach (['mls_derivable', 'native_derivable', 'owner_selectable', 'seeker_selectable', 'public_display', 'negatable'] as $flag) {
                if (! is_bool($tag[$flag] ?? null)) {
                    $errors[] = "{$key}: {$flag} must be a boolean";
                }
            }
            $status = $tag['compliance']['status'] ?? null;
            if (! in_array($status, SmartTagDefinition::COMPLIANCE_STATUSES, true)) {
                $errors[] = "{$key}: invalid compliance status";
            }
            if (in_array($status, [SmartTagDefinition::COMPLIANCE_RESTRICTED, SmartTagDefinition::COMPLIANCE_PENDING_REVIEW], true)
                && trim((string) ($tag['compliance']['note'] ?? '')) === '') {
                $errors[] = "{$key}: a {$status} tag must explain why in compliance.note";
            }
        }

        foreach (self::conflictPairs($raw) as [$a, $b]) {
            if (! array_key_exists($a, $tags)) {
                $errors[] = "conflict references unknown tag {$a}";
            }
            if (! array_key_exists($b, $tags)) {
                $errors[] = "conflict references unknown tag {$b}";
            }
            if ($a === $b) {
                $errors[] = "tag {$a} conflicts with itself";
            }
        }

        foreach (SmartTagComplianceGuard::taxonomyViolations($raw) as $violation) {
            $errors[] = $violation;
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, SmartTagDefinition>
     */
    private static function build(array $raw): array
    {
        $conflicts = [];
        foreach (self::conflictPairs($raw) as [$a, $b]) {
            $conflicts[$a][$b] = true;
            $conflicts[$b][$a] = true;
        }

        $out = [];
        foreach ((array) ($raw['tags'] ?? []) as $key => $tag) {
            if (! is_string($key) || ! is_array($tag)) {
                continue;
            }

            $contexts = [];
            foreach ((array) ($tag['contexts'] ?? []) as $ctx) {
                $resolved = SmartTagContext::tryFrom((string) $ctx);
                if ($resolved !== null && ! in_array($resolved, $contexts, true)) {
                    $contexts[] = $resolved;
                }
            }

            $compliance = (array) ($tag['compliance'] ?? []);
            $complianceStatus = in_array($compliance['status'] ?? null, SmartTagDefinition::COMPLIANCE_STATUSES, true)
                ? $compliance['status']
                // An unrecognised compliance status reads as pending review: fail closed.
                : SmartTagDefinition::COMPLIANCE_PENDING_REVIEW;

            $out[$key] = new SmartTagDefinition(
                key: $key,
                label: (string) ($tag['label'] ?? $key),
                category: (string) ($tag['category'] ?? ''),
                description: (string) ($tag['description'] ?? ''),
                status: ($tag['status'] ?? null) === SmartTagDefinition::STATUS_ACTIVE
                    ? SmartTagDefinition::STATUS_ACTIVE
                    : SmartTagDefinition::STATUS_RETIRED,
                contexts: $contexts,
                mlsDerivable: ($tag['mls_derivable'] ?? false) === true,
                nativeDerivable: ($tag['native_derivable'] ?? false) === true,
                ownerSelectable: ($tag['owner_selectable'] ?? false) === true,
                seekerSelectable: ($tag['seeker_selectable'] ?? false) === true,
                publicDisplay: ($tag['public_display'] ?? false) === true,
                negatable: ($tag['negatable'] ?? false) === true,
                complianceStatus: $complianceStatus,
                complianceNote: isset($compliance['note']) ? (string) $compliance['note'] : null,
                complianceNotice: isset($compliance['notice']) ? (string) $compliance['notice'] : null,
                displayOrder: (int) ($tag['display_order'] ?? 0),
                conflictsWith: array_keys($conflicts[$key] ?? []),
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<int, array{0: string, 1: string}>
     */
    private static function conflictPairs(array $raw): array
    {
        $pairs = [];
        $conflicts = (array) ($raw['conflicts'] ?? []);

        foreach ((array) ($conflicts['mutually_exclusive'] ?? []) as $members) {
            $members = array_values((array) $members);
            foreach ($members as $i => $a) {
                foreach (array_slice($members, $i + 1) as $b) {
                    $pairs[] = [(string) $a, (string) $b];
                }
            }
        }

        foreach ((array) ($conflicts['opposed'] ?? []) as $set) {
            foreach ((array) ($set['a'] ?? []) as $a) {
                foreach ((array) ($set['b'] ?? []) as $b) {
                    $pairs[] = [(string) $a, (string) $b];
                }
            }
        }

        return $pairs;
    }
}
