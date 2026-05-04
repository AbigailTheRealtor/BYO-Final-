<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\User;




class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Polyfill @selected and @checked Blade directives (added in Laravel 9; app runs on L8).
        // TODO: Remove these polyfills after upgrading to Laravel 9+, where the framework
        // ships these directives natively (they will conflict if both are registered).
        Blade::directive('selected', function ($expression) {
            return "<?php echo ($expression) ? 'selected' : ''; ?>";
        });
        Blade::directive('checked', function ($expression) {
            return "<?php echo ($expression) ? 'checked' : ''; ?>";
        });

        // Force HTTPS for all generated URLs in production/Replit environment
        if (config('app.env') !== 'local' || str_contains(config('app.url'), 'replit')) {
            URL::forceScheme('https');
        }
        
        Relation::enforceMorphMap([
            'seller-property' => 'App\Models\PropertyAuction',
            'landlord-property' => 'App\Models\LandlordAuction',
            'buyer-criteria' => 'App\Models\BuyerCriteriaAuction',
            'tenant-criteria' => 'App\Models\TenantCriteriaAuction',
            'seller-agent' => 'App\Models\SellerAgentAuction',
            'buyer-agent' => 'App\Models\BuyerAgentAuction',
            'landlord-agent' => 'App\Models\LandlordAgentAuction',
            'tenant-agent' => 'App\Models\TenantAgentAuction',
            'agent-service' => 'App\Models\AgentServiceAuction',
            'users' => User::class,

        ]);
    }
}
