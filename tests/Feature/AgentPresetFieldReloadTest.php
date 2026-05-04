<?php

namespace Tests\Feature;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for Agent Preset edit-form field save and reload correctness.
 *
 * Covers:
 *  §1 — Select-field reload: select fields (availability_status, is_full_time,
 *        evenings_available, weekends_available, preferred_contact_method) save their
 *        submitted value and the controller returns the correct value in profile_data
 *        so that @selected renders the right option on reload.
 *  §2 — Compensation select reload: commission_structure and purchase_fee_type save
 *        and reload correctly.
 *  §3 — transactions_last_12_months null/zero correctness: empty submission stores
 *        null, zero submission stores 0, and a positive integer stores correctly.
 *  §4 — Text-input fields save and reload without corruption.
 */
class AgentPresetFieldReloadTest extends TestCase
{
    use DatabaseTransactions;

    private const SAVE_ROUTE = 'agent.presets.save';
    private const EDIT_ROUTE = 'agent.presets.edit';

    private function makeAgent(): User
    {
        static $counter = 0;
        $counter++;
        $shortId = str_pad((string) $counter, 12, 'a', STR_PAD_LEFT);
        return User::factory()->asAgent()->create(['short_id' => $shortId]);
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

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'profile_save_scope' => 'current_preset',
            'services'           => [],
            'other_services'     => [],
            'bio'                => '',
        ], $overrides);
    }

    // =========================================================================
    // §1 — Availability & Service Style select fields save and reload
    // =========================================================================

    /**
     * @dataProvider availabilityFieldsProvider
     */
    public function test_availability_select_fields_save_and_reload(string $field, string $value): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            $field => $value,
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame($value, $data[$field], "Field '{$field}' did not save correctly");
    }

    public static function availabilityFieldsProvider(): array
    {
        return [
            'is_full_time Yes'                        => ['is_full_time',           'Yes'],
            'is_full_time No'                         => ['is_full_time',           'No'],
            'availability_status Actively Taking'     => ['availability_status',    'Actively Taking New Clients'],
            'availability_status Limited'             => ['availability_status',    'Limited Availability'],
            'availability_status By Referral Only'    => ['availability_status',    'By Referral Only'],
            'evenings_available Yes'                  => ['evenings_available',     'Yes'],
            'evenings_available No'                   => ['evenings_available',     'No'],
            'weekends_available Yes'                  => ['weekends_available',     'Yes'],
            'weekends_available No'                   => ['weekends_available',     'No'],
            'preferred_contact_method Phone'          => ['preferred_contact_method', 'Phone Call'],
            'preferred_contact_method Text'           => ['preferred_contact_method', 'Text Message'],
            'preferred_contact_method Email'          => ['preferred_contact_method', 'Email'],
        ];
    }

    public function test_availability_select_fields_save_empty_when_cleared(): void
    {
        $agent = $this->makeAgent();

        // First save with a value
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'availability_status' => 'Actively Taking New Clients',
            'is_full_time'        => 'Yes',
        ]));

        // Then clear them — controller stores null or '' (ConvertEmptyStringsToNull
        // middleware converts '' to null in the full HTTP stack; either is non-truthy
        // and correctly prevents @selected from firing on the previous option).
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'availability_status' => '',
            'is_full_time'        => '',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertEmpty($data['availability_status'] ?? null, 'Cleared availability_status must be empty/null');
        $this->assertEmpty($data['is_full_time'] ?? null, 'Cleared is_full_time must be empty/null');
    }

    // =========================================================================
    // §2 — Broker Compensation select fields save and reload
    // =========================================================================

    public function test_commission_structure_saves_and_reloads_for_buyer(): void
    {
        $agent = $this->makeAgent();

        $commValue = "Buyer Pays Out-of-Pocket";
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'commission_structure' => $commValue,
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame($commValue, $data['commission_structure']);
    }

    public function test_purchase_fee_type_saves_and_reloads_for_buyer(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'purchase_fee_type' => 'Flat Fee',
            'purchase_fee_flat' => '5000',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame('Flat Fee', $data['purchase_fee_type']);
        $this->assertSame('5000',     $data['purchase_fee_flat']);
    }

    public function test_brokerage_relationship_saves_and_reloads(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'brokerage_relationship' => 'Transaction Broker Representation',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame('Transaction Broker Representation', $data['brokerage_relationship']);
    }

    public function test_agency_agreement_timeframe_saves_and_reloads(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'agency_agreement_timeframe' => '6 Months',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame('6 Months', $data['agency_agreement_timeframe']);
    }

    public function test_compensation_selects_reload_for_seller_role(): void
    {
        $agent = $this->makeAgent();

        $commValue = "Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission";
        $this->postSave($agent, 'seller', 'residential', $this->basePayload([
            'commission_structure'    => $commValue,
            'availability_status'    => 'Limited Availability',
            'is_full_time'           => 'No',
        ]));

        $data = $this->freshData($agent, 'seller', 'residential');
        $this->assertSame($commValue,              $data['commission_structure']);
        $this->assertSame('Limited Availability',  $data['availability_status']);
        $this->assertSame('No',                    $data['is_full_time']);
    }

    public function test_availability_selects_reload_for_landlord_role(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'landlord', 'residential', $this->basePayload([
            'availability_status' => 'Actively Taking New Clients',
            'weekends_available'  => 'Yes',
            'is_full_time'        => 'Yes',
        ]));

        $data = $this->freshData($agent, 'landlord', 'residential');
        $this->assertSame('Actively Taking New Clients', $data['availability_status']);
        $this->assertSame('Yes',                         $data['weekends_available']);
        $this->assertSame('Yes',                         $data['is_full_time']);
    }

    public function test_availability_selects_reload_for_tenant_role(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'tenant', 'residential', $this->basePayload([
            'availability_status'     => 'By Referral Only',
            'evenings_available'      => 'No',
            'preferred_contact_method'=> 'Email',
        ]));

        $data = $this->freshData($agent, 'tenant', 'residential');
        $this->assertSame('By Referral Only', $data['availability_status']);
        $this->assertSame('No',    $data['evenings_available']);
        $this->assertSame('Email', $data['preferred_contact_method']);
    }

    // =========================================================================
    // §3 — transactions_last_12_months null/zero correctness
    // =========================================================================

    public function test_transactions_last_12_months_saves_null_when_field_is_empty(): void
    {
        $agent = $this->makeAgent();

        // Empty string submission — must store null, not 0
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'transactions_last_12_months' => '',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        // Use array_key_exists so null is not confused with a missing key (null ?? fallback = fallback)
        $this->assertTrue(
            array_key_exists('transactions_last_12_months', $data),
            'transactions_last_12_months key must exist in profile_data'
        );
        $this->assertNull(
            $data['transactions_last_12_months'],
            'Empty transactions_last_12_months must store null, not 0'
        );
    }

    public function test_transactions_last_12_months_saves_zero_when_zero_submitted(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'transactions_last_12_months' => '0',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame(0, $data['transactions_last_12_months'],
            'Zero transactions_last_12_months must store integer 0');
    }

    public function test_transactions_last_12_months_saves_positive_integer(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'transactions_last_12_months' => '42',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame(42, $data['transactions_last_12_months'],
            'Positive transactions_last_12_months must store the integer value');
    }

    // =========================================================================
    // §4 — Text-input fields save and reload
    // =========================================================================

    public function test_text_fields_save_and_reload_without_corruption(): void
    {
        $agent = $this->makeAgent();

        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'bio'                  => 'A thorough buyer agent bio.',
            'communication_style'  => 'Proactive communicator',
            'years_experience'     => '10',
            'avg_response_time'    => 'Within 1 hour',
            'primary_areas_served' => 'Miami-Dade, Broward',
            'purchase_fee_flat'    => '5,500',
        ]));

        $data = $this->freshData($agent, 'buyer', 'residential');
        $this->assertSame('A thorough buyer agent bio.', $data['bio']);
        $this->assertSame('Proactive communicator',     $data['communication_style']);
        $this->assertSame('10',                         $data['years_experience']);
        $this->assertSame('Within 1 hour',              $data['avg_response_time']);
        $this->assertSame('Miami-Dade, Broward',        $data['primary_areas_served']);
        $this->assertSame('5,500',                      $data['purchase_fee_flat']);
    }

    // =========================================================================
    // §5 — Edit page HTTP response verifies redirects to edit after save
    // =========================================================================

    public function test_save_redirects_back_to_edit_page_with_saved_flag(): void
    {
        $agent = $this->makeAgent();

        $response = $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'bio' => 'Test bio',
        ]));

        // The controller redirects to the edit page with ?saved=1&scope=... appended.
        $expectedBase = route(self::EDIT_ROUTE, ['role' => 'buyer', 'propertyType' => 'residential']);
        $this->assertTrue(
            str_starts_with($response->headers->get('Location'), $expectedBase),
            'Response must redirect to the edit page URL (with optional query params)'
        );
    }

    // =========================================================================
    // §6 — Blade @selected/@checked rendered HTML (end-to-end polyfill regression)
    //
    // These tests verify that the AppServiceProvider polyfill for @selected and
    // @checked is active and that the edit page emits the correct HTML attributes
    // for saved preset values — not just DB state.  This locks in the fix for
    // the root-cause bug (Laravel 8 does not natively compile @selected/@checked).
    // =========================================================================

    /**
     * @dataProvider selectHtmlRenderProvider
     */
    public function test_edit_page_renders_selected_attribute_for_saved_value(
        string $role,
        string $propertyType,
        string $field,
        string $savedValue,
        string $expectedOptionHtml
    ): void {
        $agent = $this->makeAgent();

        // Save the value first
        $this->postSave($agent, $role, $propertyType, $this->basePayload([
            $field => $savedValue,
        ]));

        // Fetch the edit page
        $response = $this->actingAs($agent)
            ->get(route(self::EDIT_ROUTE, ['role' => $role, 'propertyType' => $propertyType]));

        $response->assertStatus(200);
        $response->assertSee($expectedOptionHtml, false);
    }

    public static function selectHtmlRenderProvider(): array
    {
        // Each entry: [role, propertyType, field, savedValue, expected HTML fragment]
        // The expected HTML fragment is what the @selected polyfill must emit inside
        // the matching <option> tag.
        return [
            'buyer availability_status Limited' => [
                'buyer', 'residential',
                'availability_status', 'Limited Availability',
                'value="Limited Availability" selected',
            ],
            'buyer availability_status Actively Taking' => [
                'buyer', 'residential',
                'availability_status', 'Actively Taking New Clients',
                'value="Actively Taking New Clients" selected',
            ],
            'buyer is_full_time Yes' => [
                'buyer', 'residential',
                'is_full_time', 'Yes',
                'value="Yes" selected',
            ],
            'buyer evenings_available No' => [
                'buyer', 'residential',
                'evenings_available', 'No',
                'value="No" selected',
            ],
            'seller availability_status By Referral Only' => [
                'seller', 'residential',
                'availability_status', 'By Referral Only',
                'value="By Referral Only" selected',
            ],
            'landlord weekends_available Yes' => [
                'landlord', 'residential',
                'weekends_available', 'Yes',
                'value="Yes" selected',
            ],
            'tenant preferred_contact_method Email' => [
                'tenant', 'residential',
                'preferred_contact_method', 'Email',
                'value="Email" selected',
            ],
        ];
    }

    public function test_edit_page_renders_checked_attribute_for_saved_checkbox_value(): void
    {
        $agent = $this->makeAgent();

        // Save a checkbox-backed field (services array contains a known value)
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'services' => ['Help buyer find home'],
        ]));

        $response = $this->actingAs($agent)
            ->get(route(self::EDIT_ROUTE, ['role' => 'buyer', 'propertyType' => 'residential']));

        $response->assertStatus(200);
        // The @checked polyfill must emit "checked" on the matching checkbox input
        $response->assertSee('checked', false);
    }

    public function test_edit_page_does_not_render_selected_for_unsaved_option(): void
    {
        $agent = $this->makeAgent();

        // Save "Limited Availability"
        $this->postSave($agent, 'buyer', 'residential', $this->basePayload([
            'availability_status' => 'Limited Availability',
        ]));

        $response = $this->actingAs($agent)
            ->get(route(self::EDIT_ROUTE, ['role' => 'buyer', 'propertyType' => 'residential']));

        $response->assertStatus(200);
        // "By Referral Only" and "Actively Taking New Clients" must NOT have selected
        // (only "Limited Availability" was saved)
        $html = $response->getContent();
        $this->assertStringNotContainsString(
            'value="By Referral Only" selected',
            $html,
            'Unsaved option (By Referral Only) must not carry the selected attribute'
        );
        $this->assertStringNotContainsString(
            'value="Actively Taking New Clients" selected',
            $html,
            'Unsaved option (Actively Taking New Clients) must not carry the selected attribute'
        );
    }
}
