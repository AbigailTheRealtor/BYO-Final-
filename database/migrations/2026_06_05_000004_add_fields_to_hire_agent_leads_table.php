<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hire_agent_leads', function (Blueprint $table) {
            // Attribution: which logged-in user submitted the lead
            $table->unsignedBigInteger('requester_user_id')->nullable()->after('target_agent_id');
            $table->foreign('requester_user_id')->references('id')->on('users')->nullOnDelete();

            // Preset matching metadata
            $table->unsignedBigInteger('matched_preset_id')->nullable()->after('requester_user_id');
            $table->foreign('matched_preset_id')->references('id')->on('agent_default_profiles')->nullOnDelete();
            $table->string('preset_match_status', 32)->default('none')->after('matched_preset_id');
            // Values: 'exact' | 'role_default' | 'none'

            // Listing snapshot (denormalised for stability if listing is deleted)
            $table->string('listing_title', 255)->nullable()->after('preset_match_status');
            $table->string('listing_url', 511)->nullable()->after('listing_title');

            // Lifecycle timestamps
            $table->timestamp('viewed_at')->nullable()->after('status');
            $table->timestamp('responded_at')->nullable()->after('viewed_at');
            $table->timestamp('accepted_at')->nullable()->after('responded_at');
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('hire_agent_leads', function (Blueprint $table) {
            $table->dropForeign(['requester_user_id']);
            $table->dropForeign(['matched_preset_id']);
            $table->dropColumn([
                'requester_user_id', 'matched_preset_id', 'preset_match_status',
                'listing_title', 'listing_url',
                'viewed_at', 'responded_at', 'accepted_at', 'declined_at',
            ]);
        });
    }
};
