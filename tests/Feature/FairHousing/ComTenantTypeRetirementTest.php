<?php

namespace Tests\Feature\FairHousing;

use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fair Housing P0-C — `listing_ai_faq.com_tenant_type` is retired.
 *
 * THE QUESTION. "What type of tenants are preferred?" — a free-text provider preference
 * about who occupies a property, collected on the legacy Buyer Criteria create and edit
 * forms as `listing_ai_faq_com_tenant_type` and folded into the `listing_ai_faq` blob by
 * BuyerCriteriaAuctionController's accepted-key list. It is the same category of question
 * as the already-retired `tenant_type_preference`, and it was still actively writable.
 *
 * NO REPLACEMENT. The objective commercial concepts that a legitimate listing actually
 * needs — `com_property_use` ("what use is intended") and `com_zoning` ("what zoning is
 * acceptable") — already exist and are untouched. Asking what *type of tenant* is wanted
 * adds nothing those two do not cover, and the thing it adds is the part that is unlawful.
 * A test below pins that the two legitimate keys survived, so this retirement cannot be
 * "restored" by reintroducing an occupant question under a new name and calling it parity.
 *
 * STALE VALUES ARE INERT WITHOUT DATA REMEDIATION. Historical rows may still hold the key.
 * Two independent mechanisms make it unreachable, and both are asserted here:
 *   1. Ask AI — the P0-B admission boundary drops it (it is not in the config SSOT).
 *   2. Public display — the listing-AI knowledge-base component iterates the CONFIG
 *      questions and looks each one up in the stored blob. A stored key that no config
 *      names is never looked up, so it cannot render.
 * No rows are deleted by this branch.
 */
class ComTenantTypeRetirementTest extends TestCase
{
    use DatabaseTransactions;

    private const RETIRED_KEY = 'com_tenant_type';

    // =====================================================================
    // Gone from the write paths
    // =====================================================================

    /** @test */
    public function buyer_criteria_create_form_no_longer_offers_the_question(): void
    {
        $blade = file_get_contents(base_path('resources/views/buyer_criteria/add.blade.php'));

        $this->assertStringNotContainsString(self::RETIRED_KEY, $blade);
        $this->assertStringNotContainsString('What type of tenants are preferred?', $blade);
    }

    /** @test */
    public function buyer_criteria_edit_form_no_longer_offers_the_question(): void
    {
        $blade = file_get_contents(base_path('resources/views/buyer_criteria/edit.blade.php'));

        $this->assertStringNotContainsString(self::RETIRED_KEY, $blade);
        $this->assertStringNotContainsString('What type of tenants are preferred?', $blade);
    }

    /** @test */
    public function the_controller_accepted_key_list_no_longer_contains_it_on_create_or_update(): void
    {
        // The controller builds the blob from a fixed allowlist:
        //     foreach ($aiFaqKeys as $key) { $aiFaqData[$key] = $request->input('listing_ai_faq_'.$key); }
        // Removing the key from that list is what makes a hand-posted field inert — the
        // input is simply never read, on either storeAuction() or updateAuction().
        $source = file_get_contents(base_path('app/Http/Controllers/BuyerCriteriaAuctionController.php'));

        $this->assertStringNotContainsString(self::RETIRED_KEY, $source);
    }

    /** @test */
    public function a_hand_posted_field_cannot_persist_because_the_key_is_not_read(): void
    {
        // Structural proof of the above: the allowlist loop is still the only way a value
        // enters the blob, so a key absent from the list has no path in.
        $source = file_get_contents(base_path('app/Http/Controllers/BuyerCriteriaAuctionController.php'));

        $this->assertStringContainsString("\$fieldName = 'listing_ai_faq_' . \$key;", $source);
        $this->assertStringContainsString('foreach ($aiFaqKeys as $key)', $source);
    }

    /** @test */
    public function it_is_registered_in_no_ask_ai_config(): void
    {
        foreach (['ai_faq_seller', 'ai_faq_buyer', 'ai_faq_landlord', 'tenant_ai_faq'] as $configKey) {
            $raw = file_get_contents(base_path("config/{$configKey}.php"));
            $this->assertStringNotContainsString(self::RETIRED_KEY, $raw);
        }
    }

    /** @test */
    public function the_legitimate_commercial_questions_are_preserved(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/BuyerCriteriaAuctionController.php'));

        foreach (['com_property_use', 'com_zoning'] as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "{$key} is an objective commercial question and must not be removed alongside the occupant-type retirement."
            );
        }
    }

    // =====================================================================
    // A stale stored value is inert
    // =====================================================================

    /** @test */
    public function a_stale_stored_value_cannot_reach_ask_ai_context(): void
    {
        $listing = new class {
            public int $id = 999998;

            public function info(string $key)
            {
                // A row written before the retirement.
                return $key === 'listing_ai_faq'
                    ? json_encode(['com_tenant_type' => 'Young professionals, no families.'])
                    : null;
            }
        };

        $service = app(AskAiContextBuilderService::class);
        $method  = new ReflectionMethod(AskAiContextBuilderService::class, 'buildFaqAnswers');
        $method->setAccessible(true);

        $answers = $method->invoke($service, $listing, 'buyer');

        $this->assertArrayNotHasKey(self::RETIRED_KEY, $answers);
        $this->assertSame([], $answers);
    }

    /** @test */
    public function the_public_knowledge_base_component_renders_only_config_declared_questions(): void
    {
        // This is what makes a stale value invisible on the public page without deleting a
        // single row: the component walks the CONFIG and looks each declared key up in the
        // stored blob ($aiFaq[$key]), rather than walking the blob and printing what it
        // finds. A retired key is therefore never asked for.
        $blade = file_get_contents(base_path('resources/views/components/listing-ai-knowledge-base.blade.php'));

        $this->assertStringContainsString('$val = trim($aiFaq[$key] ?? \'\');', $blade);
        $this->assertStringNotContainsString('foreach ($aiFaq as', $blade);
    }
}
