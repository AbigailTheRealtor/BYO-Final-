<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\User;
use App\Models\PropertyAuction;
use App\Models\LandlordAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;
use App\Observers\Dna\BuyerCriteriaAuctionDnaObserver;
use App\Observers\Dna\BuyerTenantDnaProfileCompatibilityObserver;
use App\Observers\Dna\LandlordAuctionDnaObserver;
use App\Observers\Dna\PropertyAuctionDnaObserver;
use App\Observers\Dna\PropertyDnaProfileCompatibilityObserver;
use App\Observers\Dna\TenantCriteriaAuctionDnaObserver;




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

        PropertyAuction::observe(PropertyAuctionDnaObserver::class);
        LandlordAuction::observe(LandlordAuctionDnaObserver::class);
        BuyerCriteriaAuction::observe(BuyerCriteriaAuctionDnaObserver::class);
        TenantCriteriaAuction::observe(TenantCriteriaAuctionDnaObserver::class);

        // Phase F — Compatibility observers.
        // These observers hook PropertyDnaProfile and BuyerTenantDnaProfile saves to dispatch
        // ComputeCompatibilityScore jobs for active counterpart profiles (seller ↔ buyer,
        // landlord ↔ tenant). They never dispatch DNA generation jobs and never trigger
        // additional DNA generation. Dispatch is capped at FANOUT_CAP per invocation.
        PropertyDnaProfile::observe(PropertyDnaProfileCompatibilityObserver::class);
        BuyerTenantDnaProfile::observe(BuyerTenantDnaProfileCompatibilityObserver::class);
    }
}
