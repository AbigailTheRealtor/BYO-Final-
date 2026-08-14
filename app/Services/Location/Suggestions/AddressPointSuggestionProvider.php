<?php

namespace App\Services\Location\Suggestions;

use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProvenance;
use App\Services\Location\Coordinates\StreetSuffixMap;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The first concrete {@see AddressSuggestionProviderInterface}: our own imported
 * address-point corpus, read locally.
 *
 * Same table, same pinned corpus version and same gate as
 * {@see \App\Services\Location\Coordinates\Adapters\AddressPointCoordinateAdapter}
 * — one indexed SELECT against `pgsql_spatial.addresses`, no HTTP client, no
 * network, no Google. The import reaches the network; a keystroke never does.
 *
 * A SUGGESTION IS NOT A RESOLUTION
 * --------------------------------
 * This class produces {@see AddressCandidate}s and nothing else. It writes
 * nothing, it returns no `PropertyCoordinateResult`, and it has no path to
 * `property_lat` / `property_lng`. A picked candidate becomes a
 * {@see \App\Services\Location\Coordinates\PropertyAddress} via
 * {@see AddressCandidate::toPropertyAddress()} and re-enters through the
 * coordinate ladder like any other address — which is the whole point of the
 * split, and the fix for the old path where an autocomplete pick's coordinate
 * became the listing's coordinate with no provider and no precision recorded.
 *
 * THE OPPOSITE MATCHING RULE FROM THE COORDINATE RUNG, ON PURPOSE
 * ---------------------------------------------------------------
 * The rung matches `normalized` by equality only, because a near miss there is
 * another property's coordinate reported as success. Here the caller is *still
 * typing*, so equality would answer nothing until the last character. This is
 * exactly what the `addresses_trgm` GIN index was created for, and what the
 * lookup-index migration names as its second consumer: the rung uses the btree,
 * the typeahead uses the trigram index, and each stays off the other's.
 *
 * The safety property is not lost, it moves: a fuzzy *coordinate* is a wrong
 * answer, a fuzzy *suggestion* is a list a human then picks from — and whatever
 * they pick is re-resolved by equality downstream.
 *
 * WHY TOKENS, AND WHY FOLDING ONLY EVER ADDS MATCHES
 * --------------------------------------------------
 * `normalized` is `PropertyAddress::coordinateLookupLine()`:
 * `<street> <city> <state> <zip5>`, one lowercase punctuation-free string. A
 * person types "315 e madison tampa" and omits the suffix that sits between
 * "madison" and "tampa", so a single prefix match on the whole query finds
 * nothing. Every query token must therefore appear in `normalized`, in any
 * order.
 *
 * Tokens are folded the way {@see \App\Services\Location\Coordinates\PropertyAddress}
 * folds them — but as an *alternative*, never as a replacement. `foldSuffix()`
 * draws on the full USPS Publication 28 Appendix C1 vocabulary, which contains
 * `view`, `hill`, `garden` and `lake` — words far more often part of a street's
 * name than its type. Replacing a typed token with its folded form would turn
 * "mountain view" into "mtn vw" and match nothing; offering both means folding
 * can only ever widen the result set, so a half-typed word can never be folded
 * into a different one. Directionals are a closed unambiguous set and fold
 * everywhere, exactly as they do in the canonical normalizer.
 *
 * There is deliberately no second normalizer here. Query tokens go through
 * {@see StreetSuffixMap}, the same vocabulary the corpus's own `normalized`
 * column was written with; a suggestion layer with its own idea of what "st"
 * means is how a corpus stops being searchable by its own rules.
 *
 * ORDERING IS TOTAL
 * -----------------
 * Prefix hits first, then the shortest line, then alphabetically, then by `id`.
 * The last tiebreak is what makes the order a fact rather than whatever the
 * planner returned: two identical queries a keystroke apart must not reshuffle
 * a dropdown under a moving finger.
 *
 * INERT UNTIL THE CORPUS EXISTS
 * -----------------------------
 * Gated on `address_point_corpus.enabled` plus a pinned `corpus_version` — the
 * same two switches as the coordinate rung, deliberately not a third one of its
 * own. They gate the same table for the same reason: it holds zero rows and no
 * importer exists, so an ungated provider would spend a query per keystroke to
 * return nothing forever. If suggestions and resolution ever need to move
 * independently, that is a rollout decision that can add a flag then, with a
 * reason; adding one now would mean two switches that must always agree.
 *
 * Nothing binds this class, no route reaches it and no Livewire component calls
 * it. Seller/Landlord address entry still runs on Google Places Autocomplete;
 * swapping that is a later, separately-reviewed step.
 */
final class AddressPointSuggestionProvider implements AddressSuggestionProviderInterface
{
    /** What a caller gets when it expresses no opinion. A dropdown, not a page. */
    public const DEFAULT_LIMIT = 10;

    /**
     * Hard ceiling on one call, regardless of what a caller asks for.
     *
     * Not a tuning knob and not config: the `$limit` argument arrives from a
     * caller, and a caller that passes `PHP_INT_MAX` — by bug or by query
     * string, once this is ever wired to a request — must not be able to pull
     * the corpus through a typeahead endpoint.
     */
    public const MAX_LIMIT = 25;

    /**
     * Longest query we will build a predicate from.
     *
     * Every token becomes another ANDed pair of LIKE clauses, so an unbounded
     * query is an unbounded predicate. Extra tokens are dropped rather than the
     * query refused — the first several already narrow it far past the limit.
     */
    private const MAX_QUERY_TOKENS = 8;

    public function providerId(): string
    {
        // The same identity the coordinate rung reports, because it is the same
        // corpus. Telemetry that says 'address_point' means our own data
        // answered, whichever question was asked.
        return 'address_point';
    }

    public function requiresNetwork(): bool
    {
        return false;
    }

    /**
     * Flag on, a corpus version pinned, and a corpus actually present.
     *
     * Every check is local and cheap, and the whole thing is wrapped: an
     * unconfigured or unreachable spatial connection makes this provider
     * unavailable, never an exception inside a keystroke.
     */
    public function isAvailable(): bool
    {
        if (! $this->enabled() || $this->corpusVersion() === null) {
            return false;
        }

        try {
            $schema = Schema::connection($this->connection());

            // The corpus table plus the column that scopes a read to one import.
            // Without `corpus_version` the versioning migration has not run, and
            // reading anyway would mean suggesting rows from an unknown import.
            return $schema->hasTable('addresses')
                && $schema->hasColumn('addresses', 'corpus_version');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Returns `[]` for every case, without exception — disabled, no corpus
     * pinned, empty query, no match, corpus absent, connection misconfigured,
     * query failed. A caller rendering a dropdown must not have to tell those
     * apart in order to render nothing.
     *
     * WHY A FAULT IS SWALLOWED HERE AND NOT ON THE COORDINATE RUNG
     * ------------------------------------------------------------
     * The interface says to reserve exceptions for genuine provider faults, and
     * the coordinate ladder honours that: a rung that raises is a rung the
     * resolver skips, and the ladder is what turns one rung's fault into the
     * next rung's turn. There is no ladder here. This provider is the whole
     * suggestion stack, so an exception has nowhere to be caught except the
     * Seller/Landlord address field the caller is typing into — and a corpus
     * outage taking down listing creation is a far worse failure than a
     * dropdown that offers nothing.
     *
     * This provider is optional infrastructure. Nothing downstream depends on
     * it having answered: a typed address that gets no suggestion is still
     * typed, still validated and still resolved through the coordinate ladder.
     * The fault is logged so an outage is diagnosable rather than invisible.
     */
    public function suggest(string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        // Defensive: a caller is expected to check isAvailable() first, but this
        // provider must also be safe to call directly — from a probe, a test, or
        // a future caller that assembles its own suggestion stack.
        if (! $this->enabled()) {
            return [];
        }

        $version = $this->corpusVersion();

        if ($version === null) {
            return [];
        }

        $tokens = self::queryTokens($query);

        if ($tokens === []) {
            return [];
        }

        // The only statement here that can fault. Deliberately the *only* thing
        // inside the try: normalization and mapping are pure, and widening this
        // to cover them would turn a mapping bug into a silently empty dropdown
        // that nobody ever notices.
        try {
            $rows = $this->matchingRows($version, $tokens, self::normalizedQuery($query), self::boundedLimit($limit));
        } catch (Throwable $e) {
            $this->reportFault($e);

            return [];
        }

        $candidates = [];

        foreach ($rows as $row) {
            $candidates[] = self::candidateFromRow($row);
        }

        return $candidates;
    }

    // ── normalization ───────────────────────────────────────────────────────

    /**
     * The typed query reduced to the same alphabet `normalized` is written in.
     *
     * Mirrors `PropertyAddress::normalizeToken()` — lowercase, drop the period
     * that makes "st." and "st" differ, replace remaining punctuation with a
     * space, collapse whitespace. `#` survives for the same reason it does
     * there: it is part of how people write units.
     */
    public static function normalizedQuery(string $query): string
    {
        $value = strtolower(trim($query));
        $value = str_replace('.', '', $value);
        $value = preg_replace('/[^a-z0-9# ]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Query tokens, directionals folded, capped.
     *
     * Suffix folding is *not* applied here — see the class docblock. It is
     * offered per token as an alternative at match time, where widening is safe.
     *
     * @return list<string>
     */
    public static function queryTokens(string $query): array
    {
        $normalized = self::normalizedQuery($query);

        if ($normalized === '') {
            return [];
        }

        $tokens = array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $t): bool => $t !== ''
        ));

        $tokens = array_map(
            static fn (string $t): string => StreetSuffixMap::foldDirectional($t),
            $tokens
        );

        return array_slice($tokens, 0, self::MAX_QUERY_TOKENS);
    }

    /** The caller's limit, clamped into something a dropdown can hold. */
    public static function boundedLimit(int $limit): int
    {
        if ($limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    // ── mapping ─────────────────────────────────────────────────────────────

    /**
     * One corpus row as a candidate. Pure — no database, no config, no clock.
     *
     * Structured parts come from the corpus's own columns and are never
     * re-derived from `normalized`: that string has already had its suffixes and
     * directionals folded, so recovering "Madison" from "madison" and a city
     * from a position in a line is guessing dressed as parsing. The display line
     * is built from those same published parts.
     *
     * Absent values stay absent. A row with no `source_ref` yields a null
     * `sourceRef`; a row with no readable point yields no coordinate hint and
     * `CoordinatePrecision::Unknown` rather than an invented tier. Nothing here
     * fabricates a value the corpus did not state — including county, which the
     * `addresses` table has no column for and which is therefore not on
     * {@see AddressCandidate} at all.
     */
    public static function candidateFromRow(object $row): AddressCandidate
    {
        $number   = self::str($row->number   ?? null);
        $street   = self::str($row->street   ?? null);
        $unit     = self::str($row->unit     ?? null);
        $city     = self::str($row->city     ?? null);
        $state    = self::str($row->state    ?? null);
        $postcode = self::str($row->postcode ?? null);

        // The point is a hint for map framing and nothing else — see
        // AddressCandidate. Validated through the same guard the coordinate
        // rungs use, so a Null Island row or a failed cast reads as "no point"
        // here exactly as it does there.
        $latitude  = CoordinateValidator::toFloat($row->lat ?? null);
        $longitude = CoordinateValidator::toFloat($row->lng ?? null);

        $hasPoint = CoordinateValidator::isValidPair($latitude, $longitude);

        return new AddressCandidate(
            providerId:  'address_point',
            displayLine: self::displayLine($number, $street, $unit, $city, $state, $postcode)
                ?: self::str($row->normalized ?? null),
            number:      $number,
            street:      $street,
            unit:        $unit,
            city:        $city,
            state:       $state,
            zip:         $postcode,
            sourceRef:   self::str($row->source_ref ?? null) ?: null,
            latitude:    $hasPoint ? $latitude : null,
            longitude:   $hasPoint ? $longitude : null,
            // Read back through the one rule this codebase has for a stored
            // precision string, so an unrecognised value is coarse rather than
            // trusted. A row we could not read a point from states no precision
            // about a point it did not supply.
            precision:   $hasPoint
                ? CoordinateProvenance::precisionFrom(self::str($row->precision ?? null) ?: null)
                : CoordinatePrecision::Unknown,
            // The corpus reports no per-row confidence. Null is the honest
            // value; a fabricated 1.0 would read as certainty nobody asserted.
            confidence:  null,
        );
    }

    /** "123 N Main St Unit 4A, Tampa, FL 33602", skipping whatever is absent. */
    private static function displayLine(
        string $number,
        string $street,
        string $unit,
        string $city,
        string $state,
        string $postcode,
    ): string {
        $streetLine = self::join(' ', [$number, $street, $unit]);
        $stateZip   = self::join(' ', [$state, $postcode]);

        return self::join(', ', [$streetLine, $city, $stateZip]);
    }

    /** @param list<string> $parts */
    private static function join(string $glue, array $parts): string
    {
        return implode($glue, array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    private static function str(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    // ── reads ───────────────────────────────────────────────────────────────

    /**
     * Corpus rows matching every query token, within the pinned corpus version,
     * best first.
     *
     * @param  list<string> $tokens
     * @return list<object>
     */
    private function matchingRows(string $version, array $tokens, string $normalizedQuery, int $limit): array
    {
        $connection = DB::connection($this->connection());

        $query = $connection->table('addresses')
            ->select($this->selectColumns($connection->getDriverName()))
            ->where('corpus_version', $version);

        foreach ($tokens as $token) {
            $query->where(function (Builder $group) use ($token): void {
                self::tokenMatches($group, $token);

                $folded = StreetSuffixMap::foldSuffix($token);

                // Only ever an addition: see WHY TOKENS in the class docblock.
                if ($folded !== $token) {
                    self::tokenMatches($group, $folded);
                }
            });
        }

        return $this->ordered($query, $normalizedQuery)->limit($limit)->get()->all();
    }

    /**
     * One token, matched at the start of a word — never inside one.
     *
     * `normalized` is space-separated, so "start of the line, or after a space"
     * is the whole rule. It is what separates a typeahead from a substring
     * search: "mad" must offer "Madison", and "315" must not offer "1315". A
     * plain `%315%` does both, and the second one puts a neighbour's address in
     * front of somebody who typed their own.
     *
     * Widening only — added to the OR group its caller opened.
     */
    private static function tokenMatches(Builder $group, string $token): void
    {
        $escaped = self::escapeLike($token);

        $group->orWhere('normalized', 'like', $escaped . '%')
            ->orWhere('normalized', 'like', '% ' . $escaped . '%');
    }

    /**
     * Total order: prefix hits, then shortest line, then alphabetical, then id.
     *
     * A line that *starts* with what was typed is what the person is looking at;
     * everything else matched by having the tokens somewhere. Shortest next,
     * because "315 e madison st" is a better answer to "315 e mad" than
     * "1315 e madison st" is. `id` last so the order is total — without it two
     * identical rows are ordered by the planner, and a dropdown reshuffles
     * between keystrokes.
     */
    private function ordered(Builder $query, string $normalizedQuery): Builder
    {
        return $query
            ->orderByRaw('case when normalized like ? then 0 else 1 end', [self::escapeLike($normalizedQuery) . '%'])
            ->orderByRaw('length(normalized) asc')
            ->orderBy('normalized')
            ->orderBy('id');
    }

    /**
     * What to read per row.
     *
     * `geom` is `geography(Point,4326)`; ST_X/ST_Y are PostGIS, not portable
     * SQL, and casting to geometry first is what makes them return the stored
     * lon/lat rather than a great-circle interpretation — the same expression
     * the coordinate rung uses, for the same reason.
     *
     * On a connection that is not PostgreSQL there is no such accessor, so no
     * point is read and every candidate reports `hasCoordinateHint()` false.
     * That is a supported answer rather than a failure: the hint is optional by
     * contract, it drives map framing and nothing else, and a provider that
     * cannot read a point should say so instead of guessing or throwing.
     *
     * @return list<\Illuminate\Database\Query\Expression|string>
     */
    private function selectColumns(string $driver): array
    {
        $columns = [
            'id', 'number', 'street', 'unit', 'city', 'state', 'postcode',
            'normalized', 'precision', 'source_ref',
        ];

        if ($driver === 'pgsql') {
            $columns[] = DB::raw('ST_Y(geom::geometry) as lat');
            $columns[] = DB::raw('ST_X(geom::geometry) as lng');
        }

        return $columns;
    }

    /**
     * Neutralise LIKE wildcards in a typed value.
     *
     * `normalizedQuery()` already strips `%` and `_` along with the rest of the
     * punctuation, so this is belt-and-braces for any caller that reaches these
     * helpers with a raw string — but a wildcard that survives into a predicate
     * turns "_" into "match any address" and is worth one line to prevent.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Record a corpus fault, without recording what anyone was typing.
     *
     * A suggestion query *is* somebody's home address, keystroke by keystroke,
     * and this method is reached exactly when something is repeatedly failing —
     * which is the moment a log fills fastest. So the query, the tokens and the
     * candidates are all absent by construction; what an operator needs to act
     * on is which provider broke and how, and that is what is here. Same rule
     * {@see \App\Services\Location\Coordinates\CoordinateProviderTelemetry}
     * follows for the coordinate side.
     *
     * The message is truncated at ` (SQL: ` for that reason and not for
     * tidiness. `Illuminate\Database\QueryException::getMessage()` appends the
     * failing statement **with its bindings interpolated**, so on the one code
     * path that logs a query fault, the raw message is the typed address. The
     * half before that marker is the driver's own diagnosis — "no such table",
     * "could not find driver" — which is the entire actionable part.
     */
    private function reportFault(Throwable $e): void
    {
        Log::warning('address_suggestion_provider_fault', [
            'provider'   => $this->providerId(),
            'connection' => $this->connection(),
            'exception'  => get_class($e),
            'message'    => self::faultMessage($e),
        ]);
    }

    /** The driver's diagnosis, with any interpolated statement cut away. */
    public static function faultMessage(Throwable $e): string
    {
        $message = $e->getMessage();
        $marker  = strpos($message, ' (SQL: ');

        return $marker === false ? $message : substr($message, 0, $marker);
    }

    // ── config ──────────────────────────────────────────────────────────────

    private function enabled(): bool
    {
        return (bool) config('address_point_corpus.enabled', false);
    }

    private function corpusVersion(): ?string
    {
        $version = config('address_point_corpus.corpus_version');

        if (! is_string($version) || trim($version) === '') {
            return null;
        }

        return trim($version);
    }

    private function connection(): string
    {
        return (string) config('address_point_corpus.connection', 'pgsql_spatial');
    }
}
