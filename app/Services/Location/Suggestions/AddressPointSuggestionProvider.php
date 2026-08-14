<?php

namespace App\Services\Location\Suggestions;

use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProvenance;
use App\Services\Location\Coordinates\PropertyAddress;
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
 * {@see StreetSuffixMap} and {@see PropertyAddress}, the same vocabularies the
 * corpus's own `normalized` column was written with; a suggestion layer with its
 * own idea of what "st" or "florida" means is how a corpus stops being
 * searchable by its own rules.
 *
 * WHAT THE CORPUS LINE DOES NOT CONTAIN, AND THE QUERY MUST THEREFORE DROP
 * ------------------------------------------------------------------------
 * `normalized` is `PropertyAddress::coordinateLookupLine()`, which drops the
 * unit and truncates ZIP+4 to ZIP5 — deliberately, and that is not changed here.
 * But a person typing their own address types the whole thing, so the query must
 * shed exactly what the corpus line shed or every token would have to match a
 * string that never contained it. A typed "Apt 4A" or a typed "-1234" would
 * otherwise turn a correct address into zero results, which is the worst
 * possible answer: it looks like "we have never heard of your house".
 *
 * WHY DIRECTIONALS FOLD IN PLACE AND STATES DO NOT
 * ------------------------------------------------
 * Every directional abbreviation is a *prefix* of its long form — `n`/`north`,
 * `sw`/`southwest` — so folding one in place can only ever widen a word-start
 * match. State codes have no such property: `texas`/`tx`, `virginia`/`va`,
 * `pennsylvania`/`pa`, `maryland`/`md`. Folding those in place would make
 * "Texas Ave", "Virginia St" and "Pennsylvania Ave" unfindable — real street
 * names in most of the country. So a state code joins the suffix abbreviation as
 * an *alternative* the token may also match, never a replacement for it.
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

    /**
     * How long a token must be before it is worth asking the corpus at all.
     *
     * Three, because that is what the index can answer. `addresses_trgm` is a
     * GIN trigram index, and pg_trgm extracts no usable trigrams from a fragment
     * shorter than three characters — a pattern like `% n%` degrades to a scan
     * of the whole corpus. The sibling btree cannot cover the gap either: it was
     * created with the default opclass, so it serves prefix `LIKE` only under a
     * C collation, which is not something this code may assume.
     *
     * A typeahead fires on the first keystroke, so without a floor the very
     * first one or two characters of every session are the most expensive
     * queries the corpus will ever be asked. One token reaching three characters
     * is enough — shorter tokens still narrow the result once a longer one has
     * given the planner something indexable to start from.
     */
    public const MIN_TOKEN_LENGTH = 3;

    /**
     * Unit designators the *query* may drop.
     *
     * A strict subset of the vocabulary {@see PropertyAddress} folds inside
     * `unitAddress`, and the two omissions are the point. `PropertyAddress` can
     * safely treat `fl` and `no` as designators because it is looking at a field
     * already known to hold a unit; a free-text query has no such field, and
     * `fl` there is far more often Florida. Dropping it would delete the state
     * from "Tampa FL 33602". `no` is excluded for the same reason at lower
     * stakes. Everything else transfers unchanged.
     */
    private const UNIT_DESIGNATORS = [
        'apartment', 'apt', 'unit', 'suite', 'ste',
        'rm', 'room', 'bldg', 'building', 'floor', 'number',
    ];

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

        // Nothing typed, or nothing typed *yet* that the corpus index can
        // answer. Both return before any query is issued — the floor exists to
        // stop the request, not to discard its results afterwards.
        if ($tokens === [] || ! self::reachesQueryFloor($tokens)) {
            return [];
        }

        // The only statement here that can fault. Deliberately the *only* thing
        // inside the try: normalization and mapping are pure, and widening this
        // to cover them would turn a mapping bug into a silently empty dropdown
        // that nobody ever notices.
        try {
            $rows = $this->matchingRows($version, $tokens, self::boundedLimit($limit));
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
     *
     * ZIP+4 collapses to ZIP5 first, while the hyphen that identifies it is
     * still there to see. A moment later that hyphen becomes a space and
     * "33602-1234" is indistinguishable from two numbers somebody typed, so
     * this is the only point at which the +4 can be recognised rather than
     * guessed at. The truncation itself is `PropertyAddress::normalizedZip5()` —
     * the same rule the corpus line was written with, not a second copy of it.
     */
    public static function normalizedQuery(string $query): string
    {
        $value = strtolower(trim($query));

        $value = preg_replace_callback(
            '/\b\d{5}-\d{4}\b/',
            static fn (array $m): string => (new PropertyAddress(zip: $m[0]))->normalizedZip5(),
            $value
        ) ?? $value;

        $value = str_replace('.', '', $value);
        $value = preg_replace('/[^a-z0-9# ]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Query tokens: unit dropped, directionals folded, capped.
     *
     * Suffix and state folding are *not* applied here — see the class docblock.
     * They are offered per token as alternatives at match time, where widening
     * is safe.
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

        $tokens = self::withoutUnit($tokens);

        $tokens = array_map(
            static fn (string $t): string => StreetSuffixMap::foldDirectional($t),
            $tokens
        );

        return array_slice($tokens, 0, self::MAX_QUERY_TOKENS);
    }

    /**
     * The tokens with any unit designator and its identifier removed.
     *
     * `normalized` never contained the unit, so a token that only a unit could
     * have produced can only ever fail to match. Removing it is what keeps
     * "1200 N Main St Apt 4A Austin TX" finding the building instead of finding
     * nothing.
     *
     * The identifier is only consumed when it reads like one — it carries a
     * digit, or it is a lone letter ("Apt B"). Without that test, "Main St Apt
     * Austin" would eat the city. A designator with nothing identifier-shaped
     * after it is dropped on its own, because it is still a word the corpus line
     * does not contain.
     *
     * This does not touch `PropertyAddress::coordinateLookupLine()` and does not
     * make the unit part of coordinate identity — the unit still distinguishes
     * two condos everywhere it did before, and a picked candidate still carries
     * it into `propertyIdentityLine()`. This is only about what a *search* may
     * require a unit-free string to contain.
     *
     * @param  list<string> $tokens
     * @return list<string>
     */
    private static function withoutUnit(array $tokens): array
    {
        $kept  = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '#' || in_array($token, self::UNIT_DESIGNATORS, true)) {
                if (isset($tokens[$i + 1]) && self::readsAsUnitIdentifier($tokens[$i + 1])) {
                    $i++;
                }

                continue;
            }

            // "#4a" — designator and identifier arrived as one token.
            if (str_starts_with($token, '#')) {
                continue;
            }

            $kept[] = $token;
        }

        return $kept;
    }

    /** True when a token could be a unit identifier rather than a word. */
    private static function readsAsUnitIdentifier(string $token): bool
    {
        // Carries a digit and is short ("4a", "12", "b7"), or is a lone letter
        // ("Apt B"). A city, a street name or a state never looks like either.
        return preg_match('/^#?(?=.*\d)[a-z0-9]{1,6}$/', $token) === 1
            || preg_match('/^#?[a-z]$/', $token) === 1;
    }

    /**
     * True when at least one token is long enough for the corpus index to
     * answer. See {@see self::MIN_TOKEN_LENGTH}.
     *
     * @param list<string> $tokens
     */
    public static function reachesQueryFloor(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (strlen($token) >= self::MIN_TOKEN_LENGTH) {
                return true;
            }
        }

        return false;
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
    private function matchingRows(string $version, array $tokens, int $limit): array
    {
        $connection = DB::connection($this->connection());

        $query = $connection->table('addresses')
            ->select($this->selectColumns($connection->getDriverName()))
            ->where('corpus_version', $version);

        foreach ($tokens as $token) {
            $query->where(function (Builder $group) use ($token): void {
                foreach (self::tokenAlternatives($token) as $alternative) {
                    self::tokenMatches($group, $alternative);
                }
            });
        }

        return $this->ordered($query, $tokens)->limit($limit)->get()->all();
    }

    /**
     * The forms one typed token is allowed to match.
     *
     * Always the token itself, plus its street-suffix abbreviation and its state
     * code where those differ. Both extras are additions to an OR group and can
     * therefore only widen — which is the entire reason they are safe. Replacing
     * the token would fold "mountain view" into "mtn vw" and "Texas Ave" into
     * "tx ave", and neither string exists anywhere.
     *
     * @return list<string>
     */
    private static function tokenAlternatives(string $token): array
    {
        $alternatives = [$token];

        foreach ([StreetSuffixMap::foldSuffix($token), self::stateCode($token)] as $alternative) {
            if ($alternative !== '' && ! in_array($alternative, $alternatives, true)) {
                $alternatives[] = $alternative;
            }
        }

        return $alternatives;
    }

    /**
     * The two-letter code for a token that names a state, else ''.
     *
     * Answered by `PropertyAddress::normalizedState()` — the one place in this
     * codebase that decides "Florida" and "FL" are the same state. A second copy
     * of that table here would eventually disagree with the one the corpus line
     * was written with, and the disagreement would surface as "this address is
     * findable in some states and not others".
     *
     * Only single-word states resolve. "New York" and "North Carolina" arrive as
     * two tokens and are left alone deliberately: joining adjacent tokens to try
     * them as a pair would fold "New York Ave" in Washington DC into "ny ave",
     * and a street named after a state is exactly the case this must not break.
     */
    private static function stateCode(string $token): string
    {
        $code = (new PropertyAddress(state: $token))->normalizedState();

        return $code === $token ? '' : $code;
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
     *
     * THE PREFIX IS BUILT FROM THE TOKENS, NOT FROM THE RAW QUERY
     * -----------------------------------------------------------
     * It has to be the same representation the WHERE clause matched on, or the
     * ranking asks a question the corpus cannot answer. Somebody typing
     * "1200 North Main" matches `1200 n main st …` through the folded token, and
     * ranking that against a raw "1200 north main%" scores zero prefix hits
     * forever — every correctly-matched row silently demoted for having been
     * typed out in full. The unit is gone from the tokens for the same reason:
     * `normalized` never contained one, so a prefix built from a query that
     * mentions "Apt 4A" could never match either.
     *
     * @param list<string> $tokens
     */
    private function ordered(Builder $query, array $tokens): Builder
    {
        $prefix = self::escapeLike(implode(' ', $tokens)) . '%';

        return $query
            ->orderByRaw('case when normalized like ? then 0 else 1 end', [$prefix])
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
