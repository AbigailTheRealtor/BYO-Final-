<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAvatarFieldsToBuyerTenantDnaProfiles extends Migration
{
    public function up()
    {
        Schema::table('buyer_tenant_dna_profiles', function (Blueprint $table) {
            $table->string('avatar_type')->nullable()->after('archetype_label');
            $table->string('primary_motivation')->nullable()->after('avatar_type');
            $table->string('secondary_motivation')->nullable()->after('primary_motivation');
            $table->text('buyer_narrative')->nullable()->after('secondary_motivation');
            $table->json('buyer_preference_summary')->nullable()->after('buyer_narrative');
            $table->json('buyer_personality_tags')->nullable()->after('buyer_preference_summary');
            $table->json('buyer_match_preferences')->nullable()->after('buyer_personality_tags');
            $table->unsignedTinyInteger('avatar_confidence_score')->nullable()->after('buyer_match_preferences');
            $table->string('buyer_avatar_version')->nullable()->after('avatar_confidence_score');
            $table->unsignedTinyInteger('buyer_readiness_score')->nullable()->after('buyer_avatar_version');
        });
    }

    public function down()
    {
        Schema::table('buyer_tenant_dna_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_type',
                'primary_motivation',
                'secondary_motivation',
                'buyer_narrative',
                'buyer_preference_summary',
                'buyer_personality_tags',
                'buyer_match_preferences',
                'avatar_confidence_score',
                'buyer_avatar_version',
                'buyer_readiness_score',
            ]);
        });
    }
}
