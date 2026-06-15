<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AgentAi\AgentAiContextScopeResolver;
use App\Services\AgentAi\AgentAiContextBuilder;
use App\Services\AgentAi\AgentAiContextSourceRegistry;
use App\Services\AgentAi\AgentAiPromptBuilder;
use App\Services\AgentAi\AgentAiOpenAiOrchestrator;
use App\Services\AgentAi\AgentAiFinalResponseBuilder;
use App\Services\AgentAi\AgentAiLeadCaptureService;
use App\Services\AgentAi\AgentAiLeadScoringService;
use App\Services\AgentAi\AgentAiActionResolver;
use App\Services\AgentAi\AgentAiPermissionGuard;
use App\Services\AgentAi\AgentAiNotificationService;
use App\Services\AgentAi\AgentAiEscalationService;

class AgentAiServiceProvider extends ServiceProvider
{
    /**
     * Register Agent AI V2 service bindings.
     *
     * All 12 V2 service classes are bound as singletons so the registry
     * instance is shared across the request lifecycle. Loaders registered
     * in Build 2 will add themselves to the shared registry instance.
     *
     * Build 1: All service method bodies throw \RuntimeException('Not implemented').
     * Build 2+: Replace stub bodies with real implementations.
     */
    public function register(): void
    {
        $this->app->singleton(AgentAiContextSourceRegistry::class);

        $this->app->singleton(AgentAiContextScopeResolver::class);

        $this->app->singleton(AgentAiContextBuilder::class, function ($app) {
            return new AgentAiContextBuilder(
                $app->make(AgentAiContextSourceRegistry::class),
            );
        });

        $this->app->singleton(AgentAiPromptBuilder::class);

        $this->app->singleton(AgentAiOpenAiOrchestrator::class);

        $this->app->singleton(AgentAiFinalResponseBuilder::class);

        $this->app->singleton(AgentAiLeadCaptureService::class);

        $this->app->singleton(AgentAiLeadScoringService::class);

        $this->app->singleton(AgentAiActionResolver::class);

        $this->app->singleton(AgentAiPermissionGuard::class);

        $this->app->singleton(AgentAiNotificationService::class);

        $this->app->singleton(AgentAiEscalationService::class);
    }

    public function boot(): void
    {
        // Build 2: Register concrete context loaders here via AgentAiContextSourceRegistry.
    }
}
