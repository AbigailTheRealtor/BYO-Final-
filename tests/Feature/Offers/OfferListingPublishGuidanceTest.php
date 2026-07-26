<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Rules\ValidStreetAddress;
use App\Services\Location\AddressShapeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

/**
 * Create Offer Listing — publish submit path (Seller + Landlord).
 *
 * Phase 0 made `address` a publish requirement for the first time. The field
 * lives on Property Details, but Submit is pressed from a later tab, and both
 * wizards' client-side gates treated anything on an inactive tab as satisfied.
 * A blank or legacy address therefore failed server-side with no indication of
 * which field, on which tab, was at fault.
 *
 * These tests pin: publish still works, the failure is now reported back to the
 * browser with the offending field, guided correction stays scoped to fields the
 * server genuinely requires, and the server remains authoritative.
 */
class OfferListingPublishGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_ADDRESS = '100 2nd Ave N';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // us_zip:* availability is cached
    }

    private function sellerFields(string $address = self::VALID_ADDRESS): array
    {
        return [
            'listing_title' => 'My Seller Listing',
            'property_type' => 'Residential',
            'address'       => $address,
            'first_name'    => 'Ada',
            'last_name'     => 'Lovelace',
            'phone_number'  => '5551234567',
            'email'         => 'ada@example.com',
        ];
    }

    private function landlordFields(string $address = self::VALID_ADDRESS): array
    {
        return [
            'listing_title'        => 'My Landlord Listing',
            'address'              => $address,
            'first_name'           => 'Ada',
            'last_name'            => 'Lovelace',
            'phone_number'         => '5551234567',
            'email'                => 'ada@example.com',
            'desired_lease_length' => ['12 Months'],
        ];
    }

    private function fill($component, array $fields)
    {
        foreach ($fields as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    // ── Provenance ────────────────────────────────────────────────────────────
    // The worktree at /home/runner/offer-listing-submit-fix symlinks vendor/ to the
    // main workspace, so Composer's PSR-4 map resolves App\ to /home/runner/workspace/app
    // regardless of which checkout the test file sits in. This test records the file
    // actually loaded so a passing run can never be misattributed to the wrong tree.

    public function test_tests_exercise_the_intended_checkout(): void
    {
        $expectedRoot = '/home/runner/workspace';

        foreach ([SellerOfferListing::class, LandlordOfferListing::class, ValidStreetAddress::class] as $class) {
            $file = (new ReflectionClass($class))->getFileName();
            fwrite(STDERR, "  loaded {$class}\n      from {$file}\n");
            $this->assertStringStartsWith(
                $expectedRoot . '/app/',
                $file,
                "{$class} was loaded from an unexpected checkout"
            );
        }
    }

    // ── The four required scenarios ───────────────────────────────────────────

    public function test_new_seller_listing_with_valid_address_publishes(): void
    {
        $this->actingAs(User::factory()->create());

        $c = $this->fill(Livewire::test(SellerOfferListing::class), $this->sellerFields());
        $c->call('store');

        $c->assertHasNoErrors();
        $this->assertSame(1, SellerAgentAuction::where('is_draft', 0)->count());
    }

    public function test_new_landlord_listing_with_valid_address_publishes(): void
    {
        $this->actingAs(User::factory()->create());

        $c = $this->fill(Livewire::test(LandlordOfferListing::class), $this->landlordFields());
        $c->call('store');

        $c->assertHasNoErrors();
        $this->assertSame(1, LandlordAgentAuction::where('is_draft', 0)->count());
    }

    public function test_resumed_seller_draft_with_blank_address_is_guided_to_property_details(): void
    {
        $this->actingAs(User::factory()->create());

        // A draft that predates the Phase 0 address rule: everything else filled.
        $draft = $this->fill(Livewire::test(SellerOfferListing::class), $this->sellerFields(''));
        $draft->call('saveDraft');
        $draftId = $draft->get('listingId');
        $this->assertNotNull($draftId);

        $resumed = Livewire::test(SellerOfferListing::class, ['listingId' => $draftId]);
        $this->assertSame('', trim((string) $resumed->get('address')), 'draft resumed with no address');

        $resumed->call('store');

        // Server rejects, and the draft is NOT published.
        $resumed->assertHasErrors(['address']);
        $this->assertSame(1, (int) SellerAgentAuction::find($draftId)->is_draft, 'draft stays a draft');

        // The browser is told which field failed so it can open Property Details.
        $resumed->assertDispatchedBrowserEvent(
            'publish-validation-failed',
            fn ($name, $data) => in_array('address', $data['fields'], true)
                && $data['fields'][0] === 'address'
                && $data['messages']['address'] !== ''
        );

        // Entered data survives the failed publish.
        $this->assertSame('My Seller Listing', $resumed->get('listing_title'));
        $this->assertSame('ada@example.com', $resumed->get('email'));

        // The inline error renders on the address input.
        $resumed->assertSee('The address field is required.', false);
    }

    public function test_resumed_landlord_draft_with_blank_address_is_guided_to_property_details(): void
    {
        $this->actingAs(User::factory()->create());

        $draft = $this->fill(Livewire::test(LandlordOfferListing::class), $this->landlordFields(''));
        $draft->call('saveDraft');
        $draftId = $draft->get('listingId');
        $this->assertNotNull($draftId);

        $resumed = Livewire::test(LandlordOfferListing::class, ['listingId' => $draftId]);
        $this->assertSame('', trim((string) $resumed->get('address')), 'draft resumed with no address');

        $resumed->call('store');

        $resumed->assertHasErrors(['address']);
        $this->assertSame(1, (int) LandlordAgentAuction::find($draftId)->is_draft, 'draft stays a draft');

        $resumed->assertDispatchedBrowserEvent(
            'publish-validation-failed',
            fn ($name, $data) => in_array('address', $data['fields'], true)
                && $data['messages']['address'] !== ''
        );

        $this->assertSame('ada@example.com', $resumed->get('email'));
        $resumed->assertSee('The address field is required.', false);
    }

    // ── Address shape messages ────────────────────────────────────────────────

    public function test_street_number_only_input_gets_the_street_number_message(): void
    {
        // Gazetteer present and populated, but 43434 is not a ZIP → street number.
        DB::table('us_zip_codes')->insert([
            'zip_code' => '33708', 'city' => 'Saint Petersburg', 'county' => 'Pinellas',
            'state_abbrev' => 'FL', 'state_name' => 'Florida',
            'latitude' => 27.8, 'longitude' => -82.8,
        ]);

        $v = Validator::make(['address' => '43434'], ['address' => [new ValidStreetAddress()]]);

        $this->assertTrue($v->fails());
        $this->assertSame(
            (new AddressShapeValidator())->messageFor(AddressShapeValidator::NUMBER_ONLY),
            $v->errors()->first('address')
        );
    }

    public function test_zip_only_input_gets_the_zip_message_when_the_gazetteer_knows_it(): void
    {
        DB::table('us_zip_codes')->insert([
            'zip_code' => '33708', 'city' => 'Saint Petersburg', 'county' => 'Pinellas',
            'state_abbrev' => 'FL', 'state_name' => 'Florida',
            'latitude' => 27.8, 'longitude' => -82.8,
        ]);

        $v = Validator::make(['address' => '33708'], ['address' => [new ValidStreetAddress()]]);

        $this->assertTrue($v->fails());
        $this->assertSame(
            (new AddressShapeValidator())->messageFor(AddressShapeValidator::ZIP_SHAPED),
            $v->errors()->first('address')
        );
        $this->assertStringContainsString('ZIP code', $v->errors()->first('address'));
    }

    public function test_zip_only_input_still_gets_the_zip_message_when_the_gazetteer_is_unavailable(): void
    {
        // Empty us_zip_codes: we cannot tell a real ZIP from five digits, so we must
        // not assert it is a street number. Regression for the fallback that told a
        // user who typed 33708 to add a street name to their ZIP code.
        $this->assertSame(0, DB::table('us_zip_codes')->count());

        $v = Validator::make(['address' => '33708'], ['address' => [new ValidStreetAddress()]]);

        $this->assertTrue($v->fails());
        $this->assertSame(
            (new AddressShapeValidator())->messageFor(AddressShapeValidator::ZIP_SHAPED),
            $v->errors()->first('address')
        );
    }

    // ── Scoping: guided correction must not adopt DOM-required fields ─────────

    public function test_guided_correction_is_scoped_to_server_required_fields_only(): void
    {
        $this->actingAs(User::factory()->create());

        $seller   = Livewire::test(SellerOfferListing::class)->instance()->publishRequiredFieldNames();
        $landlord = Livewire::test(LandlordOfferListing::class)->instance()->publishRequiredFieldNames();

        // Fields the publish rules genuinely require.
        $this->assertContains('address', $seller);
        $this->assertContains('listing_title', $seller);
        $this->assertContains('email', $seller);

        $this->assertContains('address', $landlord);
        $this->assertContains('desired_lease_length', $landlord);
        $this->assertContains('email', $landlord);

        // Marked `required` in the DOM but NOT publish blockers. If these ever appear
        // here, the wizard will start refusing submissions the server would accept.
        foreach (['property_city', 'property_state', 'property_county', 'property_zip'] as $domOnly) {
            $this->assertNotContains($domOnly, $seller, "{$domOnly} must not block Seller publish");
            $this->assertNotContains($domOnly, $landlord, "{$domOnly} must not block Landlord publish");
        }

        foreach (['condition_prop', 'occupant_status', 'leasing_spaces', 'desired_rental_amount'] as $domOnly) {
            $this->assertNotContains($domOnly, $landlord, "{$domOnly} must not block Landlord publish");
        }

        // `unit_address` is nullable and must never be treated as required.
        $this->assertNotContains('unit_address', $seller);
        $this->assertNotContains('unit_address', $landlord);

        // Element rules (roof_type.*) are not focusable fields and must be excluded.
        foreach (array_merge($seller, $landlord) as $field) {
            $this->assertStringNotContainsString('.', $field);
        }
    }

    public function test_conditional_rules_only_appear_when_their_condition_holds(): void
    {
        $this->actingAs(User::factory()->create());

        $notBidding = Livewire::test(SellerOfferListing::class)
            ->set('auction_type', 'Standard')->instance()->publishRequiredFieldNames();
        $this->assertNotContains('auction_time', $notBidding);

        $bidding = Livewire::test(SellerOfferListing::class)
            ->set('auction_type', 'Bidding Period')->instance()->publishRequiredFieldNames();
        $this->assertContains('auction_time', $bidding);
    }

    // ── The server stays authoritative ────────────────────────────────────────

    public function test_server_rejects_invalid_address_regardless_of_client_state(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['', '43434', '33708', 'Main', '.'] as $bad) {
            $c = $this->fill(Livewire::test(SellerOfferListing::class), $this->sellerFields($bad));
            $c->call('store');
            $c->assertHasErrors(['address']);
        }

        $this->assertSame(0, SellerAgentAuction::where('is_draft', 0)->count(), 'nothing published');
    }

    public function test_landlord_server_rejects_invalid_address_regardless_of_client_state(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['', '43434', 'Main'] as $bad) {
            $c = $this->fill(Livewire::test(LandlordOfferListing::class), $this->landlordFields($bad));
            $c->call('store');
            $c->assertHasErrors(['address']);
        }

        $this->assertSame(0, LandlordAgentAuction::where('is_draft', 0)->count(), 'nothing published');
    }

    // ── Ownership protections unchanged ───────────────────────────────────────

    public function test_a_user_cannot_publish_another_users_seller_draft(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $draft = $this->fill(Livewire::test(SellerOfferListing::class), $this->sellerFields());
        $draft->call('saveDraft');
        $draftId = $draft->get('listingId');

        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        Livewire::test(SellerOfferListing::class, ['listingId' => $draftId])->call('store');

        $row = SellerAgentAuction::find($draftId);
        $this->assertSame($owner->id, (int) $row->user_id, "the owner's draft was reassigned");
        $this->assertSame(1, (int) $row->is_draft, "the owner's draft was published by another user");
    }

    public function test_a_user_cannot_publish_another_users_landlord_draft(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $draft = $this->fill(Livewire::test(LandlordOfferListing::class), $this->landlordFields());
        $draft->call('saveDraft');
        $draftId = $draft->get('listingId');

        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        Livewire::test(LandlordOfferListing::class, ['listingId' => $draftId])->call('store');

        $row = LandlordAgentAuction::find($draftId);
        $this->assertSame($owner->id, (int) $row->user_id, "the owner's draft was reassigned");
        $this->assertSame(1, (int) $row->is_draft, "the owner's draft was published by another user");
    }
}
