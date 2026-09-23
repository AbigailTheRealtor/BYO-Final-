<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use Tests\TestCase;

/**
 * Batch 3 — the typed-question vocabulary and the normalisation contract behind it.
 *
 * AN ALIAS IS A PERMISSION, not a convenience. Typing an alias reveals the answer that alias
 * points at, so an alias broader than its question is a route from a word the shopper meant
 * one way to an answer about something else. That is why the forbidden-alias tests below are
 * written as "this word must reach nothing", not "this question must not list that word":
 * the guarantee has to hold across the whole role, not one entry.
 *
 * COLLISIONS ARE NOT AUTOMATICALLY BUGS, and the distinction matters. Two questions may share
 * an alias when they can never be rendered together — the HOA fee question and the HOA
 * coverage composite are a narrower/richer pair, so exactly one of them is ever on the page.
 * The test therefore asks the real question: can two questions that COULD COEXIST answer to
 * the same words? Anything else would either miss real collisions or fail on safe ones.
 */
class TypedQuestionMatchingBatch3Test extends TestCase
{
    private const ROLES = ['seller', 'landlord', 'buyer', 'tenant'];

    /** @return array<string, array> catalog entries for one role */
    private function catalog(string $role): array
    {
        return array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry(),
            static fn ($e): bool => is_array($e) && ($e['role'] ?? null) === $role
        );
    }

    private function normalize(string $text): string
    {
        return AskAiPublicPropertyQuestionService::normalizeQuery($text);
    }

    /* ================================================================== */
    /* Normalisation contract                                              */
    /* ================================================================== */

    /**
     * @dataProvider normalisationCases
     */
    public function test_normalisation_is_exactly_the_agreed_transformation(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalize($input));
    }

    public static function normalisationCases(): array
    {
        return [
            'lowercases and drops the question mark' => ['How much is the HOA?', 'how much is the hoa'],
            'collapses whitespace and trims'         => ['  HOA   FEES ', 'hoa fees'],
            'a hyphen is a space'                    => ['Move-in date?', 'move in date'],
            'curly apostrophe straightens'           => ["What is the buyer\u{2019}s budget?", "what is the buyer's budget"],
            'an apostrophe is kept'                  => ["the buyer's budget", "the buyer's budget"],
            'sentence punctuation goes'              => ['Does it have a pool?!', 'does it have a pool'],
            'commas and colons go'                   => ['taxes, fees: how much;', 'taxes fees how much'],
            'em dash becomes a space'                => ["flood\u{2014}zone", 'flood zone'],
            'underscore becomes a space'             => ['flood_zone', 'flood zone'],
            'already normal is unchanged'            => ['flood zone', 'flood zone'],
            'empty stays empty'                      => ['   ', ''],
        ];
    }

    public function test_normalisation_does_not_stem_guess_or_correct_typos(): void
    {
        // Each of these is a DIFFERENT string after normalisation. A normaliser that folded
        // them together would be guessing, and a guess here is a wrong answer shown as a
        // verified one.
        $this->assertNotSame($this->normalize('hoa fee'), $this->normalize('hoa fees'));
        $this->assertNotSame($this->normalize('pool'), $this->normalize('poool'));
        $this->assertNotSame($this->normalize('taxes'), $this->normalize('tax'));
    }

    /* ================================================================== */
    /* Alias hygiene                                                       */
    /* ================================================================== */

    public function test_every_alias_is_already_normalised(): void
    {
        // A stored alias that does not survive normalisation unchanged can never be matched
        // by a normalised query — it would sit in the catalog looking supported.
        foreach (self::ROLES as $role) {
            foreach ($this->catalog($role) as $id => $entry) {
                foreach ($entry['aliases'] ?? [] as $alias) {
                    $this->assertSame($alias, $this->normalize($alias),
                        "{$id}: alias '{$alias}' is not in normalised form.");
                    $this->assertNotSame('', trim($alias), "{$id}: empty alias.");
                }
            }
        }
    }

    public function test_every_public_question_carries_aliases(): void
    {
        foreach (self::ROLES as $role) {
            foreach ($this->catalog($role) as $id => $entry) {
                $this->assertNotEmpty($entry['aliases'] ?? [], "{$id} has no aliases, so it can never be typed.");
            }
        }
    }

    /* ================================================================== */
    /* Forbidden aliases — private concepts must reach no public question  */
    /* ================================================================== */

    /**
     * @dataProvider forbiddenAliasCases
     */
    public function test_a_private_concept_reaches_no_public_question(string $role, string $term): void
    {
        $normalised = $this->normalize($term);

        foreach ($this->catalog($role) as $id => $entry) {
            $this->assertNotContains($normalised, $entry['aliases'] ?? [],
                "{$id} can be reached by typing '{$term}', which is a private concept for {$role}.");
        }
    }

    public static function forbiddenAliasCases(): array
    {
        $cases = [];

        // Buyer: what QUALIFIES them is private; what they intend to SPEND is the listing.
        foreach (['preapproval', 'pre-approval', 'pre approval', 'preapproval amount', 'cash',
                  'cash on hand', 'down payment', 'credit', 'credit score', 'lender',
                  'occupants', 'household', 'contingency address'] as $t) {
            $cases["buyer: {$t}"] = ['buyer', $t];
        }

        // Tenant: income, deposit capacity, screening and accommodation disclosures.
        foreach (['income', 'salary', 'monthly income', 'deposit', 'security deposit',
                  'credit', 'credit score', 'eviction', 'felony', 'screening',
                  'service animal', 'support animal', 'emotional support animal',
                  'disability', 'accessibility', 'wheelchair', 'address'] as $t) {
            $cases["tenant: {$t}"] = ['tenant', $t];
        }

        // Seller / landlord: a flood ZONE question must not answer to an INSURANCE question,
        // and the two restricted narrative flood fields must not be typeable either.
        foreach (['seller', 'landlord'] as $role) {
            foreach (['flood insurance', 'flood insurance required', 'do i need flood insurance',
                      'is flood insurance required', 'flood zone designation', 'flood zone description'] as $t) {
                $cases["{$role}: {$t}"] = [$role, $t];
            }
        }

        return $cases;
    }

    /* ================================================================== */
    /* Collisions among questions that can coexist                         */
    /* ================================================================== */

    /**
     * @dataProvider roles
     */
    public function test_no_two_coexisting_questions_answer_to_the_same_words(string $role): void
    {
        $catalog = $this->catalog($role);

        // A narrower/richer pair is suppressed to one entry at render time, so a shared alias
        // between the two is safe by construction and is not a collision.
        $paired = static function (string $a, string $b) use ($catalog): bool {
            return ($catalog[$a]['narrower_of'] ?? null) === $b
                || ($catalog[$b]['narrower_of'] ?? null) === $a;
        };

        $collisions = [];
        $ids = array_keys($catalog);

        foreach ($ids as $i => $a) {
            foreach (array_slice($ids, $i + 1) as $b) {
                if ($paired($a, $b)) {
                    continue;
                }
                $shared = array_intersect($catalog[$a]['aliases'] ?? [], $catalog[$b]['aliases'] ?? []);
                foreach ($shared as $alias) {
                    $collisions[] = "'{$alias}' -> {$a} + {$b}";
                }

                // The displayed question is stage one of matching, so two coexisting entries
                // must not display the same text either.
                if ($this->normalize($catalog[$a]['question']) === $this->normalize($catalog[$b]['question'])) {
                    $collisions[] = "display text -> {$a} + {$b}";
                }
            }
        }

        $this->assertSame([], $collisions,
            "{$role} has aliases shared by questions that can appear together: " . implode('; ', $collisions));
    }

    public static function roles(): array
    {
        return array_map(static fn ($r) => [$r], self::ROLES);
    }

    /**
     * The intentional collisions, recorded so they are not mistaken for oversights.
     */
    public function test_the_only_shared_aliases_belong_to_narrower_richer_pairs(): void
    {
        $pairsWithSharedAliases = [];

        foreach (self::ROLES as $role) {
            $catalog = $this->catalog($role);
            foreach ($catalog as $id => $entry) {
                $richer = $entry['narrower_of'] ?? null;
                if ($richer === null || !isset($catalog[$richer])) {
                    continue;
                }
                if (array_intersect($entry['aliases'] ?? [], $catalog[$richer]['aliases'] ?? []) !== []) {
                    $pairsWithSharedAliases[] = "{$id} / {$richer}";
                }
            }
        }

        // Documented, not merely allowed: these are the pairs where one entry stands in for
        // the other, so only one is ever rendered and the shared words reach exactly one
        // question on any given listing.
        $this->assertSame([
            'seller_hoa_fee / seller_hoa_fee_coverage',
            // Batch 5: the parking composite answers "garage" more completely than the
            // yes/no it supersedes, and the acreage question answers "how big is the lot"
            // more completely than the square-foot fallback.
            'seller_garage / seller_parking',
            'seller_lot_size / seller_total_acreage',
            'landlord_hoa_fee / landlord_hoa_fee_coverage',
            'landlord_renewal_option / landlord_lease_terms',
            'buyer_search_areas_counties / buyer_search_areas',
            'tenant_search_areas_counties / tenant_search_areas',
            'tenant_appliances / tenant_property_features',
        ], $pairsWithSharedAliases);
    }

    /* ================================================================== */
    /* Aliases ship only for questions that are actually available         */
    /* ================================================================== */

    public function test_only_available_questions_contribute_aliases(): void
    {
        $service = new AskAiPublicPropertyQuestionService();

        // A seller listing that can answer three questions and no others.
        $questions = $service->forListing('seller', ['listing' => ['property_type' => 'Residential', 
            'flood_zone_code'       => 'AE',
            'annual_property_taxes' => '4200',
            'tax_year'              => '2025',
            'pool'                  => 'Yes',
        ]], []);

        $ids = array_column($questions, 'id');
        sort($ids);
        $this->assertSame(['seller_flood_zone', 'seller_pool', 'seller_property_taxes'], $ids);

        $shipped = [];
        foreach ($questions as $q) {
            $this->assertArrayHasKey('aliases', $q, "{$q['id']} shipped no aliases key.");
            $shipped = array_merge($shipped, $q['aliases']);
        }

        // The vocabulary of every question this listing CANNOT answer is absent.
        foreach (['hoa', 'hoa fee', 'association fee', 'bedrooms', 'beds', 'bathrooms',
                  'square footage', 'zoning', 'roof', 'appliances', 'cdd', 'acreage',
                  'lot size', 'garage', 'financing'] as $absent) {
            $this->assertNotContains($absent, $shipped,
                "'{$absent}' was shipped although its question is not available for this listing.");
        }
    }

    public function test_a_listing_with_no_answerable_questions_ships_no_vocabulary(): void
    {
        $service = new AskAiPublicPropertyQuestionService();

        $this->assertSame([], $service->forListing('seller', ['listing' => ['property_type' => 'Residential']], []));
    }
}
