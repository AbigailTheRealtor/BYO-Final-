<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class NormalizeSellerPresetRetainerFeeApplication extends Migration
{
    /**
     * Seller presets stored 'Applied toward final compensation' (sentence case)
     * but the seller offer-listing form uses Title Case option values
     * ('Applied Toward Final Compensation'). Normalize presets to Title Case
     * so the dropdown pre-selects correctly when a preset is loaded.
     *
     * Rollback restores sentence case for symmetry; CompensationFormatter
     * normalises display via strtolower() so either form renders identically.
     */
    public function up(): void
    {
        DB::table('agent_default_profiles')
            ->where('role_type', 'seller')
            ->whereRaw("profile_data->>'retainer_fee_application' = ?", ['Applied toward final compensation'])
            ->update([
                'profile_data' => DB::raw(
                    "jsonb_set(profile_data, '{retainer_fee_application}', '\"Applied Toward Final Compensation\"')"
                ),
            ]);

        DB::table('agent_default_profiles')
            ->where('role_type', 'seller')
            ->whereRaw("profile_data->>'retainer_fee_application' = ?", ['Charged in addition to final compensation'])
            ->update([
                'profile_data' => DB::raw(
                    "jsonb_set(profile_data, '{retainer_fee_application}', '\"Charged in Addition to Final Compensation\"')"
                ),
            ]);
    }

    public function down(): void
    {
        DB::table('agent_default_profiles')
            ->where('role_type', 'seller')
            ->whereRaw("profile_data->>'retainer_fee_application' = ?", ['Applied Toward Final Compensation'])
            ->update([
                'profile_data' => DB::raw(
                    "jsonb_set(profile_data, '{retainer_fee_application}', '\"Applied toward final compensation\"')"
                ),
            ]);

        DB::table('agent_default_profiles')
            ->where('role_type', 'seller')
            ->whereRaw("profile_data->>'retainer_fee_application' = ?", ['Charged in Addition to Final Compensation'])
            ->update([
                'profile_data' => DB::raw(
                    "jsonb_set(profile_data, '{retainer_fee_application}', '\"Charged in addition to final compensation\"')"
                ),
            ]);
    }
}
