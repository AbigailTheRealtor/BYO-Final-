<?php

namespace Tests\Feature\FairHousing;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFaqEnrichmentService;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fair Housing P0-B — only recognised FAQ keys may enter Ask AI context.
 *
 * WHAT THE DEFECT WAS. `buildFaqAnswers()` looked each stored key up in the config index
 * and, on a miss, wrote `?? ['question_group' => null, 'question_label' => null, ...]` —
 * admitting the key with null metadata instead of rejecting it. That was the "umbrella".
 * Nothing downstream closed it:
 *
 *   - AskAiViewerAuthorizationService returns early for every non-tenant role, and for
 *     tenant strips only an ENUMERATED list of applicant-sensitive keys. A deny-list
 *     cannot catch a key nobody enumerated.
 *   - AskAiPromptBuilderService::sanitizeFaqAnswers() filters the FIELDS WITHIN an entry
 *     against FAQ_SAFE_FIELDS — and `answer_text` is on that list. It never filters which
 *     keys are present.
 *
 * And the source is untrusted: `listing_ai_faq` is a `public array` Livewire property
 * persisted verbatim (`saveMeta('listing_ai_faq', json_encode($this->listing_ai_faq))`) by
 * all eight Create Offer components, so a client can put any key at any path into it. The
 * net effect was that an arbitrary key — `com_tenant_type`, "What type of tenants are
 * preferred?" — reached LLM prompt context.
 *
 * WHY THE GATE IS HERE AND NOT AT THE OUTPUT. Sanitising generated text would mean asking
 * the model not to repeat something it had already been told. The only durable fix is to
 * not tell it: admission is an INTERSECTION against the config SSOT, so a key survives by
 * being named in config/ai_faq_*.php, never by failing to appear on a deny-list.
 *
 * These tests exercise the real service against the real config files.
 */
class AskAiFaqAdmissionBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Invoke the protected builder against a listing stub.
     *
     * The stub only has to satisfy what buildFaqAnswers() actually touches: `info()` for
     * the EAV blob and `id` for the DB fallback. Using the real service (resolved from the
     * container, with its real collaborators) keeps this a test of production behaviour.
     */
    private function buildFaqAnswers(array $storedBlob, string $role): array
    {
        $listing = new class($storedBlob) {
            public int $id = 999999;
            private array $blob;

            public function __construct(array $blob)
            {
                $this->blob = $blob;
            }

            public function info(string $key)
            {
                return $key === 'listing_ai_faq' ? json_encode($this->blob) : null;
            }
        };

        $service = app(AskAiContextBuilderService::class);
        $method  = new ReflectionMethod(AskAiContextBuilderService::class, 'buildFaqAnswers');
        $method->setAccessible(true);

        return $method->invoke($service, $listing, $role);
    }

    /** A key that genuinely exists in the role's config, so the "survives" case is real. */
    private function firstRegisteredKey(string $role): string
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex($role);
        $this->assertNotEmpty($index, "No registered FAQ keys for role {$role}; the SSOT did not load.");

        return (string) array_key_first($index);
    }

    // =====================================================================
    // Registered keys survive
    // =====================================================================

    /** @test */
    public function a_registered_faq_key_survives_admission(): void
    {
        $key = $this->firstRegisteredKey('buyer');

        $answers = $this->buildFaqAnswers([$key => 'A genuine answer.'], 'buyer');

        $this->assertArrayHasKey($key, $answers);
        $this->assertSame('A genuine answer.', $answers[$key]['answer_text']);
    }

    /** @test */
    public function a_registered_key_still_carries_its_config_metadata(): void
    {
        $key = $this->firstRegisteredKey('buyer');

        $answers = $this->buildFaqAnswers([$key => 'A genuine answer.'], 'buyer');

        // Metadata now always comes from the SSOT, never from the null placeholder.
        $this->assertNotNull($answers[$key]['question_group']);
        $this->assertSame($key, $answers[$key]['config_key']);
    }

    /** @test */
    public function registered_keys_for_every_role_survive_admission(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $key     = $this->firstRegisteredKey($role);
            $answers = $this->buildFaqAnswers([$key => 'Answer for ' . $role], $role);

            $this->assertArrayHasKey($key, $answers, "Role {$role} lost a registered FAQ key.");
        }
    }

    // =====================================================================
    // Unregistered keys are dropped
    // =====================================================================

    /** @test */
    public function com_tenant_type_cannot_reach_prompt_context(): void
    {
        $answers = $this->buildFaqAnswers([
            'com_tenant_type' => 'No families with children. Young professionals preferred.',
        ], 'buyer');

        $this->assertArrayNotHasKey('com_tenant_type', $answers);
        $this->assertSame([], $answers);
    }

    /** @test */
    public function com_tenant_type_is_dropped_for_every_role(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $answers = $this->buildFaqAnswers(['com_tenant_type' => 'Students only'], $role);

            $this->assertArrayNotHasKey('com_tenant_type', $answers, "Role {$role} admitted com_tenant_type.");
        }
    }

    /** @test */
    public function an_arbitrary_crafted_key_is_dropped(): void
    {
        $answers = $this->buildFaqAnswers([
            'totally_made_up_key_2026'       => 'No Section 8.',
            'preferred_tenant_demographic'   => 'No children.',
            'listing_ai_faq_com_tenant_type' => 'Injected via the form field name.',
        ], 'landlord');

        $this->assertSame([], $answers);
    }

    /** @test */
    public function a_crafted_key_alongside_registered_keys_is_dropped_without_taking_them_with_it(): void
    {
        $registered = $this->firstRegisteredKey('buyer');

        $answers = $this->buildFaqAnswers([
            $registered       => 'Legitimate answer.',
            'com_tenant_type' => 'No families with children.',
            'another_injected' => 'Adults only.',
        ], 'buyer');

        $this->assertArrayHasKey($registered, $answers);
        $this->assertArrayNotHasKey('com_tenant_type', $answers);
        $this->assertArrayNotHasKey('another_injected', $answers);
        $this->assertCount(1, $answers);
    }

    /** @test */
    public function a_nested_crafted_structure_cannot_smuggle_a_key_through(): void
    {
        // Livewire lets a client set a nested path such as
        // listing_ai_faq.com_tenant_type.value, so the stored blob is not guaranteed to be
        // flat scalars. A nested key is still just an unregistered key and must be dropped
        // before the value shape ever matters.
        $answers = $this->buildFaqAnswers([
            'com_tenant_type' => ['value' => 'No families', 'nested' => ['deeper' => 'No children']],
        ], 'landlord');

        $this->assertSame([], $answers);
    }

    /** @test */
    public function an_unknown_role_admits_nothing(): void
    {
        // Fail closed: no config index means no admissions, not unfiltered admissions.
        $registered = $this->firstRegisteredKey('buyer');

        $answers = $this->buildFaqAnswers([$registered => 'x', 'com_tenant_type' => 'y'], 'not_a_role');

        $this->assertSame([], $answers);
    }

    // =====================================================================
    // The gate is in the builder, not in output text handling
    // =====================================================================

    /** @test */
    public function the_admission_check_exists_in_the_context_builder(): void
    {
        $source = file_get_contents(base_path('app/Services/AskAi/AskAiContextBuilderService.php'));

        $this->assertStringContainsString(
            'if (! array_key_exists($qKey, $configIndex)) {',
            $source,
            'The admission boundary must live in buildFaqAnswers(), not in output sanitisation.'
        );
        $this->assertStringNotContainsString(
            "\$meta = \$configIndex[\$qKey] ?? [",
            $source,
            'The null-metadata fallback is the umbrella itself and must not return.'
        );
    }

    // =====================================================================
    // Pre-existing protections still work
    // =====================================================================

    /** @test */
    public function tenant_applicant_sensitive_faq_stripping_still_applies_to_public_viewers(): void
    {
        $context = ['faq_answers' => [
            'faq_q20'          => ['config_key' => 'faq_q20', 'answer_text' => 'Sensitive.'],
            'some_other_key'   => ['config_key' => 'some_other_key', 'answer_text' => 'Fine.'],
        ]];

        $redacted = app(AskAiViewerAuthorizationService::class)
            ->redactContext($context, 'tenant', AskAiViewerAuthorizationService::SCOPE_PUBLIC);

        $this->assertArrayNotHasKey('faq_q20', $redacted['faq_answers']);
        $this->assertArrayHasKey('some_other_key', $redacted['faq_answers']);
    }

    /** @test */
    public function tenant_applicant_sensitive_faq_survives_for_an_authorized_viewer(): void
    {
        $context = ['faq_answers' => [
            'faq_q20' => ['config_key' => 'faq_q20', 'answer_text' => 'Sensitive.'],
        ]];

        $redacted = app(AskAiViewerAuthorizationService::class)
            ->redactContext($context, 'tenant', AskAiViewerAuthorizationService::SCOPE_AUTHORIZED);

        $this->assertArrayHasKey('faq_q20', $redacted['faq_answers']);
    }

    /** @test */
    public function legitimate_accessibility_and_consumer_faq_content_is_not_collateral_damage(): void
    {
        // The gate must not be so blunt that it drops the things the product exists to
        // communicate. Every registered key in every role config is still admissible.
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $index = AskAiFaqEnrichmentService::buildConfigIndex($role);
            $blob  = [];
            foreach (array_keys($index) as $key) {
                $blob[$key] = 'answered';
            }

            $answers = $this->buildFaqAnswers($blob, $role);

            $this->assertCount(
                count($index),
                $answers,
                "Role {$role}: the gate dropped registered keys it should have admitted."
            );
        }
    }
}
