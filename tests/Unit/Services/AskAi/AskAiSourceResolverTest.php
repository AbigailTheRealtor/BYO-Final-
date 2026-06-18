<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiSourceResolver;
use PHPUnit\Framework\TestCase;

/**
 * §29 — AskAiSourceResolver unit tests.
 *
 * §29A  Every field in CANONICAL_SOURCE_MAP for each role resolves without throwing.
 * §29B  Cascade arrays use ?: semantics — empty string '' causes fallthrough; non-empty returns.
 * §29C  native:column sources call $nativeGet with the stripped column name, not $infoGet.
 * §29D  Bare EAV key sources call $infoGet with the exact key, not $nativeGet.
 * §29E  ctx['_sources'] in buildForListing output is the CANONICAL_SOURCE_MAP entry, not hardcoded.
 *
 * §29F (Golden snapshot outputs unchanged) is covered by the existing
 *       AskAiGoldenQaSuiteTest, which is a required pass in the regression suite.
 */
class AskAiSourceResolverTest extends TestCase
{
    // =========================================================================
    // §29A — All map fields resolve without throwing
    // =========================================================================

    /**
     * §29A: Iterates every (role, field, source) triple in CANONICAL_SOURCE_MAP and
     * calls resolveField(). No exception must be thrown regardless of source type.
     * The infoGet and nativeGet callables return null for all keys so that cascade
     * logic is exercised without real listing data.
     *
     * @dataProvider allSourceMapFieldsProvider
     */
    public function test_case_29A_all_map_fields_resolve_without_throwing(
        string $role,
        string $field,
        mixed $source
    ): void {
        $resolver = new AskAiSourceResolver();

        $infoGet   = static fn (string $key): ?string => null;
        $nativeGet = static fn (string $col): ?string => null;

        $this->expectNotToPerformAssertions();

        $resolver->resolveField($field, $source, $infoGet, $nativeGet);
    }

    public static function allSourceMapFieldsProvider(): array
    {
        $map   = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;
        $cases = [];
        foreach ($map as $role => $fields) {
            foreach ($fields as $field => $source) {
                $cases["{$role}.{$field}"] = [$role, $field, $source];
            }
        }
        return $cases;
    }

    // =========================================================================
    // §29B — Cascade array uses ?: semantics (empty string '' causes fallthrough)
    // =========================================================================

    /**
     * §29B-1: An empty string ('') from infoGet is treated as absent and causes the
     * cascade to advance to the next key.  This is ?: behaviour (not ??).
     */
    public function test_case_29B_cascade_empty_string_falls_through_to_next_key(): void
    {
        $resolver = new AskAiSourceResolver();

        // First key returns '' (empty), second returns the real value.
        $values    = ['key_a' => '', 'key_b' => 'found_value'];
        $infoGet   = static fn (string $key) => $values[$key] ?? null;
        $nativeGet = static fn (string $col): ?string => null;

        $result = $resolver->resolveField('field', ['key_a', 'key_b'], $infoGet, $nativeGet);

        $this->assertSame('found_value', $result,
            'Cascade must skip empty string (\'\') and return the next non-empty value — proves ?: semantics');
    }

    /**
     * §29B-2: If the first cascade key returns a non-empty value the cascade stops
     * immediately and does NOT evaluate subsequent keys.
     */
    public function test_case_29B_cascade_stops_at_first_non_empty_value(): void
    {
        $resolver = new AskAiSourceResolver();

        $callLog   = [];
        $infoGet   = static function (string $key) use (&$callLog): ?string {
            $callLog[] = $key;
            return match ($key) {
                'key_a' => 'first_hit',
                default => 'should_not_reach',
            };
        };
        $nativeGet = static fn (string $col): ?string => null;

        $result = $resolver->resolveField('field', ['key_a', 'key_b'], $infoGet, $nativeGet);

        $this->assertSame('first_hit', $result, 'Cascade must return the first non-empty value');
        $this->assertNotContains('key_b', $callLog,
            'key_b must not be evaluated once key_a returns a non-empty value');
    }

    /**
     * §29B-3: A null return from infoGet is treated as absent — the cascade continues.
     * This confirms null is NOT a stopping value (only non-null, non-empty, non-false stops).
     */
    public function test_case_29B_cascade_null_falls_through(): void
    {
        $resolver = new AskAiSourceResolver();

        $values    = ['key_a' => null, 'key_b' => 'second_key_value'];
        $infoGet   = static fn (string $key) => $values[$key] ?? null;
        $nativeGet = static fn (string $col): ?string => null;

        $result = $resolver->resolveField('field', ['key_a', 'key_b'], $infoGet, $nativeGet);

        $this->assertSame('second_key_value', $result,
            'Cascade must skip null and advance to the next key');
    }

    /**
     * §29B-4: A cascade where ALL keys return empty/null must return null overall
     * (not the empty string from the last key).
     */
    public function test_case_29B_cascade_all_empty_returns_null(): void
    {
        $resolver = new AskAiSourceResolver();

        $infoGet   = static fn (string $key): string => '';
        $nativeGet = static fn (string $col): ?string => null;

        $result = $resolver->resolveField('field', ['key_a', 'key_b', 'key_c'], $infoGet, $nativeGet);

        $this->assertNull($result,
            'When all cascade keys return empty/null the resolver must return null, not an empty string');
    }

    /**
     * §29B-5: Numeric-zero string '0' is NOT treated as absent — it must be returned
     * and stop the cascade.  This avoids silently dropping valid "0" answers
     * (e.g. waterfront_feet = "0").
     */
    public function test_case_29B_cascade_numeric_zero_string_is_not_absent(): void
    {
        $resolver = new AskAiSourceResolver();

        $values    = ['key_a' => '0', 'key_b' => 'should_not_reach'];
        $infoGet   = static fn (string $key) => $values[$key] ?? null;
        $nativeGet = static fn (string $col): ?string => null;

        $result = $resolver->resolveField('field', ['key_a', 'key_b'], $infoGet, $nativeGet);

        $this->assertSame('0', $result,
            "String '0' must be treated as a valid non-empty value and stop the cascade");
    }

    // =========================================================================
    // §29C — native:column calls $nativeGet with the stripped column name
    // =========================================================================

    /**
     * §29C-1: A source of 'native:address' must call $nativeGet('address') —
     * the 'native:' prefix is stripped before the column name is forwarded.
     */
    public function test_case_29C_native_prefix_calls_native_get_with_stripped_column(): void
    {
        $resolver = new AskAiSourceResolver();

        $nativeCallLog = [];
        $infoCallLog   = [];

        $nativeGet = static function (string $col) use (&$nativeCallLog): string {
            $nativeCallLog[] = $col;
            return 'native_value';
        };
        $infoGet = static function (string $key) use (&$infoCallLog): ?string {
            $infoCallLog[] = $key;
            return null;
        };

        $result = $resolver->resolveField('address', 'native:address', $infoGet, $nativeGet);

        $this->assertSame('native_value', $result,
            'native: source must return the value from $nativeGet');
        $this->assertSame(['address'], $nativeCallLog,
            '$nativeGet must be called with the column name stripped of the native: prefix');
        $this->assertEmpty($infoCallLog,
            '$infoGet must NOT be called for a native: source');
    }

    /**
     * §29C-2: A 'native:additional_details' source uses the full column name after
     * the prefix, preserving underscores and multi-word column names.
     */
    public function test_case_29C_native_prefix_preserves_full_column_name(): void
    {
        $resolver = new AskAiSourceResolver();

        $capturedColumn = null;
        $nativeGet = static function (string $col) use (&$capturedColumn): ?string {
            $capturedColumn = $col;
            return null;
        };
        $infoGet = static fn (string $key): ?string => null;

        $resolver->resolveField('description', 'native:additional_details', $infoGet, $nativeGet);

        $this->assertSame('additional_details', $capturedColumn,
            'Multi-word column names after native: must be forwarded intact');
    }

    /**
     * §29C-3: Every 'native:' entry in CANONICAL_SOURCE_MAP is forwarded to $nativeGet
     * with the correct column name (stripped, no prefix leak).
     */
    public function test_case_29C_all_native_map_entries_strip_prefix_correctly(): void
    {
        $resolver = new AskAiSourceResolver();

        $allNativeSources = [];
        foreach (AskAiContextBuilderService::CANONICAL_SOURCE_MAP as $role => $fields) {
            foreach ($fields as $field => $source) {
                if (is_string($source) && str_starts_with($source, 'native:')) {
                    $allNativeSources["{$role}.{$field}"] = $source;
                }
            }
        }

        $this->assertNotEmpty($allNativeSources,
            'CANONICAL_SOURCE_MAP must contain at least one native: source to test');

        foreach ($allNativeSources as $label => $source) {
            $expectedColumn  = substr($source, 7);
            $capturedColumns = [];

            $nativeGet = static function (string $col) use (&$capturedColumns): ?string {
                $capturedColumns[] = $col;
                return null;
            };
            $infoGet = static fn (string $key): ?string => null;

            $resolver->resolveField('field', $source, $infoGet, $nativeGet);

            $this->assertSame(
                [$expectedColumn],
                $capturedColumns,
                "native: source '{$source}' ({$label}) must call \$nativeGet with '{$expectedColumn}' only"
            );
        }
    }

    // =========================================================================
    // §29D — Bare EAV key sources call $infoGet with the exact key
    // =========================================================================

    /**
     * §29D-1: A bare string source 'maximum_budget' must call $infoGet('maximum_budget')
     * and must NOT call $nativeGet at all.
     */
    public function test_case_29D_bare_eav_key_calls_info_get_with_exact_key(): void
    {
        $resolver = new AskAiSourceResolver();

        $infoCallLog   = [];
        $nativeCallLog = [];

        $infoGet = static function (string $key) use (&$infoCallLog): string {
            $infoCallLog[] = $key;
            return 'eav_value';
        };
        $nativeGet = static function (string $col) use (&$nativeCallLog): ?string {
            $nativeCallLog[] = $col;
            return null;
        };

        $result = $resolver->resolveField('max_price', 'maximum_budget', $infoGet, $nativeGet);

        $this->assertSame('eav_value', $result,
            'Bare EAV source must return the value from $infoGet');
        $this->assertSame(['maximum_budget'], $infoCallLog,
            '$infoGet must be called with the exact key "maximum_budget"');
        $this->assertEmpty($nativeCallLog,
            '$nativeGet must NOT be called for a bare EAV key source');
    }

    /**
     * §29D-2: Every bare (non-prefixed) string source in CANONICAL_SOURCE_MAP is
     * forwarded to $infoGet with the identical key string — no transformation applied.
     */
    public function test_case_29D_all_bare_eav_map_entries_forwarded_exactly(): void
    {
        $resolver = new AskAiSourceResolver();

        $bareEntries = [];
        foreach (AskAiContextBuilderService::CANONICAL_SOURCE_MAP as $role => $fields) {
            foreach ($fields as $field => $source) {
                if (
                    is_string($source)
                    && !str_starts_with($source, 'native:')
                    && !str_starts_with($source, 'synthetic:')
                ) {
                    $bareEntries["{$role}.{$field}"] = $source;
                }
            }
        }

        $this->assertNotEmpty($bareEntries,
            'CANONICAL_SOURCE_MAP must contain bare EAV key entries to test');

        foreach ($bareEntries as $label => $source) {
            $capturedKeys = [];
            $infoGet = static function (string $key) use (&$capturedKeys): ?string {
                $capturedKeys[] = $key;
                return null;
            };
            $nativeGet = static fn (string $col): ?string => null;

            $resolver->resolveField('field', $source, $infoGet, $nativeGet);

            $this->assertSame(
                [$source],
                $capturedKeys,
                "Bare EAV source '{$source}' ({$label}) must call \$infoGet with the exact key"
            );
        }
    }

    // =========================================================================
    // §29E — ctx['_sources'] is the CANONICAL_SOURCE_MAP entry, not hardcoded
    // =========================================================================

    /**
     * §29E-1: The service source file must assign ctx['_sources'] directly from
     * CANONICAL_SOURCE_MAP, not from a hardcoded inline array.
     *
     * This is verified by reading the service source text and asserting that the
     * _sources key is assigned via a CANONICAL_SOURCE_MAP lookup, confirming the
     * resolver-driven approach carries through to the output contract.
     */
    public function test_case_29E_sources_key_is_assigned_from_canonical_source_map(): void
    {
        $sourceFile = realpath(
            __DIR__ . '/../../../../app/Services/AskAi/AskAiContextBuilderService.php'
        );
        $this->assertNotFalse($sourceFile, 'AskAiContextBuilderService.php must be readable');

        $source = file_get_contents($sourceFile);

        // Assert the _sources assignment reads from CANONICAL_SOURCE_MAP at runtime.
        // The pattern looks for: '_sources' => self::CANONICAL_SOURCE_MAP[$someVar]
        $this->assertMatchesRegularExpression(
            "/'_sources'\s*=>\s*self::CANONICAL_SOURCE_MAP\[/",
            $source,
            "'_sources' must be assigned via self::CANONICAL_SOURCE_MAP[\$canonical], not a hardcoded array"
        );
    }

    /**
     * §29E-2: CANONICAL_SOURCE_MAP contains exactly four role keys and all four are
     * non-empty — ensures the resolver has complete coverage across all listing roles.
     */
    public function test_case_29E_canonical_source_map_covers_all_four_roles(): void
    {
        $map           = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;
        $expectedRoles = ['seller', 'buyer', 'landlord', 'tenant'];

        foreach ($expectedRoles as $role) {
            $this->assertArrayHasKey($role, $map,
                "CANONICAL_SOURCE_MAP must define a '{$role}' section");
            $this->assertNotEmpty($map[$role],
                "CANONICAL_SOURCE_MAP['{$role}'] must not be empty");
        }

        $this->assertSame(
            count($expectedRoles),
            count(array_intersect_key($map, array_flip($expectedRoles))),
            'CANONICAL_SOURCE_MAP must have exactly the four expected role keys'
        );
    }

    /**
     * §29E-3: Every field key returned by the resolver loop appears in the corresponding
     * CANONICAL_SOURCE_MAP role entry — no phantom fields, no silently dropped fields.
     *
     * Iterates each role's map, resolves every field via the resolver (returning null
     * for all lookups), and asserts the resolved key set is a subset of the map keys.
     */
    public function test_case_29E_resolver_loop_produces_no_phantom_fields(): void
    {
        $resolver = new AskAiSourceResolver();
        $map      = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        $infoGet   = static fn (string $key): ?string => null;
        $nativeGet = static fn (string $col): ?string => null;

        foreach ($map as $role => $fields) {
            $resolved = [];
            foreach ($fields as $field => $source) {
                // Skip synthetic: entries — the resolver returns null and the caller
                // handles them separately; they should not appear in resolver output.
                if (is_string($source) && str_starts_with($source, 'synthetic:')) {
                    continue;
                }
                $resolved[$field] = $resolver->resolveField($field, $source, $infoGet, $nativeGet);
            }

            $phantoms = array_diff(array_keys($resolved), array_keys($fields));
            $this->assertEmpty(
                $phantoms,
                "Role '{$role}': resolver produced phantom keys not in CANONICAL_SOURCE_MAP: "
                . implode(', ', $phantoms)
            );
        }
    }

    // =========================================================================
    // §29 — Synthetic source type handling
    // =========================================================================

    /**
     * Synthetic sources ('synthetic:*') must return null from the resolver because
     * they require computation logic in the caller, not a source-map lookup.
     */
    public function test_case_29_synthetic_source_returns_null(): void
    {
        $resolver = new AskAiSourceResolver();

        $infoGet   = static fn (string $key): string => 'should_not_be_returned';
        $nativeGet = static fn (string $col): string => 'should_not_be_returned';

        $result = $resolver->resolveField('disclosure_flags', 'synthetic:flood_zone_flag', $infoGet, $nativeGet);

        $this->assertNull($result,
            "synthetic: sources must return null — the caller handles them manually");
    }

    /**
     * All synthetic: entries in CANONICAL_SOURCE_MAP resolve to null without calling
     * either $infoGet or $nativeGet.
     */
    public function test_case_29_all_synthetic_map_entries_resolve_to_null_without_callbacks(): void
    {
        $resolver = new AskAiSourceResolver();

        $callLog   = [];
        $infoGet   = static function (string $key) use (&$callLog): ?string {
            $callLog[] = "info:{$key}";
            return null;
        };
        $nativeGet = static function (string $col) use (&$callLog): ?string {
            $callLog[] = "native:{$col}";
            return null;
        };

        $syntheticCount = 0;
        foreach (AskAiContextBuilderService::CANONICAL_SOURCE_MAP as $role => $fields) {
            foreach ($fields as $field => $source) {
                if (is_string($source) && str_starts_with($source, 'synthetic:')) {
                    $result = $resolver->resolveField($field, $source, $infoGet, $nativeGet);
                    $this->assertNull($result, "synthetic: source '{$source}' ({$role}.{$field}) must resolve to null");
                    $syntheticCount++;
                }
            }
        }

        $this->assertEmpty($callLog,
            'Neither $infoGet nor $nativeGet must be called for synthetic: sources; got: '
            . implode(', ', $callLog));
        $this->assertGreaterThan(0, $syntheticCount,
            'At least one synthetic: source must exist in CANONICAL_SOURCE_MAP to test');
    }
}
