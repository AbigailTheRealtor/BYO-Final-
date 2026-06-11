<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ask_ai_knowledge_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type', 20)->index();
            $table->unsignedBigInteger('listing_id')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('built')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('built_at')->nullable()->index();
            $table->timestamps();

            $table->index(['listing_type', 'listing_id'], 'ask_ai_snapshots_listing_idx');
            $table->index(['listing_type', 'listing_id', 'version'], 'ask_ai_snapshots_listing_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_ai_knowledge_snapshots');
    }
};
