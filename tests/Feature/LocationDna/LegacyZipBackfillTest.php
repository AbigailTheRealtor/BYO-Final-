<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\Concerns\HasGeographyCascade;
use App\Http\Livewire\Concerns\HasSearchAreas;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The legacy `zipCodes` meta → blob `zip_codes` backfill, and the data loss it exists to prevent.
 *
 * WHAT THIS IS A PRECONDITION FOR
 * -------------------------------
 * `HasGeographyCascade::ZIP_MIRROR_WORKFLOWS` names the workflows whose cascade ZIP selection is
 * mirrored back over the host's `$zipCodes` property — the property every legacy `zipCodes` meta
 * write is fed from. For such a workflow the cascade does not merely lag the mirror, it OWNS it.
 *
 * `HasSearchAreas` backfilled legacy `cities` into the blob from the beginning and never did the
 * same for ZIPs. While the blob was only a prefill source that asymmetry cost nothing. The moment
 * a mirroring workflow goes live it becomes silent deletion: the cascade hydrates ZIPs from the
 * blob, finds none, projects none, and the next save writes `[]` over a list the user never
 * touched. Nothing compares the copies, so nothing would report it.
 *
 * {@see self::the_bug_this_backfill_prevents()} pins that failure directly, so the backfill's
 * value is asserted rather than described — remove the backfill and that test goes red alongside
 * the round-trip ones.
 *
 * WHY `hire_tenant` APPEARS BELOW, AND WHY THAT IS NOT ENABLEMENT
 * ---------------------------------------------------------------
 * It is the only key in `ZIP_MIRROR_WORKFLOWS`, so it is the only workflow that can exercise the
 * mirroring branch at all. The workflow is forced into config for the duration of a test, exactly
 * as {@see SellerLandlordCascadeExclusionTest} forces every gate open. Nothing here changes the
 * shipped config, the component workflow maps, or `ZIP_MIRROR_WORKFLOWS` itself: Tenant remains
 * unwired, and this suite is the evidence that wiring it later would not destroy stored data.
 */
class LegacyZipBackfillTest extends TestCase
{
    use DatabaseTransactions;

    private const FL       = '12';
    private const PINELLAS = '12103';

    /** In the corpus, and therefore matchable to a real selection. */
    private const KNOWN_ZIP = '33767';

    /** Not in the corpus — the preserved-history path. */
    private const UNKNOWN_ZIP = '99999';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('criteria_location_dna.geography_source', 'census');
        config()->set('criteria_location_dna.geography_cascade_enabled', true);

        $this->seedCorpus();
    }

    private function seedCorpus(): void
    {
        DB::table('census_states')->insert([
            ['geoid' => self::FL, 'usps' => 'FL', 'name' => 'Florida'],
        ]);

        DB::table('census_counties')->insert([
            ['geoid' => self::PINELLAS, 'state_geoid' => self::FL, 'countyfp' => '103',
             'name' => 'Pinellas County', 'basename' => 'Pinellas'],
        ]);

        DB::table('census_zctas')->insert([['zcta5' => self::KNOWN_ZIP]]);
        DB::table('census_zcta_counties')->insert([
            ['zcta5' => self::KNOWN_ZIP, 'county_geoid' => self::PINELLAS],
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // HARNESS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A stand-in for the auction model, answering only `info()`.
     *
     * The components under discussion hydrate through `$auction->info('<meta key>')`, and that one
     * method is the whole of `loadSearchAreas()`'s contract with the model. Building a real auction
     * would drag in the bid form, its drafts and its services to exercise a string lookup.
     *
     * @param  array<string, mixed>  $meta
     */
    private function auction(array $meta): object
    {
        return new class($meta) {
            /** @param array<string, mixed> $meta */
            public function __construct(private array $meta)
            {
            }

            public function info(string $key): mixed
            {
                return $this->meta[$key] ?? null;
            }
        };
    }

    /**
     * A host carrying both traits, as every real Buyer/Tenant surface does.
     *
     * `$zipCodes` is declared because the mirror's second gate is `property_exists()` — a host
     * without it would pass these tests for the wrong reason.
     */
    private function host(?string $workflow = null): object
    {
        $host = new class {
            use HasSearchAreas;
            use HasGeographyCascade;

            public $state    = '';
            public $counties = [];
            public $cities   = [];
            public $zipCodes = [];

            public function bootAs(?string $workflow): void
            {
                $this->bootGeographyCascade($workflow);
            }

            public function load($auction): void
            {
                $this->loadSearchAreas($auction);
            }

            public function loadCascade(array $blob): void
            {
                $this->loadGeographyCascade($blob);
            }

            public function projection(): array
            {
                return $this->geographyProjection();
            }

            public function applyToPayload(): void
            {
                $this->applyGeographyCascadeToPayload();
            }
        };

        $host->bootAs($workflow);

        return $host;
    }

    /** Turn the mirroring workflow on for one test. See the class docblock. */
    private function withTenantCascade(): object
    {
        config()->set('criteria_location_dna.geography_cascade_workflows', ['hire_tenant']);

        return $this->host('hire_tenant');
    }

    /** A stored blob whose geography resolves, minus the ZIPs. */
    private function blobWithoutZips(): string
    {
        return (string) json_encode([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => [],
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE BACKFILL ITSELF
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function legacy_zips_reach_the_blob_when_it_carries_none(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode(['33701', '33702']),
        ]));

        $this->assertSame(['33701', '33702'], $host->existingLocationDna['zip_codes']);
    }

    /**
     * THE BLOB WINS. It is the canonical copy; the mirror is allowed to lag it, so a stale mirror
     * must never overwrite a live blob value.
     *
     * @test
     */
    public function an_existing_blob_zip_list_is_never_replaced_by_the_mirror(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => json_encode(['zip_codes' => ['33767']]),
            'zipCodes'                 => json_encode(['00000', '11111']),
        ]));

        $this->assertSame(['33767'], $host->existingLocationDna['zip_codes']);
    }

    /** @test */
    public function the_backfill_is_idempotent(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode(['33701']),
        ]);

        $host = $this->host();

        $host->load($auction);
        $first = $host->existingLocationDna;

        $host->load($auction);

        $this->assertSame($first, $host->existingLocationDna);
    }

    /**
     * A ZIP is the one label legacy data plausibly stored as a NUMBER, and
     * `GeographySelectionHydrator::labels()` accepts strings only — it drops anything else without
     * even preserving it. Casting here is what keeps the backfill lossless for those records.
     *
     * @test
     */
    public function numeric_legacy_zips_are_cast_to_strings(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode([33701, 33702]),
        ]));

        $this->assertSame(['33701', '33702'], $host->existingLocationDna['zip_codes']);
    }

    /** @test */
    public function blanks_non_scalars_and_duplicates_are_dropped(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode(['33701', '', '  ', null, ['nested'], '33701', ' 33702 ']),
        ]));

        $this->assertSame(['33701', '33702'], $host->existingLocationDna['zip_codes']);
    }

    /** A record with nothing to backfill gains no key — absent stays absent. */
    /** @test */
    public function no_legacy_meta_invents_no_key(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
        ]));

        $this->assertArrayNotHasKey('zip_codes', $host->existingLocationDna);
    }

    /** @test */
    public function a_legacy_list_of_only_blanks_invents_no_key(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode(['', '   ']),
        ]));

        $this->assertArrayNotHasKey('zip_codes', $host->existingLocationDna);
    }

    /** @test */
    public function malformed_legacy_meta_is_a_no_op_rather_than_a_throw(): void
    {
        foreach (['{not json', '"a string"', '0', ''] as $raw) {
            $host = $this->host();

            $host->load($this->auction([
                'location_dna_preferences' => $this->blobWithoutZips(),
                'zipCodes'                 => $raw,
            ]));

            $this->assertArrayNotHasKey('zip_codes', $host->existingLocationDna, "raw: {$raw}");
        }
    }

    /**
     * THE STORED BLOB IS NOT REWRITTEN BY A LOAD, exactly as the cities backfill never rewrote it.
     * Opening a record and navigating away must change nothing; the merged value reaches storage
     * only through an explicit save.
     *
     * @test
     */
    public function loading_does_not_mutate_the_bridged_blob_json(): void
    {
        $stored = $this->blobWithoutZips();
        $host   = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => $stored,
            'zipCodes'                 => json_encode(['33701']),
        ]));

        $this->assertSame($stored, $host->location_dna_preferences_json);
    }

    /** The cities backfill is untouched by the addition of a ZIP one beside it. */
    /** @test */
    public function the_existing_cities_backfill_still_works(): void
    {
        $host = $this->host();

        $host->load($this->auction([
            'location_dna_preferences' => json_encode(['state' => 'Florida']),
            'cities'                   => json_encode(['Tampa, FL']),
            'zipCodes'                 => json_encode(['33701']),
        ]));

        $this->assertSame(['Tampa, FL'], $host->existingLocationDna['cities']);
        $this->assertSame(['33701'], $host->existingLocationDna['zip_codes']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE ROUND TRIP — legacy tenant ZIPs survive load → cascade → save
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * THE CLAIM THIS WHOLE CHANGE RESTS ON.
     *
     * A legacy tenant record holds ZIPs in the mirror and none in the blob. Under the mirroring
     * workflow the cascade takes ownership of `$zipCodes` — the property the mirror is written
     * from — so the value the host holds at save time IS what `saveMeta('zipCodes', …)` persists.
     * Asserting the property is therefore asserting the write.
     *
     * @test
     */
    public function a_legacy_tenant_zip_survives_the_cascade_round_trip(): void
    {
        $host = $this->withTenantCascade();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode([self::KNOWN_ZIP]),
        ]));

        $host->loadCascade($host->existingLocationDna);

        $this->assertSame([self::KNOWN_ZIP], $host->zipCodes, 'the legacy mirror was overwritten');
        $this->assertSame([self::KNOWN_ZIP], $host->projection()['zip_codes']);
    }

    /**
     * A ZIP the corpus cannot resolve is history, and history is preserved rather than dropped.
     * This is the case a naive backfill would still lose, because an unmatched label never becomes
     * a selection — it survives only through `$geoPreserved`, and only if it reached the hydrator
     * at all.
     *
     * @test
     */
    public function a_legacy_zip_the_corpus_cannot_resolve_is_preserved_not_dropped(): void
    {
        $host = $this->withTenantCascade();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode([self::UNKNOWN_ZIP]),
        ]));

        $host->loadCascade($host->existingLocationDna);

        $this->assertContains(self::UNKNOWN_ZIP, $host->geoPreserved['zip_codes']);
        $this->assertSame([self::UNKNOWN_ZIP], $host->zipCodes);
    }

    /** Matched and unmatched legacy ZIPs come back together, with nothing lost on either path. */
    /** @test */
    public function matched_and_unmatched_legacy_zips_both_survive(): void
    {
        $host = $this->withTenantCascade();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode([self::KNOWN_ZIP, self::UNKNOWN_ZIP]),
        ]));

        $host->loadCascade($host->existingLocationDna);

        $this->assertEqualsCanonicalizing(
            [self::KNOWN_ZIP, self::UNKNOWN_ZIP],
            $host->zipCodes,
        );
    }

    /** The ZIPs reach the payload that is actually persisted, not just the component property. */
    /** @test */
    public function the_surviving_zips_reach_the_persisted_payload(): void
    {
        $host = $this->withTenantCascade();

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode([self::KNOWN_ZIP]),
        ]));

        $host->loadCascade($host->existingLocationDna);
        $host->location_dna_preferences_json = (string) json_encode($host->existingLocationDna);
        $host->applyToPayload();

        $decoded = json_decode($host->location_dna_preferences_json, true);

        $this->assertSame([self::KNOWN_ZIP], $decoded['zip_codes']);
    }

    /**
     * THE BUG THE BACKFILL PREVENTS, pinned so the backfill cannot be removed quietly.
     *
     * Identical to the round-trip test except that the cascade is handed the blob as it was stored
     * — no backfill. The mirror is emptied, which on a real save is written straight over the
     * user's ZIP list.
     *
     * @test
     */
    public function the_bug_this_backfill_prevents(): void
    {
        $host = $this->withTenantCascade();

        $host->zipCodes = [self::KNOWN_ZIP];
        $host->loadCascade((array) json_decode($this->blobWithoutZips(), true));

        $this->assertSame([], $host->zipCodes);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · NOTHING WIDENED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The backfill runs for every host on the trait, cascade or no cascade — it is plain load
     * plumbing. What must NOT have moved is the mirror gate: a workflow outside
     * `ZIP_MIRROR_WORKFLOWS` still leaves `$zipCodes` alone, backfilled blob or not.
     *
     * @test
     */
    public function the_backfill_does_not_make_a_non_mirroring_workflow_write_zips(): void
    {
        config()->set('criteria_location_dna.geography_cascade_workflows', ['hire_buyer']);

        $host = $this->host('hire_buyer');
        $host->zipCodes = ['legacy-untouched'];

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode([self::KNOWN_ZIP]),
        ]));

        $host->loadCascade($host->existingLocationDna);

        $this->assertSame(['legacy-untouched'], $host->zipCodes);
    }

    /** A host with the cascade off backfills the blob and touches nothing else. */
    /** @test */
    public function a_host_with_no_workflow_is_unaffected_beyond_the_blob(): void
    {
        $host = $this->host(null);
        $host->zipCodes = ['legacy-untouched'];

        $host->load($this->auction([
            'location_dna_preferences' => $this->blobWithoutZips(),
            'zipCodes'                 => json_encode([self::KNOWN_ZIP]),
        ]));

        $host->loadCascade($host->existingLocationDna);

        $this->assertSame([self::KNOWN_ZIP], $host->existingLocationDna['zip_codes']);
        $this->assertSame(['legacy-untouched'], $host->zipCodes);
        $this->assertFalse($host->geoCascadeEnabled);
    }

    /** This suite must not have moved the shipped configuration. */
    /** @test */
    public function the_shipped_workflow_scope_is_still_buyer_only(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertSame(['hire_buyer'], $config['geography_cascade_workflows']);
    }
}
