<?php

namespace App\Support\AskAi;

use App\Support\Listing\PropertyTypeVocabulary;

/**
 * AskAiPropertyTypeResolver — the one place Ask AI decides what KIND of property a
 * listing is, so that a question written for one property type can never be offered
 * about another.
 *
 * WHY THIS EXISTS
 * ---------------
 * The public question catalog was role-scoped only. A seller listing offered the same
 * twenty questions whether it was a house, a strip mall, a laundromat or forty acres of
 * scrub — so a Vacant Land listing carrying a stray `bedrooms` meta row advertised
 * "How many bedrooms are there?", and a Commercial Sale listing had no way to be asked
 * about its ceiling height, its zoning or its parking. Role says WHO is listing;
 * it has never said WHAT.
 *
 * IT DOES NOT INVENT A SECOND VOCABULARY
 * --------------------------------------
 * {@see PropertyTypeVocabulary} already owns the translation from any stored or feed
 * property type into BidYourOffer's own categories, and already knows which categories
 * each role offers. This class is a thin, fail-closed adapter over it that converts a
 * role category into a stable token the registry can declare against. Adding a property
 * type to the platform is a change to that class, never to this one.
 *
 * WHY NOT PropertyTypeVocabulary::forRole()
 * -----------------------------------------
 * `forRole()` carries a SUBSTRING FALLBACK for values it does not recognise, and that
 * fallback tests `residential` before `income` — so "Residential Lease" collapses to
 * "Residential" and "Residential Income" collapses to "Residential". That behaviour is
 * correct where it lives (a form needs *some* vocabulary and the caller has already
 * accepted the value), and it is catastrophic here: it would publish residential
 * questions about a lease and about a multi-family sale, which is precisely the leak
 * this class exists to prevent. We use {@see PropertyTypeVocabulary::roleCategoryFor()},
 * which consults the exact-match table alone and returns null for anything else.
 *
 * FAIL CLOSED, IN THREE DIRECTIONS
 * --------------------------------
 *   • An ABSENT property type resolves to null.
 *   • An UNRECOGNISED property type resolves to null — never a guess, never a default.
 *   • A property type a ROLE HAS NO CATEGORY FOR resolves to null. A Business
 *     Opportunity is a thing we understand perfectly well and a thing the Landlord form
 *     has nowhere to put; `roleCategoryFor()` says so by returning null, and we pass
 *     that through rather than substituting the role's most common category.
 *
 * Null means "type-specific questions are withheld". Universal questions still show,
 * because a listing whose type we cannot name still has an address, a price and a
 * description, and withholding those would break every listing the moment a new feed
 * spelling appeared. That asymmetry is the whole design: unknown costs you the
 * type-specific questions and nothing else.
 *
 * Pure and deterministic. No container, no database, no clock, no network — it reads a
 * decoded meta array and returns a string or null, so the registry harness can exercise
 * every branch without booting an application.
 *
 * @see \App\Services\AskAi\AskAiFieldQuestionRegistryService
 * @see \App\Services\AskAi\AskAiPublicPropertyQuestionService::forListing()
 */
final class AskAiPropertyTypeResolver
{
    /**
     * The canonical Ask AI property-type tokens.
     *
     * Deliberately snake_case tokens rather than the display strings: a registry entry
     * declares `['residential', 'income']`, and a display string carries capitalisation
     * and a trailing " Property" that differ by role, which is exactly the sort of
     * accidental mismatch a declaration should not be able to have.
     */
    public const RESIDENTIAL = 'residential';
    public const INCOME      = 'income';
    public const COMMERCIAL  = 'commercial';
    public const BUSINESS    = 'business';
    public const VACANT_LAND = 'vacant_land';

    /** Every token, in a stable order. The registry validates declarations against this. */
    public const ALL_TYPES = [
        self::RESIDENTIAL,
        self::INCOME,
        self::COMMERCIAL,
        self::BUSINESS,
        self::VACANT_LAND,
    ];

    /**
     * The meta key every role stores its property type under.
     *
     * Verified against all four controllers: seller, landlord, buyer and tenant each
     * filter on `meta_key = 'property_type'`. One key, so one reader.
     */
    public const META_KEY = 'property_type';

    /**
     * BidYourOffer role category → Ask AI token.
     *
     * Keyed on the exact strings {@see PropertyTypeVocabulary::categoriesForRole()}
     * returns, for both roles, so a category that class starts emitting and this map has
     * never heard of resolves to null rather than to a neighbouring token. A test asserts
     * both directions.
     */
    private const CATEGORY_TO_TOKEN = [
        // Seller / Buyer / Tenant vocabulary
        'Residential'          => self::RESIDENTIAL,
        'Income'               => self::INCOME,
        'Commercial'           => self::COMMERCIAL,
        'Business'             => self::BUSINESS,
        'Vacant Land'          => self::VACANT_LAND,
        // Landlord vocabulary
        'Residential Property' => self::RESIDENTIAL,
        'Commercial Property'  => self::COMMERCIAL,
    ];

    /**
     * The Ask AI property-type tokens for a listing — a SET, not one token.
     *
     * A seller or landlord listing is exactly one property. A buyer or tenant listing is
     * a SEARCH, and a search may name several types at once: the tenant controller
     * already matches its stored `property_type` with `LIKE '%…%'`, which only makes
     * sense against a multi-valued column. Returning one token would have forced a
     * multi-select criteria listing to resolve as unrecognised, withdrawing every
     * type-specific question from a buyer who had simply ticked two boxes.
     *
     * Unrecognised members are dropped rather than poisoning the set: a stored
     * "Residential, Timeshare" yields `['residential']`, because we can name the first
     * and genuinely cannot name the second. An empty result means nothing was nameable
     * and only universal questions survive.
     *
     * @param  string               $role 'seller' | 'landlord' | 'buyer' | 'tenant'
     * @param  array<string,mixed>  $meta the listing's decoded meta array
     * @return list<string>
     */
    public static function forListing(string $role, array $meta, array $context = []): array
    {
        // CONTEXT FIRST, meta second, and the order matters.
        //
        // `listing.property_type` is a CONTEXT_BASE_KEY: the context builder resolves it
        // for every role from the listing's own info row and native column, which is the
        // same canonical, role-normalised view every other Ask AI answer is drawn from.
        // The raw meta row is the fallback for a caller that holds meta but no context.
        // Preferring meta would mean the property type Ask AI reasons about could differ
        // from the one it reports, which is the class of bug this whole file exists to
        // prevent.
        $fromContext = self::tokensFor($role, $context['listing'][self::META_KEY] ?? null);

        return $fromContext !== [] ? $fromContext : self::tokensFor($role, $meta[self::META_KEY] ?? null);
    }

    /**
     * Every token a stored property-type value names, in ALL_TYPES order.
     *
     * Accepts a scalar, a JSON array, or a comma/semicolon/pipe separated list, because
     * a multi-select stored through four different form paths over several years is all
     * three in practice. Order is normalised so two listings naming the same set compare
     * equal, and duplicates collapse.
     *
     * @return list<string>
     */
    public static function tokensFor(string $role, mixed $value): array
    {
        foreach (self::candidateValues($value) as $candidate) {
            $token = self::fromValue($role, $candidate);

            if ($token !== null) {
                $found[$token] = true;
            }
        }

        if (!isset($found)) {
            return [];
        }

        // ALL_TYPES order, so the set is canonical however it was stored.
        return array_values(array_filter(self::ALL_TYPES, static fn ($t): bool => isset($found[$t])));
    }

    /**
     * Split a stored value into the individual property types it names.
     *
     * @return list<string>
     */
    private static function candidateValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        // A JSON array is what the newer multi-select paths store.
        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_string'));
            }
        }

        // "Residential, Commercial" / "Residential|Commercial" / "Residential; Commercial".
        // Splitting on a comma is safe because no recognised category contains one.
        $parts = preg_split('/\s*[,;|]\s*/', $trimmed) ?: [$trimmed];

        return array_values(array_filter(array_map('trim', $parts), static fn ($p): bool => $p !== ''));
    }

    /**
     * The token for one stored property-type VALUE, or null.
     *
     * Separate from {@see forListing()} so a caller that already holds the value — an
     * importer, a test, a coverage harness — does not have to synthesise a meta array
     * around it just to ask the question.
     */
    public static function fromValue(string $role, mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Exact-match table only. `roleCategoryFor()` returns null for an unrecognised
        // value AND for a type this role has no category for — both are withholds here.
        $category = PropertyTypeVocabulary::roleCategoryFor($value, self::normaliseRole($role));

        if (!is_string($category)) {
            return null;
        }

        return self::CATEGORY_TO_TOKEN[$category] ?? null;
    }

    /** Is this a token the registry may declare? */
    public static function isToken(mixed $token): bool
    {
        return is_string($token) && in_array($token, self::ALL_TYPES, true);
    }

    /**
     * Does a registry entry's declared applicability admit this listing's token?
     *
     * The one place the applicability rule lives, so the service, the harness and any
     * future surface cannot each develop their own reading of it.
     *
     *   • A declaration naming every token is universal and admits ANY listing,
     *     including one whose type could not be resolved. That is what makes
     *     "What is the asking price?" survive an unrecognised property type.
     *   • A NARROWER declaration admits only a listing whose token it names, so an
     *     unresolved listing (null) is refused. Fail closed.
     *
     * An entry with no declaration at all is refused outright rather than treated as
     * universal: silence is how a new question ends up offered about every property type
     * by accident, and the harness requires the declaration precisely so that cannot
     * happen.
     *
     * @param list<string>|mixed $declared      the entry's `property_types`
     * @param list<string>       $listingTokens {@see forListing()} — may be empty
     */
    public static function admits(mixed $declared, array $listingTokens): bool
    {
        if (!is_array($declared) || $declared === []) {
            return false;
        }

        $declared = array_values(array_unique(array_filter($declared, [self::class, 'isToken'])));

        if ($declared === []) {
            return false;
        }

        // Universal: names every token there is.
        if (count($declared) === count(self::ALL_TYPES)) {
            return true;
        }

        // A narrower entry needs the listing to actually name one of its types. An
        // unresolved listing names none, so it is refused — fail closed.
        return array_intersect($declared, $listingTokens) !== [];
    }

    /**
     * Landlord is the only role with its own category vocabulary; buyer and tenant read
     * the seller table, which is the vocabulary their criteria forms actually store.
     */
    private static function normaliseRole(string $role): string
    {
        return strtolower(trim($role)) === 'landlord' ? 'landlord' : 'seller';
    }
}
