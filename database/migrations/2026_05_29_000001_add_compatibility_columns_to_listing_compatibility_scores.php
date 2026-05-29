<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompatibilityColumnsToListingCompatibilityScores extends Migration
{
    public function up()
    {
        Schema::table('listing_compatibility_scores', function (Blueprint $table) {
            $table->decimal('representation_compatibility_score', 5, 2)->nullable()->after('terms_match_score');
            $table->string('representation_compatibility_label')->nullable()->after('representation_compatibility_score');
            $table->json('compatibility_trait_results')->nullable()->after('representation_compatibility_label');
            $table->string('compatibility_framework_version')->nullable()->after('compatibility_trait_results');
            $table->string('ai_explanation_version')->nullable()->after('compatibility_framework_version');
            $table->string('moderation_status')->nullable()->after('ai_explanation_version');
            $table->timestamp('compatibility_computed_at')->nullable()->after('moderation_status');
            $table->timestamp('compatibility_archived_at')->nullable()->after('compatibility_computed_at');
        });
    }

    public function down()
    {
        // Intentionally a no-op.
        //
        // This project follows an append-only, audit-safe migration philosophy:
        // columns added for the Compatibility Engine Foundation are never dropped
        // automatically. To remove them, write a deliberate, reviewed migration.
    }
}
