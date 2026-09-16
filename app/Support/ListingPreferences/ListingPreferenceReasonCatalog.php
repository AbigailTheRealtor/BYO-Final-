<?php

namespace App\Support\ListingPreferences;

use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * The resolved reason vocabulary: valid, closed and tied to the Smart Tag
 * taxonomy where — and only where — a real canonical tag exists.
 *
 * Pure. Runs without a booted application.
 *
 * validationErrors() is the guarantee this subsystem rests on, and a test fails
 * the build on any entry. It refuses, in particular, a `smart_tag` reason whose
 * key is not canonical, active, seeker-selectable and past compliance review —
 * so the Fair Housing exclusions already encoded in the taxonomy
 * (accessible_features, playground) govern the chip list with no second list.
 */
final class ListingPreferenceReasonCatalog
{
    /** The match-scorer categories a `criteria` reason may name. */
    public const CRITERIA_DIMENSIONS = [
        'location',
        'price',
        'size',
        'property_type',
        'amenities',
        'financial',
        'lifestyle',
        'non_residential',
    ];

    /** @var array<string, ListingPreferenceReasonDefinition>|null */
    private static ?array $resolved = null;

    /** Test hook: forget the resolved catalog. */
    public static function flush(): void
    {
        self::$resolved = null;
        ListingPreferenceConfig::flush();
    }

    public static function version(): string
    {
        return (string) (ListingPreferenceConfig::reasons()['version'] ?? '');
    }

    /**
     * @return array<string, ListingPreferenceReasonDefinition>
     */
    public static function all(): array
    {
        return self::$resolved ??= self::build(ListingPreferenceConfig::reasons());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function get(string $key): ?ListingPreferenceReasonDefinition
    {
        return self::all()[$key] ?? null;
    }

    /**
     * The chips offerable for a state, in display order, optionally narrowed to
     * one listing context.
     *
     * @return array<string, ListingPreferenceReasonDefinition>
     */
    public static function forState(ListingPreferenceState $state, ?SmartTagContext $context = null): array
    {
        $out = [];

        foreach (self::all() as $key => $reason) {
            if (! $reason->isOfferable() || ! $reason->appliesToState($state)) {
                continue;
            }

            if ($context !== null && ! $reason->appliesToContext($context)) {
                continue;
            }

            $out[$key] = $reason;
        }

        uasort($out, static fn ($a, $b): int => $a->displayOrder <=> $b->displayOrder);

        return $out;
    }

    /**
     * Every reason that may ever contribute to a learned signal.
     *
     * No learning code exists yet. This is the list such code must be written
     * against, so "which reasons are learnable" is answered in one place rather
     * than re-decided by whoever builds the learner.
     *
     * @return array<string, ListingPreferenceReasonDefinition>
     */
    public static function learnable(): array
    {
        return array_filter(self::all(), static fn ($r): bool => $r->isLearnable() && $r->isOfferable());
    }

    /**
     * @return string[] empty when the config is valid
     */
    public static function validationErrors(): array
    {
        $raw    = ListingPreferenceConfig::reasons();
        $errors = [];
        $states = ListingPreferenceState::values();

        if (($raw['version'] ?? '') === '') {
            $errors[] = 'reason config version is missing';
        }

        $reasons = (array) ($raw['reasons'] ?? []);

        if ($reasons === []) {
            $errors[] = 'reason config defines no reasons';
        }

        foreach ($reasons as $key => $reason) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{1,62}$/', $key)) {
                $errors[] = 'invalid reason key: ' . var_export($key, true);
                continue;
            }

            $label = trim((string) ($reason['label'] ?? ''));
            if ($label === '') {
                $errors[] = "{$key}: missing label";
            }

            if (! in_array($reason['status'] ?? null, [
                ListingPreferenceReasonDefinition::STATUS_ACTIVE,
                ListingPreferenceReasonDefinition::STATUS_RETIRED,
            ], true)) {
                $errors[] = "{$key}: invalid status";
            }

            $dimension = ListingPreferenceReasonDimension::tryFrom((string) ($reason['dimension'] ?? ''));
            if ($dimension === null) {
                $errors[] = "{$key}: invalid dimension";
                continue;
            }

            $tagKey   = $reason['smart_tag'] ?? null;
            $criteria = $reason['criteria_dimension'] ?? null;

            // A reason links to a Smart Tag only in the smart_tag dimension, and
            // must link in that dimension. Both directions matter: a stray link
            // on a criteria reason would quietly turn price into a tag.
            if ($dimension->requiresSmartTag()) {
                if (! is_string($tagKey) || $tagKey === '') {
                    $errors[] = "{$key}: a smart_tag reason must name a canonical tag";
                } else {
                    $tag = SmartTagTaxonomy::get($tagKey);
                    if ($tag === null) {
                        $errors[] = "{$key}: unknown Smart Tag {$tagKey}";
                    } elseif (! $tag->isActive()) {
                        $errors[] = "{$key}: Smart Tag {$tagKey} is retired";
                    } elseif (! $tag->isSeekerSelectable()) {
                        $errors[] = "{$key}: Smart Tag {$tagKey} is not seeker-selectable"
                            . ' (pending review, or excluded as a seeker preference)';
                    }
                }
            } elseif ($tagKey !== null) {
                $errors[] = "{$key}: only a smart_tag reason may name a Smart Tag";
            }

            if ($dimension->requiresCriteriaDimension()) {
                if (! is_string($criteria) || ! in_array($criteria, self::CRITERIA_DIMENSIONS, true)) {
                    $errors[] = "{$key}: a criteria reason must name a known match dimension";
                }
            } elseif ($criteria !== null) {
                $errors[] = "{$key}: only a criteria reason may name a match dimension";
            }

            $reasonStates = $reason['states'] ?? null;
            if (! is_array($reasonStates) || $reasonStates === []) {
                $errors[] = "{$key}: must declare at least one state";
            } else {
                foreach ($reasonStates as $state) {
                    if (! in_array($state, $states, true)) {
                        $errors[] = "{$key}: unknown state " . var_export($state, true);
                    }
                }
                if (count($reasonStates) !== count(array_unique($reasonStates))) {
                    $errors[] = "{$key}: duplicate states";
                }
            }

            if (! is_int($reason['display_order'] ?? null)) {
                $errors[] = "{$key}: display_order must be an integer";
            }

            // Fair Housing. The SAME guard the taxonomy uses — not a copy, and
            // not a config-held pattern list, because a guard that could be
            // relaxed by the edit it exists to catch is not a guard.
            foreach (['key' => str_replace('_', ' ', $key), 'label' => $label] as $field => $text) {
                foreach (SmartTagComplianceGuard::violations($text) as $category => $match) {
                    $errors[] = "{$key}: prohibited concept ({$category}) in {$field}: \"{$match}\"";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed> $raw
     * @return array<string, ListingPreferenceReasonDefinition>
     */
    private static function build(array $raw): array
    {
        $built = [];

        foreach ((array) ($raw['reasons'] ?? []) as $key => $reason) {
            if (! is_string($key)) {
                continue;
            }

            $dimension = ListingPreferenceReasonDimension::tryFrom((string) ($reason['dimension'] ?? ''));

            if ($dimension === null) {
                continue;
            }

            $built[$key] = new ListingPreferenceReasonDefinition(
                key:               $key,
                label:             (string) ($reason['label'] ?? ''),
                dimension:         $dimension,
                smartTagKey:       is_string($reason['smart_tag'] ?? null) ? $reason['smart_tag'] : null,
                criteriaDimension: is_string($reason['criteria_dimension'] ?? null) ? $reason['criteria_dimension'] : null,
                states:            array_values(array_filter(
                    (array) ($reason['states'] ?? []),
                    static fn ($s): bool => is_string($s),
                )),
                status:            (string) ($reason['status'] ?? ListingPreferenceReasonDefinition::STATUS_ACTIVE),
                displayOrder:      (int) ($reason['display_order'] ?? 0),
            );
        }

        return $built;
    }
}
