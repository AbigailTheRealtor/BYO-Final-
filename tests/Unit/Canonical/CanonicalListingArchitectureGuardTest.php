<?php

namespace Tests\Unit\Canonical;

use App\Services\Canonical\CanonicalListingVocabulary as V;
use PHPUnit\Framework\TestCase;

/**
 * P0-4 guard — the canonical listing namespace stays source-neutral and BYO-first.
 *
 * Deliberately narrow: it scans only app/Services/Canonical, and only CODE
 * tokens (comments stripped), because the docblocks there name BridgeProperty
 * and Stellar precisely to explain why neither is read.
 */
class CanonicalListingArchitectureGuardTest extends TestCase
{
    private const NAMESPACE_DIR = __DIR__ . '/../../../app/Services/Canonical';

    /**
     * P0-5: the ONE file allowed to read the MLS ingestion DTO. It consumes a
     * PropertyCandidate's typed fields and nothing else — no Bridge class, no
     * source table, no `$raw` — so the rest of the namespace stays exactly as
     * Bridge-independent as P0-4 left it.
     */
    private const MLS_INPUT_EXCEPTION = 'Adapters/MlsListingAdapter.php';

    /** Forbidden everywhere in the namespace, the exception included. */
    private const BRIDGE_INFRASTRUCTURE = ['BridgeProperty', 'App\\Services\\Bridge', 'BridgeApiService'];

    public function test_the_canonical_namespace_has_no_bridge_or_mls_provider_dependency(): void
    {
        $this->assertSame([], $this->mlsBoundaryViolations($this->codeByFile()));
    }

    public function test_the_mls_input_exception_is_exactly_one_file_and_it_uses_it(): void
    {
        $code = $this->codeByFile();

        $this->assertArrayHasKey(self::MLS_INPUT_EXCEPTION, $code);
        $this->assertStringContainsString('PropertyCandidate', $code[self::MLS_INPUT_EXCEPTION]);

        $readers = array_keys(array_filter($code, static fn (string $body): bool => str_contains($body, 'PropertyCandidate')));
        $this->assertSame([self::MLS_INPUT_EXCEPTION], $readers, 'only the MLS adapter may read a PropertyCandidate');
    }

    /**
     * The guard must catch what it exists for — a planted violation of each kind
     * is reported, so a regex that silently stopped matching fails here.
     */
    public function test_the_boundary_guard_detects_planted_violations(): void
    {
        $clean = $this->codeByFile();

        $planted = [
            'CanonicalListing.php'    => '<?php use App\\Services\\Property\\PropertyCandidate;',
            self::MLS_INPUT_EXCEPTION => '<?php use App\\Models\\BridgeProperty; $x = $candidate->raw;',
            'Adapters/Planted.php'    => '<?php $c = new App\\Services\\Bridge\\BridgeApiService();',
        ];

        $violations = $this->mlsBoundaryViolations(array_merge($clean, $planted));

        $this->assertContains('CanonicalListing.php reads PropertyCandidate outside the MLS input exception', $violations);
        $this->assertContains(self::MLS_INPUT_EXCEPTION . ' depends on BridgeProperty', $violations);
        $this->assertContains(self::MLS_INPUT_EXCEPTION . ' reads the candidate\'s raw source record', $violations);
        $this->assertContains('Adapters/Planted.php depends on App\\Services\\Bridge', $violations);
    }

    public function test_the_mls_adapter_reads_no_provider_specific_field_and_touches_no_storage(): void
    {
        $code = $this->codeByFile()[self::MLS_INPUT_EXCEPTION];

        foreach (['STELLAR_', 'raw_json', 'App\\Models', 'DB::', 'Http::', 'Cache::', '->save(', 'dispatch('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, "MlsListingAdapter must not use {$forbidden}");
        }
    }

    /**
     * P0-5 is additive and inert: no application code outside the path itself
     * reaches an MLS canonical listing, and the BYO resolver — whose supports()
     * gates the DNA-score chain in ComputeLocationDna — does not learn `bridge`.
     */
    public function test_no_live_surface_reaches_the_mls_canonical_path(): void
    {
        $allowed = [
            'Services/Canonical/Adapters/MlsListingAdapter.php',
            'Services/Bridge/MlsCanonicalListingResolver.php',
        ];

        $root = realpath(__DIR__ . '/../../../app');
        $it   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $reach = [];

        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
            if (in_array($relative, $allowed, true)) {
                continue;
            }
            $code = $this->stripComments((string) file_get_contents($file->getPathname()));
            if (str_contains($code, 'MlsListingAdapter') || str_contains($code, 'MlsCanonicalListingResolver')) {
                $reach[] = $relative;
            }
        }

        $this->assertSame([], $reach, 'the MLS canonical path is not wired to any live surface in P0-5');

        $resolver = $this->codeByFile()['CanonicalListingResolver.php'];
        $this->assertStringNotContainsString("'bridge'", $resolver);
        $this->assertStringNotContainsString('MLS_LISTING_TYPE', $resolver);
        $this->assertStringNotContainsString('MlsListingAdapter', $resolver);
    }

    private function stripComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * @param  array<string, string> $codeByFile
     * @return list<string>
     */
    private function mlsBoundaryViolations(array $codeByFile): array
    {
        $out = [];

        foreach ($codeByFile as $file => $code) {
            foreach (self::BRIDGE_INFRASTRUCTURE as $forbidden) {
                if (str_contains($code, $forbidden)) {
                    $out[] = "{$file} depends on {$forbidden}";
                }
            }

            if ($file !== self::MLS_INPUT_EXCEPTION && str_contains($code, 'PropertyCandidate')) {
                $out[] = "{$file} reads PropertyCandidate outside the MLS input exception";
            }

            if ($file === self::MLS_INPUT_EXCEPTION && preg_match('/->\s*raw\b/', $code) === 1) {
                $out[] = "{$file} reads the candidate's raw source record";
            }
        }

        return $out;
    }

    public function test_no_stellar_field_exists_in_the_canonical_layer(): void
    {
        foreach ($this->codeByFile() as $file => $code) {
            $this->assertDoesNotMatchRegularExpression('/stellar/i', $code, "{$file} names Stellar in code");
        }

        foreach (array_keys(V::all()) as $key) {
            $this->assertDoesNotMatchRegularExpression('/stellar|bridge|mls/i', $key, "provider-specific canonical key {$key}");
        }
    }

    public function test_supply_facts_carry_no_demand_naming(): void
    {
        foreach (V::all() as $key => [, $side]) {
            if ($side !== V::SIDE_SUPPLY) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                '/needed|required|maximum|minimum|budget|desired|preferred/i',
                // property.view_preference predates P0-4 and is read by DNA; it is not renamed here.
                $key === 'property.view_preference' ? '' : $key,
                "supply key {$key} is named like a criterion"
            );
        }
    }

    public function test_every_declared_key_has_a_type_and_a_side(): void
    {
        $types = [V::TYPE_STRING, V::TYPE_INT, V::TYPE_FLOAT, V::TYPE_BOOL, V::TYPE_ARRAY];

        $this->assertSame([], array_intersect_key(V::CORE, V::EXTENSION), 'a key is declared twice');

        foreach (V::all() as $key => [$type, $side]) {
            $this->assertContains($type, $types, "{$key} has an unknown type");
            $this->assertContains($side, [V::SIDE_SUPPLY, V::SIDE_DEMAND], "{$key} has an unknown side");
        }

        $this->assertSame([], array_intersect(V::SUPPLY_LISTING_TYPES, V::DEMAND_LISTING_TYPES));
    }

    public function test_property_type_is_translated_by_the_existing_vocabulary_only(): void
    {
        $code = $this->codeByFile();

        $this->assertStringContainsString(
            'PropertyTypeVocabulary',
            $code['Adapters/ByoSupplyFacts.php'],
            'property type must come from PropertyTypeVocabulary'
        );

        foreach ($code as $file => $body) {
            // A literal BYO or feed category in canonical code would be the start
            // of a second translation table.
            foreach (['Residential Property', 'Commercial Property', 'Residential Lease', 'Vacant Land', 'Business Opportunity'] as $literal) {
                $this->assertStringNotContainsString("'{$literal}'", $body, "{$file} hard-codes property type '{$literal}'");
            }
        }

        $translators = [];
        $app = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            realpath(__DIR__ . '/../../../app'),
            \FilesystemIterator::SKIP_DOTS
        ));
        foreach ($app as $file) {
            if (str_contains($file->getFilename(), 'PropertyTypeTranslator')) {
                $translators[] = $file->getPathname();
            }
        }
        $this->assertSame([], $translators, 'a parallel property-type translator was introduced');
    }

    public function test_your_terms_keys_are_never_read_by_the_canonical_layer(): void
    {
        $terms = [
            'maximum_budget', 'desired_sale_price', 'buy_now_price', 'starting_price', 'reserve_price',
            'purchase_price', 'desired_rental_amount', 'starting_rent', 'reserve_rent', 'lease_now_price',
        ];

        foreach ($this->codeByFile() as $file => $code) {
            foreach ($terms as $term) {
                $this->assertStringNotContainsString("'{$term}'", $code, "{$file} reads Your Terms key {$term}");
            }
        }
    }

    /** @return array<string, string> relative path => source with comments removed */
    private function codeByFile(): array
    {
        $root = realpath(self::NAMESPACE_DIR);
        $out  = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = '';
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }

            $out[ltrim(str_replace($root, '', $file->getPathname()), '/')] = $code;
        }

        ksort($out);

        return $out;
    }
}
