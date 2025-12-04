<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::insert([
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'name' => 'Admin User',
                'user_name' => 'admin',
                'email' => 'admin@exp.com',
                'password' => Hash::make('12345678'),
                'user_type' => 'admin',
                'mls_id' => null,
                'email_verified_at' => Carbon::now()->toDateTimeString(),
                'is_approved' => true,
                'is_super' => true,
                'created_at' => Carbon::now()->toDateTimeString(),
            ],
            [
                'first_name' => 'John',
                'last_name' => 'Seller',
                'name' => 'John Seller',
                'user_name' => 'john',
                'email' => 'seller@exp.com',
                'password' => Hash::make('12345678'),
                'user_type' => 'seller',
                'mls_id' => '',
                'email_verified_at' => Carbon::now()->toDateTimeString(),
                'is_approved' => true,
                'is_super' => false,
                'created_at' => Carbon::now()->toDateTimeString(),
            ],
            [
                'first_name' => 'John',
                'last_name' => 'Seller Agent',
                'name' => 'John Seller Agent',
                'user_name' => 'john',
                'email' => 'seller_agent@exp.com',
                'password' => Hash::make('12345678'),
                'user_type' => 'seller_agent',
                'mls_id' => 'SA1001',
                'email_verified_at' => Carbon::now()->toDateTimeString(),
                'is_approved' => true,
                'is_super' => false,
                'created_at' => Carbon::now()->toDateTimeString(),
            ],
            [
                'first_name' => 'John',
                'last_name' => 'Buyer',
                'name' => 'John Buyer',
                'user_name' => 'john',
                'email' => 'buyer@exp.com',
                'password' => Hash::make('12345678'),
                'user_type' => 'buyer',
                'mls_id' => '',
                'email_verified_at' => Carbon::now()->toDateTimeString(),
                'is_approved' => true,
                'is_super' => false,
                'created_at' => Carbon::now()->toDateTimeString(),
            ],
            [
                'first_name' => 'John',
                'last_name' => 'Buyer Agent',
                'name' => 'John Buyer Agent',
                'user_name' => 'john',
                'email' => 'buyer_agent@exp.com',
                'password' => Hash::make('12345678'),
                'user_type' => 'buyer_agent',
                'mls_id' => 'BA1001',
                'email_verified_at' => Carbon::now()->toDateTimeString(),
                'is_approved' => true,
                'is_super' => false,
                'created_at' => Carbon::now()->toDateTimeString(),
            ],
        ]);
        /* $user = new User();
        $user->first_name = 'John';
        $user->last_name = 'Doe';
        $user->name = 'John Doe';
        $user->user_name = 'john';
        $user->email = 'john@exp.com';
        $user->password = Hash::make('12345678');
        $user->user_type = 'seller_agent';
        $user->mls_id = 'SA1001';
        $user->email_verified_at = Carbon::now()->toDateTimeString();
        $user->save(); */
    }
}
