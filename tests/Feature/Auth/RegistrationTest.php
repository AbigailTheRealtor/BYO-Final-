<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'user_name' => 'testuser',
            'email' => 'test@example.com',
            'phone_number' => '5551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'buyer',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    /**
     * BLK-02: a public registration request must not be able to create an
     * admin account by supplying user_type=admin.
     */
    public function test_registration_rejects_admin_user_type()
    {
        $response = $this->from('/register')->post('/register', [
            'first_name' => 'Evil',
            'last_name' => 'Admin',
            'user_name' => 'eviladmin',
            'email' => 'eviladmin@example.com',
            'phone_number' => '5551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'admin',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('user_type');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'eviladmin@example.com']);
    }

    /**
     * BLK-02: any user_type outside the self-registerable whitelist is rejected
     * (covers internal roles like seller_agent and arbitrary values).
     */
    public function test_registration_rejects_non_whitelisted_user_type()
    {
        foreach (['seller_agent', 'buyer_agent', 'superuser', 'root'] as $role) {
            $response = $this->from('/register')->post('/register', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'user_name' => 'testuser_' . $role,
                'email' => $role . '@example.com',
                'phone_number' => '5551234567',
                'password' => 'password',
                'password_confirmation' => 'password',
                'user_type' => $role,
                'terms' => '1',
            ]);

            $response->assertSessionHasErrors('user_type');
            $this->assertDatabaseMissing('users', ['email' => $role . '@example.com']);
        }

        $this->assertGuest();
    }

    /**
     * BLK-02: the five public roles still register successfully.
     */
    public function test_registration_accepts_whitelisted_roles()
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant', 'agent'] as $role) {
            $this->post('/register', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'user_name' => 'ok_' . $role,
                'email' => 'ok_' . $role . '@example.com',
                'phone_number' => '5551234567',
                'password' => 'password',
                'password_confirmation' => 'password',
                'user_type' => $role,
                'terms' => '1',
            ]);

            $this->assertDatabaseHas('users', [
                'email' => 'ok_' . $role . '@example.com',
                'user_type' => $role,
            ]);
            $this->post('/logout');
        }
    }
}
