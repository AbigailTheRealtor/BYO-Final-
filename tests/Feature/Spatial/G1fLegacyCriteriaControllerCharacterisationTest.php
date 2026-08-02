<?php

namespace Tests\Feature\Spatial;

use App\Http\Controllers\BuyerCriteriaAuctionController;
use App\Http\Controllers\TenantCriteriaAuctionController;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Throwable;

/**
 * G1f GAP 1 — the four legacy criteria-controller canonical writers.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * The G1f pre-implementation report records F-G1F-1: there are NINE canonical write sites
 * for `location_dna_preferences`, not five implementations, and four of them live in the
 * two legacy form-POST criteria controllers:
 *
 *   W6  BuyerCriteriaAuctionController::storeAuction()   :234
 *   W7  BuyerCriteriaAuctionController::updateAuction()  :606
 *   W8  TenantCriteriaAuctionController::store()         :148
 *   W9  TenantCriteriaAuctionController::update()        :444
 *
 * The eight-workflow model that governs G1a, G1b and the G1f migration plan does not
 * contain them, so before this suite they had NO characterisation of any kind — while
 * being live canonical writers routed over HTTP (`routes/web.php:887`, `:889`, `:819`,
 * `:821`).
 *
 * They also carry F-G1F-2, the report's most useful finding: these are the only writers in
 * the codebase that already implement D-G1-2's approved unmounted-editor rule.
 *
 *   $ldnaValue = $request->input('location_dna_preferences');
 *   if ($ldnaValue !== null && $ldnaValue !== '') {
 *       json_decode($ldnaValue);
 *       if (json_last_error() === JSON_ERROR_NONE) {
 *           $auction->saveMeta('location_dna_preferences', $ldnaValue);
 *       }
 *   }
 *
 * Absent payload → no write. Empty payload → no write. Unparseable payload → no write.
 * That is the behaviour G1f is scheduled to INTRODUCE, already running in production.
 *
 * WHY STATIC INSPECTION IS NOT SUFFICIENT
 * ---------------------------------------
 * The guard's correctness depends on what `$request->input()` returns for an absent field
 * versus an empty one — framework behaviour that cannot be read off the source. The
 * guard's KNOWN INCOMPLETENESS also needs measuring: it validates parseability but not
 * shape. And W6 turns out not to reach its own canonical write at all, which no amount of
 * reading the write site would have revealed.
 *
 * VEHICLE
 * -------
 * The controller methods are invoked DIRECTLY with a constructed `Request`, resolved
 * through the container because each controller takes an injected
 * `LocationDnaChipPresenter`. This is the same methodological choice the G1a suites
 * document for Livewire components: routing and middleware are not what is being
 * characterised, and driving the full HTTP stack would exercise session, CSRF and redirect
 * behaviour instead of the write contract.
 *
 * These controllers catch their own exceptions and `BuyerCriteriaAuctionController`
 * returns `$e->getMessage()` as the response body, so a swallowed failure would otherwise
 * look identical to "the guard correctly declined to write". Every invocation therefore
 * returns a diagnostic string that is folded into the assertion messages, and the
 * preconditions below assert the vehicle reached the write before anything else is
 * claimed about it.
 *
 * NO MIGRATION, NO REFACTOR
 * -------------------------
 * Nothing here changes the controllers. Whether W6–W9 fall inside G1f's consolidation
 * scope is owner decision D-G1F-5, which is open.
 */
class G1fLegacyCriteriaControllerCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    private const VALID_BLOB = '{"cities":["Orlando"],"state":"GA"}';

    private const SEEDED_BLOB = '{"cities":["Sarasota"],"state":"FL"}';

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /** Baseline request parameters every criteria path reads. */
    private function baseParams(array $overrides = []): array
    {
        return array_merge([
            'auction_type'    => 'Bidding Period',
            'auction_length'  => '7 days',
            'listing_date'    => '2026-01-01',
            'expiration_date' => '2026-02-01',
            'cities'          => ['Tampa'],
            'counties'        => ['Hillsborough'],
            'state'           => 'FL',
            'states'          => 'FL',
        ], $overrides);
    }

    private function request(array $params): Request
    {
        return Request::create('/g1f-characterisation', 'POST', $params);
    }

    /**
     * Invoke a write path; return '' on completion or a diagnostic on failure.
     *
     * The Tenant paths are wrapped in `TenantCriteriaAuction::withoutEvents()` for a
     * PRE-EXISTING, DOCUMENTED reason unrelated to Location DNA:
     * `TenantCriteriaAuction` uses `App\Traits\HasListingId`, whose `creating` hook assigns
     * `listing_id`, but `tenant_criteria_auctions` has no such column — the migration that
     * adds it (`2025_12_05_063000_add_listing_id_to_auctions_tables`) is guarded by
     * `Schema::hasTable()` and runs BEFORE the table is created (`2026_06_14_000002`), so
     * the column is never added on a fresh migrate.
     *
     * This is the same vehicle, for the same reason, that
     * `PublicGeometryContainmentTest::tenantCriteria()` established and documented. It is
     * suppressed only around the invocation, and it does not touch the guard, the canonical
     * write or any mirror write — all of which run normally.
     */
    private function invoke(string $path, Request $request, $auction = null): string
    {
        try {
            $result = match ($path) {
                'W6' => app(BuyerCriteriaAuctionController::class)->storeAuction($request),
                'W7' => app(BuyerCriteriaAuctionController::class)->updateAuction($request),
                'W8' => TenantCriteriaAuction::withoutEvents(
                    fn () => app(TenantCriteriaAuctionController::class)->store($request)
                ),
                'W9' => TenantCriteriaAuction::withoutEvents(
                    fn () => app(TenantCriteriaAuctionController::class)->update($auction->id, $request)
                ),
            };
        } catch (Throwable $e) {
            return ' [raised: '.$e->getMessage().']';
        }

        return is_string($result) ? ' [controller caught: '.$result.']' : '';
    }

    /**
     * A Buyer criteria row carrying the columns the migration declares NOT NULL.
     *
     * `buyer_id`, `max_price` and `title` have no defaults
     * (`2022_11_21_095636_create_buyer_criteria_auctions_table.php:19-21`), so a row cannot
     * be created without them — which is exactly why W6 cannot complete. See
     * test_w6_cannot_reach_its_canonical_write_against_the_migrated_schema.
     */
    private function seedBuyerAuction(User $owner, ?string $blob = null): BuyerCriteriaAuction
    {
        $auction = new BuyerCriteriaAuction();
        $auction->user_id        = $owner->id;
        $auction->buyer_id       = $owner->id;
        $auction->max_price      = 500000;
        $auction->title          = 'G1f criteria characterisation';
        $auction->auction_type   = 'Bidding Period';
        $auction->auction_length = 7;
        $auction->save();

        if ($blob !== null) {
            $auction->saveMeta('location_dna_preferences', $blob);
        }

        return BuyerCriteriaAuction::with('meta')->findOrFail($auction->id);
    }

    /** Built with events suppressed, for the documented `listing_id` reason in invoke(). */
    private function seedTenantAuction(User $owner, ?string $blob = null): TenantCriteriaAuction
    {
        $auction = TenantCriteriaAuction::withoutEvents(function () use ($owner) {
            $record = new TenantCriteriaAuction();
            $record->user_id     = $owner->id;
            $record->is_approved = true;
            $record->save();

            return $record;
        });

        if ($blob !== null) {
            $auction->saveMeta('location_dna_preferences', $blob);
        }

        return TenantCriteriaAuction::with('meta')->findOrFail($auction->id);
    }

    private function rereadBuyer(BuyerCriteriaAuction $a): BuyerCriteriaAuction
    {
        return BuyerCriteriaAuction::with('meta')->findOrFail($a->id);
    }

    private function rereadTenant(TenantCriteriaAuction $a): TenantCriteriaAuction
    {
        return TenantCriteriaAuction::with('meta')->findOrFail($a->id);
    }

    private function newestTenant(): ?TenantCriteriaAuction
    {
        return TenantCriteriaAuction::with('meta')->orderByDesc('id')->first();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // W6 · a write site that is never reached
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · `storeAuction()` cannot reach its canonical write against the
     * migrated schema. It fails on the initial insert.
     *
     * The most consequential result in this suite, and one that only execution could
     * produce. `buyer_criteria_auctions` declares `buyer_id`, `max_price` and `title` NOT
     * NULL with no defaults, and `storeAuction()` sets none of them — it assigns only
     * `user_id`, `auction_type` and `auction_length` before calling `save()` (`:47-:53`).
     * The insert therefore raises an integrity-constraint violation, the controller's own
     * `catch` rolls the transaction back, and execution never reaches the canonical write
     * at `:234`.
     *
     * DECLARED BOUNDARY. This is measured against the schema as MIGRATED, on the SQLite
     * test connection. It is the same class of environment-dependent result as the
     * recorded `ILIKE` and `pg_try_advisory_lock` failures, and this suite cannot
     * determine whether the live PostgreSQL database has since acquired defaults for those
     * columns outside the migration history. What is proven is narrow and still useful:
     * against the schema this repository declares, W6 is unreachable, so it cannot be
     * migrated by G1f without first being made to work — and G1f should not be the
     * increment that discovers this.
     */
    public function test_w6_cannot_reach_its_canonical_write_against_the_migrated_schema(): void
    {
        $owner = $this->owner();
        Auth::login($owner);

        $before = BuyerCriteriaAuction::count();

        $diag = $this->invoke('W6', $this->request($this->baseParams([
            'location_dna_preferences' => self::VALID_BLOB,
        ])));

        $this->assertStringContainsString(
            'buyer_id',
            $diag,
            'W6 must fail on the NOT NULL `buyer_id` column that storeAuction() never sets.'
        );
        $this->assertSame(
            $before,
            BuyerCriteriaAuction::count(),
            'W6 must leave no row behind — its own catch rolls the transaction back.'
        );
    }

    /**
     * CHARACTERISED STRUCTURALLY · W6 nevertheless carries the same guard as the other
     * three paths.
     *
     * Recorded so the guard inventory stays complete even though the path is unreachable.
     * If `storeAuction()` is ever repaired, the guard is already the D-G1-2 shape.
     */
    public function test_w6_still_carries_the_shared_guard_shape(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/BuyerCriteriaAuctionController.php'));

        $this->assertStringContainsString(
            "\$auction->saveMeta('location_dna_preferences', \$ldnaValue);",
            $source,
            'W6 must still contain its canonical write site.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PRECONDITIONS · the three reachable paths
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * PRECONDITION · W7, W8 and W9 complete without being caught.
     *
     * Asserted before any "nothing was written" claim, because a rolled-back request and a
     * guard correctly declining to write are indistinguishable at the storage layer.
     */
    public function test_precondition_the_three_reachable_paths_complete(): void
    {
        $owner = $this->owner();
        Auth::login($owner);
        $buyer = $this->seedBuyerAuction($owner, self::SEEDED_BLOB);
        $this->assertSame('', $this->invoke('W7', $this->request($this->baseParams([
            'id'                       => $buyer->id,
            'location_dna_preferences' => self::VALID_BLOB,
        ]))), 'W7 must complete');

        $owner2 = $this->owner();
        Auth::login($owner2);
        $this->assertSame('', $this->invoke('W8', $this->request($this->baseParams([
            'location_dna_preferences' => self::VALID_BLOB,
        ]))), 'W8 must complete');
        $this->assertNotNull($this->newestTenant(), 'W8 must have created a record');

        $owner3 = $this->owner();
        Auth::login($owner3);
        $tenant = $this->seedTenantAuction($owner3, self::SEEDED_BLOB);
        $this->assertSame('', $this->invoke('W9', $this->request($this->baseParams([
            'location_dna_preferences' => self::VALID_BLOB,
        ])), $tenant), 'W9 must complete');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // D-G1-2 CONFORMANCE · the guard the eight workflows lack
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · on both UPDATE paths, an absent, empty or unparseable payload
     * PRESERVES the stored canonical document.
     *
     * F-G1F-2 measured. This is D-G1-2 Option 2-A's approved rule — "an unmounted field
     * performs no operation", "an omitted field performs no operation", "absence is not
     * clear" — already in production.
     *
     * The contrast with the eight Livewire workflows is the point: there, an empty payload
     * destroys the entire blob
     * (`test_unmounted_editor_empty_payload_destroys_all_saved_geometry`).
     */
    public function test_update_paths_preserve_the_stored_document_for_every_non_writing_input_class(): void
    {
        $cases = [
            'absent'      => null,
            'empty'       => '',
            'unparseable' => '{"cities": [',
        ];

        foreach ($cases as $label => $payload) {
            // ---- W7 ----
            $owner = $this->owner();
            Auth::login($owner);
            $buyer  = $this->seedBuyerAuction($owner, self::SEEDED_BLOB);
            $params = $this->baseParams(['id' => $buyer->id]);
            if ($payload !== null) {
                $params['location_dna_preferences'] = $payload;
            }
            $diag = $this->invoke('W7', $this->request($params));

            $this->assertSame(
                self::SEEDED_BLOB,
                (string) $this->rereadBuyer($buyer)->info('location_dna_preferences'),
                "W7 · {$label}: the stored canonical document must be PRESERVED".$diag
            );

            // ---- W9 ----
            $owner2 = $this->owner();
            Auth::login($owner2);
            $tenant  = $this->seedTenantAuction($owner2, self::SEEDED_BLOB);
            $params2 = $this->baseParams();
            if ($payload !== null) {
                $params2['location_dna_preferences'] = $payload;
            }
            $diag2 = $this->invoke('W9', $this->request($params2), $tenant);

            $this->assertSame(
                self::SEEDED_BLOB,
                (string) $this->rereadTenant($tenant)->info('location_dna_preferences'),
                "W9 · {$label}: the stored canonical document must be PRESERVED".$diag2
            );
        }
    }

    /**
     * CHARACTERISED · on the reachable CREATE path, an absent, empty or unparseable
     * payload writes NO canonical key at all.
     *
     * `info()` returns boolean `false` for an unwritten meta key — the absence G1c's
     * hydrator already treats as "no document exists".
     */
    public function test_the_reachable_create_path_writes_no_canonical_key_for_non_writing_input(): void
    {
        foreach ([
            'absent'      => null,
            'empty'       => '',
            'unparseable' => 'not json at all',
        ] as $label => $payload) {
            $owner = $this->owner();
            Auth::login($owner);

            $params = $this->baseParams();
            if ($payload !== null) {
                $params['location_dna_preferences'] = $payload;
            }

            $diag    = $this->invoke('W8', $this->request($params));
            $created = $this->newestTenant();

            $this->assertNotNull($created, "W8 · {$label}: a record must have been created".$diag);
            $this->assertFalse(
                $created->info('location_dna_preferences'),
                "W8 · {$label}: no canonical key must be written".$diag
            );
        }
    }

    /**
     * CHARACTERISED · a valid canonical document IS written verbatim on all three
     * reachable paths.
     *
     * The positive control. Without it, every preservation assertion above could be
     * satisfied by a path that simply never writes anything.
     */
    public function test_a_valid_document_is_written_verbatim_on_every_reachable_path(): void
    {
        $owner = $this->owner();
        Auth::login($owner);
        $buyer = $this->seedBuyerAuction($owner, self::SEEDED_BLOB);
        $diag  = $this->invoke('W7', $this->request($this->baseParams([
            'id'                       => $buyer->id,
            'location_dna_preferences' => self::VALID_BLOB,
        ])));
        $this->assertSame(
            self::VALID_BLOB,
            (string) $this->rereadBuyer($buyer)->info('location_dna_preferences'),
            'W7: a valid document must overwrite the stored one.'.$diag
        );

        $owner2 = $this->owner();
        Auth::login($owner2);
        $diag2 = $this->invoke('W8', $this->request($this->baseParams([
            'location_dna_preferences' => self::VALID_BLOB,
        ])));
        $this->assertSame(
            self::VALID_BLOB,
            (string) $this->newestTenant()->info('location_dna_preferences'),
            'W8: a valid document must be written byte-for-byte.'.$diag2
        );

        $owner3 = $this->owner();
        Auth::login($owner3);
        $tenant = $this->seedTenantAuction($owner3, self::SEEDED_BLOB);
        $diag3  = $this->invoke('W9', $this->request($this->baseParams([
            'location_dna_preferences' => self::VALID_BLOB,
        ])), $tenant);
        $this->assertSame(
            self::VALID_BLOB,
            (string) $this->rereadTenant($tenant)->info('location_dna_preferences'),
            'W9: a valid document must overwrite the stored one.'.$diag3
        );
    }

    /**
     * CHARACTERISED GAP · the guard validates PARSEABILITY but not SHAPE.
     *
     * `json_decode("0")` is integer 0 and `json_last_error()` is `JSON_ERROR_NONE`, so a
     * syntactically valid non-object passes the guard and is persisted as the canonical
     * document. Same for a JSON list.
     *
     * This is precisely the hole G1c's hydrator closes — `LocationDnaHydrator::hydrate()`
     * rejects both a decoded scalar and a JSON list as malformed. Recorded so the report's
     * claim that these controllers are a reference implementation stays qualified by
     * measurement: they get the presence rule right and the shape rule wrong.
     */
    public function test_the_guard_admits_syntactically_valid_non_objects(): void
    {
        foreach (['scalar' => '0', 'list' => '[1,2]'] as $label => $payload) {
            $owner = $this->owner();
            Auth::login($owner);

            $diag = $this->invoke('W8', $this->request($this->baseParams([
                'location_dna_preferences' => $payload,
            ])));

            $this->assertSame(
                $payload,
                (string) $this->newestTenant()->info('location_dna_preferences'),
                "W8 · {$label}: a syntactically valid non-object is currently accepted and stored. "
                .'The guard checks json_last_error() only — it does not check shape.'.$diag
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MIRROR VOCABULARY · a third dialect
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · the Buyer criteria update path emits BOTH `states` (plural,
     * JSON-encoded) and `state` (singular, raw) in one save.
     *
     * Discovered while closing this gap, and a correction to the G1f report's §5.2 table,
     * which recorded `:63` and `:435` as `state` writes. They are `states`. The mirrors are
     * additionally written TWICE — `cities`/`counties` at `:433`-`:434` and again at
     * `:733`-`:732` — a controller-level double-write directly analogous to F-G1F-3's
     * Livewire one.
     *
     * Consequence for G1f: after an update, a Buyer criteria record carries two state keys
     * in two encodings, and no G1b consumer reads the plural one.
     */
    public function test_buyer_criteria_update_emits_both_state_keys_in_different_encodings(): void
    {
        $owner = $this->owner();
        Auth::login($owner);
        $buyer = $this->seedBuyerAuction($owner, self::SEEDED_BLOB);

        $diag = $this->invoke('W7', $this->request($this->baseParams([
            'id'                       => $buyer->id,
            'location_dna_preferences' => self::VALID_BLOB,
        ])));

        $stored = $this->rereadBuyer($buyer);

        $this->assertSame(
            json_encode('FL'),
            (string) $stored->info('states'),
            'W7 writes the plural `states` key, JSON-encoded.'.$diag
        );
        $this->assertSame(
            'FL',
            (string) $stored->info('state'),
            'W7 ALSO writes the singular `state` key, raw — two keys, two encodings, one save.'.$diag
        );
    }

    /**
     * CHARACTERISED · the Tenant criteria paths JSON-encode the singular `state` mirror,
     * unlike every Livewire writer, which stores it raw.
     *
     * F-G1F-6 measured. A reader of the `state` meta key receives `FL` from a Livewire save
     * and `"FL"` — with literal quote characters — from a Tenant criteria save, for the
     * same conceptual field.
     */
    public function test_tenant_criteria_double_encodes_the_state_mirror(): void
    {
        $owner = $this->owner();
        Auth::login($owner);

        $diag   = $this->invoke('W8', $this->request($this->baseParams([
            'location_dna_preferences' => self::VALID_BLOB,
        ])));
        $stored = (string) $this->newestTenant()->info('state');

        $this->assertSame(
            '"FL"',
            $stored,
            'The Tenant criteria path stores the state mirror JSON-encoded, so the raw column value '
            .'carries literal quote characters.'.$diag
        );
        $this->assertNotSame(
            'FL',
            $stored,
            'It is NOT the raw form every Livewire writer produces — the two are not interchangeable.'
        );
    }

    /**
     * CHARACTERISED · the canonical document and the mirrors are persisted unreconciled.
     *
     * They travel independently on these paths: the blob comes from
     * `location_dna_preferences` and the mirrors from separate request fields, with no
     * hydration step between them. A single request can therefore state `Orlando` in the
     * blob and `Tampa` in the mirror, and both are persisted.
     *
     * This is the structural opposite of the Livewire writers, where the mirror is derived
     * from the blob (GAP 2). G1f's mirror projection contract has to account for both.
     */
    public function test_canonical_and_mirror_values_are_persisted_unreconciled(): void
    {
        $owner = $this->owner();
        Auth::login($owner);

        $diag = $this->invoke('W8', $this->request($this->baseParams([
            'cities'                   => ['Tampa'],
            'location_dna_preferences' => self::VALID_BLOB,
        ])));

        $created = $this->newestTenant();

        $this->assertSame(
            self::VALID_BLOB,
            (string) $created->info('location_dna_preferences'),
            'the blob holds the request payload'.$diag
        );
        $this->assertSame(
            json_encode(['Tampa']),
            (string) $created->info('cities'),
            'the mirror holds a separate request field — no reconciliation occurs, so blob and mirror '
            .'can disagree straight out of a single save.'.$diag
        );
    }

    /**
     * CHARACTERISED · no criteria controller writes a `zipCodes` mirror.
     *
     * Completes the mirror-vocabulary picture with GAP 4: `zipCodes` is Tenant-Livewire
     * only, and the criteria controllers contribute `states` instead — three disjoint
     * mirror vocabularies across the nine canonical writers.
     */
    public function test_no_criteria_controller_writes_a_zipcodes_mirror(): void
    {
        foreach ([
            'app/Http/Controllers/BuyerCriteriaAuctionController.php',
            'app/Http/Controllers/TenantCriteriaAuctionController.php',
        ] as $relative) {
            $this->assertStringNotContainsString(
                "saveMeta('zipCodes'",
                file_get_contents(base_path($relative)),
                "{$relative}: must not write a zipCodes mirror."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TRANSACTIONALITY
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED STRUCTURALLY · three of the four paths wrap their writes in a
     * transaction; `TenantCriteriaAuctionController::update()` has its transaction
     * COMMENTED OUT.
     *
     * F-G1F-8. Asserted structurally because forcing a mid-request failure inside a
     * controller without editing it is not achievable from a test, and editing it is
     * outside this increment's authorization. The boundary is declared rather than papered
     * over.
     */
    public function test_transaction_coverage_across_the_four_paths(): void
    {
        $buyer  = file_get_contents(base_path('app/Http/Controllers/BuyerCriteriaAuctionController.php'));
        $tenant = file_get_contents(base_path('app/Http/Controllers/TenantCriteriaAuctionController.php'));

        $this->assertSame(
            2,
            substr_count($buyer, 'DB::beginTransaction();'),
            'W6 and W7 each open a transaction.'
        );
        $this->assertStringContainsString(
            '// DB::beginTransaction();',
            $tenant,
            'W9 has its transaction commented out — the reason it is non-atomic.'
        );
        $this->assertSame(
            1,
            substr_count($tenant, "\n            DB::beginTransaction();"),
            'Only W8 opens a live transaction in the Tenant controller.'
        );
    }

    /**
     * CHARACTERISED STRUCTURALLY · the canonical write precedes validation on both Tenant
     * criteria paths.
     *
     * The reason F-G1F-8 matters. On `store()` the ordering is harmless because the
     * transaction rolls the write back; on `update()` there is no transaction, so the same
     * ordering leaves a committed canonical write behind a failed request.
     */
    public function test_the_canonical_write_precedes_validation_on_both_tenant_paths(): void
    {
        $tenant = file_get_contents(base_path('app/Http/Controllers/TenantCriteriaAuctionController.php'));

        $storeWrite  = strpos($tenant, "\$auction->saveMeta('location_dna_preferences', \$ldnaTenantStore);");
        $updateWrite = strpos($tenant, "\$auction->saveMeta('location_dna_preferences', \$ldnaTenantUpdate);");

        $this->assertNotFalse($storeWrite, 'W8 canonical write must still be present');
        $this->assertNotFalse($updateWrite, 'W9 canonical write must still be present');

        $this->assertNotFalse(
            strpos($tenant, '$request->validate([', $storeWrite),
            'W8: a validate() call follows the canonical write.'
        );
        $this->assertNotFalse(
            strpos($tenant, '$request->validate([', $updateWrite),
            'W9: a validate() call follows the canonical write — with no transaction to undo it.'
        );
    }

    /**
     * CHARACTERISED STRUCTURALLY · all four guards share one shape.
     *
     * Asserted rather than assumed, so a future divergence between the four is visible.
     */
    public function test_all_four_paths_share_one_guard_shape(): void
    {
        foreach ([
            'app/Http/Controllers/BuyerCriteriaAuctionController.php',
            'app/Http/Controllers/TenantCriteriaAuctionController.php',
        ] as $relative) {
            $source = file_get_contents(base_path($relative));

            $this->assertSame(
                2,
                substr_count($source, 'if (json_last_error() === JSON_ERROR_NONE) {'),
                "{$relative}: both paths carry the parseability guard."
            );
            $this->assertSame(
                2,
                substr_count($source, "!== null && \$ldna"),
                "{$relative}: each path guards on both null and empty string before decoding."
            );
        }
    }
}
