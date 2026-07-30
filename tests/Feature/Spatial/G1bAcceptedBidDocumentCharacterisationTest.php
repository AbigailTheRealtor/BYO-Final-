<?php

namespace Tests\Feature\Spatial;

use App\Http\Controllers\AcceptedBidSummaryController;
use App\Models\AcceptedBidSummary;
use App\Models\TenantAgentAuction;
use App\Services\AcceptedBidSummaryService;
use App\Services\BuyerAcceptedBidSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1b · U-G1B-4 — does the accepted-bid durable document render exact geometry or
 * `location_notes` to a non-owner party?
 *
 * WHY THIS EXISTS
 * ---------------
 * G1b finding F-G1B-7 recorded that `AcceptedBidSummaryService` and
 * `BuyerAcceptedBidSummaryService` read the canonical blob and produce durable
 * documents without applying `PublicGeometryProjection`, and that whether exact
 * geometry actually reaches a non-owner was UNRESOLVED (U-G1B-4). This suite settles
 * the factual half. The policy half — whether accepted-bid parties *should* receive
 * geometry — is an owner decision and is **not** decided here.
 *
 * THE TRACED PATH (verified at the audited commit)
 * -----------------------------------------------
 *   route      GET /accepted-bid-summary/{id}          routes/web.php:217
 *              GET /accepted-bid-summary/{id}/download-pdf   routes/web.php:221
 *   authz      AcceptedBidSummaryController::canAccessSummary():335-338 —
 *              `$user->id === $summary->tenant_user_id || $user->id === $summary->agent_user_id`
 *   controller AcceptedBidSummaryController::view():24 → abort(403) unless permitted
 *   renderer   AcceptedBidSummaryService::getRenderedHtml():14 — the PUBLIC render
 *              boundary; returns `summary_html` with signature placeholders resolved
 *   template   AcceptedBidSummaryService::getHtmlTemplate():1077, whose sole location
 *              placeholder is `{{target_areas}}` at :1111
 *   data       buildTargetAreas():747 reads `acceptable_cities`, `acceptable_counties`,
 *              `acceptable_zip_codes`, `state` — legacy discrete meta, NOT the blob
 *   retention  extractLocationIntelligenceData():730 writes the WHOLE decoded blob to
 *              `AcceptedBidSummary.location_intelligence_snapshot`
 *   recipients tenant_user_id (owner) and agent_user_id (**counterparty / non-owner**),
 *              both authenticated participants. Not public.
 *
 * BOUNDARY EXERCISED, AND THE LIMITATION
 * --------------------------------------
 * The DomPDF renderer is **not** invoked. Generating a real summary end-to-end
 * requires a full `TenantAgentAuctionBid` + counter-bid + two users fixture, and the
 * PDF step adds nothing to the question: `downloadPdf` renders the same
 * `summary_html` that `getRenderedHtml()` returns.
 *
 * So this suite exercises the **final view-data / HTML-render boundary**:
 *   1. `buildTargetAreas()` is EXECUTED with sentinel geometry and sentinel admin
 *      labels — this is the only producer of location content in the template, so
 *      what it returns is what the document can contain.
 *   2. `getHtmlTemplate()` is EXECUTED and its placeholder inventory asserted.
 *   3. `getRenderedHtml()` — the real public boundary — is EXECUTED against a real
 *      `AcceptedBidSummary` row whose `summary_html` was produced by the real
 *      template substitution, proving the sentinels' fate through the actual method
 *      the controller calls.
 *   4. `extractLocationIntelligenceData()` is EXECUTED against a real
 *      `TenantAgentAuction` to establish what the retention column receives.
 *
 * **Declared limitation:** no HTTP request is made and DomPDF is not run, so this
 * suite proves what the renderer *produces*, not what a browser or PDF viewer
 * displays. No production seam was added to enable any of it.
 *
 * CHARACTERISATION, NOT REPAIR
 * ----------------------------
 * Every assertion records today's behaviour. No fix is applied. No owner decision
 * D-G1-1 … D-G1-6 is assumed.
 */
class G1bAcceptedBidDocumentCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    /** Sentinels chosen to be unmistakable if they ever appear in a rendered document. */
    private const SENTINEL_POLY_LAT   = '27.98765432101';
    private const SENTINEL_POLY_LNG   = '-82.61234567890';
    private const SENTINEL_RADIUS_LAT = '28.11111111111';
    private const SENTINEL_ADDRESS    = 'SENTINEL-RADIUS-CENTRE-ADDRESS';
    private const SENTINEL_NOTES      = 'SENTINEL-LOCATION-NOTES-DO-NOT-LEAK';
    private const SENTINEL_CITY       = 'SentinelCity';
    private const SENTINEL_COUNTY     = 'SentinelCounty';
    private const SENTINEL_STATE      = 'SentinelState';

    /** A full canonical blob carrying a sentinel in every sensitive dimension. */
    private function sentinelBlob(): array
    {
        return [
            'cities'            => [self::SENTINEL_CITY],
            'counties'          => [self::SENTINEL_COUNTY],
            'state'             => self::SENTINEL_STATE,
            'zip_codes'         => ['33602'],
            'polygons'          => [[
                'label' => 'Sentinel drawn area',
                'path'  => [
                    ['lat' => self::SENTINEL_POLY_LAT, 'lng' => self::SENTINEL_POLY_LNG],
                    ['lat' => '27.90000000000', 'lng' => '-82.50000000000'],
                ],
            ]],
            'radius_searches'   => [[
                'lat'          => self::SENTINEL_RADIUS_LAT,
                'lng'          => '-82.40000000000',
                'radius_miles' => 3.5,
                'address'      => self::SENTINEL_ADDRESS,
            ]],
            'flexible_location' => true,
            'location_notes'    => self::SENTINEL_NOTES,
        ];
    }

    private function tenantService(): AcceptedBidSummaryService
    {
        return app(AcceptedBidSummaryService::class);
    }

    private function invoke(object $service, string $method, array $args = [])
    {
        $m = new ReflectionMethod($service::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($service, $args);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · what the ONLY location producer emits
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · `buildTargetAreas()` emits administrative labels ONLY.
     *
     * Executed with a listing-data array carrying both the legacy discrete keys the
     * builder reads and the full canonical blob alongside them. Not one geometry or
     * notes sentinel survives into the returned string.
     */
    public function test_build_target_areas_emits_administrative_labels_only(): void
    {
        $listingData = [
            'acceptable_cities'    => [self::SENTINEL_CITY],
            'acceptable_counties'  => [self::SENTINEL_COUNTY],
            'acceptable_zip_codes' => ['33602'],
            'state'                => self::SENTINEL_STATE,
            // The canonical blob sits on the same record the builder reads from.
            'location_dna_preferences' => json_encode($this->sentinelBlob()),
        ];

        $out = $this->invoke($this->tenantService(), 'buildTargetAreas', [$listingData]);

        $this->assertIsString($out);

        // Administrative labels ARE present — the document is not empty of location.
        $this->assertStringContainsString(self::SENTINEL_CITY, $out);
        $this->assertStringContainsString(self::SENTINEL_COUNTY, $out);
        $this->assertStringContainsString(self::SENTINEL_STATE, $out);

        // No geometry and no notes.
        foreach ([
            self::SENTINEL_POLY_LAT,
            self::SENTINEL_POLY_LNG,
            self::SENTINEL_RADIUS_LAT,
            self::SENTINEL_ADDRESS,
            self::SENTINEL_NOTES,
        ] as $sentinel) {
            $this->assertStringNotContainsString(
                $sentinel,
                $out,
                "Sentinel '{$sentinel}' must not reach the rendered target-areas string."
            );
        }

        $this->assertStringNotContainsString('polygons', $out);
        $this->assertStringNotContainsString('radius', $out);
    }

    /**
     * CHARACTERISED · the builder ignores the canonical blob entirely.
     *
     * Passing ONLY the blob — with no legacy discrete keys — yields an empty string.
     * This is the decisive evidence that the document's location content is sourced
     * from legacy mirrors, not from the canonical geometry.
     */
    public function test_build_target_areas_returns_empty_when_only_the_canonical_blob_is_present(): void
    {
        $out = $this->invoke($this->tenantService(), 'buildTargetAreas', [[
            'location_dna_preferences' => json_encode($this->sentinelBlob()),
        ]]);

        $this->assertSame(
            '',
            $out,
            'CHARACTERISATION: the canonical blob contributes nothing to the rendered document.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · the real template's placeholder inventory
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · the real template has exactly one location placeholder.
     *
     * `getHtmlTemplate()` is executed and its output inspected — this is the actual
     * string the service substitutes into, not a Blade file scanned by name.
     */
    public function test_real_template_exposes_only_the_target_areas_placeholder(): void
    {
        $template = $this->invoke($this->tenantService(), 'getHtmlTemplate');

        $this->assertIsString($template);
        $this->assertStringContainsString('{{target_areas}}', $template);

        foreach ([
            '{{polygons}}', '{{radius_searches}}', '{{radius}}', '{{location_notes}}',
            '{{location_dna_preferences}}', '{{location_intelligence_snapshot}}', '{{geometry}}',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $template,
                "The template must not carry a {$forbidden} placeholder."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · the REAL public render boundary the controller calls
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · `getRenderedHtml()` — the method
     * `AcceptedBidSummaryController::view():33` actually calls — emits administrative
     * labels and no geometry.
     *
     * The row's `summary_html` is produced by the real template substitution
     * (`getHtmlTemplate()` + the same `{{target_areas}}` replacement the service
     * performs at `:665`), then passed through the real public renderer. The sentinel
     * blob is stored on `location_intelligence_snapshot` at the same time, so the row
     * under test is shaped exactly like a real one.
     */
    public function test_public_rendered_html_contains_labels_and_no_geometry(): void
    {
        $service  = $this->tenantService();
        $template = $this->invoke($service, 'getHtmlTemplate');
        $areas    = $this->invoke($service, 'buildTargetAreas', [[
            'acceptable_cities'   => [self::SENTINEL_CITY],
            'acceptable_counties' => [self::SENTINEL_COUNTY],
            'state'               => self::SENTINEL_STATE,
        ]]);

        $summary = new AcceptedBidSummary();
        $summary->forceFill([
            'summary_html'                   => str_replace('{{target_areas}}', e($areas), $template),
            'location_intelligence_snapshot' => $this->sentinelBlob(),
        ]);

        $rendered = $service->getRenderedHtml($summary);

        $this->assertIsString($rendered);
        $this->assertStringContainsString(self::SENTINEL_CITY, $rendered, 'labels survive rendering');

        foreach ([
            self::SENTINEL_POLY_LAT,
            self::SENTINEL_POLY_LNG,
            self::SENTINEL_RADIUS_LAT,
            self::SENTINEL_ADDRESS,
            self::SENTINEL_NOTES,
        ] as $sentinel) {
            $this->assertStringNotContainsString(
                $sentinel,
                $rendered,
                "CHARACTERISATION: '{$sentinel}' must not appear in the rendered document."
            );
        }

        // The retention column holds the geometry even though the render does not.
        $this->assertStringContainsString(
            self::SENTINEL_NOTES,
            json_encode($summary->location_intelligence_snapshot),
            'precondition: the snapshot column really does hold the sentinels'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · the retention column — the fact behind R12
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · `extractLocationIntelligenceData()` retains the WHOLE canonical
     * blob, geometry and notes included, unprojected.
     *
     * Executed against a real `TenantAgentAuction` with real meta. This is the at-rest
     * retention half of F-G1B-7, and it confirms R12's concern with a live value
     * rather than by inspection.
     */
    public function test_snapshot_extraction_retains_full_unprojected_geometry(): void
    {
        $auction = TenantAgentAuction::factory()->create();
        $auction->saveMeta('location_dna_preferences', json_encode($this->sentinelBlob()));
        $fresh = TenantAgentAuction::with('meta')->findOrFail($auction->id);

        $out = $this->invoke($this->tenantService(), 'extractLocationIntelligenceData', [$fresh]);

        $this->assertArrayHasKey('location_intelligence_snapshot', $out);
        $snapshot = $out['location_intelligence_snapshot'];

        $this->assertSame(self::SENTINEL_NOTES, $snapshot['location_notes']);
        $this->assertSame(self::SENTINEL_POLY_LAT, $snapshot['polygons'][0]['path'][0]['lat']);
        $this->assertSame(self::SENTINEL_ADDRESS, $snapshot['radius_searches'][0]['address']);

        // No projection marker — the value was never passed through PublicGeometryProjection.
        $this->assertArrayNotHasKey(
            \App\Services\LocationDna\PublicGeometryProjection::MARKER,
            $snapshot,
            'CHARACTERISATION: the retained snapshot is unprojected.'
        );
    }

    /** CHARACTERISED · the extractor degrades safely on absent, empty and malformed input. */
    public function test_snapshot_extraction_degrades_safely(): void
    {
        $service = $this->tenantService();

        $none = TenantAgentAuction::with('meta')->findOrFail(TenantAgentAuction::factory()->create()->id);
        $this->assertSame([], $this->invoke($service, 'extractLocationIntelligenceData', [$none]));

        $bad = TenantAgentAuction::factory()->create();
        $bad->saveMeta('location_dna_preferences', '{"cities": ["Tampa"');
        $badFresh = TenantAgentAuction::with('meta')->findOrFail($bad->id);
        $this->assertSame([], $this->invoke($service, 'extractLocationIntelligenceData', [$badFresh]));

        $empty = TenantAgentAuction::factory()->create();
        $empty->saveMeta('location_dna_preferences', json_encode([]));
        $emptyFresh = TenantAgentAuction::with('meta')->findOrFail($empty->id);
        $this->assertSame([], $this->invoke($service, 'extractLocationIntelligenceData', [$emptyFresh]));
    }

    /**
     * CHARACTERISED · the retention column has ZERO read sites in production.
     *
     * Structural, and deliberately so: the claim being pinned is an absence, which no
     * behavioural test can express. `location_intelligence_snapshot` appears only in
     * the two services that write it, the backfill command that writes it, and the
     * model's `$fillable` / `$casts`. Nothing reads it, so it cannot reach a rendered
     * document by any path — which is why the render assertions above hold despite the
     * column carrying full geometry.
     *
     * Recorded as a **known weaker assertion**. If a reader is ever added, this fails
     * and the exposure question must be re-opened.
     */
    public function test_retention_column_has_no_production_read_sites(): void
    {
        // Sorted, to match the sorted scan result below.
        $writers = [
            'app/Console/Commands/BackfillLocationSnapshots.php',
            'app/Models/AcceptedBidSummary.php',
            'app/Services/AcceptedBidSummaryService.php',
            'app/Services/BuyerAcceptedBidSummaryService.php',
        ];

        $found = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app')));
        foreach ($rii as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $rel = str_replace(base_path().'/', '', $file->getPathname());
            if (str_contains(file_get_contents($file->getPathname()), 'location_intelligence_snapshot')) {
                $found[] = $rel;
            }
        }
        sort($found);

        $this->assertSame(
            $writers,
            $found,
            'Only the three writers and the model may mention the retention column. '
            .'A new file here means a possible read path — re-open U-G1B-4.'
        );

        // And no view references it at all.
        $viewHits = [];
        $vii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/views')));
        foreach ($vii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (str_contains(file_get_contents($file->getPathname()), 'location_intelligence_snapshot')) {
                $viewHits[] = $file->getPathname();
            }
        }
        $this->assertSame([], $viewHits, 'No view may reference the retention column.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · recipient classification, and the Buyer-side variant
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · the document is reachable by the OWNER and by a
     * COUNTERPARTY / NON-OWNER participant — and by nobody else.
     *
     * `canAccessSummary()` is invoked directly on the real controller with three
     * synthetic principals. This settles the recipient classification U-G1B-4 asks
     * for: the audience is authenticated participants, **including a non-owner**, and
     * is **not** public.
     */
    public function test_recipients_are_owner_and_counterparty_only(): void
    {
        $summary = new AcceptedBidSummary();
        $summary->forceFill(['tenant_user_id' => 101, 'agent_user_id' => 202]);

        $controller = app(AcceptedBidSummaryController::class);
        $can = new ReflectionMethod(AcceptedBidSummaryController::class, 'canAccessSummary');
        $can->setAccessible(true);

        $owner        = (object) ['id' => 101];
        $counterparty = (object) ['id' => 202];
        $stranger     = (object) ['id' => 999];

        $this->assertTrue($can->invoke($controller, $summary, $owner), 'owner may view');
        $this->assertTrue(
            $can->invoke($controller, $summary, $counterparty),
            'CHARACTERISATION: a NON-OWNER counterparty may retrieve the document.'
        );
        $this->assertFalse($can->invoke($controller, $summary, $stranger), 'an unrelated user may not');
    }

    /**
     * CHARACTERISED · the BUYER-side accepted-bid document contains no location
     * content at all.
     *
     * `BuyerAcceptedBidSummaryService` has no `buildTargetAreas` and its template
     * carries no `{{target_areas}}` placeholder, so the buyer variant emits neither
     * geometry nor administrative labels. It nevertheless retains the full blob in the
     * same snapshot column (`:476`) — the same split as the tenant side.
     */
    public function test_buyer_side_document_contains_no_location_content(): void
    {
        $service = app(BuyerAcceptedBidSummaryService::class);

        $this->assertFalse(
            method_exists($service, 'buildTargetAreas'),
            'The buyer service has no target-areas builder.'
        );

        $source = file_get_contents(base_path('app/Services/BuyerAcceptedBidSummaryService.php'));
        $this->assertStringNotContainsString('{{target_areas}}', $source);
        $this->assertStringNotContainsString('acceptable_cities', $source);

        // But it does retain the snapshot, exactly like the tenant side.
        $this->assertStringContainsString('location_intelligence_snapshot', $source);
    }
}
