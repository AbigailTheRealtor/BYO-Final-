<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_preset_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20);
            $table->string('property_type', 50);
            $table->unsignedBigInteger('preset_id');
            $table->unsignedBigInteger('listing_id');
            $table->string('event', 50)->default('preset_applied');
            $table->smallInteger('field_count_populated')->default(0);
            $table->jsonb('metadata')->nullable()->comment('Reserved for P7 analytics extensions');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('preset_id')->references('id')->on('agent_default_profiles')->onDelete('cascade');

            $table->index(['user_id', 'role'], 'ape_user_role_idx');
            $table->index(['preset_id'], 'ape_preset_idx');
            $table->index(['event', 'created_at'], 'ape_event_created_idx');
            $table->index(['listing_id'], 'ape_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_preset_events');
    }
};
