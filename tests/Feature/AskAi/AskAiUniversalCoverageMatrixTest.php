<?php

namespace Tests\Feature\AskAi;

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService as Scope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Support\AskAi\PageFactCoverageProbe as P;
use Tests\Support\AskAi\MlsImportFixture;
use Tests\TestCase;

/**
 * Universal coverage audit (2026-09-24) — every role × property type, manual AND MLS-imported,
 * with representative facts that belong to that type, through the real free-text runner.
 *
 * For each combination it proves: the applicable fact is a card question; ordinary typed
 * wording answers it; a missing fact refuses; a fact that does not belong to the type refuses;
 * a private fact stays private to a guest / non-owner while the owner is still answered; a
 * prohibited fact stays prohibited; ambiguous wording is refused, never guessed. The model
 * client is a mock that fails the test if it is ever called, and HTTP is faked to refuse.
 */
class AskAiUniversalCoverageMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);
        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
        Http::fake(static fn () => throw new \RuntimeException('No outbound request is allowed in this test.'));
    }

    // ── Seller ────────────────────────────────────────────────────────────────

    public function test_seller_residential_manual(): void
    {
        $l = $this->listing('seller', 'Residential', [
            'bedrooms' => '3', 'bathrooms' => '2.5', 'minimum_heated_square' => '1850', 'year_built' => '1998',
            'flood_insurance_required' => 'No', 'excluded_items' => 'Dining room chandelier',
            'offered_financing' => ['Cash', 'Lease Option'], 'lease_option_conditions' => 'Minimum 12-month term',
            'interest_rate' => '6.5', 'leasing_55_plus' => 'Yes',
        ]);
        $this->answers($l, 'seller', 'how many bathrooms', '2.5 bathrooms');
        $this->answers($l, 'seller', 'bedrooms', '3 bedrooms');
        $this->answers($l, 'seller', 'flood insurance required', 'Flood Insurance Required: No.');
        $this->answers($l, 'seller', 'what is excluded', 'Dining room chandelier');
        $this->answers($l, 'seller', 'lease option conditions / requirements', 'Minimum 12-month term');
        $this->refuses($l, 'seller', 'how big is the property');          // vague size: refused, never guessed
        $this->refuses($l, 'seller', 'interest rate', '6.5');               // lending trigger term: prohibited
        $this->refuses($l, 'seller', 'leasing 55 plus', 'Yes');             // Fair Housing gate
        $this->refuses($l, 'seller', 'zoning');                             // missing: refused
        $this->onCard($l, 'seller', 'seller_bathrooms');
    }

    public function test_seller_offer_term_follows_its_parent_selection(): void
    {
        // A lease-option answer left behind after the seller stopped offering a lease option.
        $l = $this->listing('seller', 'Residential', ['offered_financing' => ['Cash'], 'lease_option_conditions' => 'Minimum 12-month term']);
        $this->refuses($l, 'seller', 'lease option conditions / requirements', 'Minimum 12-month term');
    }

    public function test_seller_income_manual(): void
    {
        $l = $this->listing('seller', 'Income', [
            'gross_annual_income' => '96000', 'annual_operating_expenses' => '41000', 'unit_buildings' => '2',
            'rent_roll_available' => 'Yes', 'number_water_meters' => '4',
            'bedrooms' => '9', // not collected on the manual Income form: a stray value
        ]);
        $this->answers($l, 'seller', 'gross income', '$96,000');
        $this->answers($l, 'seller', 'operating expenses', '$41,000');
        $this->answers($l, 'seller', 'how many buildings', '2');
        $this->answers($l, 'seller', 'rent roll available', 'Yes');
        $this->answers($l, 'seller', 'number of water meters', '4');
        $this->refuses($l, 'seller', 'how many bedrooms', '9');            // not applicable to a manual Income listing
    }

    public function test_seller_commercial_manual(): void
    {
        $l = $this->listing('seller', 'Commercial', [
            'ceiling_height' => '18 ft', 'total_square_feet' => '12,400', 'zoning' => 'CG',
            'existing_lease_type' => 'NNN', 'price_per_sqft' => '145', 'bedrooms' => '4',
        ]);
        $this->answers($l, 'seller', 'ceiling height', '18 ft');
        $this->answers($l, 'seller', 'building size', '12,400');
        $this->answers($l, 'seller', 'zoning', 'CG');
        $this->answers($l, 'seller', 'existing lease', 'NNN');
        $this->answers($l, 'seller', 'price per square foot', '$145');
        $this->refuses($l, 'seller', 'how many bedrooms', '4');
    }

    public function test_seller_business_manual(): void
    {
        $l = $this->listing('seller', 'Business', [
            'business_name' => 'Harbor Bait & Tackle', 'annual_revenue' => '1200000', 'employee_count' => '6',
            'business_location_leased' => 'Yes', 'business_lease_monthly_rent' => '4500',
            'reason_for_sale' => 'Retiring', 'bedrooms' => '2',
        ]);
        $this->answers($l, 'seller', 'business name', 'Harbor Bait & Tackle');
        $this->answers($l, 'seller', 'revenue', '$1,200,000');
        $this->answers($l, 'seller', 'how many employees', '6');
        $this->answers($l, 'seller', 'business lease monthly rent', '$4,500');
        $this->refuses($l, 'seller', 'reason for sale', 'Retiring');                          // motivation: private
        $this->refuses($l, 'seller', 'how many bedrooms', '2');
    }

    public function test_seller_business_lease_follows_location_leased(): void
    {
        $l = $this->listing('seller', 'Business', ['business_location_leased' => 'No', 'business_lease_expiration' => '2029-06-30']);
        $this->refuses($l, 'seller', 'business lease expiration date', '2029');
    }

    public function test_seller_vacant_land_manual(): void
    {
        $l = $this->listing('seller', 'Vacant Land', [
            'front_footage' => '240', 'water_available' => 'Yes', 'buildable' => 'Yes', 'zoning' => 'AG-1',
            'total_acreage' => '5 to less than 10 acres', 'year_built' => '1990',
        ]);
        $this->answers($l, 'seller', 'frontage', '240');
        $this->answers($l, 'seller', 'is water available', 'Yes');
        $this->answers($l, 'seller', 'is it buildable', 'Yes');
        $this->answers($l, 'seller', 'zoning', 'AG-1');
        $this->answers($l, 'seller', 'acreage', '5 to less than 10 acres');
        $this->refuses($l, 'seller', 'year built', '1990');                // vacant land: not applicable
        $this->refuses($l, 'seller', 'how many bathrooms');
    }

    // ── Landlord ──────────────────────────────────────────────────────────────

    public function test_landlord_residential_manual(): void
    {
        $l = $this->listing('landlord', 'Residential Property', [
            'bathrooms' => '2', 'bedrooms' => '3', 'lease_amount_frequency' => 'Monthly', 'tenant_require' => 'Furnished',
            'pets' => 'Yes', 'number_of_pets' => '2', 'min_credit_score' => '700', 'leasing_55_plus' => 'Yes',
        ]);
        $this->answers($l, 'landlord', 'how many bathrooms', '2 bathrooms');
        $this->answers($l, 'landlord', 'rent frequency', 'Monthly');
        $this->answers($l, 'landlord', 'is it furnished', 'Furnished');
        $this->answers($l, 'landlord', 'how many pets', '2');
        $this->refuses($l, 'landlord', 'min credit score', '700');         // screening (D4): private to the public …
        $this->answers($l, 'landlord', 'minimum credit score', '700', Scope::SCOPE_OWNER); // … and still the owner's own fact
        $this->refuses($l, 'landlord', 'leasing 55 plus', 'Yes');
    }

    public function test_landlord_commercial_manual(): void
    {
        $l = $this->listing('landlord', 'Commercial Property', [
            'cam_nnn_additional_rent_charges' => '$4.10/sq ft/yr', 'commercial_lease_type' => 'NNN',
            'signage_rights' => 'Pylon sign available', 'minimum_leaseable' => '3,200', 'bedrooms' => '3',
        ]);
        $this->answers($l, 'landlord', 'cam', '$4.10/sq ft/yr');
        $this->answers($l, 'landlord', 'lease type', 'NNN');
        $this->answers($l, 'landlord', 'signage', 'Pylon sign available');
        $this->answers($l, 'landlord', 'square footage', '3,200');
        $this->refuses($l, 'landlord', 'how many bedrooms', '3');
    }

    // ── Buyer ────────────────────────────────────────────────────────────────

    public function test_buyer_every_type(): void
    {
        $r = $this->listing('buyer', 'Residential', ['bedrooms' => '3', 'bathrooms' => '2', 'carport_needed' => 'Yes', 'pre_approved' => 'Yes', 'interest_rate' => '6.1']);
        $this->answers($r, 'buyer', 'how many bathrooms', '2');
        $this->answers($r, 'buyer', 'carport', 'Yes');
        $this->refuses($r, 'buyer', 'pre approved', 'Yes');                 // qualification (D2)
        $this->refuses($r, 'buyer', 'interest rate', '6.1');

        $i = $this->listing('buyer', 'Income', ['unit_size' => '4', 'minimum_cap_rate' => '8']);
        $this->answers($i, 'buyer', 'acceptable number of units', '4');
        $this->answers($i, 'buyer', 'minimum cap rate', '8%');

        $c = $this->listing('buyer', 'Commercial', ['garage_parking_spaces' => 'Yes', 'bedrooms' => '3']);
        $this->answers($c, 'buyer', 'garage parking features needed', 'Yes');
        $this->refuses($c, 'buyer', 'how many bedrooms', '3');

        $b = $this->listing('buyer', 'Business', ['real_estate_purchase' => 'Real estate must be included']);
        $this->answers($b, 'buyer', 'business & real estate purchase requirements', 'Real estate must be included');

        $v = $this->listing('buyer', 'Vacant Land', ['flood_zone_tolerance' => 'X only', 'hoa_acceptance' => 'No HOA']);
        $this->answers($v, 'buyer', 'flood zone preference', 'X only');
        $this->answers($v, 'buyer', 'hoa acceptance', 'No HOA');
    }

    // ── Tenant ───────────────────────────────────────────────────────────────

    public function test_tenant_every_type(): void
    {
        $r = $this->listing('tenant', 'Residential Property', ['bathrooms' => '2', 'carport_needed' => 'Yes', 'smoking_preference' => 'Smoker']);
        $this->answers($r, 'tenant', 'how many bathrooms', '2');
        $this->answers($r, 'tenant', 'carport', 'Yes');
        $this->refuses($r, 'tenant', 'smoking preference', 'Smoker');       // about the applicant: private

        $c = $this->listing('tenant', 'Commercial Property', ['cam_nnn_preference' => 'Modified gross preferred', 'intended_business_use' => 'Dental office', 'bedrooms' => '3']);
        $this->answers($c, 'tenant', 'cam nnn preference', 'Modified gross preferred');
        $this->answers($c, 'tenant', 'intended business use', 'Dental office');
        $this->refuses($c, 'tenant', 'how many bedrooms', '3');
    }

    // ── MLS-imported (the committed Bridge fixtures through the real quick import) ─────────

    public function test_mls_residential_bathrooms_are_the_canonical_total(): void
    {
        $l = MlsImportFixture::import($this, 'residential', 'seller');
        $this->assertSame('1.5', $l->info('bathrooms'), 'import stores the canonical total, not the rounded integer');
        $this->answers($l, 'seller', 'how many bathrooms', '1.5 bathrooms');
        $this->answers($l, 'seller', 'full bathrooms', 'Full Bathrooms: 1.');
        $this->answers($l, 'seller', 'hoa fee', '726');
        $this->refuses($l, 'seller', 'how big is the property');
    }

    public function test_the_same_fact_behaves_the_same_manual_or_mls(): void
    {
        $manual = $this->listing('seller', 'Residential', ['bathrooms' => '1.5']);
        $mls    = MlsImportFixture::import($this, 'residential', 'seller');
        $this->assertSame($this->ask($manual, 'seller', 'how many bathrooms'), $this->ask($mls, 'seller', 'how many bathrooms'));
    }

    public function test_mls_income_answers_what_the_page_shows(): void
    {
        $l = MlsImportFixture::import($this, 'income', 'seller');
        $this->answers($l, 'seller', 'how many bedrooms', '2 bedrooms');
        $this->answers($l, 'seller', 'how many bathrooms', '1.5 bathrooms');
        $this->answers($l, 'seller', 'how many units', 'Total Units');
    }

    public function test_mls_commercial_business_land_and_leases(): void
    {
        $com = MlsImportFixture::import($this, 'commercial_sale', 'seller');
        $this->assertNull($com->info('bathrooms') ?: null, 'a feed placeholder 0 is never stored as a fact');
        $this->refuses($com, 'seller', 'how many bathrooms');
        $this->answers($com, 'seller', 'frontage', 'Road Frontage');
        $this->answers($com, 'seller', 'building size', '3536'); // printed as stored, as the page prints it

        $bus = MlsImportFixture::import($this, 'business_opportunity', 'seller');
        $this->answers($bus, 'seller', 'business type', 'Agriculture');

        $land = MlsImportFixture::import($this, 'vacant_land', 'seller');
        $this->answers($land, 'seller', 'frontage', 'Road Frontage');
        $this->refuses($land, 'seller', 'how many bedrooms');

        $res = MlsImportFixture::import($this, 'residential_lease', 'landlord');
        $this->answers($res, 'landlord', 'how many bathrooms', '1.5 bathrooms');
        $this->answers($res, 'landlord', 'rent frequency', 'monthly');

        $cl = MlsImportFixture::import($this, 'commercial_lease', 'landlord');
        $this->answers($cl, 'landlord', 'square footage', '2,750');
    }

    public function test_an_older_import_page_and_ask_ai_agree_on_the_component_total_without_reimport(): void
    {
        // The rounded "2" an import wrote before BathroomTotal existed, beside Full 1 / Half 1.
        $l = MlsImportFixture::import($this, 'residential', 'seller');
        $l->saveMeta('bathrooms', '2');
        $this->assertSame('2', $l->fresh()->info('bathrooms'), 'the stored row is not rewritten');

        $page = strip_tags($this->get(route('offer.listing.seller.view', $l->id))->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/Bathrooms\s*1\.5\b/', $page, 'the page shows the component total');
        $this->assertDoesNotMatchRegularExpression('/Bathrooms\s*2\b/', $page, 'the page no longer shows the rounded total');
        $this->answers($l, 'seller', 'how many bathrooms', '1.5 bathrooms');
        $this->onCard($l, 'seller', 'seller_bathrooms');

        // A missing stored total reads the same way.
        $l->saveMeta('bathrooms', '');
        $this->answers($l, 'seller', 'how many bathrooms', '1.5 bathrooms');

        // Landlord: same projection, same answer.
        $r = MlsImportFixture::import($this, 'residential_lease', 'landlord');
        $r->saveMeta('bathrooms', '2');
        $rp = strip_tags($this->get(route('offer.listing.landlord.view', $r->id))->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/Bathrooms\s*1\.5\b/', $rp);
        $this->answers($r, 'landlord', 'how many bathrooms', '1.5 bathrooms');

        // A manual listing (no MLS components) keeps exactly what the owner entered.
        $m = $this->listing('seller', 'Residential', ['bathrooms' => '2']);
        $this->answers($m, 'seller', 'how many bathrooms', '2 bathrooms');
    }

    public function test_seller_assignment_fee_is_answered_as_the_page_states_it(): void
    {
        $l = $this->listing('seller', 'Residential', [
            'sale_provision' => ['Assignment Contract'], 'sale_provision_assignment' => 'Yes',
            'assignment_fee_type' => '%', 'assignment_fee_amount' => '3',
        ]);
        $this->answers($l, 'seller', 'assignment fee amount', 'Assignment Fee Amount: 3%.');
        $this->answers($l, 'seller', 'assignment contract fee to broker', 'Percentage of Contract Assignment Value');

        $stale = $this->listing('seller', 'Residential', ['sale_provision' => ['Short Sale'], 'assignment_fee_type' => '$', 'assignment_fee_amount' => '5000']);
        $this->refuses($stale, 'seller', 'assignment fee amount', '5,000');

        // The option is still selected but the seller answered "No": the page hides the fee
        // rows (its $isAssigning rule), and ConditionalTerms closes them the same way.
        $declined = $this->listing('seller', 'Residential', [
            'sale_provision' => ['Assignment Contract'], 'sale_provision_assignment' => 'No',
            'assignment_fee_type' => '$', 'assignment_fee_amount' => '7000',
        ]);
        $page = strip_tags($this->get(route('offer.listing.seller.view', $declined->id))->assertOk()->getContent());
        $this->assertStringNotContainsString('Assignment Fee Amount', $page);
        $this->refuses($declined, 'seller', 'assignment fee amount', '7,000');
    }

    public function test_seller_business_real_estate_purchase_is_on_the_page_without_seller_financing(): void
    {
        // It used to print only inside the Seller Financing block. Now it is a Business row.
        $l = $this->listing('seller', 'Business', ['real_estate_purchase' => 'Business Only', 'offered_financing' => ['Cash']]);
        $page = strip_tags($this->get(route('offer.listing.seller.view', $l->id))->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/Business &(amp;)? Real Estate Purchase\s*Business Only/', $page);
        $this->assertStringNotContainsString('Real Estate Purchase Included', $page);
        $this->onCard($l, 'seller', 'seller_field_real_estate_purchase');
        $this->answers($l, 'seller', 'business & real estate purchase', 'Business Only');
        $this->answers($l, 'seller', 'business and real estate purchase', 'Business Only');
        $this->answers($l, 'seller', 'is the real estate included', 'Business Only');

        // A stale value on a non-Business listing: neither the page nor Ask AI states it.
        $res = $this->listing('seller', 'Residential', ['real_estate_purchase' => 'Business Only']);
        $rp = strip_tags($this->get(route('offer.listing.seller.view', $res->id))->assertOk()->getContent());
        $this->assertStringNotContainsString('Business Only', $rp);
        $this->refuses($res, 'seller', 'business and real estate purchase', 'Business Only');
    }

    public function test_buyer_contingency_periods_home_sale_and_possession_follow_the_page(): void
    {
        $l = $this->listing('buyer', 'Residential', [
            'inspection_contingency_buyer' => 'Yes', 'inspection_contingency_period' => '10',
            'appraisal_contingency_buyer' => 'Negotiable', 'appraisal_contingency_days' => '21',
            'financing_contingency_buyer' => 'No', 'financing_contingency_period' => '30',
            'home_sale_contingency' => 'Yes', 'home_sale_contingency_period' => '45', 'home_sale_contingency_address' => '9 Private Road',
            'possession_preference' => 'Seller Rent Back', 'possession_details' => 'Up to 30 days',
        ]);
        $this->answers($l, 'buyer', 'inspection contingency', 'Included');          // legacy "Yes", worded as the page
        $this->answers($l, 'buyer', 'inspection contingency period', '10 days');
        $this->answers($l, 'buyer', 'appraisal contingency period', '21 days');
        // Waived: the page prints no period, so none is stated (the contingency itself may answer).
        $this->assertStringNotContainsString('30 days', $this->ask($l, 'buyer', 'financing contingency period')['answer']);
        $this->answers($l, 'buyer', 'home sale contingency period', '45 days');
        $this->refuses($l, 'buyer', 'home sale contingency address', '9 Private Road'); // the buyer's own home: private
        $this->answers($l, 'buyer', 'possession details', 'Up to 30 days');

        $other = $this->listing('buyer', 'Residential', ['possession_preference' => 'Other', 'possession_preference_other' => 'Two weeks after closing', 'possession_details' => 'hidden']);
        $this->answers($other, 'buyer', 'possession preference', 'Two weeks after closing');
        $this->refuses($other, 'buyer', 'possession details', 'hidden');

        $notIncluded = $this->listing('buyer', 'Residential', ['home_sale_contingency' => 'No', 'home_sale_contingency_period' => '45']);
        $this->refuses($notIncluded, 'buyer', 'home sale contingency', 'Not Included'); // the page shows no block
        $this->refuses($notIncluded, 'buyer', 'home sale contingency period', '45');
    }

    public function test_tenant_carport_and_garage_stay_distinct_on_the_page_and_in_ask_ai(): void
    {
        $l = $this->listing('tenant', 'Residential Property', ['carport_needed' => 'No', 'garage_needed' => 'Yes']);
        $page = strip_tags($this->get(route('offer.listing.tenant.view', $l->id))->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/Carport Needed:\s*No/', $page);
        $this->assertMatchesRegularExpression('/Garage Needed:\s*Yes/', $page);

        // A carport requirement alone still renders its section (the gate names it now).
        $only = $this->listing('tenant', 'Residential Property', ['carport_needed' => 'Yes']);
        $this->assertMatchesRegularExpression('/Carport Needed:\s*Yes/', strip_tags($this->get(route('offer.listing.tenant.view', $only->id))->assertOk()->getContent()));
        $this->answers($l, 'tenant', 'carport', 'Carport Needed: No.');
        $this->answers($l, 'tenant', 'garage', 'Garage Needed: Yes.');
    }

    public function test_a_listing_the_feed_forbids_displaying_answers_no_mls_fact_to_a_non_owner(): void
    {
        $l = MlsImportFixture::import($this, 'residential', 'seller', ['IDXParticipationYN' => false]);
        $l->saveMeta('bathrooms', '');
        $this->refuses($l, 'seller', 'full bathrooms', 'Full Bathrooms');
        $this->refuses($l, 'seller', 'how many bathrooms', '1.5');
        $this->answers($l, 'seller', 'how many bathrooms', '1.5 bathrooms', Scope::SCOPE_OWNER);
    }

    public function test_ambiguous_wording_is_refused_not_guessed(): void
    {
        // Two cash-percentage rows (crypto and NFT) share the page wording "cash % of purchase price".
        $l = $this->listing('seller', 'Residential', [
            'offered_financing' => ['Cryptocurrency', 'Non-Fungible Token (NFT)'],
            'cash_percentage_crypto' => '40', 'cash_percentage_nft' => '30',
        ]);
        $this->refuses($l, 'seller', 'cash % of purchase price', '%');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function listing(string $role, string $type, array $meta): object
    {
        return P::makeListing($role, $type, $meta);
    }

    private function ask(object $listing, string $role, string $question, string $scope = Scope::SCOPE_PUBLIC): array
    {
        $r = app(AskAiRunnerV2Service::class)->run($role, $listing->id, $question, ['viewer_scope' => $scope]);

        return ['status' => (string) ($r['status'] ?? ''), 'answer' => (string) ($r['final_response']['answer'] ?? '')];
    }

    private function answers(object $listing, string $role, string $question, string $expect, string $scope = Scope::SCOPE_PUBLIC): void
    {
        $r = $this->ask($listing, $role, $question, $scope);
        $this->assertSame('ready', $r['status'], "{$role} '{$question}' ({$scope}) was not answered: {$r['answer']}");
        $this->assertStringContainsString($expect, $r['answer'], "{$role} '{$question}' ({$scope})");
    }

    private function refuses(object $listing, string $role, string $question, ?string $mustNotContain = null, string $scope = Scope::SCOPE_PUBLIC): void
    {
        $r = $this->ask($listing, $role, $question, $scope);
        if ($mustNotContain !== null) {
            $this->assertStringNotContainsString($mustNotContain, $r['answer'], "{$role} '{$question}' ({$scope}) published it.");
        }
        $this->assertNotSame('ready', $r['status'], "{$role} '{$question}' ({$scope}) should refuse, answered: {$r['answer']}");
    }

    private function onCard(object $listing, string $role, string $id): void
    {
        $ids = array_column(app(AskAiPublicPropertyQuestionService::class)->forStoredListing($role, $listing->id, false), 'id');
        $this->assertContains($id, $ids);
    }
}
