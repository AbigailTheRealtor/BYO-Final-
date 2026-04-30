<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        $defaults = [
            'title'       => 'Bid Your Offer',
            'logo'        => 'assets/admin/images/logo/logo.png',
            'favicon'     => 'assets/admin/images/logo/favicon.png',
            'footer_text' => '© ' . date('Y') . ' Bid Your Offer. All rights reserved.',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
