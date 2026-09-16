<?php

namespace App\Services\SmartTags\Derivation;

/**
 * How the structured rule engine reads one listing's values, whatever the store.
 *
 * Each method receives the rule, so an accessor can decide how the rule's
 * `field` (or `column`) is addressed in its own storage.
 */
interface StructuredValueAccessor
{
    /** @param array<string, mixed> $rule */
    public function boolean(array $rule): ?bool;

    /** @param array<string, mixed> $rule */
    public function scalar(array $rule): ?string;

    /**
     * @param array<string, mixed> $rule
     * @return string[]
     */
    public function values(array $rule): array;

    /** @param array<string, mixed> $rule */
    public function number(array $rule): ?float;

    /** @param array<string, mixed> $rule */
    public function flag(array $rule): ?bool;

    /**
     * The inputs a set of rules reads, for change detection.
     *
     * THE CONTRACT IS STABILITY UNDER EQUAL MEANING: two records the rules would
     * read identically must produce an identical array, whatever representation
     * the underlying store happened to hand back. An accessor whose store can
     * return one logical value in more than one shape must canonicalise here —
     * {@see BridgeRecordAccessor::inputsFor()} does, because a boolean column
     * reads back as PHP `true` from a just-written model and as `1` from a
     * re-read one, and hashing that difference made unchanged rows look changed.
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array<string, mixed>
     */
    public function inputsFor(array $rules): array;
}
