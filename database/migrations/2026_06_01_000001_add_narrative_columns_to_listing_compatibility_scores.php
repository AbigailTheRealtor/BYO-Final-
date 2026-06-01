<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NOTE: This platform is PostgreSQL-only (confirmed in replit.md system architecture).
// `json()` is used here for consistency with all existing columns in
// `listing_compatibility_scores` (which use `$table->json(...)`). PostgreSQL handles
// both `json` and `jsonb`; `json` is preferred here to match the project-wide convention.

class AddNarrativeColumnsToListingCompatibilityScores extends Migration
{
    public function up()
    {
        Schema::table('listing_compatibility_scores', function (Blueprint $table) {
            $table->text('compatibility_narrative')->nullable()->after('compatibility_archived_at');
            $table->json('compatibility_summary_json')->nullable()->after('compatibility_narrative');
            $table->json('compatibility_highlights')->nullable()->after('compatibility_summary_json');
            $table->json('compatibility_warnings')->nullable()->after('compatibility_highlights');
            $table->float('compatibility_readiness_score')->nullable()->after('compatibility_warnings');
        });
    }

    public function down()
    {
        // Intentionally a no-op.
        //
        // This project follows an append-only, audit-safe migration philosophy.
        // To remove these columns, write a deliberate, reviewed migration.
    }
}
