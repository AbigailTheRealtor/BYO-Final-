<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        /*
         * offer-playoff
         *
         * Single Gate for all Offer Playoff access (routes, Blade, Livewire).
         * Backing store is config('offer.playoff_access.allowed_user_ids').
         * Set that value to '*' for open rollout, or swap the logic here for
         * a DB flag / subscription check — no callers need to change.
         */
        Gate::define('offer-playoff', function ($user) {
            // Admins always have oversight access
            if ($user->user_type === 'admin') {
                return true;
            }

            $allowed = config('offer.playoff_access.allowed_user_ids', []);

            // '*' = open to all authenticated users (future rollout)
            if ($allowed === '*') {
                return true;
            }

            return in_array($user->id, (array) $allowed);
        });
    }
}
