<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostTrackingColumnsToAskAiUsageLogs extends Migration
{
    public function up()
    {
        Schema::table('ask_ai_usage_logs', function (Blueprint $table) {
            $table->integer('prompt_tokens')->default(0)->after('error_code');
            $table->integer('completion_tokens')->default(0)->after('prompt_tokens');
            $table->integer('total_tokens')->default(0)->after('completion_tokens');
            $table->decimal('estimated_cost_usd', 10, 6)->nullable()->after('total_tokens');
            $table->string('api_request_id', 255)->nullable()->after('estimated_cost_usd');

            $table->index('model');
        });
    }

    public function down()
    {
        Schema::table('ask_ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['model']);
            $table->dropColumn([
                'prompt_tokens',
                'completion_tokens',
                'total_tokens',
                'estimated_cost_usd',
                'api_request_id',
            ]);
        });
    }
}
