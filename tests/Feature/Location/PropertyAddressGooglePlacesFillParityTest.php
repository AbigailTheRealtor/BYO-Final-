<?php

namespace Tests\Feature\Location;

use App\Http\Livewire\Concerns\HandlesGooglePlacesAddress;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase 1 — the four Seller/Landlord offer-listing components share ONE
 * `fillFromGooglePlaces()`, and it is the trait's.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * Phase 1's objective is consolidation: five implementations of the same method
 * became one. The roadmap's acceptance criterion is "zero behaviour change",
 * asserted by an existing-vs-new parity test. This is that test. It pins the
 * post-consolidation behaviour so a future edit cannot quietly re-fork it.
 *
 * THE ONE DELIBERATE BEHAVIOURAL DIFFERENCE
 * -----------------------------------------
 * Before consolidation the four copies were not identical. The two Create copies
 * reset `highlightedPropertyCityIndex` to -1 after a Google fill; the two Edit
 * copies (SellerOfferListingEdit, LandlordOfferListingEdit) did not — even though
 * both declare the property and both maintain it everywhere else.
 *
 * That omission was a latent bug, not a feature. `highlightedPropertyCityIndex`
 * points into `propertyCitySuggestions`. The Edit copies cleared the suggestions
 * array but left the index pointing at the entry that used to be there, so after
 * a Google fill the Edit flows carried a stale index into an empty list. The
 * Create flows did not.
 *
 * Adopting the trait wholesale therefore CORRECTS the two Edit flows. That is the
 * only observable difference this consolidation introduces, it moves the Edit
 * flows onto the behaviour the Create flows already had, and it is asserted
 * explicitly below (see test_the_highlight_index_is_reset_on_every_flow) so that
 * a reviewer reading the diff does not have to take the claim on trust.
 *
 * Approved as a correctness fix by the owner, 2026-07-26.
 *
 * @see \App\Http\Livewire\Concerns\HandlesGooglePlacesAddress
 */
class PropertyAddressGooglePlacesFillParityTest extends TestCase
{
    /**
     * The four components this phase consolidated.
     *
     * Instantiated directly rather than through `Livewire::test()`: the method
     * under test is pure property assignment, and mounting the Edit components
     * would drag in an auction fixture and the raw-ILIKE `getPlaceSuggestions()`
     * debt (TD-2) that PropertyAddressValidationFlowTest documents. Neither is
     * relevant to what this asserts.
     *
     * @return array<string,array{class-string}>
     */
    public function consolidatedComponents(): array
    {
        return [
            'Create Seller listing'   => [SellerOfferListing::class],
            'Edit Seller listing'     => [SellerOfferListingEdit::class],
            'Create Landlord listing' => [LandlordOfferListing::class],
            'Edit Landlord listing'   => [LandlordOfferListingEdit::class],
        ];
    }

    /**
     * There is exactly one implementation, and every component inherits it from
     * the trait rather than declaring its own.
     *
     * `ReflectionMethod::getFileName()` on a trait-provided method reports the
     * trait's file, so this fails the moment anyone re-declares the method on a
     * component — which is precisely the fork Phase 1 removed.
     *
     * @dataProvider consolidatedComponents
     */
    public function test_the_fill_method_comes_from_the_shared_trait(string $component): void
    {
        $this->assertContains(
            HandlesGooglePlacesAddress::class,
            (new ReflectionClass($component))->getTraitNames(),
            "{$component} must use the shared trait"
        );

        $this->assertSame(
            (new ReflectionClass(HandlesGooglePlacesAddress::class))->getFileName(),
            (new ReflectionMethod($component, 'fillFromGooglePlaces'))->getFileName(),
            "{$component} must not declare its own fillFromGooglePlaces()"
        );
    }

    /**
     * The address parts land in the same properties on all four flows — the parity
     * the consolidation had to preserve.
     *
     * @dataProvider consolidatedComponents
     */
    public function test_every_flow_fills_the_same_address_fields(string $component): void
    {
        $subject = new $component();

        $subject->fillFromGooglePlaces(
            '100 2nd Ave N',
            'Saint Petersburg',
            'Pinellas',
            'FL',
            '33701',
            '27.7712000',
            '-82.6386000',
            'place-id-abc'
        );

        $this->assertSame('100 2nd Ave N', $subject->address);
        $this->assertSame('Saint Petersburg', $subject->property_city);
        $this->assertSame('Pinellas', $subject->property_county);
        $this->assertSame('FL', $subject->property_state);
        $this->assertSame('33701', $subject->property_zip);
        $this->assertSame('27.7712000', $subject->property_lat);
        $this->assertSame('-82.6386000', $subject->property_lng);
        $this->assertSame('place-id-abc', $subject->google_place_id);
    }

    /**
     * An open suggestion dropdown is closed by a Google fill, on every flow.
     *
     * @dataProvider consolidatedComponents
     */
    public function test_the_suggestion_list_is_cleared_on_every_flow(string $component): void
    {
        $subject = new $component();
        $subject->propertyCitySuggestions = ['Saint Petersburg', 'Saint Cloud'];

        $subject->fillFromGooglePlaces('100 2nd Ave N', 'Saint Petersburg', 'Pinellas', 'FL', '33701', '', '', '');

        $this->assertSame([], $subject->propertyCitySuggestions);
    }

    /**
     * THE INTENTIONAL CORRECTION.
     *
     * Pre-consolidation this passed on the two Create flows and FAILED on the two
     * Edit flows. Post-consolidation it passes on all four. A stale index into a
     * cleared list is not a behaviour worth preserving, so the two Edit flows are
     * corrected onto the Create flows' behaviour rather than the reverse.
     *
     * If this test ever fails for an Edit flow again, the fork has returned.
     *
     * @dataProvider consolidatedComponents
     */
    public function test_the_highlight_index_is_reset_on_every_flow(string $component): void
    {
        $subject = new $component();
        $subject->propertyCitySuggestions      = ['Saint Petersburg', 'Saint Cloud'];
        $subject->highlightedPropertyCityIndex = 1;

        $subject->fillFromGooglePlaces('100 2nd Ave N', 'Saint Petersburg', 'Pinellas', 'FL', '33701', '', '', '');

        $this->assertSame(
            -1,
            $subject->highlightedPropertyCityIndex,
            "{$component} must not leave a stale highlight index pointing into a cleared suggestion list"
        );
    }

    /**
     * The trait defaults lat/lng/placeId; the four copies it replaced required all
     * eight arguments. Widening a signature cannot break an existing caller, but
     * the Blade component passes all eight and Phase 2's renderer swap may not, so
     * the shorter call is pinned here as supported.
     */
    public function test_the_geo_arguments_are_optional(): void
    {
        $subject = new SellerOfferListing();

        $subject->fillFromGooglePlaces('100 2nd Ave N', 'Saint Petersburg', 'Pinellas', 'FL', '33701');

        $this->assertSame('100 2nd Ave N', $subject->address);
        $this->assertSame('', $subject->property_lat);
        $this->assertSame('', $subject->property_lng);
        $this->assertSame('', $subject->google_place_id);
    }
}
