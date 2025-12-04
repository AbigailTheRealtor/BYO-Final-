<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
