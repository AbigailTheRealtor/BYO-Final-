<?php

namespace Tests\Feature\Security;

use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BLK-03: UserSeeder creates shared-credential dev/test accounts (password
 * 12345678, including an admin@exp.com admin). It must refuse to run in the
 * production environment so those accounts can never be provisioned there.
 */
class UserSeederProductionGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_does_not_create_accounts_in_production()
    {
        $this->app['env'] = 'production';

        (new UserSeeder())->run();

        $this->assertDatabaseMissing('users', ['email' => 'admin@exp.com']);
        $this->assertDatabaseMissing('users', ['email' => 'seller@exp.com']);
    }

    public function test_seeder_creates_accounts_outside_production()
    {
        // Sanity control: in a non-production environment the seeder still works.
        $this->app['env'] = 'testing';

        (new UserSeeder())->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@exp.com', 'user_type' => 'admin']);
    }
}
