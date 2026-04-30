<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedDefaultAppSettings extends Migration
{
    public function up()
    {
        $footerText = '© ' . date('Y') . ' Bid Your Offer. All rights reserved.';

        $rows = [
            'title'       => 'Bid Your Offer',
            'logo'        => 'assets/admin/images/logo/logo.png',
            'favicon'     => 'assets/admin/images/logo/favicon.png',
            'footer_text' => $footerText,
        ];

        foreach ($rows as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if (! $exists) {
                DB::table('settings')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        $defaultValues = [
            'title'       => 'Bid Your Offer',
            'logo'        => 'assets/admin/images/logo/logo.png',
            'favicon'     => 'assets/admin/images/logo/favicon.png',
        ];

        foreach ($defaultValues as $key => $value) {
            DB::table('settings')
                ->where('key', $key)
                ->where('value', $value)
                ->delete();
        }

        DB::table('settings')
            ->where('key', 'footer_text')
            ->where('value', 'like', '% Bid Your Offer. All rights reserved.')
            ->delete();
    }
}
