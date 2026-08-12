<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen bridge_properties.pets_allowed from varchar(50) to varchar(255).
 *
 * WHY THIS IS NEEDED NOW
 * ----------------------
 * BridgePropertyNormalizer used to keep only the first element of the RESO
 * PetsAllowed array, so the stored value could never be longer than one term
 * and 50 characters was ample. Now that the complete policy is preserved, the
 * joined value is as long as the listing's real policy.
 *
 * This is not a precaution against a hypothetical. Measured against the 669
 * Bridge records already imported locally: 465 carry PetsAllowed, up to 5
 * values each, and 4 of them join to more than 50 characters — the longest is
 * "Breed Restrictions, Dogs OK, Number Limit, Size Limit, Yes" at 58. On
 * PostgreSQL an over-long value raises rather than truncating, so without this
 * the fidelity fix would turn a silent data loss into a failed import.
 *
 * 255 rather than something snugger: the surrounding columns in
 * add_phase1_native_columns_to_bridge_properties are plain strings, and a
 * controlled vocabulary that grows by one term should not require another
 * migration.
 *
 * No data change. Widening a varchar preserves every existing value, and the
 * down() path is only safe because nothing longer than 50 was ever stored by
 * the code that shipped before this.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bridge_properties', 'pets_allowed')) {
            return;
        }

        Schema::table('bridge_properties', function (Blueprint $table) {
            $table->string('pets_allowed', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('bridge_properties', 'pets_allowed')) {
            return;
        }

        // Values written since up() ran may exceed 50 characters; truncate them
        // rather than let the column change fail half-way through a rollback.
        // substr() rather than LEFT() — LEFT() does not exist on SQLite, which
        // is what the test suite migrates against.
        DB::table('bridge_properties')
            ->whereRaw('LENGTH(pets_allowed) > 50')
            ->update(['pets_allowed' => DB::raw('substr(pets_allowed, 1, 50)')]);

        Schema::table('bridge_properties', function (Blueprint $table) {
            $table->string('pets_allowed', 50)->nullable()->change();
        });
    }
};
