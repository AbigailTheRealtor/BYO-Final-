<?php

namespace App\Services\SmartTags\Seeker;

use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\NativeMetaValueReader;
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
 *       – THAT RULE IS DECLARED ABLE TO PROVE THE TAG ABSENT
 *         (`bridge.negative_evidence`, read through {@see SmartTagSourceRules::negativeEvidence()}).
 *         Emitting a tag and ruling it out are separate claims: Stellar's
 *         InteriorFeatures can say "Quartz Counters" but never carries it, so its
 *         silence proves nothing. Undeclared means it cannot — fail-closed;
 *       – that rule's source field carries an INFORMATIVE value on THIS record, read
 *         through the same accessor reading the rule engine uses
 *         ({@see BridgeRecordAccessor::populated()}): not only `uninformative` tokens
 *         ("Other"), and no `masked_by` value that could stand for the tag ("Vinyl");
 *       – the stored resolution is CURRENT for this record: same tagger version,
 *         same context, same structured-inputs hash — the derivation service's own
 *         staleness rule, so the rows were derived from exactly these values;
 *       – the tag was not dropped by a cross-tag conflict (it has present evidence
 *         but no assignment) and could not have been cancelled by contradictory
 *         authoritative answers;
 *   • anything else is UNKNOWN: no rule, empty field, a field that cannot say no,
 *     only uninformative values, a masking value, never derived, stale, wrong
 *     context, pending/inactive/retired. {@see self::explain()} says which.
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

    /** Why a pick is UNKNOWN — {@see self::explain()}. */
    public const WHY_NOT_ELIGIBLE = 'not_seeker_eligible';
    public const WHY_NOT_CURRENT = 'not_current';
    public const WHY_CONFLICT = 'conflict';
    public const WHY_NO_RULE = 'no_structured_rule';
    public const WHY_FIELD_UNAVAILABLE = 'field_unavailable';
    public const WHY_NO_NEGATIVE_EVIDENCE = 'no_negative_evidence';
    public const WHY_UNINFORMATIVE = 'uninformative_only';
    public const WHY_MASKED = 'masked';

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
     * Whether ANY governed rule may ever prove this tag absent in this context — i.e.
     * whether a known miss is possible at all. A tag with structured capability but no
     * negative capability can be present or unknown, never "Does not list".
     */
    public static function hasNegativeCapability(string $tag, SmartTagContext $context): bool
    {
        foreach (self::rulesFor($tag, $context) as $rule) {
            if (SmartTagSourceRules::negativeEvidence($rule, $tag) !== null) {
                return true;
            }
        }

        return false;
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

    /**
     * Why $key is UNKNOWN on this record (one of the WHY_* constants), or null when it is
     * present or a known non-match. The same decision {@see self::classify()} makes.
     *
     * @param array<string, SmartTagState> $resolved
     * @param array<string, true>          $dropped
     */
    public static function explain(
        ?BridgeRecordAccessor $record,
        ?SmartTagContext $context,
        string $key,
        array $resolved,
        array $dropped,
        bool $currentForRecord,
    ): ?string {
        return self::decide($record, $context, $key, $resolved[$key] ?? null, isset($dropped[$key]), $currentForRecord)[1];
    }

    private static function classifyOne(
        ?BridgeRecordAccessor $record,
        ?SmartTagContext $context,
        string $key,
        ?SmartTagState $resolved,
        bool $dropped,
        bool $currentForRecord,
    ): string {
        return self::decide($record, $context, $key, $resolved, $dropped, $currentForRecord)[0];
    }

    /**
     * @return array{0: string, 1: string|null} the answer, and why when it is UNKNOWN
     */
    private static function decide(
        ?BridgeRecordAccessor $record,
        ?SmartTagContext $context,
        string $key,
        ?SmartTagState $resolved,
        bool $dropped,
        bool $currentForRecord,
    ): array {
        $definition = SmartTagTaxonomy::get($key);

        if ($definition === null || ! $definition->isSeekerSelectable()) {
            return [self::UNKNOWN, self::WHY_NOT_ELIGIBLE];
        }

        if ($resolved === SmartTagState::Present) {
            return [self::PRESENT, null];
        }

        // A stored absent is an explicit answer the rule engine recorded (a Y/N "No",
        // PetsAllowed "No") — evidence, not inference.
        if ($resolved === SmartTagState::Absent) {
            return [self::KNOWN_ABSENT, null];
        }

        if ($record === null || $context === null || ! $currentForRecord) {
            return [self::UNKNOWN, self::WHY_NOT_CURRENT];
        }

        if ($dropped) {
            return [self::UNKNOWN, self::WHY_CONFLICT];
        }

        $rules = self::rulesFor($key, $context);

        if ($rules === []) {
            return [self::UNKNOWN, self::WHY_NO_RULE];
        }

        if (self::couldHaveCancelled($definition->negatable, $rules, $record)) {
            return [self::UNKNOWN, self::WHY_CONFLICT];
        }

        // Most specific reason wins when no rule proves absence.
        $why = self::WHY_FIELD_UNAVAILABLE;
        $rank = [self::WHY_FIELD_UNAVAILABLE => 0, self::WHY_NO_NEGATIVE_EVIDENCE => 1, self::WHY_UNINFORMATIVE => 2, self::WHY_MASKED => 3];

        foreach ($rules as $rule) {
            $verdict = self::absenceVerdict($rule, $key, $record);

            if ($verdict === null) {
                return [self::KNOWN_ABSENT, null];
            }

            if ($rank[$verdict] > $rank[$why]) {
                $why = $verdict;
            }
        }

        return [self::UNKNOWN, $why];
    }

    /**
     * Null when this rule proves $tag absent on this record; otherwise why it does not.
     *
     * @param array<string, mixed> $rule
     */
    private static function absenceVerdict(array $rule, string $tag, BridgeRecordAccessor $record): ?string
    {
        if (! $record->populated($rule)) {
            return self::WHY_FIELD_UNAVAILABLE;
        }

        $evidence = SmartTagSourceRules::negativeEvidence($rule, $tag);

        if ($evidence === null) {
            return self::WHY_NO_NEGATIVE_EVIDENCE;
        }

        $kind = $rule['kind'] ?? null;

        if ($kind === 'equals') {
            // A single-select value: the same uninformative / mask tokens apply to it.
            $raw = [(string) $record->scalar($rule)];
        } elseif (in_array($kind, self::LIST_KINDS, true)) {
            $raw = $record->values($rule);
        } else {
            return null; // a Y/N or a count: populated is the answer
        }

        $values = array_map(
            static fn (string $v) => NativeMetaValueReader::normalizeOption($v),
            $raw,
        );

        if (array_intersect($values, $evidence['masked_by']) !== []) {
            return self::WHY_MASKED;
        }

        if (array_diff($values, $evidence['uninformative']) === []) {
            return self::WHY_UNINFORMATIVE;
        }

        return null;
    }

    /** Rule kinds that read a list of values ({@see BridgeRecordAccessor::values()}). */
    private const LIST_KINDS = ['vocab', 'any', 'prefix', 'nonempty'];

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
