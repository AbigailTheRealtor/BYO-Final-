<?php

namespace Tests\Feature\Location;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Suggestions\AddressCandidate;
use App\Services\Location\Suggestions\AddressPointSuggestionProvider;
use App\Services\Location\Suggestions\AddressSuggestionProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The corpus-backed suggestion provider.
 *
 * FIXTURES, NOT DATA
 * ------------------
 * Every row below is invented, six rows total, spread across five states. No
 * address dataset is downloaded, imported or seeded by this suite, and the real
 * corpus stays empty and flag-off. The spread is deliberate: a provider that
 * only ever saw Florida rows could hard-code a jurisdiction and still pass.
 *
 * Fixture `normalized` values are produced by {@see PropertyAddress} rather than
 * typed by hand, because that is the string an importer is obliged to write. A
 * hand-typed fixture would let this suite pass against a normalizer the corpus
 * will never actually contain.
 *
 * The fixture connection is SQLite and therefore not PostGIS, which is what
 * makes the "no readable point, so no coordinate hint" path the default here.
 * The point-carrying path is covered purely, through
 * {@see AddressPointSuggestionProvider::candidateFromRow()}.
 */
class AddressPointSuggestionProviderTest extends TestCase
{
    private const CONNECTION = 'address_corpus_fixture';
    private const VERSION    = 'fixture-2026-a';

    private AddressPointSuggestionProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new AddressPointSuggestionProvider();

        config([
            'database.connections.' . self::CONNECTION => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ],
            'address_point_corpus.connection'     => self::CONNECTION,
            'address_point_corpus.enabled'        => true,
            'address_point_corpus.corpus_version' => self::VERSION,
        ]);

        $this->createCorpusTable();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    // ── fixture corpus ──────────────────────────────────────────────────────

    private function createCorpusTable(): void
    {
        // Mirrors the columns the provider reads from `pgsql_spatial.addresses`.
        // `geom` is absent: SQLite has no PostGIS, and its absence is the point
        // of test_a_corpus_without_a_readable_point_yields_no_hint().
        Schema::connection(self::CONNECTION)->create('addresses', function ($table): void {
            $table->increments('id');
            $table->string('source');
            $table->string('number')->nullable();
            $table->string('street')->nullable();
            $table->string('unit')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('normalized');
            $table->string('precision');
            $table->string('corpus_version');
            $table->string('source_ref')->nullable();
        });
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function fixtureRows(): array
    {
        return [
            // The query target, and its two near neighbours — one in the same
            // city, one two thousand miles away with the same street name.
            ['315',  'E Madison St',        '',   'Tampa',    'FL', '33602', 'fx-tampa-315'],
            ['1315', 'E Madison St',        '',   'Tampa',    'FL', '33602', 'fx-tampa-1315'],
            ['315',  'E Madison St',        '',   'Madison',  'WI', '53703', 'fx-madison-315'],

            // The two USPS C1 hazards named in PropertyAddress: `hill` and
            // `view` are suffix vocabulary and also ordinary street names.
            ['4600', 'Silver Hill Road',    '',   'Suitland', 'MD', '20746', 'fx-suitland-4600'],
            ['100',  'Mountain View Drive', '',   'Denver',   'CO', '80202', null],

            // A unit, to prove the unit column survives into the candidate.
            ['1200', 'N Main St',           '4A', 'Austin',   'TX', '78701', 'fx-austin-1200-4a'],

            // A pair sharing a house number where the shorter line is NOT the
            // prefix hit — the only arrangement in which prefix-before-length
            // is observable rather than merely asserted.
            ['12',   'W Washington Blvd',   '',   'Chicago',  'IL', '60602', 'fx-chicago-12'],
            ['12',   'Elm St',              '',   'West Palm Beach', 'FL', '33401', 'fx-wpb-12'],
        ];
    }

    private function seedFixtures(): void
    {
        $insert = [];

        foreach ($this->fixtureRows() as [$number, $street, $unit, $city, $state, $zip, $sourceRef]) {
            $insert[] = [
                'source'         => 'fixture',
                'number'         => $number,
                'street'         => $street,
                'unit'           => $unit,
                'city'           => $city,
                'state'          => $state,
                'postcode'       => $zip,
                'normalized'     => self::normalizedFor($number, $street, $city, $state, $zip),
                'precision'      => 'rooftop',
                'corpus_version' => self::VERSION,
                'source_ref'     => $sourceRef,
            ];
        }

        DB::connection(self::CONNECTION)->table('addresses')->insert($insert);
    }

    /** The exact string an importer owes the `normalized` column. */
    private static function normalizedFor(
        string $number,
        string $street,
        string $city,
        string $state,
        string $zip,
    ): string {
        return (new PropertyAddress(
            address: trim($number . ' ' . $street),
            city:    $city,
            state:   $state,
            zip:     $zip,
        ))->coordinateLookupLine();
    }

    /** @return list<string> */
    private function displayLines(array $candidates): array
    {
        return array_map(
            static fn (AddressCandidate $c): string => $c->displayLine,
            $candidates
        );
    }

    // ── identity ────────────────────────────────────────────────────────────

    public function test_it_satisfies_the_suggestion_contract(): void
    {
        $this->assertInstanceOf(AddressSuggestionProviderInterface::class, $this->provider);
    }

    public function test_it_reports_the_corpus_identity_and_no_network_cost(): void
    {
        $this->assertSame('address_point', $this->provider->providerId());
        $this->assertFalse($this->provider->requiresNetwork());
    }

    // ── A. disabled / inert ─────────────────────────────────────────────────

    public function test_the_shipped_default_is_still_off(): void
    {
        // Read from the config file rather than this test's override, so this
        // fails if the corpus flag is ever flipped on in the file itself.
        $shipped = require base_path('config/address_point_corpus.php');

        $this->assertFalse($shipped['enabled']);
        $this->assertNull($shipped['corpus_version']);
    }

    public function test_a_disabled_provider_is_unavailable_and_suggests_nothing_without_querying(): void
    {
        config(['address_point_corpus.enabled' => false]);

        $queries = [];
        DB::listen(function ($q) use (&$queries): void { $queries[] = $q->sql; });

        $this->assertFalse($this->provider->isAvailable());
        $this->assertSame([], $this->provider->suggest('315 e mad'));
        $this->assertSame([], $queries, 'A disabled provider must not cost a query per keystroke.');
    }

    public function test_an_enabled_provider_without_a_pinned_version_stays_inert(): void
    {
        // The dangerous half-configured state: somebody enabled the corpus and
        // did not say which import to read. It must not guess.
        config(['address_point_corpus.corpus_version' => null]);

        $queries = [];
        DB::listen(function ($q) use (&$queries): void { $queries[] = $q->sql; });

        $this->assertFalse($this->provider->isAvailable());
        $this->assertSame([], $this->provider->suggest('315 e mad'));
        $this->assertSame([], $queries);
    }

    public function test_a_blank_pinned_version_counts_as_none(): void
    {
        config(['address_point_corpus.corpus_version' => '   ']);

        $this->assertFalse($this->provider->isAvailable());
        $this->assertSame([], $this->provider->suggest('315 e mad'));
    }

    public function test_it_reads_only_the_pinned_corpus_version(): void
    {
        config(['address_point_corpus.corpus_version' => 'some-other-import']);

        $this->assertSame(
            [],
            $this->provider->suggest('315 e mad'),
            'Rows belong to the import that loaded them; an unpinned import is not readable.'
        );
    }

    // ── I. unavailable corpus fails safely ──────────────────────────────────

    public function test_an_unreachable_connection_makes_the_provider_unavailable_not_fatal(): void
    {
        config(['address_point_corpus.connection' => 'no_such_connection']);

        $this->assertFalse($this->provider->isAvailable());
    }

    public function test_a_connection_without_the_corpus_table_makes_the_provider_unavailable(): void
    {
        Schema::connection(self::CONNECTION)->drop('addresses');

        $this->assertFalse($this->provider->isAvailable());
    }

    public function test_a_corpus_without_the_version_column_makes_the_provider_unavailable(): void
    {
        // Without `corpus_version` the versioning migration has not run, and
        // reading anyway would mean suggesting rows from an unknown import.
        Schema::connection(self::CONNECTION)->table('addresses', function ($table): void {
            $table->dropColumn('corpus_version');
        });

        $this->assertFalse($this->provider->isAvailable());
    }

    public function test_it_is_available_once_the_corpus_is_present_pinned_and_enabled(): void
    {
        $this->assertTrue($this->provider->isAvailable());
    }

    // ── I. a corpus fault never escapes into the address field ──────────────

    /**
     * @dataProvider brokenCorpusStates
     */
    public function test_a_broken_corpus_returns_no_suggestions_instead_of_throwing(callable $break): void
    {
        // This provider is optional infrastructure and is the whole suggestion
        // stack — there is no ladder underneath it to catch a raise, so the only
        // place an exception could surface is the Seller/Landlord address field
        // somebody is typing into. A corpus outage must cost a dropdown, never
        // listing creation.
        $break();

        Log::spy();

        $this->assertSame([], $this->provider->suggest('315 e mad'));
    }

    public static function brokenCorpusStates(): array
    {
        return [
            'connection does not exist' => [
                static function (): void {
                    config(['address_point_corpus.connection' => 'no_such_connection']);
                },
            ],
            'connection is misconfigured' => [
                static function (): void {
                    config(['database.connections.' . self::CONNECTION . '.driver' => 'not_a_driver']);

                    // The seeded connection is already resolved and its PDO
                    // still works; purging is what makes the next call actually
                    // re-read the broken config, as a fresh request would.
                    DB::purge(self::CONNECTION);
                },
            ],
            'corpus table is absent' => [
                static function (): void {
                    Schema::connection(self::CONNECTION)->drop('addresses');
                },
            ],
            'corpus is missing a column the query reads' => [
                static function (): void {
                    Schema::connection(self::CONNECTION)->table('addresses', static function ($table): void {
                        $table->dropColumn('normalized');
                    });
                },
            ],
        ];
    }

    public function test_a_corpus_fault_is_logged_so_an_outage_is_not_invisible(): void
    {
        // Returning [] must not mean nobody finds out. A corpus that has been
        // down for a week looks exactly like a corpus with no matching rows.
        Schema::connection(self::CONNECTION)->drop('addresses');

        Log::spy();

        $this->provider->suggest('315 e mad');

        $captured = null;

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message, $context) use (&$captured): bool {
                $captured = ['message' => $message, 'context' => $context];

                return true;
            });

        $this->assertSame('address_suggestion_provider_fault', $captured['message']);
        $this->assertSame('address_point', $captured['context']['provider']);
        $this->assertSame(self::CONNECTION, $captured['context']['connection']);
        $this->assertNotSame('', $captured['context']['message']);
    }

    public function test_a_logged_fault_never_carries_what_somebody_was_typing(): void
    {
        // QueryException::getMessage() appends the failing statement with its
        // bindings interpolated, so the naive log line for this exact path is
        // somebody's home address — written on every keystroke, at the moment
        // the corpus is failing hardest and the log is filling fastest.
        Schema::connection(self::CONNECTION)->table('addresses', static function ($table): void {
            $table->dropColumn('normalized');
        });

        Log::spy();

        $this->provider->suggest('4471 zzqqwx terrace');

        $captured = null;

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message, $context) use (&$captured): bool {
                $captured = $context;

                return true;
            });

        $haystack = strtolower(json_encode($captured) ?: '');

        foreach (['zzqqwx', '4471', 'terrace', 'select '] as $fragment) {
            $this->assertStringNotContainsString($fragment, $haystack, $fragment);
        }
    }

    public function test_an_interpolated_statement_is_cut_out_of_a_fault_message(): void
    {
        $this->assertSame(
            'SQLSTATE[HY000]: no such column: normalized',
            AddressPointSuggestionProvider::faultMessage(new \RuntimeException(
                'SQLSTATE[HY000]: no such column: normalized (SQL: select * from addresses where normalized like %315 elm%)'
            ))
        );
    }

    public function test_a_message_with_no_statement_in_it_survives_intact(): void
    {
        $this->assertSame(
            'could not find driver',
            AddressPointSuggestionProvider::faultMessage(new \RuntimeException('could not find driver'))
        );
    }

    public function test_a_healthy_corpus_logs_no_fault(): void
    {
        // The other half of the contract: swallowing must be reachable only by
        // an actual fault, so a working provider stays silent.
        Log::spy();

        $this->assertNotEmpty($this->provider->suggest('315 e mad'));
        $this->assertSame([], $this->provider->suggest('999 nonexistent boulevard'));

        Log::shouldNotHaveReceived('warning');
    }

    // ── B. empty query ──────────────────────────────────────────────────────

    /** @dataProvider emptyQueries */
    public function test_an_empty_query_suggests_nothing_without_querying(string $query): void
    {
        $queries = [];
        DB::listen(function ($q) use (&$queries): void { $queries[] = $q->sql; });

        $this->assertSame([], $this->provider->suggest($query));
        $this->assertSame([], $queries, 'Nothing typed is nothing to look up.');
    }

    public static function emptyQueries(): array
    {
        return [
            'empty'       => [''],
            'whitespace'  => ['   '],
            'punctuation' => ['   ,,, ... --- '],
        ];
    }

    // ── C / D. partial query, structured result ─────────────────────────────

    public function test_a_partial_street_address_query_returns_candidates(): void
    {
        $results = $this->provider->suggest('315 e mad');

        $this->assertNotEmpty($results);
        $this->assertContainsOnlyInstancesOf(AddressCandidate::class, $results);
    }

    public function test_a_candidate_carries_the_corpus_structured_parts(): void
    {
        $results = $this->provider->suggest('315 e madison st tampa');

        $this->assertCount(1, $results);

        $candidate = $results[0];

        $this->assertSame('address_point', $candidate->providerId);
        $this->assertSame('315',          $candidate->number);
        $this->assertSame('E Madison St', $candidate->street);
        $this->assertSame('',             $candidate->unit);
        $this->assertSame('Tampa',        $candidate->city);
        $this->assertSame('FL',           $candidate->state);
        $this->assertSame('33602',        $candidate->zip);
        $this->assertSame('315 E Madison St, Tampa, FL 33602', $candidate->displayLine);
    }

    public function test_a_unit_survives_into_the_candidate_and_its_display_line(): void
    {
        $results = $this->provider->suggest('1200 n main austin');

        $this->assertCount(1, $results);
        $this->assertSame('4A', $results[0]->unit);
        $this->assertSame('1200 N Main St 4A, Austin, TX 78701', $results[0]->displayLine);
    }

    public function test_structured_parts_are_the_corpus_columns_not_a_reparsed_display_line(): void
    {
        // `normalized` is folded and lowercased; the parts are as published.
        // Recovering "E Madison St" from "e madison st" would be guessing.
        $candidate = $this->provider->suggest('315 e madison st tampa')[0];

        $this->assertNotSame(strtolower($candidate->street), $candidate->street);
    }

    // ── E. deterministic ordering ───────────────────────────────────────────

    public function test_equally_ranked_results_are_ordered_shortest_line_first(): void
    {
        $this->assertSame(
            [
                // Both start with "315 e mad"; the shorter line is the tighter
                // answer to what was typed.
                '315 E Madison St, Tampa, FL 33602',
                '315 E Madison St, Madison, WI 53703',
            ],
            $this->displayLines($this->provider->suggest('315 e mad'))
        );
    }

    public function test_a_prefix_hit_outranks_a_shorter_scattered_hit(): void
    {
        // "12 w washington blvd chicago il 60602" starts with what was typed.
        // "12 elm st west palm beach fl 33401" is three characters shorter and
        // matched "w" only by way of "west", so length alone would invert these.
        $this->assertSame(
            [
                '12 W Washington Blvd, Chicago, IL 60602',
                '12 Elm St, West Palm Beach, FL 33401',
            ],
            $this->displayLines($this->provider->suggest('12 w'))
        );
    }

    public function test_a_house_number_does_not_match_inside_a_longer_one(): void
    {
        // The failure this rule exists for: typing your own house number and
        // being offered your neighbour's. "315" must not find "1315".
        $lines = $this->displayLines($this->provider->suggest('315 e madison'));

        $this->assertNotContains('1315 E Madison St, Tampa, FL 33602', $lines);
        $this->assertContains('315 E Madison St, Tampa, FL 33602', $lines);
    }

    public function test_a_partial_word_still_matches_from_its_start(): void
    {
        // The other half of the same rule: "mad" must reach "madison", or a
        // typeahead cannot answer until the last character.
        $this->assertNotEmpty($this->provider->suggest('315 e mad'));
    }

    public function test_the_same_query_returns_the_same_order_every_time(): void
    {
        $first  = $this->displayLines($this->provider->suggest('e madison st'));
        $second = $this->displayLines($this->provider->suggest('e madison st'));

        $this->assertSame($first, $second);
        $this->assertNotEmpty($first);
    }

    public function test_ties_are_broken_by_id_so_the_order_is_total(): void
    {
        // Two rows identical in every column the ordering reads. Without the
        // final `id` tiebreak their relative order would be the planner's.
        $identical = [
            'source' => 'fixture', 'number' => '77', 'street' => 'Twin St',
            'unit' => '', 'city' => 'Reno', 'state' => 'NV', 'postcode' => '89501',
            'normalized' => self::normalizedFor('77', 'Twin St', 'Reno', 'NV', '89501'),
            'precision' => 'rooftop', 'corpus_version' => self::VERSION,
        ];

        DB::connection(self::CONNECTION)->table('addresses')->insert([
            $identical + ['source_ref' => 'fx-reno-a'],
            $identical + ['source_ref' => 'fx-reno-b'],
        ]);

        $refs = array_map(
            static fn (AddressCandidate $c): ?string => $c->sourceRef,
            $this->provider->suggest('77 twin')
        );

        $this->assertSame(['fx-reno-a', 'fx-reno-b'], $refs);
    }

    // ── F. result limit ─────────────────────────────────────────────────────

    public function test_the_caller_limit_bounds_the_result_set(): void
    {
        // "madison" matches three fixture rows.
        $this->assertCount(3, $this->provider->suggest('madison'));

        $this->assertCount(1, $this->provider->suggest('madison', 1));
        $this->assertCount(2, $this->provider->suggest('madison', 2));
        $this->assertCount(3, $this->provider->suggest('madison', 3));
    }

    public function test_the_limit_is_clamped_into_something_a_dropdown_can_hold(): void
    {
        $this->assertSame(
            AddressPointSuggestionProvider::MAX_LIMIT,
            AddressPointSuggestionProvider::boundedLimit(PHP_INT_MAX),
            'A caller must not be able to pull the corpus through a typeahead.'
        );

        $this->assertSame(
            AddressPointSuggestionProvider::DEFAULT_LIMIT,
            AddressPointSuggestionProvider::boundedLimit(0)
        );

        $this->assertSame(
            AddressPointSuggestionProvider::DEFAULT_LIMIT,
            AddressPointSuggestionProvider::boundedLimit(-5)
        );

        $this->assertSame(3, AddressPointSuggestionProvider::boundedLimit(3));
    }

    public function test_an_absurd_limit_still_produces_a_bounded_query(): void
    {
        $sql = [];
        DB::listen(function ($q) use (&$sql): void { $sql[] = $q->sql; });

        $this->provider->suggest('315 e mad', PHP_INT_MAX);

        $this->assertCount(1, $sql);
        $this->assertStringContainsString('limit ' . AddressPointSuggestionProvider::MAX_LIMIT, strtolower($sql[0]));
    }

    // ── G. normalization ────────────────────────────────────────────────────

    public function test_punctuation_and_case_do_not_change_the_answer(): void
    {
        $plain = $this->displayLines($this->provider->suggest('315 e madison st tampa'));
        $messy = $this->displayLines($this->provider->suggest('  315 E. Madison St., TAMPA  '));

        $this->assertSame($plain, $messy);
        $this->assertNotEmpty($plain);
    }

    public function test_a_spelled_out_suffix_matches_the_folded_corpus_form(): void
    {
        // The corpus stores "st"; the person typed "Street".
        $results = $this->displayLines($this->provider->suggest('315 e madison street tampa'));

        $this->assertSame(['315 E Madison St, Tampa, FL 33602'], $results);
    }

    public function test_a_spelled_out_directional_matches_the_folded_corpus_form(): void
    {
        // The corpus stores "n"; the person typed "North".
        $results = $this->displayLines($this->provider->suggest('1200 north main austin'));

        $this->assertSame(['1200 N Main St 4A, Austin, TX 78701'], $results);
    }

    /** @dataProvider suffixVocabularyStreetNames */
    public function test_a_street_named_after_suffix_vocabulary_is_still_findable(
        string $query,
        string $expected,
    ): void {
        // `hill` and `view` are USPS C1 suffix vocabulary AND ordinary street
        // names — the exact pair PropertyAddress documents. Folding a typed
        // token in place would turn "mountain view" into "mtn vw" and match
        // nothing; folding as an alternative can only ever widen.
        $this->assertSame([$expected], $this->displayLines($this->provider->suggest($query)));
    }

    public static function suffixVocabularyStreetNames(): array
    {
        return [
            'hill' => ['4600 silver hill',  '4600 Silver Hill Road, Suitland, MD 20746'],
            'view' => ['100 mountain view', '100 Mountain View Drive, Denver, CO 80202'],
        ];
    }

    public function test_tokens_may_be_typed_in_any_order(): void
    {
        // `normalized` is "<street> <city> <state> <zip>", so a person who types
        // the city before finishing the street still gets their address.
        $this->assertSame(
            ['315 E Madison St, Tampa, FL 33602'],
            $this->displayLines($this->provider->suggest('tampa 315 madison'))
        );
    }

    // ── H. no match ─────────────────────────────────────────────────────────

    public function test_a_query_matching_nothing_returns_an_empty_list_not_an_error(): void
    {
        // "No suggestions" is an ordinary answer while somebody is still typing.
        $this->assertSame([], $this->provider->suggest('999 nonexistent boulevard'));
    }

    public function test_one_unmatched_token_is_enough_to_exclude_a_row(): void
    {
        // Every token must appear. "315 e madison" matches; adding "seattle"
        // must not fall back to a partial match.
        $this->assertSame([], $this->provider->suggest('315 e madison seattle'));
    }

    // ── M / N. provider identity and source_ref ─────────────────────────────

    public function test_every_candidate_reports_the_provider_that_offered_it(): void
    {
        foreach ($this->provider->suggest('e madison st') as $candidate) {
            $this->assertSame($this->provider->providerId(), $candidate->providerId);
        }
    }

    public function test_the_corpus_source_ref_is_preserved(): void
    {
        $candidate = $this->provider->suggest('315 e madison st tampa')[0];

        $this->assertSame('fx-tampa-315', $candidate->sourceRef);
    }

    public function test_a_row_without_a_source_ref_yields_null_rather_than_an_empty_string(): void
    {
        $candidate = $this->provider->suggest('100 mountain view')[0];

        $this->assertNull($candidate->sourceRef);
    }

    // ── coordinate hints: read, never invented, never persisted ─────────────

    public function test_a_corpus_without_a_readable_point_yields_no_hint(): void
    {
        // The fixture connection is SQLite, so there is no PostGIS accessor to
        // read `geom` with and no point is selected. A provider that cannot read
        // a point says so rather than guessing one.
        foreach ($this->provider->suggest('e madison st') as $candidate) {
            $this->assertFalse($candidate->hasCoordinateHint());
            $this->assertNull($candidate->latitude);
            $this->assertNull($candidate->longitude);
            $this->assertSame(CoordinatePrecision::Unknown, $candidate->precision);
        }
    }

    public function test_a_row_that_carries_a_point_keeps_it_as_a_hint_at_the_corpus_precision(): void
    {
        $candidate = AddressPointSuggestionProvider::candidateFromRow((object) [
            'number' => '315', 'street' => 'E Madison St', 'city' => 'Tampa',
            'state' => 'FL', 'postcode' => '33602', 'precision' => 'rooftop',
            'lat' => 27.9506, 'lng' => -82.4572,
        ]);

        $this->assertTrue($candidate->hasCoordinateHint());
        $this->assertSame(['lat' => 27.9506, 'lng' => -82.4572], $candidate->displayCoordinateHint());
        $this->assertSame(CoordinatePrecision::Rooftop, $candidate->precision);
    }

    /** @dataProvider unusablePoints */
    public function test_an_unusable_point_is_dropped_rather_than_carried(mixed $lat, mixed $lng): void
    {
        // Null Island, out of range and unparseable junk are the three ways a
        // stored coordinate column lies. The same guard the coordinate rungs
        // use decides here, so the two cannot disagree.
        $candidate = AddressPointSuggestionProvider::candidateFromRow((object) [
            'street' => 'Somewhere', 'precision' => 'rooftop',
            'lat' => $lat, 'lng' => $lng,
        ]);

        $this->assertFalse($candidate->hasCoordinateHint());
        $this->assertSame(CoordinatePrecision::Unknown, $candidate->precision);
    }

    public static function unusablePoints(): array
    {
        return [
            'null island' => [0.0, 0.0],
            'out of range' => [95.0, -82.4],
            'unparseable' => ['abc', 'def'],
            'absent'      => [null, null],
            'half a pair' => [27.95, null],
        ];
    }

    public function test_an_unrecognised_corpus_precision_reads_as_coarse(): void
    {
        $candidate = AddressPointSuggestionProvider::candidateFromRow((object) [
            'street' => 'Somewhere', 'precision' => 'extremely-precise-honest',
            'lat' => 27.9506, 'lng' => -82.4572,
        ]);

        $this->assertSame(CoordinatePrecision::Unknown, $candidate->precision);
        $this->assertFalse($candidate->precision->isExact());
    }

    // ── O. no coordinate persistence path ───────────────────────────────────

    public function test_the_provider_names_no_property_coordinate_column(): void
    {
        $source = self::providerCode();

        // A picked candidate must travel address -> ladder -> persistence.
        // A second path from a dropdown straight to the column is the exact
        // failure this architecture exists to prevent.
        foreach ([
            'property_lat',
            'property_lng',
            'PropertyCoordinatePersistenceService',
            'PropertyCoordinateResolver',
            'PropertyCoordinateResult',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $source, $needle);
        }
    }

    // ── L. no writes ────────────────────────────────────────────────────────

    public function test_suggesting_writes_nothing(): void
    {
        $statements = [];
        DB::listen(function ($q) use (&$statements): void { $statements[] = strtolower(trim($q->sql)); });

        $this->provider->isAvailable();
        $this->provider->suggest('315 e mad');
        $this->provider->suggest('100 mountain view');
        $this->provider->suggest('999 nothing here');

        $this->assertNotEmpty($statements, 'The reads themselves must be observable for this to mean anything.');

        foreach ($statements as $sql) {
            $this->assertMatchesRegularExpression(
                '/^(select|pragma)\b/',
                $sql,
                "A suggestion provider reads; it found a way to write: {$sql}"
            );
        }
    }

    // ── J / K. no network, no Google ────────────────────────────────────────

    public function test_suggesting_never_reaches_the_network(): void
    {
        Http::fake();

        $this->provider->isAvailable();
        $this->provider->suggest('315 e mad');
        $this->provider->suggest('');

        Http::assertNothingSent();
    }

    public function test_the_provider_depends_on_no_outbound_client_and_no_google_symbol(): void
    {
        $source = self::providerCode();

        foreach ([
            'Http::', 'GuzzleHttp', 'curl_init', 'file_get_contents(\'http',
            'googleapis', 'GOOGLE_PLACES', 'google_place_id', 'Autocomplete',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $source, $needle);
        }
    }

    /**
     * The provider's source with comments removed.
     *
     * These two scans are about what the file *does*. Reading its prose too
     * would forbid the file from explaining the rule it is being held to — the
     * docblock has to be able to say "no Google" and "never `property_lat`"
     * without that being the violation.
     */
    private static function providerCode(): string
    {
        $source = file_get_contents(
            base_path('app/Services/Location/Suggestions/AddressPointSuggestionProvider.php')
        );

        self::assertIsString($source);

        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    // ── P. the candidate still re-enters through the ladder ─────────────────

    public function test_a_candidate_converts_back_to_the_address_the_corpus_indexed(): void
    {
        $candidate = $this->provider->suggest('315 e madison st tampa')[0];

        $address = $candidate->toPropertyAddress();

        $this->assertInstanceOf(PropertyAddress::class, $address);

        // The round trip that makes the pick usable: the address a picked
        // candidate produces is the one the coordinate rung matches on by
        // equality. If these ever diverge, picking a suggestion would resolve
        // to a different row than the one that was suggested.
        $this->assertSame(
            self::normalizedFor('315', 'E Madison St', 'Tampa', 'FL', '33602'),
            $address->coordinateLookupLine()
        );

        $this->assertTrue($address->hasMinimumForLookup());
    }

    public function test_a_candidate_with_a_unit_keeps_it_out_of_the_lookup_line_and_in_the_identity(): void
    {
        $address = $this->provider->suggest('1200 north main austin')[0]->toPropertyAddress();

        $this->assertTrue($address->hasUnit());
        $this->assertStringNotContainsString('4a', $address->coordinateLookupLine());
        $this->assertStringContainsString('4a', $address->propertyIdentityLine());
    }

    public function test_a_candidate_carries_no_listing_handle_into_the_ladder(): void
    {
        // A suggestion knows an address, not a record. Handing the ladder a
        // listing handle it did not come from is how a coordinate gets reused
        // for the wrong property.
        $address = $this->provider->suggest('315 e madison st tampa')[0]->toPropertyAddress();

        $this->assertFalse($address->hasListingHandle());
        $this->assertFalse($address->hasMlsListingKey());
    }
}
