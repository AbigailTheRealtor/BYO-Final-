<?php

namespace App\Providers;

use App\Enums\AgentAiContextScope;

use App\Services\AgentAi\Loaders\AgentPresetLoader;
use App\Services\AgentAi\Loaders\AgentProfileLoader;
use App\Services\AgentAi\Loaders\BuyerCriteriaLoader;
use App\Services\AgentAi\Loaders\ExtendedKnowledgeLoader;
use App\Services\AgentAi\Loaders\LandlordListingLoader;
use App\Services\AgentAi\Loaders\SellerListingLoader;
use App\Services\AgentAi\Loaders\TenantCriteriaLoader;

use App\Services\AgentAi\AgentAiContextBuilder;
use App\Services\AgentAi\AgentAiContextSourceRegistry;
use App\Services\AgentAi\AgentAiPermissionGuard;

use App\Services\AgentAi\AgentAiActionResolver;
use App\Services\AgentAi\AgentAiContextScopeResolver;
use App\Services\AgentAi\AgentAiEscalationService;
use App\Services\AgentAi\AgentAiFinalResponseBuilder;
use App\Services\AgentAi\AgentAiLeadCaptureService;
use App\Services\AgentAi\AgentAiLeadScoringService;
use App\Services\AgentAi\AgentAiNotificationService;
use App\Services\AgentAi\AgentAiOpenAiOrchestrator;
use App\Services\AgentAi\AgentAiPromptBuilder;

use Illuminate\Support\ServiceProvider;

/**
 * AgentAiServiceProvider
 *
 * Registers and boots all Agent AI V2 services and context loaders.
 *
 * ┌─────────────────────────────────────────────────────────┐
 * │  BUILD SCOPE GUARD — what this provider MUST NOT do     │
 * ├─────────────────────────────────────────────────────────┤
 * │  ✗ No V1 Ask AI execution path modified                 │
 * │  ✗ No OpenAI API calls wired (stubs throw RuntimeEx)    │
 * │  ✗ No UI routes or Blade views registered               │
 * │  ✗ No lead capture persistence executed                 │
 * │  ✗ No OCR, document parsing, MLS persistence,          │
 * │    embeddings, or knowledge-library storage             │
 * └─────────────────────────────────────────────────────────┘
 *
 * Build history:
 *   Build 1 — Service class stubs bound; all method bodies throw RuntimeException.
 *   Build 2 — 7 context loaders registered; AgentAiContextBuilder::buildForScope()
 *              and AgentAiPermissionGuard::validateAgentScope() implemented.
 *              Three future loaders reserved as gap contracts (see boot()).
 *   Build 3 — AgentAiPermissionGuard::check(), AgentAiPromptBuilder::build(),
 *              AgentAiOpenAiOrchestrator::call(), AgentAiFinalResponseBuilder::build()
 *              all implemented. agent_ai_chat_sessions and agent_ai_chat_messages
 *              tables migrated. Full conversation pipeline wired in controller.
 *   Build 4 — AgentAiActionResolver implemented. Actions envelope added to every
              response. Inline view_agent_services handler bypasses OpenAI.
              AgentAiFinalResponseBuilder now takes AgentAiActionResolver via DI.
 */
class AgentAiServiceProvider extends ServiceProvider
{
    /**
     * Register Agent AI V2 service bindings.
     *
     * All V2 service classes are bound as singletons so the registry instance
     * is shared across the request lifecycle. Build 3+ stubs are registered
     * here so the container can resolve them (returning the stub that throws
     * RuntimeException) rather than producing a BindingResolutionException.
     */
    public function register(): void
    {
        // ── Build 2: active services ─────────────────────────────────────────
        $this->app->singleton(AgentAiContextSourceRegistry::class);

        $this->app->singleton(AgentAiPermissionGuard::class);

        $this->app->singleton(AgentAiContextBuilder::class, function ($app) {
            return new AgentAiContextBuilder(
                $app->make(AgentAiContextSourceRegistry::class),
                $app->make(AgentAiPermissionGuard::class),
            );
        });

        // ── Build 3: fully implemented services ──────────────────────────────
        // AgentAiPromptBuilder, AgentAiOpenAiOrchestrator, AgentAiFinalResponseBuilder,
        // and AgentAiPermissionGuard::check() are all implemented in Build 3.
        // AgentAiChatSession and AgentAiChatMessage models are also new in Build 3.
        $this->app->singleton(AgentAiContextScopeResolver::class);
        $this->app->singleton(AgentAiPromptBuilder::class);
        $this->app->singleton(AgentAiOpenAiOrchestrator::class);
        $this->app->singleton(AgentAiFinalResponseBuilder::class);

        // ── Build 4: CTA / Action Resolver (implemented) ─────────────────────
        // AgentAiActionResolver is implemented. AgentAiFinalResponseBuilder now
        // depends on it via constructor injection; Laravel auto-resolves it.
        $this->app->singleton(AgentAiActionResolver::class);

        // ── Build 5+ stubs (not yet implemented) ─────────────────────────────
        $this->app->singleton(AgentAiLeadCaptureService::class);
        $this->app->singleton(AgentAiLeadScoringService::class);
        $this->app->singleton(AgentAiNotificationService::class);
        $this->app->singleton(AgentAiEscalationService::class);
    }

    /**
     * Boot: Register all Build 2 context loaders with the shared registry.
     *
     * Registration order and priorities are taken from
     * docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md and the Build 2 spec.
     *
     * Priority semantics (from AgentAiContextSourceRegistry):
     *   Lower numbers run first. For token-budget truncation in
     *   AgentAiContextBuilder::buildForScope(), the SOURCE_KEY_RETENTION map
     *   governs drop order independently of registration priority.
     *
     * ┌──────────────────────────────┬──────────┬──────────────────────────────────────┐
     * │ Loader                       │ Priority │ Scopes                               │
     * ├──────────────────────────────┼──────────┼──────────────────────────────────────┤
     * │ ExtendedKnowledgeLoader      │ 60       │ seller, landlord, buyer, tenant       │
     * │ AgentPresetLoader            │ 70       │ all five scopes                       │
     * │ AgentProfileLoader           │ 80       │ all five scopes                       │
     * │ SellerListingLoader          │ 100      │ public_listing_seller                 │
     * │ LandlordListingLoader        │ 100      │ public_listing_landlord               │
     * │ BuyerCriteriaLoader          │ 100      │ buyer_criteria                        │
     * │ TenantCriteriaLoader         │ 100      │ tenant_criteria                       │
     * ├──────────────────────────────┼──────────┼──────────────────────────────────────┤
     * │ RESERVED — not yet implemented (requires underlying public-safe data stores)   │
     * │ UploadedDocumentLoader       │ TBD      │ all listing scopes (gap — #2803)      │
     * │ MlsImportSnapshotLoader      │ TBD      │ seller, landlord (gap — #2803)        │
     * │ KnowledgeDocumentLoader      │ TBD      │ all listing scopes (gap — #2803)      │
     * └──────────────────────────────┴──────────┴──────────────────────────────────────┘
     *
     * Reserved-loader contracts (#2803):
     *   UploadedDocumentLoader — will surface public-safe excerpts from agent-uploaded
     *     documents attached to a listing. Blocked on: public-safe document store +
     *     text-extraction pipeline.
     *   MlsImportSnapshotLoader — will expose the read-only MLS import snapshot
     *     (field_map + parsed values) for a listing. Blocked on: dedicated
     *     agent_ai_mls_snapshots table with public_allowed column.
     *   KnowledgeDocumentLoader — will surface approved knowledge-base documents
     *     (agent FAQ, area guides, etc.). Blocked on: knowledge_documents table +
     *     approval workflow.
     *
     *   None of the reserved loaders produce OpenAI calls, OCR, embeddings, or
     *   write to any table. They are read-only context providers gated on
     *   public_allowed=true in their respective stores.
     */
    public function boot(): void
    {
        /** @var AgentAiContextSourceRegistry $registry */
        $registry = $this->app->make(AgentAiContextSourceRegistry::class);

        $allListingScopes = [
            AgentAiContextScope::PublicListingSeller,
            AgentAiContextScope::PublicListingLandlord,
            AgentAiContextScope::BuyerCriteria,
            AgentAiContextScope::TenantCriteria,
        ];

        $allScopes = [
            AgentAiContextScope::PublicListingSeller,
            AgentAiContextScope::PublicListingLandlord,
            AgentAiContextScope::BuyerCriteria,
            AgentAiContextScope::TenantCriteria,
            AgentAiContextScope::AgentProfile,
        ];

        // ── Active Build 2 loaders ────────────────────────────────────────────

        $registry->register(
            'extended_knowledge',
            $allListingScopes,
            ExtendedKnowledgeLoader::PRIORITY,
            new ExtendedKnowledgeLoader()
        );

        $registry->register(
            'agent_presets',
            $allScopes,
            AgentPresetLoader::PRIORITY,
            new AgentPresetLoader()
        );

        $registry->register(
            'agent_profile',
            $allScopes,
            AgentProfileLoader::PRIORITY,
            new AgentProfileLoader()
        );

        $registry->register(
            'listing_core',
            [AgentAiContextScope::PublicListingSeller],
            SellerListingLoader::PRIORITY,
            new SellerListingLoader()
        );

        $registry->register(
            'listing_core',
            [AgentAiContextScope::PublicListingLandlord],
            LandlordListingLoader::PRIORITY,
            new LandlordListingLoader()
        );

        $registry->register(
            'listing_core',
            [AgentAiContextScope::BuyerCriteria],
            BuyerCriteriaLoader::PRIORITY,
            new BuyerCriteriaLoader()
        );

        $registry->register(
            'listing_core',
            [AgentAiContextScope::TenantCriteria],
            TenantCriteriaLoader::PRIORITY,
            new TenantCriteriaLoader()
        );

        // ── Reserved future loaders (NOT registered — blocked on data stores) ─
        //
        // UploadedDocumentLoader::class     — gap from #2803
        // MlsImportSnapshotLoader::class    — gap from #2803
        // KnowledgeDocumentLoader::class    — gap from #2803
        //
        // These will be registered here once their backing tables and
        // public_allowed gating exist. See boot() docblock above for details.
    }
}
