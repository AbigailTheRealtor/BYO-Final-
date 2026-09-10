<?php

namespace App\Support\Listing;

/**
 * The one place a source property type becomes BidYourOffer's own vocabulary,
 * and the one place we say what a source property type MEANS.
 *
 * WHY THIS EXISTS
 * ---------------
 * BYO does not use RESO's property-type words, and the two roles do not even
 * use the same words as each other:
 *
 *   Landlord forms say  "Residential Property" / "Commercial Property"
 *   Seller/Buyer/Tenant say "Residential" / "Commercial" / "Business" /
 *                           "Income" / "Vacant Land"
 *
 * A feed says "Residential Lease", "Commercial Sale", "Business Opportunity".
 * Every one of those has to be translated before a canonical Blade compares it,
 * because the canonical Landlord Leasing Terms partial gates fourteen
 * conditional sections on an EXACT match against its two words.
 *
 * THE BUG THIS ORIGINALLY CLOSED
 * ------------------------------
 * The URL/text importer translated; MLS Quick Import did not. So a landlord who
 * imported a Residential Lease record carried the literal string "Residential
 * Lease" into a Blade asking `$property_type === 'Residential Property'`, which
 * is false — and utilities, maintenance responsibility and response time,
 * renewal details, rent escalation, storage, owner-pays, terms of lease, the
 * commercial lease terms and CAM/NNN simply did not render.
 *
 * The fix is normalisation at the boundary, NOT teaching the Blade to accept
 * feed vocabulary. One internal vocabulary, translated once on the way in, is
 * the only version of this that stays correct — aliases scattered through views
 * multiply with every feed quirk and every new tab.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THREE THINGS THIS CLASS ANSWERS, AND WHY THEY ARE SEPARATE
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * 1. VOCABULARY — {@see forRole()}: "what kind of property is this, in this
 *    role's words?" This is a translation and nothing else.
 *
 * 2. TRANSACTION — {@see transactionFor()}: "is the source record a SALE or a
 *    LEASE?" This is a property of the MLS record, not of the building.
 *
 * 3. RECOGNITION — {@see classifySource()}: "is this a source value we have
 *    actually established a meaning for?" An unrecognised value is not a
 *    Residential listing and is not a Commercial one; it is a listing we cannot
 *    classify, and callers that can fail closed must be able to find that out.
 *
 * Keeping (1) apart from (2) is deliberate. "Commercial Sale" translates to
 * "Commercial Property" perfectly well for a landlord — the building really is
 * commercial — and it is still the wrong record for a landlord to import,
 * because its ListPrice is a purchase price. A single method answering both
 * would have to pick one of those to be wrong about. So the vocabulary stays
 * permissive and idempotent, and eligibility is decided by asking BOTH.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHERE THE SOURCE VALUES COME FROM — evidence, not memory
 * ─────────────────────────────────────────────────────────────────────────────
 * `SOURCE_TYPES` below tags every entry with its provenance, in the same spirit
 * as {@see MlsSourceStatus}, so nobody later reads this table as proof that the
 * live dataset emits all of it. It does not.
 *
 *   PROVENANCE_CACHED   — the value appears on a REAL scrubbed Stellar record in
 *                         tests/fixtures/mls/bridge/. Four of the seven fixtures
 *                         are real (see that directory's README): Residential,
 *                         Residential Lease, Commercial Sale, Business
 *                         Opportunity.
 *   PROVENANCE_COMPOSED — the value is carried by a COMPOSED fixture. The local
 *                         bridge_properties cache holds only locally-seeded
 *                         stubs for Income, Commercial Lease and Vacant Land, so
 *                         no real record of those types could be extracted. The
 *                         field names and value shapes are the feed's; only the
 *                         combination is constructed. Treat these as "the
 *                         platform declares support", not "the feed was observed
 *                         to send this".
 *   PROVENANCE_RESO     — a RESO-standard PropertyType enumeration we support
 *                         deliberately even though this dataset has not been
 *                         observed sending it. Only enum members are listed. No
 *                         speculative synonyms.
 *   PROVENANCE_BYO      — BidYourOffer's OWN vocabulary, present so that
 *                         forRole() is idempotent: a value that has already been
 *                         normalised must survive a second pass unchanged, and a
 *                         resumed draft is normalised again. These carry NO
 *                         transaction, because they are not things a feed says.
 *
 * There is deliberately no live probe for property types. `mls:probe-lifecycle`
 * exists for statuses and cost one narrow request per candidate; the equivalent
 * here would be new outbound traffic for a classification the fixtures already
 * pin, and this branch does not spend it.
 *
 * NOT IN THIS TABLE, ON PURPOSE: `Farm` and `Manufactured In Park`. Both are
 * real RESO PropertyType members and NEITHER has an existing BidYourOffer
 * category to land in. Inventing one, or folding Farm into Vacant Land and a
 * park-model home into Residential, would be this class asserting a
 * classification the platform has never made. They are left unrecognised, which
 * is what makes the quick-import guard refuse them with an explanation instead
 * of publishing a listing in the wrong track.
 */
final class PropertyTypeVocabulary
{
    public const TRANSACTION_SALE  = 'sale';
    public const TRANSACTION_LEASE = 'lease';

    public const PROVENANCE_CACHED   = 'cached_record';
    public const PROVENANCE_COMPOSED = 'composed_fixture';
    public const PROVENANCE_RESO     = 'reso_standard';
    public const PROVENANCE_BYO      = 'byo_vocabulary';

    /**
     * Every source property type this application has established a meaning for.
     *
     * Keyed by {@see lookupKey()} — lower-cased, with a trailing " Property"
     * removed and every non-alphanumeric character stripped — so
     * "Residential Income", "residential income" and RESO's own unspaced
     * "ResidentialIncome" are one entry rather than three.
     *
     * `landlord => null` means "this property type has no Landlord category".
     * It is not the same as "unrecognised": we know exactly what a Business
     * Opportunity is, and BidYourOffer's Landlord form has nowhere to put one.
     *
     * @var array<string, array{seller:string, landlord:?string, transaction:?string, provenance:string}>
     */
    private const SOURCE_TYPES = [
        // ── Proven on real cached Stellar records ────────────────────────────
        'residential' => [
            'seller' => 'Residential', 'landlord' => 'Residential Property',
            'transaction' => self::TRANSACTION_SALE, 'provenance' => self::PROVENANCE_CACHED,
        ],
        'residentiallease' => [
            'seller' => 'Residential', 'landlord' => 'Residential Property',
            'transaction' => self::TRANSACTION_LEASE, 'provenance' => self::PROVENANCE_CACHED,
        ],
        'commercialsale' => [
            'seller' => 'Commercial', 'landlord' => 'Commercial Property',
            'transaction' => self::TRANSACTION_SALE, 'provenance' => self::PROVENANCE_CACHED,
        ],
        'businessopportunity' => [
            'seller' => 'Business', 'landlord' => null,
            'transaction' => self::TRANSACTION_SALE, 'provenance' => self::PROVENANCE_CACHED,
        ],

        // ── Carried by composed fixtures; platform declares support ──────────
        'income' => [
            'seller' => 'Income', 'landlord' => null,
            'transaction' => self::TRANSACTION_SALE, 'provenance' => self::PROVENANCE_COMPOSED,
        ],
        'commerciallease' => [
            'seller' => 'Commercial', 'landlord' => 'Commercial Property',
            'transaction' => self::TRANSACTION_LEASE, 'provenance' => self::PROVENANCE_COMPOSED,
        ],
        'vacantland' => [
            'seller' => 'Vacant Land', 'landlord' => null,
            'transaction' => self::TRANSACTION_SALE, 'provenance' => self::PROVENANCE_COMPOSED,
        ],

        // ── RESO enumeration members supported deliberately ──────────────────
        //
        // `ResidentialIncome` is the RESO-standard spelling of the category this
        // dataset's composed fixture calls `Income`. BOTH are supported and BOTH
        // resolve to Seller `Income`, because we cannot prove which spelling a
        // real Stellar multi-family record carries and the cost of being wrong
        // is a whole property type landing in the wrong track. Supporting two
        // spellings of one RESO member is not a synonym list.
        'residentialincome' => [
            'seller' => 'Income', 'landlord' => null,
            'transaction' => self::TRANSACTION_SALE, 'provenance' => self::PROVENANCE_RESO,
        ],
        'land' => [
            'seller' => 'Vacant Land', 'landlord' => null,
            'transaction' => self::TRANSACTION_SALE, 'provenance' => self::PROVENANCE_RESO,
        ],

        // ── BidYourOffer's own words, for idempotency only ───────────────────
        //
        // No transaction: a BYO category says what a property IS, never whether
        // the MLS record offering it was a sale or a lease. A caller asking
        // transactionFor('Residential') gets null, which is the honest answer.
        'commercial' => [
            'seller' => 'Commercial', 'landlord' => 'Commercial Property',
            'transaction' => null, 'provenance' => self::PROVENANCE_BYO,
        ],
        'business' => [
            'seller' => 'Business', 'landlord' => null,
            'transaction' => null, 'provenance' => self::PROVENANCE_BYO,
        ],
    ];

    /**
     * Translate a source property type into the vocabulary $role's forms use.
     *
     * The recognised table is consulted FIRST. That ordering is the whole fix
     * for Residential Income: the substring fallback below tests `residential`
     * before `income`, so "Residential Income" used to collapse into
     * "Residential" and a multi-family sale was published in the Residential
     * track — with Residential-only MLS feature applicability and the
     * Residential Your Terms branches — while BidYourOffer has a separate,
     * populated `Income` category sitting unused. Seller Residential and Seller
     * Income are not interchangeable, so the exact-value table decides first and
     * the fallback never sees the string.
     *
     * The substring fallback is retained UNCHANGED IN SPIRIT because this method
     * also serves the URL/raw-text importer (HasMlsImport), whose input is
     * scraped prose — "Single Family Residence", "Condominium", "Townhouse" —
     * that no exact table can enumerate. It is reached only when the table has
     * no entry, so it can no longer misclassify a value we actually know.
     *
     * Two changes inside the fallback:
     *   · `income` is tested before `residential`, matching the table's answer
     *     rather than contradicting it on free text.
     *   · the land test no longer fires on a bare `land` SUBSTRING. It required
     *     only `str_contains($lower, 'land')`, so any value containing those
     *     four letters became Vacant Land. It now needs the whole value to be
     *     "land", or the words "vacant" or "unimproved" to be present.
     *
     * An unrecognised value still passes through untouched. That is deliberate
     * and load-bearing: a value already in BYO vocabulary must survive a second
     * pass unchanged, and rewriting a genuinely unknown type into a category it
     * may not belong to is the failure this class exists to prevent. Callers
     * that must REFUSE an unknown type ask {@see classifySource()} instead —
     * pass-through here is not an assertion that the value is valid.
     */
    public static function forRole(string $value, string $role): string
    {
        $v     = trim($value);
        $lower = strtolower($v);

        $known = self::SOURCE_TYPES[self::lookupKey($v)] ?? null;

        if ($known !== null) {
            $mapped = ($role === 'landlord') ? $known['landlord'] : $known['seller'];

            // A recognised type with no category for this role falls through to
            // the fallback, which preserves exactly what this method used to
            // return for it (Business Opportunity stays "Business Opportunity"
            // on the landlord side, Residential Income becomes "Residential
            // Property"). The quick-import guard is what refuses those records;
            // this method is not the place to start throwing.
            if ($mapped !== null) {
                return $mapped;
            }
        }

        if ($role === 'landlord') {
            // Landlord blades use "Residential Property" / "Commercial Property".
            if (str_contains($lower, 'commercial'))  return 'Commercial Property';
            if (str_contains($lower, 'residential')) return 'Residential Property';

            return $v;
        }

        // Seller, buyer, tenant: short-form values (no " Property" suffix).
        //
        // Income precedes residential — see the docblock. Every feed spelling we
        // know of is already handled by the table above; this ordering is what
        // keeps free text from disagreeing with it.
        if (str_contains($lower, 'income') || str_contains($lower, 'multifamily')
            || str_contains($lower, 'multi-family') || str_contains($lower, 'multi family')) {
            return 'Income';
        }

        if (str_contains($lower, 'residential')   || str_contains($lower, 'single family')
            || str_contains($lower, 'condominium') || str_contains($lower, 'condo')
            || str_contains($lower, 'townhome')    || str_contains($lower, 'townhouse')
            || str_contains($lower, 'mobile home')) {
            return 'Residential';
        }

        // "Business Opportunity" → 'Business'. Must precede the 'commercial'
        // check: some exports say "Business, Commercial".
        if (str_contains($lower, 'business')) {
            return 'Business';
        }

        if (str_contains($lower, 'commercial')) {
            return 'Commercial';
        }

        if ($lower === 'land' || str_contains($lower, 'vacant')
            || str_contains($lower, 'unimproved')) {
            return 'Vacant Land';
        }

        return $v; // already-normalised or unrecognised — pass through
    }

    /**
     * The table entry for a source value, or null when we have not established a
     * meaning for it.
     *
     * Null is the answer for a missing value, a blank one, `Farm`,
     * `Manufactured In Park` and anything the feed invents tomorrow. Callers
     * that publish a listing must treat null as "stop", never as "assume
     * Residential".
     *
     * @return array{seller:string, landlord:?string, transaction:?string, provenance:string}|null
     */
    public static function classifySource(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::SOURCE_TYPES[self::lookupKey($value)] ?? null;
    }

    /** Is this a source property type we have established a meaning for? */
    public static function isRecognisedSource(?string $value): bool
    {
        return self::classifySource($value) !== null;
    }

    /**
     * Was the source record a SALE or a LEASE — or do we not know?
     *
     * Null for an unrecognised value AND for a bare BidYourOffer category, and
     * those must both read as "do not know". A caller deciding whether a
     * landlord may import a record cannot be handed a guess here: guessing
     * "lease" is how a purchase price becomes a monthly rent.
     */
    public static function transactionFor(?string $value): ?string
    {
        return self::classifySource($value)['transaction'] ?? null;
    }

    /** Does this role have a category for this recognised source type? */
    public static function roleCategoryFor(?string $value, string $role): ?string
    {
        $known = self::classifySource($value);

        if ($known === null) {
            return null;
        }

        return ($role === 'landlord') ? $known['landlord'] : $known['seller'];
    }

    /** The BidYourOffer categories a role actually offers. */
    public static function categoriesForRole(string $role): array
    {
        return $role === 'landlord'
            ? ['Residential Property', 'Commercial Property']
            : ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land'];
    }

    /**
     * The table key for a value.
     *
     * Lower-cases, drops a trailing " Property" so BidYourOffer's own landlord
     * wording folds onto the same entry as the feed's, then strips every
     * non-alphanumeric character so "Residential Income", "residential  income"
     * and RESO's unspaced "ResidentialIncome" are one key. Stripping punctuation
     * rather than only spaces also absorbs "Residential-Income".
     */
    private static function lookupKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/\s+property$/', '', $key) ?? $key;

        return preg_replace('/[^a-z0-9]/', '', $key) ?? $key;
    }
}
