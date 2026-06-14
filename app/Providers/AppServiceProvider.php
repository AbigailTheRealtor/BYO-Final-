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
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Contracts\BoundaryAdapterInterface;
use App\Contracts\FloodZoneAdapterInterface;
use App\Contracts\CommuteTimeAdapterInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Contracts\SchoolDistrictAdapterInterface;
use App\Services\LocationDna\CensusTigerBoundaryAdapter;
use App\Services\LocationDna\FemaFloodZoneAdapter;
use App\Services\LocationDna\CommuteTimeStubAdapter;
use App\Services\LocationDna\CensusSchoolDistrictAdapter;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\StubPoiLookupAdapter;
use App\Services\LocationDna\BoundaryLookupService;
use App\Services\LocationDna\FloodZoneLookupService;
use App\Services\LocationDna\CommuteTimeLookupService;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Services\LocationDna\SchoolDistrictLookupService;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Explicit binding for AskAiRunnerV2Service to ensure AskAiIntentNormalizerService
        // and AskAiKnowledgeSearchService are always injected as concrete non-null instances.
        // Laravel's auto-wiring would also resolve this correctly today, but explicit DI is
        // safer: it is immune to future constructor changes in those dependencies and makes
        // the intent clear to anyone reading the service graph.
        $this->app->bind(BoundaryAdapterInterface::class, CensusTigerBoundaryAdapter::class);
        $this->app->bind(FloodZoneAdapterInterface::class, FemaFloodZoneAdapter::class);
        $this->app->bind(SchoolDistrictAdapterInterface::class, CensusSchoolDistrictAdapter::class);

        if (config('location_dna.commute_time.provider') === 'stub') {
            $this->app->bind(CommuteTimeAdapterInterface::class, CommuteTimeStubAdapter::class);
        }

        $this->app->bind(BoundaryLookupService::class, function ($app) {
            return new BoundaryLookupService($app->make(BoundaryAdapterInterface::class));
        });

        $this->app->bind(FloodZoneLookupService::class, function ($app) {
            return new FloodZoneLookupService($app->make(FloodZoneAdapterInterface::class));
        });

        $this->app->bind(SchoolDistrictLookupService::class, function ($app) {
            return new SchoolDistrictLookupService($app->make(SchoolDistrictAdapterInterface::class));
        });

        $this->app->bind(CommuteTimeLookupService::class, function ($app) {
            return new CommuteTimeLookupService($app->make(CommuteTimeAdapterInterface::class));
        });

        // POI Distance Lookup — Buyer/Tenant search-area geometry (Phase 3C)
        // Adapter selection respects location_dna.poi.provider first, then falls back to
        // StubPoiLookupAdapter whenever the provider is not 'google' or the key is absent.
        // Bound (not singleton) so config changes in tests always produce a fresh instance.
        $this->app->bind(PoiLookupAdapterInterface::class, function ($app) {
            $provider = config('location_dna.poi.provider', 'google');
            if ($provider === 'google' && !blank(config('services.google.places_key'))) {
                return new GooglePlacesPoiAdapter();
            }
            return new StubPoiLookupAdapter();
        });

        $this->app->bind(PoiDistanceLookupService::class, function ($app) {
            return new PoiDistanceLookupService($app->make(PoiLookupAdapterInterface::class));
        });

        $this->app->bind(AskAiRunnerV2Service::class, function ($app) {
            return new AskAiRunnerV2Service(
                $app->make(AskAiQuestionClassifierService::class),
                $app->make(AskAiInternalRunnerService::class),
                $app->make(AskAiOpenAiAdapterService::class),
                $app->make(AskAiFinalResponseBuilderService::class),
                $app->make(AskAiFollowUpQuestionService::class),
                $app->make(AskAiIntentNormalizerService::class),
                $app->make(AskAiKnowledgeSearchService::class),
            );
        });
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
