<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * BLK-01: the /dev-login/{id} convenience route logs in as an arbitrary user
 * with no credentials. It must be unreachable in any deployment. It is
 * double-gated (non-production environment AND config('app.dev_login_enabled'),
 * which defaults false), so by default — and always in production — it must not
 * be registered.
 */
class DevLoginRouteTest extends TestCase
{
    public function test_dev_login_route_is_not_registered_by_default()
    {
        // DEV_LOGIN_ENABLED is unset in the test env → config default false.
        $this->assertFalse(config('app.dev_login_enabled'));
        $this->assertFalse(Route::has('dev.login'), 'dev.login route must not be registered by default.');
    }

    public function test_dev_login_url_returns_404_by_default()
    {
        $this->get('/dev-login/1')->assertNotFound();
    }
}
