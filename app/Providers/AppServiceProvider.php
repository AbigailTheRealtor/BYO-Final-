<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\User;
use App\Models\PropertyAuction;
use App\Models\LandlordAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\SellerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\BuyerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;
use App\Observers\Dna\BuyerCriteriaAuctionDnaObserver;
use App\Observers\Dna\BuyerTenantDnaProfileCompatibilityObserver;
use App\Observers\Dna\LandlordAuctionDnaObserver;
use App\Observers\Dna\PropertyAuctionDnaObserver;
use App\Observers\Dna\PropertyDnaProfileCompatibilityObserver;
use App\Observers\Dna\TenantCriteriaAuctionDnaObserver;
use App\Observers\Dna\SellerAgentAuctionDnaScoreObserver;
use App\Observers\Dna\LandlordAgentAuctionDnaScoreObserver;
use App\Observers\Dna\BuyerAgentAuctionDnaScoreObserver;
use App\Observers\Dna\TenantAgentAuctionDnaScoreObserver;
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
use App\Services\LocationDna\LocationDnaEnrichmentRunner;
use App\Services\LocationDna\LocationIntelligenceComposer;
use App\Services\LocationDna\LocationIntelligenceSummaryService;
use App\Services\LocationDna\LocationPreferenceAnalyzer;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Services\LocationDna\Providers\LocationProviderRegistry;
use App\Services\LocationDna\SchoolDistrictLookupService;
use App\Services\Dna\Relevance\CandidateSourceInterface;
use App\Services\Dna\Relevance\ScoredEntityCandidateSource;
use App\Services\Dna\Relevance\CandidateAttributeResolverInterface;
use App\Services\Dna\Relevance\OnPlatformCandidateAttributeResolver;
use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Matching V2 — candidate discovery (consumption slice 2). The default
        // candidate source resolves the provider-agnostic universe from the unified
        // dna_scores layer. Bound behind the interface so future DNA-enabled sources
        // are additive without touching CandidateDiscoveryService.
        $this->app->bind(CandidateSourceInterface::class, ScoredEntityCandidateSource::class);

        // Matching V2 — candidate narrowing (slice 2B). The default attribute resolver
        // reads the on-platform *_agent auction tables; provider-specific reads are
        // isolated here so the narrowers stay provider-agnostic.
        $this->app->bind(CandidateAttributeResolverInterface::class, OnPlatformCandidateAttributeResolver::class);

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

        // Outbound Guzzle client for every server-side Google caller.
        // LocationDnaPoiDistanceService (Path A), GooglePlacesPoiAdapter (Path B),
        // and LocationDnaGeocodeService all resolve their HTTP client from this
        // binding — no bare `new Client()` remains in any of them — so tests can
        // bind a fake/blocking client and no live call can slip through unmocked.
        // (See docs/investigations/Google-Places-Root-Cause-Analysis.md.)
        //
        // Phase 0 / S3a: the handler stack carries GoogleOutboundTelemetryMiddleware,
        // which records endpoint, HTTP status, and Google's in-body `status` for every
        // request to maps.googleapis.com. Google answers an invalid or revoked key with
        // HTTP 200 + {"status":"REQUEST_DENIED"}, so the body — not the HTTP status — is
        // what reveals the credential's true state (SIA-D32: telemetry, never a probe).
        $this->app->bind(ClientInterface::class, function () {
            $stack = HandlerStack::create();
            $stack->push(GoogleOutboundTelemetryMiddleware::make(), 'byo_google_outbound_telemetry');

            return new Client(['handler' => $stack]);
        });

        // POI Distance Lookup — Buyer/Tenant search-area geometry (Phase 3C)
        // Stage E: the active POI adapter is now selected via the provider registry
        // (config/location_providers.php), which supersedes the legacy
        // location_dna.poi.provider flag (kept but no longer read). With the current
        // config only google_places is enabled, so effectiveBase('poi.default')
        // resolves to google_places → GooglePlacesPoiAdapter when the Places key is
        // present, else StubPoiLookupAdapter — behaviourally identical to before.
        // Bound (not singleton) so config changes in tests always produce a fresh instance.
        $this->app->bind(PoiLookupAdapterInterface::class, function ($app) {
            $registry = new LocationProviderRegistry((array) config('location_providers', []));
            $base     = $registry->effectiveBase('poi.default');

            if (
                ($base['provider'] ?? null) === 'google_places'
                && !blank(config('services.google.places_key'))
            ) {
                return new GooglePlacesPoiAdapter();
            }

            return new StubPoiLookupAdapter();
        });

        $this->app->bind(PoiDistanceLookupService::class, function ($app) {
            return new PoiDistanceLookupService($app->make(PoiLookupAdapterInterface::class));
        });

        $this->app->bind(LocationDnaEnrichmentRunner::class, function ($app) {
            return new LocationDnaEnrichmentRunner(
                $app->make(FloodZoneLookupService::class),
                $app->make(SchoolDistrictLookupService::class),
                $app->make(PoiDistanceLookupService::class),
                $app->make(CommuteTimeLookupService::class),
            );
        });

        $this->app->bind(LocationIntelligenceComposer::class, function ($app) {
            return new LocationIntelligenceComposer(
                $app->make(LocationDnaEnrichmentRunner::class),
                $app->make(LocationIntelligenceSummaryService::class),
                $app->make(LocationPreferenceAnalyzer::class),
            );
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
                (bool) config('ask_ai.enable_description_fallback', false),
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

        // Phase 13 — production dna_scores generation. These observers fire on
        // every save of the four *_agent listing types (the OfferListing flow,
        // the Hire-an-Agent wizards, and MLS imports all funnel through a model
        // save), dispatching ComputeDnaScores behind the default-off master flag
        // (config dna_scores.generation_enabled). Additive and inert until the
        // owner enables generation; independent of Matching V2.
        SellerAgentAuction::observe(SellerAgentAuctionDnaScoreObserver::class);
        LandlordAgentAuction::observe(LandlordAgentAuctionDnaScoreObserver::class);
        BuyerAgentAuction::observe(BuyerAgentAuctionDnaScoreObserver::class);
        TenantAgentAuction::observe(TenantAgentAuctionDnaScoreObserver::class);

        // Phase F — Compatibility observers.
        // These observers hook PropertyDnaProfile and BuyerTenantDnaProfile saves to dispatch
        // ComputeCompatibilityScore jobs for active counterpart profiles (seller ↔ buyer,
        // landlord ↔ tenant). They never dispatch DNA generation jobs and never trigger
        // additional DNA generation. Dispatch is capped at FANOUT_CAP per invocation.
        PropertyDnaProfile::observe(PropertyDnaProfileCompatibilityObserver::class);
        BuyerTenantDnaProfile::observe(BuyerTenantDnaProfileCompatibilityObserver::class);

        // Boot-time guard: warn immediately when the Google Maps/Places API key is absent.
        // The key MUST be set in .env (not only as a Replit platform secret) because phpdotenv
        // reads only .env at startup — platform secrets are not injected into the workflow process.
        // Missing key → x-google-maps-script emits an amber warning div instead of the <script>
        // tag, breaking address autocomplete on seller/landlord/offers pages and the drawing map
        // on buyer/tenant pages.
        if (empty(config('services.google.places_key'))) {
            Log::warning(
                '[BYO] GOOGLE_PLACES_API_KEY is not set. ' .
                'Address autocomplete and the property-preference map will not work. ' .
                'Add GOOGLE_PLACES_API_KEY to .env (not only as a Replit platform secret).'
            );
        }
    }
}
