<?php

namespace Tests\Feature\FairHousing;

use App\Models\AiFaqAnswer;
use App\Services\AgentAi\Loaders\ExtendedKnowledgeLoader;
use App\Services\AskAi\AskAiFaqEnrichmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fair Housing P0-B (completion) — the FAQ membership boundary on the second AI path.
 *
 * WHAT THE PRE-PR AUDIT FOUND. Gating AskAiContextBuilderService::buildFaqAnswers() closed
 * Ask AI V1, and missed a second, independent route to a prompt:
 *
 *     listing_ai_faq blob  (untrusted: public Livewire array property on all eight Create
 *          │                Offer components; unfiltered $request->listing_ai_faq on two
 *          │                legacy controllers)
 *          ▼
 *     AskAiFaqEnrichmentService::sync()      ← wrote ANY key: `$index[$k] ?? [nulls]`
 *          ▼
 *     ai_faq_answers row
 *          ▼
 *     ExtendedKnowledgeLoader::loadFaqAnswers()  ← returned every row verbatim
 *          ▼
 *     $content['faq_answers'] → Agent AI V2 prompt context
 *
 * Neither hop consulted the registry, and neither passes through buildFaqAnswers(), so the
 * V1 gate could not see them. `com_tenant_type` — "What type of tenants are preferred?" —
 * could reach the assistant this way.
 *
 * WHY BOTH ENDS ARE GUARDED. The writer guard stops new laundering: once an unregistered
 * key has an `ai_faq_answers` row, a consumer reading the TABLE cannot distinguish it from
 * a curated answer. The reader guard is still required, for two reasons the writer guard
 * cannot cover — rows written before the guard existed, and rows whose question was later
 * RETIRED from config (a retirement leaves rows behind by definition). Guarding only the
 * writer would mean a question stops being asked and keeps being answered.
 *
 * ONE SSOT, THREE GATES. All three read config/ai_faq_{seller,buyer,landlord}.php and
 * config/tenant_ai_faq.php through AskAiFaqEnrichmentService::buildConfigIndex(). No
 * second hand-maintained list: three copies of "which questions are legitimate" would
 * drift, and the copy that drifted would be the one nobody was reading.
 */
class AgentAiFaqMembershipBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private function firstRegisteredKey(string $role): string
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex($role);
        $this->assertNotEmpty($index, "No registered FAQ keys for {$role}; the SSOT did not load.");

        return (string) array_key_first($index);
    }

    /** Drive the real private loader against real rows. */
    private function loadFaqAnswers(string $listingType, int $listingId): array
    {
        $method = new ReflectionMethod(ExtendedKnowledgeLoader::class, 'loadFaqAnswers');
        $method->setAccessible(true);

        return $method->invoke(new ExtendedKnowledgeLoader(), $listingType, $listingId);
    }

    /**
     * Create a REAL listing row of the type sync() resolves, carrying the given FAQ blob.
     *
     * sync() looks the listing up itself through a private match() on the legacy models
     * (PropertyAuction / BuyerCriteriaAuction / LandlordAuction / TenantCriteriaAuction),
     * so there is nothing to stub: the test has to put a real row where the production
     * code will look. That keeps the whole path under test — lookup, blob decode, config
     * index, membership check, write.
     */
    private function seedListingWithFaqBlob(string $role, array $blob): int
    {
        $model = match ($role) {
            'seller'   => \App\Models\PropertyAuction::class,
            'buyer'    => \App\Models\BuyerCriteriaAuction::class,
            'landlord' => \App\Models\LandlordAuction::class,
            'tenant'   => \App\Models\TenantCriteriaAuction::class,
            default    => null,
        };

        $this->assertNotNull($model, "No listing model for role {$role}");

        $listing = new $model();
        $listing->user_id = \App\Models\User::factory()->create()->id;

        // Fill the columns each legacy table declares NOT NULL. These carry no meaning for
        // the boundary under test; they exist so a real row can be inserted.
        if ($role === 'buyer') {
            $listing->buyer_id  = $listing->user_id;
            $listing->max_price = 100000;
            $listing->title     = 'FAQ boundary fixture';
        }

        $listing->save();

        if ($role === 'tenant') {
            // Tenant keeps the blob in a native column; every other role uses EAV meta.
            $listing->listing_ai_faq = json_encode($blob);
            $listing->save();
        } else {
            $listing->saveMeta('listing_ai_faq', json_encode($blob));
        }

        return (int) $listing->id;
    }

    /** Run the real sync() over a real row. */
    private function syncRole(string $role, array $blob): array
    {
        $listingId = $this->seedListingWithFaqBlob($role, $blob);

        return [app(AskAiFaqEnrichmentService::class)->sync($role, $listingId), $listingId];
    }

    // =====================================================================
    // Writer — AskAiFaqEnrichmentService::sync()
    // =====================================================================

    /** @test */
    public function sync_writes_a_registered_faq_key(): void
    {
        // Tenant exercises loadFaqJson()'s native-column branch; the buyer tests below
        // exercise the EAV-meta branch. Both must reach the same membership check.
        $key = $this->firstRegisteredKey('tenant');

        [$result, $listingId] = $this->syncRole('tenant', [$key => 'A genuine tenant answer.']);

        $this->assertContains($key, $result['synced']);
        $this->assertDatabaseHas('ai_faq_answers', [
            'listing_type' => 'tenant',
            'listing_id'   => $listingId,
            'question_key' => $key,
        ]);
    }

    /** @test */
    public function sync_does_not_write_an_unregistered_faq_key(): void
    {
        [$result, $listingId] = $this->syncRole('buyer', ['com_tenant_type' => 'No families with children.']);

        $this->assertNotContains('com_tenant_type', $result['synced']);
        $this->assertContains('com_tenant_type', $result['skipped']);
        $this->assertDatabaseMissing('ai_faq_answers', [
            'listing_type' => 'buyer',
            'listing_id'   => $listingId,
            'question_key' => 'com_tenant_type',
        ]);
    }

    /** @test */
    public function sync_keeps_registered_keys_while_dropping_unregistered_ones(): void
    {
        // Buyer rather than landlord: `landlord_auctions` — the table the legacy
        // LandlordAuction model points at, and the one sync() resolves for that role —
        // has no migration in this schema at all, so no landlord row can be created here.
        // That is a pre-existing quirk of the legacy model, out of scope for this fix; the
        // landlord side of the boundary is covered by the READER tests below, which need
        // only ai_faq_answers rows.
        $key = $this->firstRegisteredKey('buyer');

        [$result, $listingId] = $this->syncRole('buyer', [
            $key                           => 'Legitimate landlord answer.',
            'com_tenant_type'              => 'Adults only.',
            'preferred_tenant_demographic' => 'No children.',
        ]);

        $this->assertSame([$key], $result['synced']);
        $this->assertDatabaseHas('ai_faq_answers', ['listing_id' => $listingId, 'question_key' => $key]);
        $this->assertDatabaseMissing('ai_faq_answers', ['listing_id' => $listingId, 'question_key' => 'com_tenant_type']);
        $this->assertDatabaseMissing('ai_faq_answers', ['listing_id' => $listingId, 'question_key' => 'preferred_tenant_demographic']);
    }

    /** @test */
    public function sync_fails_closed_for_an_unknown_role(): void
    {
        $key = $this->firstRegisteredKey('buyer');

        // An unknown role resolves no listing model at all, so nothing is written and the
        // error is reported rather than the blob being trusted.
        $result = app(AskAiFaqEnrichmentService::class)->sync('not_a_role', 970104);

        $this->assertSame([], $result['synced']);
        $this->assertNotNull($result['error']);
        $this->assertDatabaseMissing('ai_faq_answers', ['listing_id' => 970104]);
    }

    /** @test */
    public function the_writer_no_longer_carries_the_null_metadata_umbrella(): void
    {
        $source = file_get_contents(base_path('app/Services/AskAi/AskAiFaqEnrichmentService.php'));

        $this->assertStringContainsString(
            'if (! array_key_exists($configKey, $index)) {',
            $source
        );
        $this->assertStringNotContainsString("\$meta = \$index[\$configKey] ?? [", $source);
    }

    // =====================================================================
    // Reader — ExtendedKnowledgeLoader::loadFaqAnswers()
    // =====================================================================

    /** @test */
    public function the_loader_returns_a_registered_row(): void
    {
        $key = $this->firstRegisteredKey('tenant');

        AiFaqAnswer::create([
            'listing_type'  => 'tenant',
            'listing_id'    => 970201,
            'question_key'  => $key,
            'answer_text'   => 'A legitimate tenant answer.',
        ]);

        $answers = $this->loadFaqAnswers('tenant', 970201);

        $this->assertArrayHasKey($key, $answers);
        $this->assertSame('A legitimate tenant answer.', $answers[$key]);
    }

    /** @test */
    public function the_loader_drops_a_stale_pre_existing_unregistered_row(): void
    {
        // Written before the writer guard existed — exactly the case the reader guard is
        // for, and the case a writer-only fix would have missed.
        AiFaqAnswer::create([
            'listing_type' => 'landlord',
            'listing_id'   => 970202,
            'question_key' => 'com_tenant_type',
            'answer_text'  => 'No families with children.',
        ]);

        $answers = $this->loadFaqAnswers('landlord', 970202);

        $this->assertArrayNotHasKey('com_tenant_type', $answers);
        $this->assertSame([], $answers);
    }

    /** @test */
    public function com_tenant_type_cannot_enter_agent_ai_v2_context_even_alongside_valid_rows(): void
    {
        $key = $this->firstRegisteredKey('landlord');

        AiFaqAnswer::create([
            'listing_type' => 'landlord',
            'listing_id'   => 970203,
            'question_key' => $key,
            'answer_text'  => 'Legitimate.',
        ]);
        AiFaqAnswer::create([
            'listing_type' => 'landlord',
            'listing_id'   => 970203,
            'question_key' => 'com_tenant_type',
            'answer_text'  => 'Young professionals preferred, no children.',
        ]);

        $answers = $this->loadFaqAnswers('landlord', 970203);

        $this->assertArrayHasKey($key, $answers);
        $this->assertArrayNotHasKey('com_tenant_type', $answers);
        $this->assertCount(1, $answers);
    }

    /** @test */
    public function the_full_fragment_carries_no_unregistered_faq_key(): void
    {
        // Through the loader's real public entry point, not just the private helper —
        // this is the value that actually reaches the Agent AI context builder.
        AiFaqAnswer::create([
            'listing_type' => 'buyer',
            'listing_id'   => 970204,
            'question_key' => 'com_tenant_type',
            'answer_text'  => 'No families.',
        ]);

        $fragment = (new ExtendedKnowledgeLoader())([
            'scope'        => 'buyer',
            'agent_id'     => 1,
            'listing_type' => 'buyer',
            'listing_id'   => 970204,
        ]);

        $faq = $fragment['content']['faq_answers'] ?? [];
        $this->assertArrayNotHasKey('com_tenant_type', $faq);
        $this->assertSame([], $faq);
    }

    /** @test */
    public function the_loader_fails_closed_for_an_unknown_role(): void
    {
        AiFaqAnswer::create([
            'listing_type' => 'not_a_role',
            'listing_id'   => 970205,
            'question_key' => 'anything_at_all',
            'answer_text'  => 'Should never be returned.',
        ]);

        $this->assertSame([], $this->loadFaqAnswers('not_a_role', 970205));
    }

    /** @test */
    public function legitimate_registered_buyer_and_tenant_content_remains_available(): void
    {
        // The guard must not be so blunt that it starves the assistant of the answers the
        // product exists to surface.
        foreach (['buyer', 'tenant'] as $i => $role) {
            $listingId = 970300 + $i;
            $index     = AskAiFaqEnrichmentService::buildConfigIndex($role);
            $keys      = array_slice(array_keys($index), 0, 5);

            foreach ($keys as $key) {
                AiFaqAnswer::create([
                    'listing_type' => $role,
                    'listing_id'   => $listingId,
                    'question_key' => $key,
                    'answer_text'  => "Answer for {$key}",
                ]);
            }

            $answers = $this->loadFaqAnswers($role, $listingId);

            $this->assertCount(count($keys), $answers, "Role {$role}: registered content was dropped.");
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $answers);
            }
        }
    }

    /** @test */
    public function the_reader_guard_uses_the_shared_ssot_and_not_a_second_allowlist(): void
    {
        $source = file_get_contents(base_path('app/Services/AgentAi/Loaders/ExtendedKnowledgeLoader.php'));

        $this->assertStringContainsString(
            'AskAiFaqEnrichmentService::buildConfigIndex($listingType)',
            $source
        );
        $this->assertStringContainsString('whereIn(\'question_key\', array_keys($registered))', $source);
    }
}
