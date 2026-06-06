<?php

namespace Tests\Feature;

use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiRunnerV2Service;
use Tests\TestCase;

/**
 * AskAiContainerBindingTest
 *
 * Laravel feature test verifying that AskAiRunnerV2Service resolves from the
 * container with a non-null AskAiIntentNormalizerService injected.
 *
 * The explicit binding added to AppServiceProvider::register() ensures the
 * nullable ?AskAiIntentNormalizerService parameter is always wired — without
 * it, Laravel's auto-wiring leaves nullable parameters as null, silently
 * disabling intent normalization even when the feature flag is enabled.
 *
 * Test coverage:
 *   A. Container resolves AskAiRunnerV2Service without throwing.
 *   B. The resolved instance is the correct class.
 *   C. The normalizer private property is non-null (verified via reflection).
 *   D. The normalizer is an instance of AskAiIntentNormalizerService.
 *   E. AppServiceProvider source file contains the explicit binding.
 */
class AskAiContainerBindingTest extends TestCase
{
    private function providerFilePath(): string
    {
        return base_path('app/Providers/AppServiceProvider.php');
    }

    private function runnerFilePath(): string
    {
        return base_path('app/Services/AskAi/AskAiRunnerV2Service.php');
    }

    /**
     * Resolve AskAiRunnerV2Service from the container and return it.
     */
    private function resolveRunner(): AskAiRunnerV2Service
    {
        return $this->app->make(AskAiRunnerV2Service::class);
    }

    // =========================================================================
    // Case A — Container resolves the runner without throwing
    // =========================================================================

    public function test_case_A_container_resolves_runner_without_throwing(): void
    {
        $runner = null;
        $exception = null;

        try {
            $runner = $this->resolveRunner();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Resolving AskAiRunnerV2Service should not throw: ' . ($exception?->getMessage() ?? ''));
        $this->assertNotNull($runner);
    }

    // =========================================================================
    // Case B — Resolved instance is the correct class
    // =========================================================================

    public function test_case_B_resolved_instance_is_correct_class(): void
    {
        $runner = $this->resolveRunner();

        $this->assertInstanceOf(AskAiRunnerV2Service::class, $runner);
    }

    // =========================================================================
    // Case C — Normalizer private property is non-null (PHP reflection)
    // =========================================================================

    public function test_case_C_normalizer_private_property_is_non_null(): void
    {
        $runner = $this->resolveRunner();

        $reflection = new \ReflectionClass($runner);
        $property   = $reflection->getProperty('normalizer');
        $property->setAccessible(true);
        $normalizerValue = $property->getValue($runner);

        $this->assertNotNull(
            $normalizerValue,
            'AskAiRunnerV2Service::$normalizer must be non-null when resolved from the container. '
            . 'Check that AppServiceProvider::register() explicitly binds the normalizer.'
        );
    }

    // =========================================================================
    // Case D — The injected normalizer is AskAiIntentNormalizerService
    // =========================================================================

    public function test_case_D_injected_normalizer_is_correct_class(): void
    {
        $runner = $this->resolveRunner();

        $reflection = new \ReflectionClass($runner);
        $property   = $reflection->getProperty('normalizer');
        $property->setAccessible(true);
        $normalizerValue = $property->getValue($runner);

        $this->assertInstanceOf(
            AskAiIntentNormalizerService::class,
            $normalizerValue,
            'The injected normalizer must be an instance of AskAiIntentNormalizerService.'
        );
    }

    // =========================================================================
    // Case E — AppServiceProvider source contains the explicit binding
    // =========================================================================

    public function test_case_E_app_service_provider_contains_explicit_runner_binding(): void
    {
        $content = file_get_contents($this->providerFilePath());

        $this->assertStringContainsString(
            'AskAiRunnerV2Service::class',
            $content,
            'AppServiceProvider must contain an explicit binding for AskAiRunnerV2Service'
        );
    }

    public function test_case_E_app_service_provider_binds_intent_normalizer_service(): void
    {
        $content = file_get_contents($this->providerFilePath());

        $this->assertStringContainsString(
            'AskAiIntentNormalizerService::class',
            $content,
            'AppServiceProvider binding must explicitly resolve AskAiIntentNormalizerService'
        );
    }

    public function test_case_E_runner_service_file_normalizer_property_is_nullable(): void
    {
        $content = file_get_contents($this->runnerFilePath());

        $this->assertStringContainsString(
            '?AskAiIntentNormalizerService',
            $content,
            'AskAiRunnerV2Service must keep the ?AskAiIntentNormalizerService nullable type hint '
            . 'so unit tests can pass null explicitly without breaking the constructor signature.'
        );
    }
}
