<?php

namespace Tests\Feature\AskAi;

use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PropertyQaP0StructuralGuardTest
 *
 * Two things P0 promised NOT to break, asserted rather than assumed:
 *
 *  1. The owner's existing Ask AI experience. Defaulting unclassified facts to
 *     'owner_only' must not withdraw the owner's own answers — which it WOULD have done
 *     had the default been 'restricted', because AskAiKnowledgeSearchService blocks
 *     restricted facts for every scope including the owner's.
 *
 *  2. Unrelated OpenAI-backed features. P0 removes OpenAI from nothing yet; it only
 *     closes privacy holes. AI Marketing Intelligence and Agent AI must keep their
 *     OpenAI wiring, and the shared configuration must stay intact.
 */
class PropertyQaP0StructuralGuardTest extends TestCase
{
    use DatabaseTransactions;

    private function makeSnapshotWithFact(string $key, string $value, string $visibility): AskAiKnowledgeSnapshot
    {
        $snapshot = AskAiKnowledgeSnapshot::create([
            'snapshot_uuid' => (string) Str::uuid(),
            'listing_type'  => 'seller',
            'listing_id'    => 771001,
            'version'       => 1,
            'status'        => 'ready',
            'built_at'      => now(),
        ]);

        AskAiFact::create([
            'snapshot_id'    => $snapshot->id,
            'canonical_key'  => $key,
            'value'          => $value,
            'visibility'     => $visibility,
            'listing_type'   => 'seller',
            'listing_id'     => 771001,
            'label'          => SnapshotFactVisibility::deriveLabel($key),
            'value_type'     => 'string',
            'source_path'    => 'context.listing.' . $key,
            'classification' => $visibility === SnapshotFactVisibility::RESTRICTED
                ? 'compliance_sensitive'
                : ($visibility === SnapshotFactVisibility::PUBLIC_ALLOWED ? 'public_factual' : 'owner_only'),
            'public_allowed' => $visibility === SnapshotFactVisibility::PUBLIC_ALLOWED,
            'restricted'     => $visibility === SnapshotFactVisibility::RESTRICTED,
            'sort_order'     => 0,
        ]);

        return $snapshot;
    }

    // =====================================================================
    // 1. Owner experience preserved
    // =====================================================================

    /**
     * An 'owner_only' fact must still resolve to a database hit. This is the whole reason
     * owner_only is a separate tier from restricted: the owner keeps their answer, and
     * only the public claim is withdrawn.
     */
    public function test_owner_only_facts_still_produce_a_database_hit(): void
    {
        $this->makeSnapshotWithFact('some_unclassified_key', 'A stored value', SnapshotFactVisibility::OWNER_ONLY);

        $result = app(AskAiKnowledgeSearchService::class)->search(
            'seller',
            771001,
            'anything',
            ['normalized_field_key' => 'listing.some_unclassified_key']
        );

        $this->assertSame(
            'database_hit',
            $result['outcome'],
            'An owner_only fact must still answer from the database — only restricted facts are blocked.'
        );
        $this->assertSame('A stored value', $result['answer']);
    }

    /** Restricted facts must still be blocked — that behaviour is unchanged by P0. */
    public function test_restricted_facts_are_still_blocked(): void
    {
        $this->makeSnapshotWithFact('flood_zone_code', 'AE', SnapshotFactVisibility::RESTRICTED);

        $result = app(AskAiKnowledgeSearchService::class)->search(
            'seller',
            771001,
            'what flood zone',
            ['normalized_field_key' => 'listing.flood_zone_code']
        );

        $this->assertSame('restricted', $result['outcome']);
    }

    /**
     * The owner-facing Ask AI endpoint must still exist and still be authenticated —
     * P0 removed the unauthenticated /ask-ai/ask route, not this one.
     */
    public function test_owner_ask_ai_endpoint_still_exists_and_is_protected(): void
    {
        $response = $this->postJson('/ask-ai/listing-question', []);

        $this->assertNotEquals(404, $response->getStatusCode(), 'The owner Ask AI route must still be routable.');
        $this->assertNotEquals(405, $response->getStatusCode(), 'The owner Ask AI route must still accept POST.');
        $this->assertContains(
            $response->getStatusCode(),
            [401, 403, 419, 422, 302],
            'A guest must be refused by auth/validation, never served an answer.'
        );
    }

    // =====================================================================
    // 2. Unrelated OpenAI features untouched
    // =====================================================================

    /**
     * The set of classes wired to OpenAI must be exactly what it was before P0.
     *
     * Guards both directions: P0 must not have removed OpenAI from the unrelated
     * marketing-report feature, and must not have added a new OpenAI dependency anywhere.
     */
    public function test_openai_client_consumers_are_unchanged(): void
    {
        $expected = [
            'app/Services/AskAi/AskAiOpenAiAdapterService.php',
            'app/Services/AskAi/AskAiIntentNormalizerService.php',
            'app/Services/Dna/AiMarketingReportGeneratorService.php',
        ];

        $found = [];
        $base  = base_path();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base . '/app', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents !== false && str_contains($contents, 'use App\Services\Ai\OpenAiClientService;')) {
                $found[] = str_replace($base . '/', '', $file->getPathname());
            }
        }

        sort($expected);
        sort($found);

        $this->assertSame(
            $expected,
            $found,
            'The set of OpenAI client consumers changed. P0 must not remove OpenAI from the '
            . 'AI Marketing Intelligence feature, nor introduce any new OpenAI dependency.'
        );
    }

    /** The marketing-report generator — an unrelated feature — must still resolve. */
    public function test_ai_marketing_report_generator_still_resolves(): void
    {
        $this->assertTrue(
            class_exists(\App\Services\Dna\AiMarketingReportGeneratorService::class),
            'AI Marketing Intelligence must be structurally intact after P0.'
        );
        $this->assertTrue(class_exists(\App\Services\Ai\OpenAiClientService::class));
    }

    /** Shared OpenAI configuration must remain — P0 removes no environment wiring. */
    public function test_shared_openai_configuration_is_intact(): void
    {
        $ai = config('ai');

        $this->assertIsArray($ai);
        foreach (['api_key', 'model', 'prompt_version', 'timeout_seconds', 'max_retries'] as $key) {
            $this->assertArrayHasKey($key, $ai, "config('ai.{$key}') must still exist — other features depend on it.");
        }
    }

    /**
     * The classes P0 touched in the snapshot/visibility path must contain no OpenAI
     * reference at all. These are the classes the future Property Q&A will read from,
     * and they must stay deterministic.
     */
    public function test_snapshot_visibility_path_contains_no_openai_reference(): void
    {
        $files = glob(base_path('app/Services/AskAi/Snapshot/*.php'));

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsStringIgnoringCase(
                'OpenAi',
                $contents,
                basename($file) . ' must never reference OpenAI — the snapshot layer is deterministic.'
            );
        }
    }
}
