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

    public function test_the_canonical_namespace_has_no_bridge_or_mls_provider_dependency(): void
    {
        foreach ($this->codeByFile() as $file => $code) {
            foreach (['BridgeProperty', 'App\\Services\\Bridge', 'BridgeApiService', 'PropertyCandidate'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $code, "{$file} depends on {$forbidden}; MLS adaptation is P0-5");
            }
        }
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
