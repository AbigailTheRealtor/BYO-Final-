<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * SellerIncomeFieldRoundTripTest
 *
 * Round-trip tests for the income / multifamily fields added to
 * SellerOfferListingEdit in Task 2530.
 *
 * Covers two concerns:
 *
 * A. SAVE PATH — after saveAllMetadata(), the following meta keys are persisted:
 *      gross_annual_income, annual_operating_expenses, rent_roll_available,
 *      operating_statement_available, assumable_occupancy_requirement,
 *      minimum_annual_net_income, monthly_income, minimum_cap_rate,
 *      unit_type_configurations (JSON)
 *
 * B. DUPLICATE-SAVE REMOVAL — after saveAllMetadata(), the following flat meta
 *    keys must NOT be present in seller_agent_auction_metas:
 *      number_occupied, expected_rent, beds_unit, baths_unit,
 *      carport_spaces, unit_type_description
 *    These values are persisted only inside the unit_type_configurations JSON.
 *
 * C. LOAD PATH — loadAuctionData() hydrates the component properties from the
 *    stored meta values.
 */
class SellerIncomeFieldRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAuction(array $meta = []): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Income Test Listing',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        foreach ($meta as $key => $value) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => $key,
                'meta_value'              => (string) $value,
            ]);
        }

        return $auction;
    }

    /**
     * Mount an Edit component, set properties on the live instance, call
     * saveAllMetadata() via reflection, and return the refreshed auction.
     */
    private function saveWithProperties(SellerAgentAuction $auction, array $props): SellerAgentAuction
    {
        $component     = Livewire::actingAs($this->user)->test(SellerOfferListingEdit::class);
        $liveComponent = $component->instance();

        foreach ($props as $prop => $value) {
            $liveComponent->{$prop} = $value;
        }

        $method = new ReflectionMethod(SellerOfferListingEdit::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($liveComponent, $auction);

        $auction->refresh();
        return $auction;
    }

    // =========================================================================
    // A. Save path — income financial fields are persisted
    // =========================================================================

    public function test_save_persists_gross_annual_income(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['gross_annual_income' => '120000']);

        $this->assertSame('120000', $auction->info('gross_annual_income'),
            'gross_annual_income must be saved as a meta key by saveAllMetadata');
    }

    public function test_save_persists_annual_operating_expenses(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['annual_operating_expenses' => '45000']);

        $this->assertSame('45000', $auction->info('annual_operating_expenses'),
            'annual_operating_expenses must be saved as a meta key by saveAllMetadata');
    }

    public function test_save_persists_rent_roll_available(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['rent_roll_available' => 'Yes']);

        $this->assertSame('Yes', $auction->info('rent_roll_available'),
            'rent_roll_available must be saved as a meta key by saveAllMetadata');
    }

    public function test_save_persists_operating_statement_available(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['operating_statement_available' => 'No']);

        $this->assertSame('No', $auction->info('operating_statement_available'),
            'operating_statement_available must be saved as a meta key by saveAllMetadata');
    }

    public function test_save_persists_assumable_occupancy_requirement(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['assumable_occupancy_requirement' => '90%']);

        $this->assertSame('90%', $auction->info('assumable_occupancy_requirement'),
            'assumable_occupancy_requirement must be saved as a meta key by saveAllMetadata');
    }

    public function test_save_persists_minimum_annual_net_income(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['minimum_annual_net_income' => '75000']);

        $this->assertSame('75000', $auction->info('minimum_annual_net_income'),
            'minimum_annual_net_income must be saved as a meta key by saveAllMetadata');
    }

    public function test_save_persists_monthly_income(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['monthly_income' => '5000']);

        $this->assertSame('5000', $auction->info('monthly_income'),
            'monthly_income must be saved as a meta key by saveAllMetadata');
    }

    public function test_save_persists_minimum_cap_rate(): void
    {
        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['minimum_cap_rate' => '6.5']);

        $this->assertSame('6.5', $auction->info('minimum_cap_rate'),
            'minimum_cap_rate must be saved as a meta key by saveAllMetadata');
    }

    // =========================================================================
    // A. Save path — unit_type_configurations JSON
    // =========================================================================

    public function test_save_persists_unit_type_configurations_as_json(): void
    {
        $configs = [
            [
                'number_of_units'    => '4',
                'beds_unit'          => '1',
                'baths_unit'         => '1',
                'expected_rent'      => '1200',
                'number_occupied'    => '3',
                'carport_spaces'     => '0',
                'unit_type_description' => 'Studio',
            ],
        ];

        $auction = $this->makeAuction();
        $auction = $this->saveWithProperties($auction, ['unit_type_configurations' => $configs]);

        $stored = $auction->info('unit_type_configurations');
        $this->assertNotNull($stored, 'unit_type_configurations must be saved as a JSON meta key');

        $decoded = json_decode($stored, true);
        $this->assertIsArray($decoded, 'unit_type_configurations meta value must be valid JSON');
        $this->assertNotEmpty($decoded);
        $this->assertSame('4', $decoded[0]['number_of_units']);
        $this->assertSame('1200', $decoded[0]['expected_rent']);
    }

    // =========================================================================
    // B. Duplicate-save removal — flat keys must NOT exist in meta after save
    //
    // number_occupied, expected_rent, beds_unit, baths_unit, carport_spaces,
    // and unit_type_description must live ONLY inside unit_type_configurations
    // JSON.  They must not appear as standalone meta keys.
    // =========================================================================

    public function test_number_occupied_is_not_saved_as_flat_meta_key(): void
    {
        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'number_occupied'         => '3',
            'unit_type_configurations'=> [['number_occupied' => '3']],
        ]);

        $flatMeta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'number_occupied')
            ->first();

        $this->assertNull($flatMeta,
            'number_occupied must not be saved as a flat meta key — it belongs inside unit_type_configurations JSON');
    }

    public function test_expected_rent_is_not_saved_as_flat_meta_key(): void
    {
        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'expected_rent'           => '1500',
            'unit_type_configurations'=> [['expected_rent' => '1500']],
        ]);

        $flatMeta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'expected_rent')
            ->first();

        $this->assertNull($flatMeta,
            'expected_rent must not be saved as a flat meta key — it belongs inside unit_type_configurations JSON');
    }

    public function test_beds_unit_is_not_saved_as_flat_meta_key(): void
    {
        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'beds_unit'               => '2',
            'unit_type_configurations'=> [['beds_unit' => '2']],
        ]);

        $flatMeta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'beds_unit')
            ->first();

        $this->assertNull($flatMeta,
            'beds_unit must not be saved as a flat meta key — it belongs inside unit_type_configurations JSON');
    }

    public function test_baths_unit_is_not_saved_as_flat_meta_key(): void
    {
        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'baths_unit'              => '1',
            'unit_type_configurations'=> [['baths_unit' => '1']],
        ]);

        $flatMeta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'baths_unit')
            ->first();

        $this->assertNull($flatMeta,
            'baths_unit must not be saved as a flat meta key — it belongs inside unit_type_configurations JSON');
    }

    public function test_garage_spaces_is_not_saved_as_flat_meta_key(): void
    {
        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'garage_spaces'           => '2',
            'unit_type_configurations'=> [['garage_spaces' => '2']],
        ]);

        $flatMeta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'garage_spaces')
            ->first();

        $this->assertNull($flatMeta,
            'garage_spaces must not be saved as a flat meta key — it belongs inside unit_type_configurations JSON');
    }

    public function test_carport_spaces_is_not_saved_as_flat_meta_key(): void
    {
        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'carport_spaces'          => '1',
            'unit_type_configurations'=> [['carport_spaces' => '1']],
        ]);

        $flatMeta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'carport_spaces')
            ->first();

        $this->assertNull($flatMeta,
            'carport_spaces must not be saved as a flat meta key — it belongs inside unit_type_configurations JSON');
    }

    public function test_unit_type_description_is_not_saved_as_flat_meta_key(): void
    {
        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'unit_type_description'   => 'Studio',
            'unit_type_configurations'=> [['unit_type_description' => 'Studio']],
        ]);

        $flatMeta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'unit_type_description')
            ->first();

        $this->assertNull($flatMeta,
            'unit_type_description must not be saved as a flat meta key — it belongs inside unit_type_configurations JSON');
    }

    /**
     * Composite test: saves all 7 formerly-duplicate keys at once and asserts
     * none exist as flat meta rows.  Also verifies unit_type_configurations
     * IS present and contains the data.
     */
    public function test_all_formerly_duplicate_unit_keys_are_absent_as_flat_meta(): void
    {
        $configs = [[
            'number_of_units'        => '4',
            'beds_unit'              => '2',
            'baths_unit'             => '1',
            'expected_rent'          => '1400',
            'number_occupied'        => '3',
            'garage_spaces'          => '1',
            'carport_spaces'         => '0',
            'unit_type_description'  => 'Classic 2BR',
        ]];

        $auction = $this->makeAuction();
        $this->saveWithProperties($auction, [
            'number_occupied'         => '3',
            'expected_rent'           => '1400',
            'beds_unit'               => '2',
            'baths_unit'              => '1',
            'garage_spaces'           => '1',
            'carport_spaces'          => '0',
            'unit_type_description'   => 'Classic 2BR',
            'unit_type_configurations'=> $configs,
        ]);

        $flatKeys = [
            'number_occupied', 'expected_rent', 'beds_unit',
            'baths_unit', 'garage_spaces', 'carport_spaces', 'unit_type_description',
        ];

        foreach ($flatKeys as $key) {
            $exists = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
                ->where('meta_key', $key)
                ->exists();

            $this->assertFalse($exists,
                "Flat meta key '{$key}' must not exist after saveAllMetadata — data belongs in unit_type_configurations JSON");
        }

        $auction->refresh();
        $stored = $auction->info('unit_type_configurations');
        $this->assertNotNull($stored, 'unit_type_configurations must be saved');
        $decoded = json_decode($stored, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Classic 2BR', $decoded[0]['unit_type_description'],
            'unit_type_description must be accessible inside unit_type_configurations JSON');
    }

    // =========================================================================
    // C. Load path — loadAuctionData() hydrates income fields
    // =========================================================================

    public function test_load_hydrates_gross_annual_income(): void
    {
        $auction = $this->makeAuction(['gross_annual_income' => '95000']);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('gross_annual_income', '95000');
    }

    public function test_load_hydrates_annual_operating_expenses(): void
    {
        $auction = $this->makeAuction(['annual_operating_expenses' => '35000']);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('annual_operating_expenses', '35000');
    }

    public function test_load_hydrates_minimum_cap_rate(): void
    {
        $auction = $this->makeAuction(['minimum_cap_rate' => '7.0']);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('minimum_cap_rate', '7.0');
    }

    public function test_load_hydrates_assumable_occupancy_requirement(): void
    {
        $auction = $this->makeAuction(['assumable_occupancy_requirement' => '85%']);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('assumable_occupancy_requirement', '85%');
    }

    public function test_load_hydrates_monthly_income(): void
    {
        $auction = $this->makeAuction(['monthly_income' => '8500']);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('monthly_income', '8500');
    }
}
