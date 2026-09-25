<?php

namespace Tests\Unit\Stellar\Matching\Parity;

use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Services\Stellar\Matching\Parity\CanonicalParityAllowedDifferences;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\Support\Matching\CanonicalFactsParity;

/**
 * P1-B2 — source-level guards for the OFFLINE canonical matching parity diagnostic.
 *
 *  1. Nothing live reaches it: only the parity namespace and its one Artisan command name
 *     a parity class — no controller, route, job, listener, schedule, service or view.
 *  2. The allowed-difference registry has exactly one definition and parity-only readers;
 *     the P1-B test harness aliases it rather than restating it.
 *  3. The parity code writes nothing, calls no provider or HTTP client, dispatches nothing,
 *     changes no setting, derives no Smart Tag, and never consults CanonicalListingResolver.
 */
class CanonicalParityArchitectureGuardTest extends TestCase
{
    private const PARITY_DIR = 'app/Services/Stellar/Matching/Parity';
    private const COMMAND    = 'app/Console/Commands/MatchingCanonicalParity.php';
    private const REGISTRY   = 'app/Services/Stellar/Matching/Parity/CanonicalParityAllowedDifferences.php';
    private const RUNNER     = 'app/Services/Stellar/Matching/Parity/CanonicalMatchingParityRunner.php';

    private const PARITY_CLASSES = [
        'CanonicalMatchingParityRunner', 'CanonicalParityAllowedDifferences',
        'CanonicalParityCriteriaMatrix', 'CanonicalParityReport', 'Matching\\Parity',
    ];

    /** @var array<string,string> label => pattern, matched against code with comments removed */
    private const FORBIDDEN = [
        'a model or query-builder write'   => '/->\s*(save|update|delete|insert|insertOrIgnore|upsert|forceDelete|increment|decrement|truncate|touch|push|sync|attach|detach)\s*\(/',
        'a static model write'             => '/::\s*(create|forceCreate|insert|upsert|updateOrCreate|firstOrCreate|updateOrInsert|destroy|truncate)\s*\(/',
        'a raw or transactional statement' => '/\bDB\s*::\s*(statement|unprepared|insert|update|delete|transaction|beginTransaction|table)\b/',
        'a schema change'                  => '/\bSchema\s*::/',
        'an HTTP or provider client'       => '/\b(Http|GuzzleHttp|ClientInterface|BridgeApiService|LazyBridgeImportService|BridgeListingLookupService|BridgeRelatedResourceService|refreshByListingKey|importForCriteria)\b|\bcurl_\w+\s*\(/',
        'a dispatch or event'              => '/\b(dispatch|dispatchSync|dispatchNow|event|broadcast)\s*\(|\b(Queue|Bus|Event|Notification|Mail)\s*::/',
        'a setting change'                 => '/\bconfig\s*\(\s*\[|config\s*\(\s*\)\s*->\s*set|\bConfig\s*::\s*set|\bputenv\s*\(/',
        'a cache, storage or log write'    => '/\b(Cache|Storage|Log|Redis|Session)\s*::/',
        'Smart Tag derivation or writing'  => '/\b(SmartTagLifecycle|SmartTagDerivationService|SmartTagEvidenceWriter|SmartTagResolver|SmartTagAssignmentProjector|SmartTagAssignmentPurger|ManualSmartTagWriter|tryDerive\w*|tryPurge)\b/',
        'the generic canonical resolver'   => '/(?<!Mls)\bCanonicalListingResolver\b/',
        'Location DNA'                     => '/\b(ComputeLocationDna|LocationDna\w*)\b/',
    ];

    public function test_only_the_parity_namespace_and_its_command_reference_parity_classes(): void
    {
        $found = [];

        foreach (['app', 'routes', 'config', 'database', 'resources/views'] as $dir) {
            foreach (self::phpFiles($dir) as $relative) {
                if (str_starts_with($relative, self::PARITY_DIR . '/') || $relative === self::COMMAND) {
                    continue;
                }
                $code = self::code($relative);
                foreach (self::PARITY_CLASSES as $needle) {
                    if (str_contains($code, $needle)) {
                        $found[] = "{$relative} → {$needle}";
                    }
                }
            }
        }

        $this->assertSame([], $found, 'the offline parity diagnostic must not be reachable from any live path');
    }

    public function test_the_command_is_neither_scheduled_nor_routed(): void
    {
        $kernel = self::code('app/Console/Kernel.php');
        $this->assertStringNotContainsString('canonical-parity', $kernel);
        $this->assertStringNotContainsString('MatchingCanonicalParity', $kernel);

        foreach (self::phpFiles('routes') as $relative) {
            $this->assertStringNotContainsString('canonical-parity', self::code($relative), $relative);
        }
    }

    public function test_the_parity_code_writes_calls_and_dispatches_nothing(): void
    {
        $files = array_merge(self::phpFiles(self::PARITY_DIR), [self::COMMAND]);
        $this->assertCount(5, $files, 'the four parity classes and the command');

        foreach ($files as $relative) {
            $code = self::code($relative);
            foreach (self::FORBIDDEN as $label => $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $code, "{$relative} reaches for {$label}");
            }
            if ($relative !== self::COMMAND) {
                // Only the command writes, and only the report file the operator names.
                $this->assertDoesNotMatchRegularExpression('/\b(file_put_contents|fopen|fwrite|mkdir|unlink|rename|copy|tempnam)\s*\(/', $code, "{$relative} touches the filesystem");
            }
        }

        $this->assertSame(1, preg_match_all('/\bfile_put_contents\s*\(/', self::code(self::COMMAND)), 'the command writes one file: --output');
    }

    public function test_the_runner_resolves_through_the_explicit_mls_resolver_and_the_live_tag_index_only(): void
    {
        $runner = self::code(self::RUNNER);

        $this->assertStringContainsString('MlsCanonicalListingResolver', $runner);
        $this->assertStringContainsString('->forBridgeProperty(', $runner);
        $this->assertStringContainsString('ListingSmartTagIndex::forBridgeRows(', $runner, 'tags come from the live batched read');
        $this->assertStringContainsString('SmartTagSeekerPreferenceGate::matchingEnabled()', $runner, 'the existing gate decides; the runner never flips it');
        $this->assertStringContainsString('BridgeListingMatchFactsBuilder::build(', $runner, 'facts A is the live builder, unchanged');
    }

    public function test_the_registry_is_defined_once_and_the_harness_aliases_it(): void
    {
        $definitions = [];
        foreach (['app', 'tests'] as $dir) {
            foreach (self::phpFiles($dir) as $relative) {
                if (preg_match('/[\'"]AD-\d+[\'"]\s*=>\s*\[/', self::code($relative))) {
                    $definitions[] = $relative;
                }
            }
        }
        $this->assertSame([self::REGISTRY], $definitions, 'exactly one allowed-difference registry');

        $harness = new class {
            use CanonicalFactsParity;

            public static function registry(): array { return [self::$ALLOWED_DIFFERENCES, self::$COMPARATORS]; }
        };
        [$entries, $comparators] = $harness::registry();

        $this->assertSame(CanonicalParityAllowedDifferences::ENTRIES, $entries);
        $this->assertSame(CanonicalParityAllowedDifferences::COMPARATORS, $comparators);
        $this->assertStringContainsString('CanonicalParityAllowedDifferences::ENTRIES', self::code('tests/Support/Matching/CanonicalFactsParity.php'));
    }

    public function test_the_registry_holds_exactly_the_six_approved_rules_and_nothing_generic(): void
    {
        $this->assertSame(['AD-1', 'AD-2', 'AD-3', 'AD-4', 'AD-5', 'AD-6'], array_keys(CanonicalParityAllowedDifferences::ENTRIES));

        $fields = array_map(static fn ($p) => $p->getName(), (new ReflectionClass(ListingMatchFacts::class))->getConstructor()->getParameters());

        foreach (CanonicalParityAllowedDifferences::ENTRIES as $id => $entry) {
            $this->assertNotEmpty($entry['fields'], "{$id} names its fields");
            $this->assertSame([], array_diff($entry['fields'], $fields), "{$id} names only real ListingMatchFacts fields");
            $this->assertNotContains('smartTags', $entry['fields'], "{$id}: tags are attached after facts, never an allowed difference");
            $this->assertSame([], array_diff($entry['propagates'], CanonicalParityAllowedDifferences::OUTCOME_KEYS), "{$id} propagates only to real outcome keys");
            $this->assertNotContains('exception', $entry['propagates'], "{$id} may never excuse an exception");
            $this->assertNotSame('', trim($entry['reason']));
            $this->assertNotSame('', trim($entry['closes_at']));
        }

        // Every predicate is written out per id — no fallback accepts an unknown one.
        $registry = self::code(self::REGISTRY);
        foreach (array_keys(CanonicalParityAllowedDifferences::ENTRIES) as $id) {
            $this->assertSame(2, preg_match_all("/'{$id}'\\s*=>/", $registry), "{$id}: one entry, one shape predicate of its own");
        }
        $this->assertMatchesRegularExpression('/default\s*=>\s*false/', $registry);
    }

    public function test_the_outcome_keys_are_the_runner_projections_keys(): void
    {
        $runner = self::code(self::RUNNER);
        $this->assertSame(1, preg_match('/private static function project\(.*?return \[(.*?)\];/s', $runner, $m));
        preg_match_all("/'([a-z_]+)'\\s*=>/", $m[1], $keys);

        $this->assertSame(CanonicalParityAllowedDifferences::OUTCOME_KEYS, $keys[1]);
    }

    public function test_the_registry_is_read_only_by_parity_code(): void
    {
        $readers = [];
        foreach (self::phpFiles('app') as $relative) {
            if ($relative !== self::REGISTRY && str_contains(self::code($relative), 'CanonicalParityAllowedDifferences')) {
                $readers[] = $relative;
            }
        }

        $this->assertSame([self::RUNNER], $readers);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** @return list<string> repo-relative .php paths under $dir */
    private static function phpFiles(string $dir): array
    {
        $root = self::root();
        if (!is_dir("{$root}/{$dir}")) {
            return [];
        }

        $out = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $out[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($out);

        return $out;
    }

    /** The file's code with comments removed. */
    private static function code(string $relative): string
    {
        $code = '';
        foreach (token_get_all((string) file_get_contents(self::root() . '/' . $relative)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    private static function root(): string
    {
        return dirname(__DIR__, 5);
    }
}
