<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralColumnsToUsersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'referred_by_agent_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('referred_by_agent_id')->nullable()->after('is_deleted');
                $table->string('referral_source_code')->nullable()->after('referred_by_agent_id');
                $table->timestamp('referral_captured_at')->nullable()->after('referral_source_code');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'referred_by_agent_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['referred_by_agent_id', 'referral_source_code', 'referral_captured_at']);
            });
        }
    }
}
