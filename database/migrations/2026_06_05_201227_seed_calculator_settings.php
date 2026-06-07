<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedCalculatorSettings extends Migration
{
    public function up()
    {
        $defaults = [
            ['key' => 'calc_interest_rate',    'value' => '6.5'],
            ['key' => 'calc_down_payment_pct', 'value' => '10'],
            ['key' => 'calc_loan_term',        'value' => '30'],
            ['key' => 'calc_tax_rate',         'value' => '1.1'],
            ['key' => 'calc_insurance_rate',   'value' => '0.5'],
            ['key' => 'calc_pmi_rate',         'value' => '0.85'],
        ];

        foreach ($defaults as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                ['key' => $row['key'], 'value' => $row['value'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down()
    {
        DB::table('settings')->whereIn('key', [
            'calc_interest_rate',
            'calc_down_payment_pct',
            'calc_loan_term',
            'calc_tax_rate',
            'calc_insurance_rate',
            'calc_pmi_rate',
        ])->delete();
    }
}
