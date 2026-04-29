<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development / test account seeder.
 *
 * Uses firstOrCreate() so it is fully idempotent — safe to re-run at any time
 * without duplicating rows. UserObserver fires on create(), so short_id and
 * user_name are auto-generated if the row does not yet exist.
 *
 * Run manually:   php artisan db:seed --class=UserSeeder
 * Run via script: scripts/post-merge.sh calls this automatically.
 */
class UserSeeder extends Seeder
{
    /**
     * Dev accounts.  password for all: 12345678
     */
    private array $accounts = [
        // ── Admin ──────────────────────────────────────────────
        [
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'name'       => 'Admin User',
            'email'      => 'admin@exp.com',
            'user_type'  => 'admin',
            'is_approved'=> true,
            'is_super'   => true,
        ],
        // ── Seller ─────────────────────────────────────────────
        [
            'first_name' => 'John',
            'last_name'  => 'Seller',
            'name'       => 'John Seller',
            'email'      => 'seller@exp.com',
            'user_type'  => 'seller',
        ],
        // ── Seller Agent ────────────────────────────────────────
        [
            'first_name' => 'John',
            'last_name'  => 'Seller Agent',
            'name'       => 'John Seller Agent',
            'email'      => 'seller_agent@exp.com',
            'user_type'  => 'seller_agent',
            'mls_id'     => 'SA1001',
        ],
        // ── Buyer ──────────────────────────────────────────────
        [
            'first_name' => 'John',
            'last_name'  => 'Buyer',
            'name'       => 'John Buyer',
            'email'      => 'buyer@exp.com',
            'user_type'  => 'buyer',
        ],
        // ── Buyer Agent ─────────────────────────────────────────
        [
            'first_name' => 'John',
            'last_name'  => 'Buyer Agent',
            'name'       => 'John Buyer Agent',
            'email'      => 'buyer_agent@exp.com',
            'user_type'  => 'buyer_agent',
            'mls_id'     => 'BA1001',
        ],
        // ── Tenant ─────────────────────────────────────────────
        [
            'first_name' => 'Tenant',
            'last_name'  => 'User',
            'name'       => 'Tenant User',
            'email'      => 'tenant@exp.com',
            'user_type'  => 'tenant',
        ],
        // ── Agent accounts ──────────────────────────────────────
        [
            'first_name' => 'John',
            'last_name'  => 'Agent',
            'name'       => 'John Agent',
            'email'      => 'john@exp.com',
            'user_type'  => 'agent',
        ],
        [
            'first_name' => 'John',
            'last_name'  => 'Long',
            'name'       => 'John Long',
            'email'      => 'johnlong@exp.com',
            'user_type'  => 'agent',
        ],
        [
            'first_name' => 'Abigail',
            'last_name'  => 'Baschuk',
            'name'       => 'Abigail Baschuk',
            'email'      => 'abigailbaschuk@gmail.com',
            'user_type'  => 'agent',
        ],
    ];

    public function run(): void
    {
        $now = Carbon::now()->toDateTimeString();

        foreach ($this->accounts as $acct) {
            // firstOrCreate fires UserObserver on create, so short_id and
            // user_name are auto-populated for new rows.
            User::firstOrCreate(
                ['email' => $acct['email']],
                array_merge([
                    'password'           => Hash::make('12345678'),
                    'email_verified_at'  => $now,
                    'is_approved'        => $acct['is_approved'] ?? false,
                    'is_super'           => $acct['is_super']    ?? false,
                    'mls_id'             => $acct['mls_id']      ?? null,
                ], $acct)
            );
        }
    }
}
