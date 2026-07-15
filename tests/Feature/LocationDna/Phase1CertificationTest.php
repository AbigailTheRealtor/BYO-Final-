<?php

namespace Tests\Feature\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaLifestyleScoreService;
use App\Services\LocationDna\LocationDnaSummaryService;
use GuzzleHttp\ClientInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BlocksGooglePlacesHttpClient;
use Tests\TestCase;

/**
 * Phase1CertificationTest — the Phase 1 ("Provider Abstraction") Definition-of-Done
 * certification, invariants INV-1 / INV-2 / INV-3, proven against the same deterministic
 * seed that LocationDnaJsonSnapshotParityTest freezes.
 *
 * INV-1 (zero outbound) — the read/summarize/score path emits no outbound Google request.
 *   The base TestCase binds {@see BlocksGooglePlacesHttpClient}, which throws on ANY outbound
 *   call. Running the full Phase-1 document-production path over every listing type without a
 *   throw is the proof; the test also asserts the blocking guard is actually the bound client.
 *
 * INV-2 (roles + bridge identical) — the four role auctions and the `bridge` listing type
 *   share one role-agnostic code path. Seeded with byte-identical POI data, all five produce
 *   byte-identical `summary_json` and `lifestyle_json`. Nothing about a role or the bridge
 *   source leaks into the documents.
 *
 * INV-3 (JSON shapes stable) — every listing type reproduces the frozen Phase-1 snapshot
 *   (tests/Fixtures/LocationDna/phase1-json-snapshots.json) byte-for-byte.
 *
 * The seed lives in the shared fixture so the two tests cannot drift apart: the parity test
 * freezes the byte image, this test certifies it holds across all consumers.
 */
class Phase1CertificationTest extends TestCase
{
    use DatabaseTransactions;

    private const FIXTURE = 'tests/Fixtures/LocationDna/phase1-json-snapshots.json';

    /** Stable json_encode flags — must match LocationDnaJsonSnapshotParityTest exactly. */
    private const CANON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Every listing type that resolves through the shared Location DNA document path: the four
     * role auctions (INV-2 "roles") plus the external MLS source (INV-2 "bridge").
     */
    private const LISTING_TYPES = [
        'seller_agent_auction',
        'buyer_agent_auction',
        'landlord_agent_auction',
        'tenant_agent_auction',
        'bridge',
    ];

    private function loadFixture(): array
    {
        $decoded = json_decode(file_get_contents(base_path(self::FIXTURE)), true);
        $this->assertIsArray($decoded, 'The snapshot fixture is not valid JSON.');
        $this->assertNotNull($decoded['summary_json'] ?? null, 'Snapshot not captured — run LocationDnaJsonSnapshotParityTest first.');
        $this->assertNotNull($decoded['lifestyle_json'] ?? null);

        return $decoded;
    }

    private function canon(mixed $value): string
    {
        return json_encode($value, self::CANON);
    }

    /**
     * Apply the deterministic fixture seed for one listing type, run both document services,
     * and read back the two persisted JSON documents.
     *
     * @return array{0: array, 1: array} [summary_json, lifestyle_json]
     */
    private function seedRunAndReadJson(array $seed, string $listingType, int $listingId): array
    {
        $dna = $seed['dna'];
        PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => $dna['source_address'],
            'source_city'    => $dna['source_city'],
            'source_state'   => $dna['source_state'],
            'geocoded_lat'   => $dna['geocoded_lat'],
            'geocoded_lng'   => $dna['geocoded_lng'],
            'geocode_source' => $dna['geocode_source'],
            'geocode_status' => $dna['geocode_status'],
            'geocoded_at'    => Carbon::parse($dna['geocoded_at']),
        ]);

        foreach ($seed['pois'] as $poi) {
            PropertyLocationPoi::create([
                'listing_type'   => $listingType,
                'listing_id'     => $listingId,
                'poi_category'   => $poi['poi_category'],
                'poi_subtype'    => $poi['poi_subtype'],
                'poi_name'       => $poi['poi_name'],
                'source_lat'     => $dna['geocoded_lat'],
                'source_lng'     => $dna['geocoded_lng'],
                'distance_miles' => $poi['distance_miles'],
                'rank'           => $poi['rank'],
                'ranking_score'  => $poi['ranking_score'],
                'data_source'    => $poi['data_source'],
                'status'         => $poi['status'],
                'calculated_at'  => Carbon::parse($dna['geocoded_at']),
            ]);
        }

        (new LocationDnaSummaryService())->summarizeForListing($listingType, $listingId);
        (new LocationDnaLifestyleScoreService())->generateForListing($listingType, $listingId);

        $record = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->first();

        return [$record->summary_json, $record->lifestyle_json];
    }

    // =========================================================================

    /**
     * INV-1 — zero outbound. With a client bound that throws on ANY outbound request, the full
     * Phase-1 read/summarize/score path over every listing type completes without a single
     * outbound attempt. These services read persisted rows only; the provider seam stays inert.
     *
     * @test
     */
    public function inv1_the_document_path_makes_zero_outbound_requests(): void
    {
        $this->assertInstanceOf(
            BlocksGooglePlacesHttpClient::class,
            app(ClientInterface::class),
            'The outbound-request guard is not the bound client; INV-1 cannot be certified.',
        );

        $seed = $this->loadFixture()['seed'];

        // If any of these attempted an outbound call, BlocksGooglePlacesHttpClient would throw
        // and fail the test. Reaching the assertion is the zero-outbound proof.
        foreach (self::LISTING_TYPES as $i => $listingType) {
            [$summary, $lifestyle] = $this->seedRunAndReadJson($seed, $listingType, 910000 + $i);
            $this->assertIsArray($summary);
            $this->assertIsArray($lifestyle);
        }

        $this->assertTrue(true, 'Zero outbound requests across all listing types.');
    }

    /**
     * INV-2 — roles + bridge identical. The four role auctions and the bridge source, seeded
     * with byte-identical POI data, produce byte-identical documents. No role/source leakage.
     *
     * @test
     */
    public function inv2_all_roles_and_bridge_produce_identical_documents(): void
    {
        $seed = $this->loadFixture()['seed'];

        $summaries  = [];
        $lifestyles = [];
        foreach (self::LISTING_TYPES as $i => $listingType) {
            [$summary, $lifestyle] = $this->seedRunAndReadJson($seed, $listingType, 920000 + $i);
            $summaries[$listingType]  = $this->canon($summary);
            $lifestyles[$listingType] = $this->canon($lifestyle);
        }

        $reference = self::LISTING_TYPES[0];
        foreach (self::LISTING_TYPES as $listingType) {
            $this->assertSame(
                $summaries[$reference],
                $summaries[$listingType],
                "summary_json for '{$listingType}' differs from '{$reference}' — a role/source leaked into the document (INV-2).",
            );
            $this->assertSame(
                $lifestyles[$reference],
                $lifestyles[$listingType],
                "lifestyle_json for '{$listingType}' differs from '{$reference}' (INV-2).",
            );
        }
    }

    /**
     * INV-3 — JSON shapes stable. Every listing type reproduces the frozen Phase-1 snapshot
     * byte-for-byte, tying INV-2's cross-role identity to the same frozen contract the parity
     * test guards.
     *
     * @test
     */
    public function inv3_every_listing_type_matches_the_frozen_snapshot(): void
    {
        $fixture     = $this->loadFixture();
        $frozenSum   = $this->canon($fixture['summary_json']);
        $frozenLife  = $this->canon($fixture['lifestyle_json']);

        foreach (self::LISTING_TYPES as $i => $listingType) {
            [$summary, $lifestyle] = $this->seedRunAndReadJson($fixture['seed'], $listingType, 930000 + $i);

            $this->assertSame(
                $frozenSum,
                $this->canon($summary),
                "summary_json for '{$listingType}' drifted from the frozen Phase-1 snapshot (INV-3).",
            );
            $this->assertSame(
                $frozenLife,
                $this->canon($lifestyle),
                "lifestyle_json for '{$listingType}' drifted from the frozen Phase-1 snapshot (INV-3).",
            );
        }
    }
}
