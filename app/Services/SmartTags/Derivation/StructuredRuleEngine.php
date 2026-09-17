<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * Evaluates declarative structured rules against one listing's values.
 *
 * Shared by the Bridge and native derivers, so "Vaulted Ceiling(s)" in Bridge
 * InteriorFeatures and in native interior_features resolve through the same
 * code to the same key.
 *
 * RULES OF EVIDENCE
 *   • A rule emits only in contexts its tag (and the rule) allow.
 *   • "Absent" is emitted only by a structured value that explicitly says no,
 *     and only for a negatable tag. A value missing from a checklist emits
 *     nothing: unknown, not no.
 *   • When several rules in ONE source speak about one tag, an authoritative
 *     (Yes/No) answer wins over checklist presence; authoritative answers that
 *     disagree with each other cancel to unknown.
 */
final class StructuredRuleEngine
{
    /**
     * @param array<int, array<string, mixed>> $rules
     * @return TagEvidence[] one per tag, keyed by tag
     */
    public static function evaluate(
        array $rules,
        StructuredValueAccessor $accessor,
        SmartTagContext $context,
        SmartTagListingType $listingType,
        SmartTagSource $source,
    ): array {
        /** @var array<string, TagEvidence[]> $byTag */
        $byTag = [];

        foreach ($rules as $rule) {
            foreach (self::evaluateRule($rule, $accessor, $context, $listingType, $source) as $evidence) {
                $byTag[$evidence->tagKey][] = $evidence;
            }
        }

        $out = [];
        foreach ($byTag as $tag => $items) {
            $merged = self::merge($items);
            if ($merged !== null) {
                $out[$tag] = $merged;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @param array<string, mixed> $rule
     * @return TagEvidence[]
     */
    private static function evaluateRule(
        array $rule,
        StructuredValueAccessor $accessor,
        SmartTagContext $context,
        SmartTagListingType $listingType,
        SmartTagSource $source,
    ): array {
        $kind = (string) ($rule['kind'] ?? '');
        $field = (string) ($rule['column'] ?? $rule['field'] ?? '');
        $ruleId = (string) ($rule['id'] ?? '');

        $emit = static function (string $tag, SmartTagState $state, bool $authoritative, int $confidence) use ($rule, $context, $listingType, $source, $field, $ruleId): ?TagEvidence {
            $definition = SmartTagTaxonomy::get($tag);
            if ($definition === null || ! $definition->isActive()) {
                return null;
            }
            if (! in_array($context, SmartTagSourceRules::ruleContexts($rule, $tag, $listingType), true)) {
                return null;
            }
            if ($state === SmartTagState::Absent && (! $definition->negatable || ! $source->canAssertAbsent())) {
                return null;
            }

            return new TagEvidence($tag, $state, $source, $context, $confidence, $field, $ruleId, $authoritative);
        };

        $present = SmartTagSourceRules::confidence('structured_present', 95);
        $absent = SmartTagSourceRules::confidence('structured_absent', 90);
        $listed = SmartTagSourceRules::confidence('structured_list', 90);
        $tag = isset($rule['tag']) ? (string) $rule['tag'] : '';

        $in = static function (?string $value, array $candidates): bool {
            if ($value === null) {
                return false;
            }
            $needle = NativeMetaValueReader::normalizeOption($value);
            foreach ($candidates as $candidate) {
                if (NativeMetaValueReader::normalizeOption((string) $candidate) === $needle) {
                    return true;
                }
            }
            return false;
        };

        $results = [];

        switch ($kind) {
            case 'boolean':
                $value = $accessor->boolean($rule);
                $authoritative = ($rule['authoritative'] ?? true) === true;
                if ($value === true) {
                    $results[] = $emit($tag, SmartTagState::Present, $authoritative, $present);
                } elseif ($value === false) {
                    $results[] = $emit($tag, SmartTagState::Absent, $authoritative, $absent);
                }
                break;

            case 'equals':
                $value = $accessor->scalar($rule);
                $authoritative = ($rule['authoritative'] ?? false) === true;
                if ($in($value, (array) ($rule['values'] ?? []))) {
                    $results[] = $emit($tag, SmartTagState::Present, $authoritative, $listed);
                } elseif ($in($value, (array) ($rule['negate_values'] ?? []))) {
                    $results[] = $emit($tag, SmartTagState::Absent, $authoritative, $absent);
                }
                break;

            case 'any':
                $values = $accessor->values($rule);
                $authoritative = ($rule['authoritative'] ?? false) === true;
                $hit = false;
                foreach ($values as $value) {
                    if ($in($value, (array) ($rule['values'] ?? []))) {
                        $hit = true;
                        break;
                    }
                }
                if ($hit) {
                    $results[] = $emit($tag, SmartTagState::Present, $authoritative, $listed);
                } else {
                    foreach ($values as $value) {
                        if ($in($value, (array) ($rule['negate_values'] ?? []))) {
                            $results[] = $emit($tag, SmartTagState::Absent, $authoritative, $absent);
                            break;
                        }
                    }
                }
                break;

            case 'prefix':
                $prefix = NativeMetaValueReader::normalizeOption((string) ($rule['prefix'] ?? ''));
                if ($prefix !== '') {
                    foreach ($accessor->values($rule) as $value) {
                        if (str_starts_with(NativeMetaValueReader::normalizeOption($value), $prefix)) {
                            $results[] = $emit($tag, SmartTagState::Present, false, $listed);
                            break;
                        }
                    }
                }
                break;

            case 'nonempty':
                $values = $accessor->values($rule);
                $except = (array) ($rule['except'] ?? []);
                $remaining = array_filter($values, static fn (string $v) => ! $in($v, $except));
                $authoritative = ($rule['authoritative'] ?? false) === true;
                if ($remaining !== []) {
                    $results[] = $emit($tag, SmartTagState::Present, $authoritative, $listed);
                } elseif ($values !== []) {
                    $negate = (array) ($rule['negate_values'] ?? []);
                    $allNegate = $negate !== [] && array_filter($values, static fn (string $v) => ! $in($v, $negate)) === [];
                    if ($allNegate) {
                        $results[] = $emit($tag, SmartTagState::Absent, $authoritative, $absent);
                    }
                }
                break;

            case 'vocab':
                $vocab = SmartTagSourceRules::vocabulary((string) ($rule['vocab'] ?? ''));
                $seen = [];
                foreach ($accessor->values($rule) as $value) {
                    $mapped = $vocab[NativeMetaValueReader::normalizeOption($value)] ?? null;
                    if ($mapped !== null && ! isset($seen[$mapped])) {
                        $seen[$mapped] = true;
                        $results[] = $emit($mapped, SmartTagState::Present, false, $listed);
                    }
                }
                break;

            case 'number_gt':
                $number = $accessor->number($rule);
                if ($number !== null && $number > (float) ($rule['threshold'] ?? 0)) {
                    $results[] = $emit($tag, SmartTagState::Present, false, $listed);
                }
                break;

            case 'flag':
                if ($accessor->flag($rule) === true) {
                    $results[] = $emit($tag, SmartTagState::Present, ($rule['authoritative'] ?? true) === true, $present);
                }
                break;
        }

        return array_values(array_filter($results));
    }

    /**
     * @param TagEvidence[] $items same tag, same source
     */
    private static function merge(array $items): ?TagEvidence
    {
        $authoritative = array_values(array_filter($items, static fn (TagEvidence $e) => $e->authoritative));
        $pool = $authoritative !== [] ? $authoritative : $items;

        $states = array_unique(array_map(static fn (TagEvidence $e) => $e->state->value, $pool));
        if (count($states) > 1) {
            // Authoritative answers that disagree cancel out; a checklist cannot
            // outvote itself. Unknown is the only honest result.
            return null;
        }

        usort($pool, static fn (TagEvidence $a, TagEvidence $b) => [$b->confidence, $a->ruleId] <=> [$a->confidence, $b->ruleId]);

        return $pool[0];
    }
}
