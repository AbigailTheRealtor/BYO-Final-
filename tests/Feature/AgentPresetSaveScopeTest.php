<?php

namespace Tests\Feature;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentPresetCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for AgentPresetController save scope propagation.
 *
 * Covers:
 *  §1 — 'current_preset' scope: saves only the current preset, leaves all others unchanged.
 *  §2 — 'current_role' scope: propagates all fields (compensation, services, agreement terms)
 *        to every other property-type preset for the same role, for all four roles.
 *  §3 — 'current_role' cross-role isolation: saving "All Buyer presets" never touches
 *        Seller, Landlord, or Tenant records (and vice versa).
 *  §4 — 'current_role' file-upload path exclusion: presentation_upload_path and
 *        business_card_upload_path are NOT propagated to other presets.
 *  §5 — 'all_roles' scope: only PROFILE_FIELDS propagate; compensation/services are excluded.
 */
class AgentPresetSaveScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const SAVE_ROUTE = 'agent.presets.save';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(): User
    {
        static $counter = 0;
        $counter++;
        $shortId = str_pad((string) $counter, 12, 'x', STR_PAD_LEFT);
        return User::factory()->asAgent()->create(['short_id' => $shortId]);
    }

    /**
     * Pre-populate a preset record so scope propagation has a target to update.
     */
    private function seedPreset(User $agent, string $role, string $propertyType, array $data = []): AgentDefaultProfile
    {
        return AgentDefaultProfile::create([
            'user_id'      => $agent->id,
            'role_type'    => $role,
            'property_type'=> $propertyType,
            'profile_data' => $data,
        ]);
    }

    /**
     * Minimal valid form payload for the save action.
     * Only includes the fields exercised by the test; all others default to ''.
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'profile_save_scope' => 'current_preset',
            'services'           => [],
            'other_services'     => [],
            'bio'                => '',
        ], $overrides);
    }

    private function postSave(User $agent, string $role, string $propertyType, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($agent)
            ->post(route(self::SAVE_ROUTE, ['role' => $role, 'propertyType' => $propertyType]), $payload);
    }

    private function freshData(User $agent, string $role, string $propertyType): array
    {
        return AgentDefaultProfile::findForAgent($agent->id, $role, $propertyType)?->profile_data ?? [];
    }

    // =========================================================================
    // §1 — 'current_preset' scope: only current preset is written
    // =========================================================================

    public function test_current_preset_scope_does_not_touch_other_property_types(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'buyer', 'residential', ['bio' => 'original bio', 'commission_structure' => 'old comp']);
        $this->seedPreset($agent, 'buyer', 'income',      ['bio' => 'income bio',   'commission_structure' => 'income comp']);

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope'   => 'current_preset',
            'bio'                  => 'new bio',
            'commission_structure' => 'new comp',
        ]));

        // income preset must be untouched
        $incomeData = $this->freshData($agent, 'buyer', 'income');
        $this->assertSame('income bio',   $incomeData['bio']);
        $this->assertSame('income comp',  $incomeData['commission_structure']);
    }

    // =========================================================================
    // §2 — 'current_role' scope: all fields propagate to same-role presets
    // =========================================================================

    /**
     * @dataProvider buyerPropertyTypesProvider
     */
    public function test_current_role_propagates_all_fields_to_buyer_property_types(string $targetPropertyType): void
    {
        $agent = $this->makeAgent();

        // Seed residential (the one we save from) and the target preset.
        $this->seedPreset($agent, 'buyer', 'residential',      ['bio' => 'old', 'commission_structure' => 'old']);
        $this->seedPreset($agent, 'buyer', $targetPropertyType, ['bio' => 'old target']);

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope'   => 'current_role',
            'bio'                  => 'propagated bio',
            'commission_structure' => '3%',
            'purchase_fee_type'    => 'percentage',
            'protection_period'    => '90',
            'agency_agreement_timeframe' => '6_months',
            'services'             => ['Help buyer find home'],
            'other_services'       => ['Custom service A'],
        ]));

        $data = $this->freshData($agent, 'buyer', $targetPropertyType);

        $this->assertSame('propagated bio', $data['bio'],                  "bio not propagated to buyer/{$targetPropertyType}");
        $this->assertSame('3%',             $data['commission_structure'], "commission_structure not propagated to buyer/{$targetPropertyType}");
        $this->assertSame('percentage',     $data['purchase_fee_type'],    "purchase_fee_type not propagated to buyer/{$targetPropertyType}");
        $this->assertSame('90',             $data['protection_period'],    "protection_period not propagated to buyer/{$targetPropertyType}");
        $this->assertSame('6_months',       $data['agency_agreement_timeframe'], "agency_agreement_timeframe not propagated to buyer/{$targetPropertyType}");
        $this->assertSame(['Help buyer find home'], $data['services'],     "services not propagated to buyer/{$targetPropertyType}");
        $this->assertSame(['Custom service A'],     $data['other_services'], "other_services not propagated to buyer/{$targetPropertyType}");
    }

    public static function buyerPropertyTypesProvider(): array
    {
        return [
            'buyer/income'      => ['income'],
            'buyer/commercial'  => ['commercial'],
            'buyer/business'    => ['business'],
            'buyer/vacant_land' => ['vacant_land'],
        ];
    }

    /**
     * @dataProvider sellerPropertyTypesProvider
     */
    public function test_current_role_propagates_all_fields_to_seller_property_types(string $targetPropertyType): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'seller', 'residential',      ['bio' => 'old']);
        $this->seedPreset($agent, 'seller', $targetPropertyType, ['bio' => 'old target']);

        $this->postSave($agent, 'seller', 'residential', $this->basePayload([
            'profile_save_scope'   => 'current_role',
            'bio'                  => 'seller bio',
            'commission_structure' => '2.5%',
            'services'             => ['List on MLS'],
        ]));

        $data = $this->freshData($agent, 'seller', $targetPropertyType);

        $this->assertSame('seller bio', $data['bio'],                  "bio not propagated to seller/{$targetPropertyType}");
        $this->assertSame('2.5%',       $data['commission_structure'], "commission_structure not propagated to seller/{$targetPropertyType}");
        $this->assertSame(['List on MLS'], $data['services'],          "services not propagated to seller/{$targetPropertyType}");
    }

    public static function sellerPropertyTypesProvider(): array
    {
        return [
            'seller/income'      => ['income'],
            'seller/commercial'  => ['commercial'],
            'seller/business'    => ['business'],
            'seller/vacant_land' => ['vacant_land'],
        ];
    }

    public function test_current_role_propagates_all_fields_to_landlord_commercial(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'landlord', 'residential', ['bio' => 'old']);
        $this->seedPreset($agent, 'landlord', 'commercial',  ['bio' => 'old commercial']);

        $this->postSave($agent, 'landlord', 'residential', $this->basePayload([
            'profile_save_scope'         => 'current_role',
            'bio'                        => 'landlord bio',
            'tenant_broker_flat_fee'     => '1500',
            'renewal_fee_type'           => 'percentage',
            'services'                   => ['Market rental unit'],
            'other_services'             => ['Extra landlord service'],
        ]));

        $data = $this->freshData($agent, 'landlord', 'commercial');

        $this->assertSame('landlord bio',      $data['bio']);
        $this->assertSame('1500',              $data['tenant_broker_flat_fee']);
        $this->assertSame('percentage',        $data['renewal_fee_type']);
        $this->assertSame(['Market rental unit'],     $data['services']);
        $this->assertSame(['Extra landlord service'], $data['other_services']);
    }

    public function test_current_role_propagates_all_fields_to_tenant_commercial(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'tenant', 'residential', ['bio' => 'old']);
        $this->seedPreset($agent, 'tenant', 'commercial',  ['bio' => 'old commercial']);

        $this->postSave($agent, 'tenant', 'residential', $this->basePayload([
            'profile_save_scope'                => 'current_role',
            'bio'                               => 'tenant bio',
            'tenant_broker_commission_structure'=> 'gross_lease',
            'protection_period'                 => '60',
            'services'                          => ['Find commercial space'],
        ]));

        $data = $this->freshData($agent, 'tenant', 'commercial');

        $this->assertSame('tenant bio',    $data['bio']);
        $this->assertSame('gross_lease',   $data['tenant_broker_commission_structure']);
        $this->assertSame('60',            $data['protection_period']);
        $this->assertSame(['Find commercial space'], $data['services']);
    }

    /**
     * Fields whose request key differs from their stored profile_data key
     * (reviews_links_raw → reviews_links, website_link_raw → website_link,
     * social_media_raw → social_media) must still propagate under current_role.
     *
     * The controller runs these through splitLines() before storing them, so
     * propagation must use the stored key name, not the raw request key name.
     */
    public function test_current_role_propagates_transformed_link_fields(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'buyer', 'residential', [
            'reviews_links' => ['https://review1.example.com'],
            'website_link'  => ['https://buyeragent.example.com'],
            'social_media'  => ['https://instagram.com/buyeragent'],
        ]);
        $this->seedPreset($agent, 'buyer', 'income', [
            'reviews_links' => ['https://old-review.example.com'],
            'website_link'  => ['https://old-site.example.com'],
            'social_media'  => ['https://old-social.example.com'],
        ]);

        // The form submits these as *_raw (newline-separated strings).
        // The controller runs splitLines() to produce the stored array.
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope'  => 'current_role',
            'reviews_links_raw'   => "https://review1.example.com\nhttps://review2.example.com",
            'website_link_raw'    => 'https://buyeragent.example.com',
            'social_media_raw'    => 'https://instagram.com/buyeragent',
        ]));

        $incomeData = $this->freshData($agent, 'buyer', 'income');

        $this->assertSame(
            ['https://review1.example.com', 'https://review2.example.com'],
            $incomeData['reviews_links'],
            'reviews_links (from reviews_links_raw) must propagate under current_role'
        );
        $this->assertSame(
            ['https://buyeragent.example.com'],
            $incomeData['website_link'],
            'website_link (from website_link_raw) must propagate under current_role'
        );
        $this->assertSame(
            ['https://instagram.com/buyeragent'],
            $incomeData['social_media'],
            'social_media (from social_media_raw) must propagate under current_role'
        );
    }

    // =========================================================================
    // §3 — 'current_role' cross-role isolation
    // =========================================================================

    public function test_saving_all_buyer_presets_does_not_touch_seller_landlord_tenant(): void
    {
        $agent = $this->makeAgent();

        // Seed presets for all roles
        $this->seedPreset($agent, 'buyer',    'residential', ['bio' => 'buyer orig',    'commission_structure' => 'buyer comp']);
        $this->seedPreset($agent, 'buyer',    'income',      ['bio' => 'buyer income']);
        $this->seedPreset($agent, 'seller',   'residential', ['bio' => 'seller orig',   'commission_structure' => 'seller comp']);
        $this->seedPreset($agent, 'landlord', 'residential', ['bio' => 'landlord orig', 'commission_structure' => 'landlord comp']);
        $this->seedPreset($agent, 'tenant',   'residential', ['bio' => 'tenant orig',   'commission_structure' => 'tenant comp']);

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope'   => 'current_role',
            'bio'                  => 'buyer updated',
            'commission_structure' => 'buyer new comp',
        ]));

        // Other roles must be completely untouched
        $sellerData   = $this->freshData($agent, 'seller',   'residential');
        $landlordData = $this->freshData($agent, 'landlord', 'residential');
        $tenantData   = $this->freshData($agent, 'tenant',   'residential');

        $this->assertSame('seller orig',    $sellerData['bio'],               'seller bio was modified');
        $this->assertSame('seller comp',    $sellerData['commission_structure'], 'seller comp was modified');
        $this->assertSame('landlord orig',  $landlordData['bio'],             'landlord bio was modified');
        $this->assertSame('landlord comp',  $landlordData['commission_structure'], 'landlord comp was modified');
        $this->assertSame('tenant orig',    $tenantData['bio'],               'tenant bio was modified');
        $this->assertSame('tenant comp',    $tenantData['commission_structure'], 'tenant comp was modified');

        // But buyer/income must have been updated
        $buyerIncomeData = $this->freshData($agent, 'buyer', 'income');
        $this->assertSame('buyer updated',   $buyerIncomeData['bio']);
        $this->assertSame('buyer new comp',  $buyerIncomeData['commission_structure']);
    }

    public function test_saving_all_seller_presets_does_not_touch_buyer_records(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'seller', 'residential', ['bio' => 'seller orig', 'commission_structure' => 'seller comp']);
        $this->seedPreset($agent, 'seller', 'income',      ['bio' => 'seller income old']);
        $this->seedPreset($agent, 'buyer',  'residential', ['bio' => 'buyer orig',  'commission_structure' => 'buyer comp']);

        $this->postSave($agent, 'seller', 'residential', $this->basePayload([
            'profile_save_scope'   => 'current_role',
            'bio'                  => 'seller updated',
            'commission_structure' => 'seller new comp',
        ]));

        $buyerData = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame('buyer orig', $buyerData['bio'],               'buyer bio was modified by seller scope save');
        $this->assertSame('buyer comp', $buyerData['commission_structure'], 'buyer comp was modified by seller scope save');

        // seller/income must have been updated
        $sellerIncomeData = $this->freshData($agent, 'seller', 'income');
        $this->assertSame('seller updated', $sellerIncomeData['bio']);
    }

    public function test_saving_all_landlord_presets_does_not_touch_tenant_records(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'landlord', 'residential', ['bio' => 'landlord orig']);
        $this->seedPreset($agent, 'landlord', 'commercial',  ['bio' => 'landlord comm old']);
        $this->seedPreset($agent, 'tenant',   'residential', ['bio' => 'tenant orig', 'protection_period' => '60']);

        $this->postSave($agent, 'landlord', 'residential', $this->basePayload([
            'profile_save_scope' => 'current_role',
            'bio'                => 'landlord updated',
            'protection_period'  => '30',
        ]));

        $tenantData = $this->freshData($agent, 'tenant', 'residential');
        $this->assertSame('tenant orig', $tenantData['bio'],             'tenant bio was modified by landlord scope save');
        $this->assertSame('60',          $tenantData['protection_period'], 'tenant protection_period was modified by landlord scope save');
    }

    // =========================================================================
    // §4 — 'current_role' file-upload path exclusion
    // =========================================================================

    public function test_current_role_does_not_propagate_file_upload_paths(): void
    {
        $agent = $this->makeAgent();

        // The target preset already has its own file paths stored.
        $this->seedPreset($agent, 'buyer', 'residential', [
            'bio'                        => 'source bio',
            'presentation_upload_path'   => 'agent-offer-presets/1/presentation_source.pdf',
            'business_card_upload_path'  => 'agent-offer-presets/1/card_source.jpg',
        ]);
        $this->seedPreset($agent, 'buyer', 'income', [
            'bio'                        => 'income bio',
            'presentation_upload_path'   => 'agent-offer-presets/1/presentation_income.pdf',
            'business_card_upload_path'  => 'agent-offer-presets/1/card_income.jpg',
        ]);

        // Save residential with scope=current_role; no new file uploaded, so the
        // controller carries the existing path into $profileData via $existingData.
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope' => 'current_role',
            'bio'                => 'updated bio',
        ]));

        // income preset must keep its own upload paths, not receive the source paths.
        $incomeData = $this->freshData($agent, 'buyer', 'income');
        $this->assertSame('updated bio', $incomeData['bio'], 'bio should have propagated');
        $this->assertSame(
            'agent-offer-presets/1/presentation_income.pdf',
            $incomeData['presentation_upload_path'],
            'presentation_upload_path must not be overwritten by scope propagation'
        );
        $this->assertSame(
            'agent-offer-presets/1/card_income.jpg',
            $incomeData['business_card_upload_path'],
            'business_card_upload_path must not be overwritten by scope propagation'
        );
    }

    /**
     * An explicitly submitted field (even when cleared to an empty value) MUST propagate
     * to other same-role presets. This confirms that presence-aware propagation sends
     * any field that was part of the HTTP request — only truly-absent keys are skipped.
     *
     * Note: The app's ConvertEmptyStringsToNull middleware converts empty-string form
     * values to null before they reach the controller. So a field submitted as '' arrives
     * in the controller as null, propagates as null, and reads back as null. The key
     * assertion here is that the value WAS changed from its original (non-null) state,
     * confirming propagation occurred.
     */
    public function test_current_role_propagates_intentionally_cleared_scalar_field(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'buyer', 'residential', ['bio' => 'old bio', 'commission_structure' => 'old comp']);
        $this->seedPreset($agent, 'buyer', 'income',      ['bio' => 'income bio', 'commission_structure' => 'income comp']);

        // Submit with bio='' (explicitly cleared) and commission_structure='' (explicitly
        // cleared). Both keys are present in the HTTP request body (even though empty), so
        // both must propagate to same-role presets. ConvertEmptyStringsToNull middleware
        // converts '' to null before the controller sees it, so the propagated value is null.
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope'   => 'current_role',
            'bio'                  => '',
            'commission_structure' => '',
        ]));

        $incomeData = $this->freshData($agent, 'buyer', 'income');

        // ConvertEmptyStringsToNull converts submitted '' to null; propagation sends null.
        // The critical assertion: the value WAS changed from 'income bio' (confirming
        // the field was propagated), and the stored value is null (not the original string).
        $this->assertNotSame('income bio', $incomeData['bio'] ?? 'NOT_CHANGED',
            'bio was not propagated — original value still present in target preset');
        $this->assertNull($incomeData['bio'],
            'Cleared bio (submitted as empty string → null via middleware) must propagate as null');

        $this->assertNotSame('income comp', $incomeData['commission_structure'] ?? 'NOT_CHANGED',
            'commission_structure was not propagated — original value still present');
        $this->assertNull($incomeData['commission_structure'],
            'Cleared commission_structure must propagate as null');
    }

    /**
     * Regression: empty-string controller defaults must NOT blank out target-preset
     * field values that the source form did not explicitly submit.
     *
     * $profileData is built with request->input($key, '') for every compensation
     * key regardless of which form fields are shown. Propagation must use request
     * key presence, not the full $profileData, to avoid this silent blanking.
     */
    public function test_current_role_does_not_blank_out_target_fields_not_in_source_form(): void
    {
        $agent = $this->makeAgent();

        // buyer/income has compensation fields intentionally set.
        $this->seedPreset($agent, 'buyer', 'residential', ['bio' => 'residential bio']);
        $this->seedPreset($agent, 'buyer', 'income', [
            'bio'                  => 'income bio',
            'commission_structure' => 'income specific comp',
            'lease_fee_flat'       => '1500',
            'protection_period'    => '90',
        ]);

        // Save buyer/residential with scope=current_role but WITHOUT setting
        // commission_structure or the other compensation keys. The controller defaults
        // them to '' in $profileData. These empty strings must NOT propagate.
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope' => 'current_role',
            'bio'                => 'updated residential bio',
            // commission_structure, lease_fee_flat, protection_period intentionally omitted
        ]));

        $incomeData = $this->freshData($agent, 'buyer', 'income');

        // bio propagates because it was explicitly set
        $this->assertSame('updated residential bio', $incomeData['bio'], 'bio should propagate');

        // These fields were not set in the source form — target values must survive
        $this->assertSame('income specific comp', $incomeData['commission_structure'],
            'commission_structure was blanked out by empty-string default propagation');
        $this->assertSame('1500', $incomeData['lease_fee_flat'],
            'lease_fee_flat was blanked out by empty-string default propagation');
        $this->assertSame('90', $incomeData['protection_period'],
            'protection_period was blanked out by empty-string default propagation');
    }

    // =========================================================================
    // §5 — 'all_roles' scope: only PROFILE_FIELDS propagate; compensation excluded
    // =========================================================================

    public function test_all_roles_scope_does_not_propagate_compensation_or_services(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'buyer',  'residential', ['bio' => 'old buyer']);
        $this->seedPreset($agent, 'seller', 'residential', [
            'bio'                  => 'old seller bio',
            'commission_structure' => 'seller original comp',
            'services'             => ['Old seller service'],
        ]);

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope'   => 'all_roles',
            'bio'                  => 'shared bio',
            'commission_structure' => 'buyer new comp',
            'services'             => ['Buyer service propagated'],
        ]));

        $sellerData = $this->freshData($agent, 'seller', 'residential');

        // bio is in PROFILE_FIELDS — must propagate
        $this->assertSame('shared bio', $sellerData['bio'], 'bio should propagate under all_roles scope');

        // compensation and services are NOT in PROFILE_FIELDS — must not propagate
        $this->assertSame('seller original comp', $sellerData['commission_structure'],
            'commission_structure must not propagate under all_roles scope');
        $this->assertSame(['Old seller service'], $sellerData['services'],
            'services must not propagate under all_roles scope');
    }

    public function test_all_roles_scope_does_not_propagate_file_upload_paths(): void
    {
        $agent = $this->makeAgent();

        // presentation_upload_path and business_card_upload_path appear in PROFILE_FIELDS
        // but must still be excluded from all_roles propagation.
        $this->seedPreset($agent, 'buyer', 'residential', [
            'bio'                       => 'buyer bio',
            'presentation_upload_path'  => 'agent-offer-presets/1/pres_buyer.pdf',
            'business_card_upload_path' => 'agent-offer-presets/1/card_buyer.jpg',
        ]);
        $this->seedPreset($agent, 'seller', 'residential', [
            'bio'                       => 'seller bio',
            'presentation_upload_path'  => 'agent-offer-presets/1/pres_seller.pdf',
            'business_card_upload_path' => 'agent-offer-presets/1/card_seller.jpg',
        ]);

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope' => 'all_roles',
            'bio'                => 'shared bio',
        ]));

        $sellerData = $this->freshData($agent, 'seller', 'residential');

        $this->assertSame('shared bio', $sellerData['bio'], 'bio should propagate under all_roles');
        $this->assertSame('agent-offer-presets/1/pres_seller.pdf', $sellerData['presentation_upload_path'],
            'presentation_upload_path must not be overwritten by all_roles propagation');
        $this->assertSame('agent-offer-presets/1/card_seller.jpg', $sellerData['business_card_upload_path'],
            'business_card_upload_path must not be overwritten by all_roles propagation');
    }

    public function test_all_roles_scope_propagates_profile_fields_across_roles(): void
    {
        $agent = $this->makeAgent();

        $this->seedPreset($agent, 'buyer',    'residential', ['bio' => 'old']);
        $this->seedPreset($agent, 'seller',   'residential', ['bio' => 'old seller']);
        $this->seedPreset($agent, 'landlord', 'residential', ['bio' => 'old landlord']);
        $this->seedPreset($agent, 'tenant',   'residential', ['bio' => 'old tenant']);

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'profile_save_scope' => 'all_roles',
            'bio'                => 'global bio',
            'first_name'         => 'Jane',
            'last_name'          => 'Agent',
        ]));

        foreach (['seller', 'landlord', 'tenant'] as $role) {
            $data = $this->freshData($agent, $role, 'residential');
            $this->assertSame('global bio', $data['bio'],       "{$role}/residential bio not propagated by all_roles");
            $this->assertSame('Jane',       $data['first_name'],"{$role}/residential first_name not propagated by all_roles");
            $this->assertSame('Agent',      $data['last_name'], "{$role}/residential last_name not propagated by all_roles");
        }
    }
}
