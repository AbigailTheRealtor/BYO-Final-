<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AskAiAnalyticsDashboardTest extends TestCase
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

    private function seedLogs(): void
    {
        $now       = now()->toDateTimeString();
        $yesterday = now()->subDay()->toDateTimeString();

        DB::table('ask_ai_usage_logs')->insert([
            [
                'listing_type'       => 'seller',
                'listing_id'         => 1,
                'user_id'            => null,
                'ip_address'         => '1.2.3.4',
                'question_hash'      => 'abc123',
                'question_type'      => 'property_standout',
                'status'             => 'success',
                'success'            => true,
                'model'              => 'gpt-4o',
                'response_time_ms'   => 800,
                'error_code'         => null,
                'prompt_tokens'      => 500,
                'completion_tokens'  => 200,
                'total_tokens'       => 700,
                'estimated_cost_usd' => 0.005500,
                'api_request_id'     => 'req-001',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'listing_type'       => 'buyer',
                'listing_id'         => 2,
                'user_id'            => null,
                'ip_address'         => '5.6.7.8',
                'question_hash'      => 'def456',
                'question_type'      => 'suited_audience',
                'status'             => 'rate_limited',
                'success'            => false,
                'model'              => 'gpt-4o',
                'response_time_ms'   => null,
                'error_code'         => 'guest_ip_hourly',
                'prompt_tokens'      => 0,
                'completion_tokens'  => 0,
                'total_tokens'       => 0,
                'estimated_cost_usd' => null,
                'api_request_id'     => null,
                'created_at'         => $yesterday,
                'updated_at'         => $yesterday,
            ],
            [
                'listing_type'       => 'landlord',
                'listing_id'         => 3,
                'user_id'            => null,
                'ip_address'         => '9.10.11.12',
                'question_hash'      => 'ghi789',
                'question_type'      => 'marketing_angles',
                'status'             => 'success',
                'success'            => true,
                'model'              => 'gpt-3.5-turbo',
                'response_time_ms'   => 400,
                'error_code'         => null,
                'prompt_tokens'      => 300,
                'completion_tokens'  => 100,
                'total_tokens'       => 400,
                'estimated_cost_usd' => 0.000300,
                'api_request_id'     => 'req-003',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
        ]);
    }

    // (a) Admin can access the analytics dashboard — 200
    public function test_admin_can_access_analytics_dashboard(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);
    }

    // (b) Non-admin authenticated user is redirected — 302
    public function test_non_admin_is_redirected_from_analytics_dashboard(): void
    {
        $user = $this->makeNonAdminUser();

        $response = $this->actingAs($user)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(302);
    }

    // (c) Unauthenticated user is redirected — 302
    public function test_unauthenticated_user_is_redirected_from_analytics_dashboard(): void
    {
        $response = $this->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(302);
    }

    // (d) Dashboard renders all expected summary card labels
    public function test_dashboard_renders_all_expected_card_labels(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);

        // Fixed-window cards (always present regardless of active filter)
        $response->assertSee('Questions Today');
        $response->assertSee('Questions Last 7 Days');
        $response->assertSee('Questions Last 30 Days');
        $response->assertSee('Estimated Cost Today');
        $response->assertSee('Estimated Cost Last 7 Days');
        $response->assertSee('Estimated Cost Last 30 Days');

        // Active-filter summary cards
        $response->assertSee('Total Questions');
        $response->assertSee('Average Cost Per Question');
        $response->assertSee('Unique Listings Using Ask AI');
        $response->assertSee('Rate Limited Requests');
    }

    // (e) Cost metrics appear on the dashboard
    public function test_cost_metrics_appear(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Estimated Cost Today');
        $response->assertSee('Estimated Cost Last 7 Days');
        $response->assertSee('Estimated Cost Last 30 Days');
        $response->assertSee('Average Cost Per Question');
    }

    // (f) Usage metrics appear (question type table with all nine types)
    public function test_usage_metrics_appear(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);
        $response->assertSee('property_standout');
        $response->assertSee('suited_audience');
        $response->assertSee('buyer_tenant_match');
        $response->assertSee('compatibility_signals');
        $response->assertSee('missing_data');
        $response->assertSee('marketing_angles');
        $response->assertSee('educational');
        $response->assertSee('unsupported');
        $response->assertSee('blocked');
    }

    // (g) Model metrics appear (model usage table)
    public function test_model_metrics_appear(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Model Usage');
        $response->assertSee('gpt-4o');
        $response->assertSee('gpt-3.5-turbo');
        $response->assertSee('Prompt Tokens');
        $response->assertSee('Completion Tokens');
        $response->assertSee('Total Tokens');
    }

    // (h) Listing metrics appear (top 25 listing analytics table)
    public function test_listing_metrics_appear(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Top 25 Listings');
        $response->assertSee('Listing ID');
        $response->assertSee('Listing Type');
    }

    // (i) No writes occur — log count unchanged after GET
    public function test_no_writes_occur_on_get(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $before = DB::table('ask_ai_usage_logs')->count();

        $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $after = DB::table('ask_ai_usage_logs')->count();

        $this->assertSame($before, $after, 'GET request must not insert any rows into ask_ai_usage_logs.');
    }

    // Rate limiter table shows all five canonical key labels
    public function test_rate_limiter_table_shows_all_keys(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);
        $response->assertSee('guest_ip_hourly');
        $response->assertSee('user_hourly');
        $response->assertSee('admin_daily');
        $response->assertSee('ip_shared_hourly');
        $response->assertSee('listing_hourly');
    }

    // Daily cost table section is visible
    public function test_daily_cost_table_is_visible(): void
    {
        $admin = $this->makeAdminUser();
        $this->seedLogs();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Daily Cost');
    }

    // Date filter scopes ALL sections — custom range excludes out-of-range rows
    public function test_date_filter_scopes_all_sections_consistently(): void
    {
        $admin = $this->makeAdminUser();

        // Wipe any rows that may have accumulated from previous tests in this
        // process (SQLite :memory: with RefreshDatabase does not truncate
        // between tests — it only re-runs migrations idempotently).
        DB::table('ask_ai_usage_logs')->truncate();

        // Insert one row 60 days ago (outside a narrow custom range = today only)
        $oldDate = now()->subDays(60)->toDateTimeString();
        DB::table('ask_ai_usage_logs')->insert([
            'listing_type'       => 'seller',
            'listing_id'         => 99,
            'user_id'            => null,
            'ip_address'         => '1.1.1.1',
            'question_hash'      => 'old001',
            'question_type'      => 'educational',
            'status'             => 'success',
            'success'            => true,
            'model'              => 'gpt-4o',
            'response_time_ms'   => 500,
            'error_code'         => null,
            'prompt_tokens'      => 100,
            'completion_tokens'  => 50,
            'total_tokens'       => 150,
            'estimated_cost_usd' => 0.001000,
            'api_request_id'     => 'req-old',
            'created_at'         => $oldDate,
            'updated_at'         => $oldDate,
        ]);

        // Insert one row from today (the only row inside "today only" custom range)
        $todayTs = now()->toDateTimeString();
        DB::table('ask_ai_usage_logs')->insert([
            'listing_type'       => 'buyer',
            'listing_id'         => 100,
            'user_id'            => null,
            'ip_address'         => '2.2.2.2',
            'question_hash'      => 'new001',
            'question_type'      => 'property_standout',
            'status'             => 'success',
            'success'            => true,
            'model'              => 'gpt-4o',
            'response_time_ms'   => 400,
            'error_code'         => null,
            'prompt_tokens'      => 200,
            'completion_tokens'  => 80,
            'total_tokens'       => 280,
            'estimated_cost_usd' => 0.002200,
            'api_request_id'     => 'req-new',
            'created_at'         => $todayTs,
            'updated_at'         => $todayTs,
        ]);

        $todayDate = now()->toDateString();

        // Request with custom range = today only
        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics', [
            'preset' => 'custom',
            'from'   => $todayDate,
            'to'     => $todayDate,
        ]));

        $response->assertStatus(200);

        // Assert the controller passed exactly 1 question (today's row only)
        // to the view — this is deterministic regardless of HTML rendering.
        $response->assertViewHas('totalQuestions', 1);

        // The 60-day-old row's cost must not be included in totalCost
        // (its cost was 0.001000; only today's row cost 0.002200 should appear)
        $viewTotalCost = $response->viewData('totalCost');
        $this->assertEqualsWithDelta(0.002200, $viewTotalCost, 0.000001,
            'totalCost must reflect only the rows inside the active date filter.');
    }

    // Malformed date inputs fall back to last 30 days gracefully (no 500)
    public function test_malformed_custom_date_input_falls_back_gracefully(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.ask-ai.analytics', [
            'preset' => 'custom',
            'from'   => 'not-a-date',
            'to'     => 'also-bad',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Total Questions');
    }
}
