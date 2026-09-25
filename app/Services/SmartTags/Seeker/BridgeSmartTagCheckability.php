<?php

namespace App\Services\SmartTags\Seeker;

use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * Could Bridge data CHECK a seeker's selected tag on this listing? Present, known
 * non-match, or unknown — per listing, per tag.
 *
 * STORAGE STAYS THREE-STATE. `smart_tag_assignments` holds present, absent, or no
 * row, and a missing row alone never says "absent": vocabulary rules emit nothing
 * for a value a checklist omits. This class adds CHECKABILITY beside that storage,
 * computed at input-construction time from things that already exist:
 *
 *   • a resolved PRESENT row → present; a resolved ABSENT row → known non-match;
 *   • otherwise the tag is a KNOWN NON-MATCH only when ALL of these hold:
 *       – the tag is seeker-selectable (active, not pending review);
 *       – a governed structured Bridge rule can emit it in this listing's context
 *         (config/smart_tag_sources.php `bridge.rules` — never today's frequency);
 *       – that rule's source field is populated on THIS record, read through the
 *         same accessor reading the rule engine uses ({@see BridgeRecordAccessor::populated()});
 *       – the stored resolution is CURRENT for this record: same tagger version,
 *         same context, same structured-inputs hash — the derivation service's own
 *         staleness rule, so the rows were derived from exactly these values;
 *       – the tag was not dropped by a cross-tag conflict (it has present evidence
 *         but no assignment) and could not have been cancelled by contradictory
 *         authoritative answers;
 *   • anything else is UNKNOWN: no rule, empty field, never derived, stale, wrong
 *     context, pending/inactive/retired.
 *
 * It never runs the rule engine and restates none of its matching: "populated" is
 * whether there was anything to look at, and the engine's own stored answer says
 * what it found. Pure: no query, no container.
 */
final class BridgeSmartTagCheckability
{
    public const PRESENT = 'present';
    public const KNOWN_ABSENT = 'known_absent';
    public const UNKNOWN = 'unknown';

    /** @var array{0: array<int, array<string, mixed>>, 1: array<string, list<array<string, mixed>>>}|null the rules it was built from, and tag => rules */
    private static ?array $rulesByTag = null;

    /**
     * The governed structured Bridge rules that may emit this tag in this context.
     * Empty for a tag that is not customer-eligible (inactive, retired, pending
     * review, not seeker-selectable) — such a tag is never checkable.
     *
     * @return list<array<string, mixed>>
     */
    public static function rulesFor(string $tag, SmartTagContext $context): array
    {
        $definition = SmartTagTaxonomy::get($tag);

        if ($definition === null || ! $definition->isSeekerSelectable() || ! $definition->appliesTo($context)) {
            return [];
        }

        return array_values(array_filter(
            self::rulesByTag()[$tag] ?? [],
            static fn (array $rule): bool => in_array($context, SmartTagSourceRules::ruleContexts($rule, $tag, SmartTagListingType::Bridge), true),
        ));
    }

    public static function hasStructuredCapability(string $tag, SmartTagContext $context): bool
    {
        return self::rulesFor($tag, $context) !== [];
    }

    /**
     * @param list<string>                $keys          the seeker's selected keys
     * @param array<string, SmartTagState> $resolved      resolved state per key, as stored (or simulated)
     * @param array<string, true>          $dropped       keys with present evidence but no assignment (conflict-dropped)
     * @param bool                         $currentForRecord whether $resolved was derived from exactly this record's inputs
     * @return array<string, string> key => PRESENT | KNOWN_ABSENT | UNKNOWN, in $keys order
     */
    public static function classify(
        ?BridgeRecordAccessor $record,
        ?SmartTagContext $context,
        array $keys,
        array $resolved,
        array $dropped,
        bool $currentForRecord,
    ): array {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = self::classifyOne($record, $context, $key, $resolved[$key] ?? null, isset($dropped[$key]), $currentForRecord);
        }

        return $out;
    }

    private static function classifyOne(
        ?BridgeRecordAccessor $record,
        ?SmartTagContext $context,
        string $key,
        ?SmartTagState $resolved,
        bool $dropped,
        bool $currentForRecord,
    ): string {
        $definition = SmartTagTaxonomy::get($key);

        if ($definition === null || ! $definition->isSeekerSelectable()) {
            return self::UNKNOWN;
        }

        if ($resolved === SmartTagState::Present) {
            return self::PRESENT;
        }

        if ($resolved === SmartTagState::Absent) {
            return self::KNOWN_ABSENT;
        }

        if ($record === null || $context === null || ! $currentForRecord || $dropped) {
            return self::UNKNOWN;
        }

        $rules = self::rulesFor($key, $context);

        if ($rules === [] || self::couldHaveCancelled($definition->negatable, $rules, $record)) {
            return self::UNKNOWN;
        }

        foreach ($rules as $rule) {
            if ($record->populated($rule)) {
                return self::KNOWN_ABSENT;
            }
        }

        return self::UNKNOWN;
    }

    /**
     * The rule engine cancels a tag to "no evidence" when its rules disagree
     * (StructuredRuleEngine::merge()). That needs a second rule AND an "absent" from
     * a populated absent-capable rule on a negatable tag. When that is possible here,
     * a missing row may be a contradiction rather than a silence: unknown.
     *
     * @param list<array<string, mixed>> $rules
     */
    private static function couldHaveCancelled(bool $negatable, array $rules, BridgeRecordAccessor $record): bool
    {
        if (! $negatable || count($rules) < 2) {
            return false;
        }

        foreach ($rules as $rule) {
            $canAssertAbsent = in_array($rule['kind'] ?? null, ['boolean', 'flag'], true) || ! empty($rule['negate_values']);

            if ($canAssertAbsent && $record->populated($rule)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private static function rulesByTag(): array
    {
        $rules = SmartTagSourceRules::bridgeRules();

        // Rebuilt whenever the governed rules differ from the ones it was built from,
        // so a config change can never leave a stale capability map behind.
        if (self::$rulesByTag === null || self::$rulesByTag[0] !== $rules) {
            $map = [];

            foreach ($rules as $rule) {
                foreach (SmartTagSourceRules::tagsEmittableBy($rule) as $tag) {
                    $map[$tag][] = $rule;
                }
            }

            self::$rulesByTag = [$rules, $map];
        }

        return self::$rulesByTag[1];
    }
}
