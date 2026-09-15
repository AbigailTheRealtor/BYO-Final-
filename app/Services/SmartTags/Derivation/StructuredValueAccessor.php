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
     * The raw inputs a set of rules reads, for change detection.
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array<string, mixed>
     */
    public function inputsFor(array $rules): array;
}
