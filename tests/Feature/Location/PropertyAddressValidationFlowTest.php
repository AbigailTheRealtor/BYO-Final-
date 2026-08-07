<?php

namespace Tests\Feature\Location;

use App\Http\Livewire\Concerns\ValidatesPropertyAddress;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuction;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 0 — the rule is enforced on every Seller/Landlord surface, and the ZIP
 * recovery behaves.
 *
 * The unit tests prove the classifier is correct; ValidStreetAddressRuleTest
 * proves the rule composes with the gazetteer. This file proves the wiring: that
 * all four flows actually call it, and that a user who types a ZIP into the
 * street field is helped rather than scolded.
 *
 * A note on how the flows are driven. `getPlaceSuggestions()` — duplicated across
 * the offer-listing components — issues raw PostgreSQL `ILIKE` queries, which are
 * a syntax error under the SQLite test database. Any `set('property_city')` or
 * `set('state')` therefore throws before the assertion is reached. That is
 * pre-existing debt (TD-2), not something this phase introduced, so these tests
 * drive the address field only and assert on the error bag, which is the
 * behaviour under test.
 *
 * @see \App\Rules\ValidStreetAddress
 * @see \App\Http\Livewire\Concerns\ValidatesPropertyAddress
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

        $this->actingAs(User::factory()->create());
    }

    /**
     * The four publish surfaces. Each is driven to submit with a junk address and
     * must reject it — this is the regression that the audit opened with.
     *
     * @return array<string,array{class-string}>
     */
    public function publishFlows(): array
    {
        return [
            'Create Seller listing'   => [SellerOfferListing::class, 'store'],
            'Create Landlord listing' => [LandlordOfferListing::class, 'store'],
            'Hire Seller agent'       => [SellerAgentAuction::class, 'store'],
            'Hire Landlord agent'     => [LandLordAgentAuction::class, 'store'],
        ];
    }

    /**
     * Audit scenario 1, on every flow: `43434` is a street number, not an address.
     *
     * @dataProvider publishFlows
     */
    public function test_a_bare_street_number_cannot_be_published(string $component, string $action): void
    {
        Livewire::test($component)
            ->set('address', '43434')
            ->call($action)
            ->assertHasErrors('address');
    }

    /**
     * @dataProvider publishFlows
     */
    public function test_an_empty_street_address_cannot_be_published(string $component, string $action): void
    {
        Livewire::test($component)
            ->set('address', '')
            ->call($action)
            ->assertHasErrors('address');
    }

    /**
     * The negative case matters as much as the positive one: a real address must
     * raise no address error. Other required fields are left blank deliberately —
     * those errors are expected and are not what this asserts.
     *
     * @dataProvider publishFlows
     */
    public function test_a_real_pinellas_address_raises_no_address_error(string $component, string $action): void
    {
        Livewire::test($component)
            ->set('address', '100 2nd Ave N, St. Petersburg')
            ->call($action)
            ->assertHasNoErrors('address');
    }

    // ─── ZIP recovery and autofill ────────────────────────────────────────────

    public function test_a_zip_typed_into_the_street_field_is_moved_and_explained(): void
    {
        $component = $this->addressHarness();
        $component->address = '33708';

        $this->assertTrue($component->assistPropertyAddress());

        $this->assertSame('', $component->address, 'the ZIP is taken out of the street field');
        $this->assertSame('33708', $component->zip_code, 'and put where it belongs');
        $this->assertStringContainsString('33708', $component->addressAssistNotice);
        $this->assertStringContainsString('ZIP Code field', $component->addressAssistNotice);
    }

    /**
     * `43434` is ZIP-shaped but is not a US ZIP. We have no evidence the user meant
     * it as a ZIP, so we must not move it — it falls through to the rule.
     */
    public function test_five_digits_that_are_not_a_real_zip_are_left_alone(): void
    {
        $component = $this->addressHarness();
        $component->address = '43434';

        $this->assertFalse($component->assistPropertyAddress());

        $this->assertSame('43434', $component->address);
        $this->assertSame('', $component->zip_code);
        $this->assertSame('', $component->addressAssistNotice);
    }

    public function test_zip_entry_autofills_city_county_and_state(): void
    {
        $component = $this->addressHarness();

        $filled = $component->selectPropertyZip('33708');

        $this->assertSame('Saint Petersburg', $component->property_city);
        $this->assertSame('Pinellas County, FL', $component->property_county);
        $this->assertSame('Florida', $component->state);
        $this->assertContains('property_city', $filled);
    }

    /**
     * Autofill is a convenience, not an authority. A user who corrected the county
     * by hand must not have that correction reverted.
     */
    public function test_autofill_does_not_overwrite_a_value_the_user_already_set(): void
    {
        $component = $this->addressHarness();
        $component->property_city   = 'Madeira Beach';
        $component->property_county = 'Pinellas County, FL';

        $component->address = '33708';
        $component->assistPropertyAddress();

        $this->assertSame('Madeira Beach', $component->property_city, 'the user’s city survives');
        $this->assertSame('Pinellas County, FL', $component->property_county);
        $this->assertSame('Florida', $component->state, 'but a blank field is still filled');
    }

    /** Picking a ZIP from the list is an unambiguous statement of intent. */
    public function test_an_explicit_zip_selection_may_overwrite(): void
    {
        $component = $this->addressHarness();
        $component->property_city = 'Madeira Beach';

        $component->selectPropertyZip('33708');

        $this->assertSame('Saint Petersburg', $component->property_city);
    }

    /**
     * A ZIP centroid is the middle of a postal area, not a property location.
     * Writing one would poison the geo narrower that arrives in a later phase.
     */
    public function test_zip_autofill_never_writes_coordinates(): void
    {
        $component = $this->addressHarness();
        $component->selectPropertyZip('33708');

        $this->assertNull($component->latitude);
        $this->assertNull($component->longitude);
    }

    public function test_an_unknown_zip_fills_nothing(): void
    {
        $component = $this->addressHarness();

        $this->assertSame([], $component->selectPropertyZip('99999'));
        $this->assertSame('', $component->property_city);
    }

    /**
     * A minimal component carrying only the address fields the trait touches.
     *
     * The eight real components are 2,000–4,400 lines each and their constructors
     * reach for MLS config, service-order config and Google Places. Driving the
     * trait through them would test those dependencies, not this behaviour. The
     * flows' actual adoption of the rule is covered by the dataprovider tests
     * above; this harness covers the trait's own logic.
     */
    private function addressHarness(): Component
    {
        return new class extends Component
        {
            use ValidatesPropertyAddress;

            public $address = '';
            public $unit_address = '';
            public $zip_code = '';
            public $property_city = '';
            public $property_county = '';
            public $property_zip = '';
            public $state = '';

            /** Present so the test can prove they are never written to. */
            public $latitude = null;
            public $longitude = null;

            public function render()
            {
                return '<div></div>';
            }
        };
    }
}
