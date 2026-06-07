<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateCalcInterestRateDefaultTo65 extends Migration
{
    public function up()
    {
        DB::table('settings')
            ->where('key', 'calc_interest_rate')
            ->where('value', '7.0')
            ->update(['value' => '6.5', 'updated_at' => now()]);
    }

    public function down()
    {
        // No-op: rolling back cannot safely distinguish between admin-intentional
        // 6.5 values and values this migration set. Leave the setting as-is.
    }
}
