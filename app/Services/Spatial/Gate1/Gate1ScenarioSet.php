<?php

namespace App\Services\Spatial\Gate1;

use InvalidArgumentException;

/**
 * Gate1ScenarioSet — an ordered, non-empty collection of Gate1Scenario.
 *
 * PART OF: Phase 2 Batch 2D Part B — Hybrid Gate 1 Harness, Option D (synthetic benchmark).
 *
 * LOADING
 * -------
 * `fromArray()` adapts a decoded fixture (`{ _meta, scenarios: [...] }`); `fromJsonFile()` reads
 * that fixture off disk. File reading is the ONLY I/O in this Gate 1 subsystem — no DB, no
 * network, no PostGIS, no secrets. The path is always passed in explicitly (by the command or a
 * test); nothing here reaches for an ambient location.
 *
 * FAIL CLOSED
 * -----------
 * An empty set is rejected (erratum E-41 discipline: a harness that evaluates nothing must not
 * report a pass). A malformed scenario throws at construction, naming the offender.
 */
final class Gate1ScenarioSet
{
    /**
     * @param  list<Gate1Scenario>  $scenarios
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        private readonly array $scenarios,
        private readonly array $meta,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Decoded fixture: { _meta?: array, scenarios: array }.
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['scenarios']) || ! is_array($data['scenarios']) || $data['scenarios'] === []) {
            throw new InvalidArgumentException('Gate1ScenarioSet requires a non-empty `scenarios` array (fail closed).');
        }

        $scenarios = [];
        $keys      = [];

        foreach (array_values($data['scenarios']) as $i => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException("Gate1ScenarioSet scenario #{$i} is not an object.");
            }

            $scenario = Gate1Scenario::fromArray($raw);

            if (isset($keys[$scenario->key()])) {
                throw new InvalidArgumentException("Gate1ScenarioSet has a duplicate scenario key '{$scenario->key()}'.");
            }
            $keys[$scenario->key()] = true;

            $scenarios[] = $scenario;
        }

        return new self($scenarios, is_array($data['_meta'] ?? null) ? $data['_meta'] : []);
    }

    public static function fromJsonFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Gate1 scenario fixture not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Gate1 scenario fixture is not valid JSON: {$path}");
        }

        return self::fromArray($decoded);
    }

    /** @return list<Gate1Scenario> */
    public function all(): array
    {
        return $this->scenarios;
    }

    public function count(): int
    {
        return count($this->scenarios);
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }
}
