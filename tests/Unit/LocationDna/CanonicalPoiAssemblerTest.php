<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\Providers\CanonicalLocationMerger;
use App\Services\LocationDna\Providers\CanonicalPoiAssembler;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1 Batch 2 — proves CanonicalPoiAssembler wires CanonicalLocationMerger into a
 * REAL runtime path (the merger is genuinely invoked, once per candidate) while producing
 * BYTE-IDENTICAL output for the current single-provider (google_places) configuration.
 */
class CanonicalPoiAssemblerTest extends TestCase
{
    /** A minimal single-provider config where poi.default resolves to google_places. */
    private function singleProviderConfig(): array
    {
        return [
            'providers' => [
                'google_places' => [
                    'tier'    => 'premium',
                    'license' => 'google-tos',
                    'serves'  => ['rating', 'quality'],
                    'enabled' => true,
                ],
            ],
            'capabilities' => [
                'poi.default' => [
                    ['provider' => 'google_places', 'role' => 'base'],
                ],
            ],
            'regional_overrides' => [],
        ];
    }

    /** Two raw Google-shaped candidate rows, order-sensitive. */
    private function rawCandidates(): array
    {
        return [
            [
                'name'               => 'Publix Super Market',
                'place_id'           => 'PID-1',
                'geometry'           => ['location' => ['lat' => 27.95, 'lng' => -82.46]],
                'types'              => ['grocery_or_supermarket', 'store'],
                'rating'             => 4.5,
                'user_ratings_total' => 320,
                'vicinity'           => '123 Main St',
            ],
            [
                'name'               => 'Winn-Dixie',
                'place_id'           => 'PID-2',
                'geometry'           => ['location' => ['lat' => 27.96, 'lng' => -82.47]],
                'types'              => ['grocery_or_supermarket'],
                'rating'             => null,
                'vicinity'           => '456 Oak Ave',
            ],
        ];
    }

    public function test_single_provider_output_is_byte_identical_to_input(): void
    {
        $raw       = $this->rawCandidates();
        $assembler = new CanonicalPoiAssembler($this->singleProviderConfig());

        $out = $assembler->assemble($raw);

        // Byte-identical: same count, same order, same content (JSON round-trip equality).
        $this->assertCount(count($raw), $out);
        $this->assertSame(
            json_encode($raw, JSON_UNESCAPED_SLASHES),
            json_encode($out, JSON_UNESCAPED_SLASHES),
            'single-provider assemble() must be a byte-identical passthrough'
        );
        // Order + membership preserved element-wise.
        $this->assertSame($raw[0], $out[0]);
        $this->assertSame($raw[1], $out[1]);
    }

    public function test_empty_input_yields_empty_output(): void
    {
        $assembler = new CanonicalPoiAssembler($this->singleProviderConfig());
        $this->assertSame([], $assembler->assemble([]));
    }

    public function test_the_merger_is_genuinely_invoked_once_per_candidate(): void
    {
        // A spy merger proves the assembler is a REAL runtime path for the merger,
        // not a no-op that merely happens to return the input.
        $spy = new class extends CanonicalLocationMerger {
            public int $calls = 0;
            /** @var array<int,int> */
            public array $contributionCounts = [];
            public function merge(array $contributions, array $options = []): \App\Services\LocationDna\Providers\CanonicalField
            {
                $this->calls++;
                $this->contributionCounts[] = count($contributions);
                return parent::merge($contributions, $options);
            }
        };

        $raw       = $this->rawCandidates();
        $assembler = new CanonicalPoiAssembler($this->singleProviderConfig(), $spy);

        $out = $assembler->assemble($raw);

        $this->assertSame(2, $spy->calls, 'merger must be called once per candidate');
        $this->assertSame([1, 1], $spy->contributionCounts, 'single-provider config yields one contribution per candidate');
        // And the real merge still produced byte-identical values.
        $this->assertSame(
            json_encode($raw, JSON_UNESCAPED_SLASHES),
            json_encode($out, JSON_UNESCAPED_SLASHES)
        );
    }

    public function test_nested_row_structure_survives_the_merge(): void
    {
        $raw       = $this->rawCandidates();
        $assembler = new CanonicalPoiAssembler($this->singleProviderConfig());

        $out = $assembler->assemble($raw);

        // Deep structure the downstream ranking/exclusion depends on is intact.
        $this->assertSame(['location' => ['lat' => 27.95, 'lng' => -82.46]], $out[0]['geometry']);
        $this->assertSame(['grocery_or_supermarket', 'store'], $out[0]['types']);
        $this->assertSame(4.5, $out[0]['rating']);
        $this->assertArrayHasKey('rating', $out[1]);
        $this->assertNull($out[1]['rating']); // null preserved, not coerced to 0
    }
}
