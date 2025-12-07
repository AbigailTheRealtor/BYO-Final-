<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsStatesSeeder extends Seeder
{
    public function run()
    {
        $states = [
            ['name' => 'Alabama', 'abbreviation' => 'AL', 'fips_code' => '01'],
            ['name' => 'Alaska', 'abbreviation' => 'AK', 'fips_code' => '02'],
            ['name' => 'Arizona', 'abbreviation' => 'AZ', 'fips_code' => '04'],
            ['name' => 'Arkansas', 'abbreviation' => 'AR', 'fips_code' => '05'],
            ['name' => 'California', 'abbreviation' => 'CA', 'fips_code' => '06'],
            ['name' => 'Colorado', 'abbreviation' => 'CO', 'fips_code' => '08'],
            ['name' => 'Connecticut', 'abbreviation' => 'CT', 'fips_code' => '09'],
            ['name' => 'Delaware', 'abbreviation' => 'DE', 'fips_code' => '10'],
            ['name' => 'District of Columbia', 'abbreviation' => 'DC', 'fips_code' => '11'],
            ['name' => 'Florida', 'abbreviation' => 'FL', 'fips_code' => '12'],
            ['name' => 'Georgia', 'abbreviation' => 'GA', 'fips_code' => '13'],
            ['name' => 'Hawaii', 'abbreviation' => 'HI', 'fips_code' => '15'],
            ['name' => 'Idaho', 'abbreviation' => 'ID', 'fips_code' => '16'],
            ['name' => 'Illinois', 'abbreviation' => 'IL', 'fips_code' => '17'],
            ['name' => 'Indiana', 'abbreviation' => 'IN', 'fips_code' => '18'],
            ['name' => 'Iowa', 'abbreviation' => 'IA', 'fips_code' => '19'],
            ['name' => 'Kansas', 'abbreviation' => 'KS', 'fips_code' => '20'],
            ['name' => 'Kentucky', 'abbreviation' => 'KY', 'fips_code' => '21'],
            ['name' => 'Louisiana', 'abbreviation' => 'LA', 'fips_code' => '22'],
            ['name' => 'Maine', 'abbreviation' => 'ME', 'fips_code' => '23'],
            ['name' => 'Maryland', 'abbreviation' => 'MD', 'fips_code' => '24'],
            ['name' => 'Massachusetts', 'abbreviation' => 'MA', 'fips_code' => '25'],
            ['name' => 'Michigan', 'abbreviation' => 'MI', 'fips_code' => '26'],
            ['name' => 'Minnesota', 'abbreviation' => 'MN', 'fips_code' => '27'],
            ['name' => 'Mississippi', 'abbreviation' => 'MS', 'fips_code' => '28'],
            ['name' => 'Missouri', 'abbreviation' => 'MO', 'fips_code' => '29'],
            ['name' => 'Montana', 'abbreviation' => 'MT', 'fips_code' => '30'],
            ['name' => 'Nebraska', 'abbreviation' => 'NE', 'fips_code' => '31'],
            ['name' => 'Nevada', 'abbreviation' => 'NV', 'fips_code' => '32'],
            ['name' => 'New Hampshire', 'abbreviation' => 'NH', 'fips_code' => '33'],
            ['name' => 'New Jersey', 'abbreviation' => 'NJ', 'fips_code' => '34'],
            ['name' => 'New Mexico', 'abbreviation' => 'NM', 'fips_code' => '35'],
            ['name' => 'New York', 'abbreviation' => 'NY', 'fips_code' => '36'],
            ['name' => 'North Carolina', 'abbreviation' => 'NC', 'fips_code' => '37'],
            ['name' => 'North Dakota', 'abbreviation' => 'ND', 'fips_code' => '38'],
            ['name' => 'Ohio', 'abbreviation' => 'OH', 'fips_code' => '39'],
            ['name' => 'Oklahoma', 'abbreviation' => 'OK', 'fips_code' => '40'],
            ['name' => 'Oregon', 'abbreviation' => 'OR', 'fips_code' => '41'],
            ['name' => 'Pennsylvania', 'abbreviation' => 'PA', 'fips_code' => '42'],
            ['name' => 'Rhode Island', 'abbreviation' => 'RI', 'fips_code' => '44'],
            ['name' => 'South Carolina', 'abbreviation' => 'SC', 'fips_code' => '45'],
            ['name' => 'South Dakota', 'abbreviation' => 'SD', 'fips_code' => '46'],
            ['name' => 'Tennessee', 'abbreviation' => 'TN', 'fips_code' => '47'],
            ['name' => 'Texas', 'abbreviation' => 'TX', 'fips_code' => '48'],
            ['name' => 'Utah', 'abbreviation' => 'UT', 'fips_code' => '49'],
            ['name' => 'Vermont', 'abbreviation' => 'VT', 'fips_code' => '50'],
            ['name' => 'Virginia', 'abbreviation' => 'VA', 'fips_code' => '51'],
            ['name' => 'Washington', 'abbreviation' => 'WA', 'fips_code' => '53'],
            ['name' => 'West Virginia', 'abbreviation' => 'WV', 'fips_code' => '54'],
            ['name' => 'Wisconsin', 'abbreviation' => 'WI', 'fips_code' => '55'],
            ['name' => 'Wyoming', 'abbreviation' => 'WY', 'fips_code' => '56'],
            ['name' => 'Puerto Rico', 'abbreviation' => 'PR', 'fips_code' => '72'],
            ['name' => 'Guam', 'abbreviation' => 'GU', 'fips_code' => '66'],
            ['name' => 'U.S. Virgin Islands', 'abbreviation' => 'VI', 'fips_code' => '78'],
            ['name' => 'American Samoa', 'abbreviation' => 'AS', 'fips_code' => '60'],
            ['name' => 'Northern Mariana Islands', 'abbreviation' => 'MP', 'fips_code' => '69'],
        ];

        $now = now();
        foreach ($states as &$state) {
            $state['created_at'] = $now;
            $state['updated_at'] = $now;
        }

        DB::table('us_states')->insertOrIgnore($states);
    }
}
