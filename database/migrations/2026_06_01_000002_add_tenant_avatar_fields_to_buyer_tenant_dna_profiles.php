<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTenantAvatarFieldsToBuyerTenantDnaProfiles extends Migration
{
    public function up()
    {
        Schema::table('buyer_tenant_dna_profiles', function (Blueprint $table) {
            $table->text('tenant_narrative')->nullable()->after('buyer_readiness_score');
            $table->json('tenant_preference_summary')->nullable()->after('tenant_narrative');
            $table->json('tenant_personality_tags')->nullable()->after('tenant_preference_summary');
            $table->json('tenant_match_preferences')->nullable()->after('tenant_personality_tags');
            $table->string('tenant_avatar_version')->nullable()->after('tenant_match_preferences');
        });
    }

    public function down()
    {
        Schema::table('buyer_tenant_dna_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_narrative',
                'tenant_preference_summary',
                'tenant_personality_tags',
                'tenant_match_preferences',
                'tenant_avatar_version',
            ]);
        });
    }
}
