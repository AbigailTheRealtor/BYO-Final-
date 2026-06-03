<?php

namespace Tests\Feature;

use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AskAiAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser()
    {
        return \App\Models\User::factory()->asAdmin()->create();
    }

    private function makeNonAdminUser()
    {
        return \App\Models\User::factory()->create(['user_type' => 'buyer']);
    }

    private function fakeRunnerResult(): array
    {
        return [
            'success'        => true,
            'status'         => 'ready',
            'classification' => ['question_type' => 'property_standout', 'confidence' => 0.9, 'reason' => 'test'],
            'context'        => ['status' => 'assembled'],
            'contract'       => ['status' => 'contract_ready'],
            'prompt_package' => ['status' => 'prompt_ready'],
            'adapter_result' => ['success' => true, 'status' => 'generated', 'raw_response' => 'Test answer.', 'model' => 'gpt-4o', 'error' => null],
            'final_response' => [
                'success'            => true,
                'status'             => 'ready',
                'answer'             => 'Test answer.',
                'disclosures'        => ['Data sourced from structured property records.'],
                'source_attribution' => ['required_sources' => ['property_intelligence']],
                'refusal_message'    => null,
                'error'              => null,
            ],
            'error'          => null,
        ];
    }

    // (a) Admin user GETs the test page → 200
    public function test_admin_user_can_access_get_route(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.test'));

        $response->assertStatus(200);
    }

    // (b) Non-admin authenticated user GETs → 302 redirect
    public function test_non_admin_authenticated_user_is_redirected(): void
    {
        $user = $this->makeNonAdminUser();

        $response = $this->actingAs($user)->get(route('admin.ask-ai.test'));

        $response->assertStatus(302);
    }

    // (c) Unauthenticated GET → 302 redirect
    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get(route('admin.ask-ai.test'));

        $response->assertStatus(302);
    }

    // (d) Admin POSTs valid input → 200, view contains all nine panel key strings
    public function test_admin_post_calls_runner_and_view_contains_all_panel_keys(): void
    {
        $admin = $this->makeAdminUser();

        $this->instance(
            AskAiRunnerV2Service::class,
            \Mockery::mock(AskAiRunnerV2Service::class, function ($mock) {
                $mock->shouldReceive('run')
                    ->once()
                    ->andReturn($this->fakeRunnerResult());
            })
        );

        $response = $this->actingAs($admin)->post(route('admin.ask-ai.test.run'), [
            'listing_type' => 'seller',
            'listing_id'   => 1,
            'question'     => 'What makes this property stand out?',
            'options'      => null,
        ]);

        $response->assertStatus(200);

        foreach (['classification', 'context', 'contract', 'prompt_package', 'adapter_result', 'final_response', 'error', 'success', 'status'] as $panelKey) {
            $response->assertSee($panelKey);
        }
    }

    // (d2) Admin POSTs invalid JSON in options → 422 validation error
    public function test_admin_post_with_invalid_json_options_returns_validation_error(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin)->post(route('admin.ask-ai.test.run'), [
            'listing_type' => 'seller',
            'listing_id'   => 1,
            'question'     => 'What makes this property stand out?',
            'options'      => '{not valid json',
        ]);

        $response->assertSessionHasErrors('options');
    }

    // (d3) When only source_attribution is present (no disclosures), the source_attribution sub-panel still renders
    public function test_admin_post_source_attribution_panel_visible_without_disclosures(): void
    {
        $admin = $this->makeAdminUser();

        $resultWithOnlySourceAttribution = $this->fakeRunnerResult();
        $resultWithOnlySourceAttribution['final_response']['disclosures'] = null;

        $this->instance(
            AskAiRunnerV2Service::class,
            \Mockery::mock(AskAiRunnerV2Service::class, function ($mock) use ($resultWithOnlySourceAttribution) {
                $mock->shouldReceive('run')
                    ->once()
                    ->andReturn($resultWithOnlySourceAttribution);
            })
        );

        $response = $this->actingAs($admin)->post(route('admin.ask-ai.test.run'), [
            'listing_type' => 'seller',
            'listing_id'   => 1,
            'question'     => 'What makes this property stand out?',
        ]);

        $response->assertStatus(200);
        $response->assertSee('final_response → source_attribution');
        $response->assertDontSee('final_response → disclosures');
    }

    // (e) The admin test interface has no conversation-session or request-log tables of its own.
    //     Note: ask_ai_usage_logs intentionally exists as the platform-wide usage logging layer
    //     (written by AskAiListingQuestionController, not by this admin tool).
    public function test_admin_test_interface_has_no_own_persistence_tables(): void
    {
        $admin = $this->makeAdminUser();

        $this->instance(
            AskAiRunnerV2Service::class,
            \Mockery::mock(AskAiRunnerV2Service::class, function ($mock) {
                $mock->shouldReceive('run')
                    ->once()
                    ->andReturn($this->fakeRunnerResult());
            })
        );

        $this->actingAs($admin)->post(route('admin.ask-ai.test.run'), [
            'listing_type' => 'seller',
            'listing_id'   => 1,
            'question'     => 'What makes this property stand out?',
        ]);

        // These admin-tool-specific tables must never be created. The only
        // intentional Ask AI storage table is ask_ai_usage_logs, which is
        // managed by AskAiListingQuestionController and AskAiUsageLoggerService.
        $forbiddenTables = [
            'ask_ai_logs',
            'ask_ai_conversations',
            'ask_ai_sessions',
            'ask_ai_requests',
        ];

        foreach ($forbiddenTables as $table) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Schema::hasTable($table),
                "Unexpected Ask AI admin table '{$table}' found — the admin test interface must not introduce its own storage layer."
            );
        }

        // Confirm the platform usage log table does exist (created by migration).
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('ask_ai_usage_logs'),
            "ask_ai_usage_logs must exist — it is the platform-wide Ask AI usage logging table."
        );
    }
}
