<?php

namespace Tests\Feature\Location;

use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuction;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 0 — `43434` is rejected on every Seller/Landlord surface.
 *
 * The audit found the street field had no server-side validation on the Create
 * flows and `required|string` on the Hire flows. All four published a street
 * number as a whole property address. This test is the regression guard: it runs
 * the real publish path of all four live components and asserts the address
 * error is raised, so a future refactor cannot quietly drop the rule from one
 * role while leaving it on the other three — the exact drift that produced five
 * copies of `fillFromGooglePlaces()`.
 *
 * Publish also fails here for unrelated missing fields; `assertHasErrors` checks
 * the named key only, which is precisely the assertion we want.
 *
 * @see docs/spatial-ui-integration-audit-2026-07-25.md §9 scenarios 1, 2, 4
 */
class PropertyAddressValidationFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        DB::table('us_zip_codes')->insert([
            'zip_code'     => '33708',
            'city'         => 'Saint Petersburg',
            'state_abbrev' => 'FL',
            'state_name'   => 'Florida',
            'county'       => 'Pinellas',
            'latitude'     => 27.8116080,
            'longitude'    => -82.8014300,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /** Every live Seller/Landlord publish path, create side. */
    public static function scopeAFlows(): array
    {
        return [
            'Create Seller listing'  => [SellerOfferListing::class],
            'Create Landlord listing' => [LandlordOfferListing::class],
            'Hire Seller agent'      => [SellerAgentAuction::class],
            'Hire Landlord agent'    => [LandLordAgentAuction::class],
        ];
    }

    /**
     * @dataProvider scopeAFlows
     */
    public function test_a_bare_street_number_cannot_be_published(string $component): void
    {
        Livewire::actingAs($this->seller())
            ->test($component)
            ->set('address', '43434')
            ->call('store')
            ->assertHasErrors(['address']);
    }

    /**
     * @dataProvider scopeAFlows
     */
    public function test_an_empty_street_address_cannot_be_published(string $component): void
    {
        Livewire::actingAs($this->seller())
            ->test($component)
            ->set('address', '')
            ->call('store')
            ->assertHasErrors(['address']);
    }

    /**
     * @dataProvider scopeAFlows
     */
    public function test_a_real_pinellas_address_raises_no_address_error(string $component): void
    {
        Livewire::actingAs($this->seller())
            ->test($component)
            ->set('address', '100 2nd Ave N, St. Petersburg')
            ->call('store')
            ->assertHasNoErrors(['address']);
    }

    /**
     * Audit scenario 2 — a real ZIP typed into the street field is recovered,
     * not merely rejected: it moves to the ZIP field, fills the location, and
     * explains itself.
     */
    public function test_a_zip_typed_into_the_street_field_is_moved_and_explained(): void
    {
        Livewire::actingAs($this->seller())
            ->test(SellerOfferListing::class)
            ->set('address', '33708')
            ->assertSet('address', '')
            ->assertSet('zip_code', '33708')
            ->assertSet('property_city', 'Saint Petersburg')
            ->assertSet('property_county', 'Pinellas')
            ->assertSet('property_state', 'FL')
            ->assertSee('We moved 33708 to the ZIP Code field');
    }

    /** ZIP → City / County / State, from owned data, with no external call. */
    public function test_zip_entry_autofills_city_county_and_state(): void
    {
        Livewire::actingAs($this->seller())
            ->test(SellerOfferListing::class)
            ->set('zip_code', '33708')
            ->assertSet('property_city', 'Saint Petersburg')
            ->assertSet('property_county', 'Pinellas')
            ->assertSet('property_state', 'FL')
            ->assertSet('state', 'Florida');
    }

    /**
     * A user correction must survive: autofill never overwrites existing input.
     *
     * Driven through the component instance rather than `set('property_city')`,
     * because that setter fires the legacy city-suggestion hook, whose `ILIKE`
     * query is Postgres-only and cannot run on the SQLite test connection. That
     * is a pre-existing constraint of the harness, unrelated to this change.
     */
    public function test_autofill_does_not_overwrite_a_value_the_user_already_set(): void
    {
        $component = Livewire::actingAs($this->seller())->test(SellerOfferListing::class);
        $instance = $component->instance();

        $instance->property_city = 'Madeira Beach';
        $instance->applyZipLocation('33708');

        $this->assertSame('Madeira Beach', $instance->property_city, 'user correction was overwritten');
        $this->assertSame('Pinellas', $instance->property_county, 'empty field was not filled');
    }

    /** An explicit ZIP pick may overwrite — the user asked for it. */
    public function test_an_explicit_zip_selection_may_overwrite(): void
    {
        $instance = Livewire::actingAs($this->seller())
            ->test(SellerOfferListing::class)
            ->instance();

        $instance->property_city = 'Madeira Beach';
        $instance->applyZipLocation('33708', true);

        $this->assertSame('Saint Petersburg', $instance->property_city);
    }

    /**
     * A ZIP centroid is not a property location. Nothing in Phase 0 may write
     * coordinates — the geo narrower switched on in Phase 4 would treat them as
     * a rooftop fix and silently mis-rank every listing.
     */
    public function test_zip_autofill_never_writes_coordinates(): void
    {
        Livewire::actingAs($this->seller())
            ->test(SellerOfferListing::class)
            ->set('zip_code', '33708')
            ->assertSet('property_lat', '')
            ->assertSet('property_lng', '');
    }

    private function seller(): User
    {
        return User::factory()->create(['user_type' => 'seller']);
    }
}
