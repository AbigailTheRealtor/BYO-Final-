<?php

namespace Tests\Unit\Http\Livewire\OfferListing;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use ReflectionMethod;
use Tests\TestCase;

/**
 * CHARACTERISATION of `hydrateDiscreteLocationFromBlob()` across every component that has one.
 *
 * WHY THIS EXISTS, AND WHY IT WAS WRITTEN BEFORE THE CHANGE
 * --------------------------------------------------------
 * The four Offer Listing components each carried a private copy of this method, byte-identical to
 * one another and to {@see \App\Http\Livewire\Concerns\HasSearchAreas}'s version except that the
 * trait guards both writes with `property_exists`. The consolidation deletes the four copies and
 * mixes in the trait instead.
 *
 * That is a refactor, so the only thing worth asserting is that NOTHING MOVED. A test written after
 * the fact would describe the trait; this one was written and run against the four private copies
 * first, so it describes the behaviour that shipped, and the consolidation has to keep satisfying
 * it unchanged. Every case below is a rule the old copies actually obeyed.
 *
 * THE HIRE COMPONENT IS IN THE TABLE ON PURPOSE
 * ---------------------------------------------
 * {@see HireBuyerCreate} already used the trait before this change and is not being modified. It
 * runs through the identical assertions, which is what turns "the Offer components still behave the
 * same" into the stronger "all eight components now behave the same, and the four that already did
 * were not disturbed."
 *
 * NO DATABASE AND NO LIVEWIRE LIFECYCLE. The method reads one string property and writes two others;
 * mounting a component would add fixtures that have nothing to do with what is being pinned. The
 * components declare no constructor, so a plain `new` reaches the method directly.
 *
 * It extends the Laravel {@see TestCase} rather than PHPUnit's, and that is not incidental:
 * Livewire's `Component` base resolves the container on property access, so a bare `new` outside a
 * booted application dies on `Class "config" does not exist`. Booting the app is the whole reason —
 * no migration runs, no connection is opened, and no `RefreshDatabase`/`DatabaseTransactions` trait
 * is used, so this stays a pure in-memory exercise of one method.
 */
class SearchAreasHydrationCharacterisationTest extends TestCase
{
    /**
     * Every component that resolves the blob into discrete state/counties.
     *
     * @return array<string, array{0: class-string}>
     */
    public static function components(): array
    {
        return [
            'Offer Buyer create'  => [BuyerOfferListing::class],
            'Offer Buyer edit'    => [BuyerOfferListingEdit::class],
            'Offer Tenant create' => [TenantOfferListing::class],
            'Offer Tenant edit'   => [TenantOfferListingEdit::class],
            'Hire Buyer create (already on the trait)' => [HireBuyerCreate::class],
        ];
    }

    /**
     * Run the component's hydration against a blob and hand back the resulting discrete values.
     *
     * @return array{state: mixed, counties: mixed}
     */
    private function hydrate(string $class, ?string $blobJson, mixed $seedState = '', mixed $seedCounties = []): array
    {
        $component = new $class();

        $component->location_dna_preferences_json = $blobJson;
        $component->state    = $seedState;
        $component->counties = $seedCounties;

        $method = new ReflectionMethod($class, 'hydrateDiscreteLocationFromBlob');
        $method->setAccessible(true);
        $method->invoke($component);

        return ['state' => $component->state, 'counties' => $component->counties];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE HAPPY PATH
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider components
     */
    public function test_a_populated_blob_sets_state_and_counties(string $class): void
    {
        $result = $this->hydrate($class, json_encode([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL', 'Hillsborough County, FL'],
        ]));

        $this->assertSame('Florida', $result['state']);
        $this->assertSame(['Pinellas County, FL', 'Hillsborough County, FL'], $result['counties']);
    }

    /** Surrounding whitespace is trimmed off the state, but not off a county. */
    public function test_the_state_is_trimmed(): void
    {
        $result = $this->hydrate(BuyerOfferListing::class, json_encode(['state' => '  Florida  ']));

        $this->assertSame('Florida', $result['state']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE NON-EMPTY GUARDS — an absent blob value never wipes a discrete one
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * This is the load-bearing rule. A user who opens a record whose blob has no state must not
     * have the state they already had silently erased.
     *
     * @dataProvider components
     */
    public function test_an_empty_blob_state_does_not_wipe_the_discrete_state(string $class): void
    {
        $result = $this->hydrate($class, json_encode(['state' => '', 'counties' => []]), 'Georgia', ['Cobb County, GA']);

        $this->assertSame('Georgia', $result['state']);
        $this->assertSame(['Cobb County, GA'], $result['counties']);
    }

    /**
     * @dataProvider components
     */
    public function test_a_whitespace_only_blob_state_does_not_wipe_the_discrete_state(string $class): void
    {
        $result = $this->hydrate($class, json_encode(['state' => '   ']), 'Georgia');

        $this->assertSame('Georgia', $result['state']);
    }

    /**
     * @dataProvider components
     */
    public function test_a_blob_missing_the_keys_entirely_changes_nothing(string $class): void
    {
        $result = $this->hydrate($class, json_encode(['cities' => ['Tampa, FL']]), 'Georgia', ['Cobb County, GA']);

        $this->assertSame('Georgia', $result['state']);
        $this->assertSame(['Cobb County, GA'], $result['counties']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · COUNTY FILTERING
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Non-strings and blanks are dropped, and the result is re-indexed to a list.
     *
     * @dataProvider components
     */
    public function test_blank_and_non_string_counties_are_filtered_out(string $class): void
    {
        $result = $this->hydrate($class, json_encode([
            'counties' => ['Pinellas County, FL', '', '   ', 123, null, 'Cobb County, GA'],
        ]));

        $this->assertSame(['Pinellas County, FL', 'Cobb County, GA'], $result['counties']);
    }

    /**
     * A counties list of nothing but blanks is `empty()` after filtering — but the guard runs on
     * the RAW value, which is non-empty, so the discrete value is overwritten with an empty list.
     *
     * This is arguably surprising, and it is exactly why the behaviour is pinned rather than
     * described: the consolidation must not quietly "fix" it.
     *
     * @dataProvider components
     */
    public function test_a_counties_list_of_only_blanks_clears_the_discrete_counties(string $class): void
    {
        $result = $this->hydrate($class, json_encode(['counties' => ['', '  ']]), 'Georgia', ['Cobb County, GA']);

        $this->assertSame([], $result['counties']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · MALFORMED INPUT IS A NO-OP, NEVER A THROW
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider components
     */
    public function test_invalid_json_changes_nothing(string $class): void
    {
        $result = $this->hydrate($class, '{not json at all', 'Georgia', ['Cobb County, GA']);

        $this->assertSame('Georgia', $result['state']);
        $this->assertSame(['Cobb County, GA'], $result['counties']);
    }

    /**
     * @dataProvider components
     */
    public function test_an_empty_string_changes_nothing(string $class): void
    {
        $result = $this->hydrate($class, '', 'Georgia', ['Cobb County, GA']);

        $this->assertSame('Georgia', $result['state']);
        $this->assertSame(['Cobb County, GA'], $result['counties']);
    }

    /**
     * @dataProvider components
     */
    public function test_a_null_blob_changes_nothing(string $class): void
    {
        $result = $this->hydrate($class, null, 'Georgia', ['Cobb County, GA']);

        $this->assertSame('Georgia', $result['state']);
        $this->assertSame(['Cobb County, GA'], $result['counties']);
    }

    /**
     * A JSON scalar decodes successfully but is not an array, so the method returns early.
     *
     * @dataProvider components
     */
    public function test_a_json_scalar_changes_nothing(string $class): void
    {
        $result = $this->hydrate($class, '"just a string"', 'Georgia', ['Cobb County, GA']);

        $this->assertSame('Georgia', $result['state']);
        $this->assertSame(['Cobb County, GA'], $result['counties']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · ALL FIVE COMPONENTS AGREE, FIELD FOR FIELD
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The consolidation's actual claim: these components are interchangeable on this method.
     *
     * Asserted by running one non-trivial blob through every component and comparing the results
     * to each other rather than to a literal — so the test keeps its meaning even if the expected
     * output is ever deliberately changed for all of them at once.
     */
    public function test_every_component_produces_identical_output_for_the_same_blob(): void
    {
        $blob = json_encode([
            'state'    => '  Florida ',
            'counties' => ['Pinellas County, FL', '', 42, 'Cobb County, GA'],
            'cities'   => ['Tampa, FL'],
        ]);

        $results = [];

        foreach (self::components() as $label => [$class]) {
            $results[$label] = $this->hydrate($class, $blob, 'Georgia', ['Seed County, GA']);
        }

        $reference = reset($results);

        foreach ($results as $label => $actual) {
            $this->assertSame($reference, $actual, "{$label} diverged from the others.");
        }
    }
}
