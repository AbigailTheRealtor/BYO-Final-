<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_score_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Which bid this snapshot belongs to (nullable for agent_hired via HireAgentLead)
            $table->string('bid_type', 30)->nullable()
                  ->comment('seller_agent|buyer_agent|landlord_agent|tenant_agent');
            $table->unsignedBigInteger('bid_id')->nullable();

            // Dimensions required for funnel, distribution, and conversion reports
            $table->string('role', 20)
                  ->comment('seller|buyer|landlord|tenant');
            $table->string('property_type', 50)->nullable();
            $table->string('event_type', 30)
                  ->comment('bid_created|bid_updated|bid_submitted|bid_accepted|agent_hired');

            // Readiness and score at capture time (immutable)
            $table->string('readiness_state', 30)
                  ->comment('not_ready|quick_match_ready|full_match_ready|unknown');
            $table->string('score_type', 20)
                  ->comment('quick_match|full_match|none');
            $table->unsignedSmallInteger('score_value')->nullable()
                  ->comment('0-100 integer score, null when score_type is none');

            // Version tag from CompatibilityScoreService so future rule changes
            // do not corrupt historical distributions
            $table->string('scoring_version', 20)->default('1.0');

            // Deduplication key: prevents two snapshots for the same logical event
            // trigger. Format: {bid_type}:{bid_id}:{event_type}:{YmdHi}
            $table->string('guard_key', 120)->unique();

            // Event timestamp — no updated_at, rows are never modified
            $table->timestamp('captured_at')->useCurrent();

            $table->index(['bid_type', 'bid_id'], 'bss_bid_idx');
            $table->index(['role', 'property_type', 'event_type'], 'bss_role_pt_event_idx');
            $table->index(['event_type', 'captured_at'], 'bss_event_captured_idx');
            $table->index(['readiness_state', 'role'], 'bss_readiness_role_idx');
            $table->index('captured_at', 'bss_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_score_snapshots');
    }
};
