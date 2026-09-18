<?php

namespace Tests\Feature\AskAi;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Support\AskAi\AskAiKnowledgeBaseQuestionMatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tier-2 MLS Details facts on the owner's free-text path, built from the committed Bridge
 * fixtures through the real MlsSupplementalDetails::fromRecord(), so the rows are exactly the
 * ones a listing page renders under "MLS Details". No model is reachable.
 */
class AskAiMlsDetailsOwnerPathTest extends TestCase
{
    use DatabaseTransactions;

    private const FIXTURES = [
        'residential'          => ['seller', SellerAgentAuction::class],
        'income'               => ['seller', SellerAgentAuction::class],
        'commercial_sale'      => ['seller', SellerAgentAuction::class],
        'business_opportunity' => ['seller', SellerAgentAuction::class],
        'vacant_land'          => ['seller', SellerAgentAuction::class],
        'residential_lease'    => ['landlord', LandlordAgentAuction::class],
        'commercial_lease'     => ['landlord', LandlordAgentAuction::class],
    ];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
        config(['ask_ai.enable_openai_intent_normalization' => true]);

        $this->owner = User::factory()->create();
    }

    public function test_the_owner_gets_every_mls_details_fact_by_its_label(): void
    {
        $runner   = app(AskAiRunnerV2Service::class);
        $failures = [];
        $asked    = 0;

        foreach (self::FIXTURES as $slug => [$role, $model]) {
            [$listingId, $details] = $this->importedListing($slug, $role, $model);

            foreach ($this->uniqueFactRows($details) as $label => $value) {
                $asked++;
                $answer = $this->answer($runner->run($role, $listingId, $label, $this->owner()));
                if ($answer !== $label . ': ' . rtrim($value, '.') . '.') {
                    $failures[] = "{$slug} '{$label}': got '" . mb_substr($answer, 0, 90) . "'";
                }
            }
        }

        $this->assertGreaterThan(100, $asked, 'Too few MLS Details rows reached the runner — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_no_other_viewer_is_answered_from_mls_details(): void
    {
        $runner = app(AskAiRunnerV2Service::class);
        [$listingId, $details] = $this->importedListing('residential', 'seller', SellerAgentAuction::class);

        foreach ($this->uniqueFactRows($details) as $label => $value) {
            foreach (['public', 'authorized'] as $scope) {
                $result = $runner->run('seller', $listingId, $label, ['viewer_scope' => $scope]);
                $this->assertNotSame('mls_details_fact', $result['outcome_category'] ?? null, "{$scope} '{$label}'");
            }
        }
    }

    public function test_contact_rows_are_never_answered(): void
    {
        $runner = app(AskAiRunnerV2Service::class);
        [$listingId, $details] = $this->importedListing('residential', 'seller', SellerAgentAuction::class);

        $contactLabels = [];
        foreach (['contacts', 'related', 'listing'] as $group) {
            foreach ($details->group($group) as $section) {
                foreach ($section['rows'] as $row) {
                    $contactLabels[] = $row['label'];
                }
            }
        }
        $this->assertNotEmpty($contactLabels, 'The fixture carries no contact rows — the guard would be vacuous.');

        foreach (array_unique($contactLabels) as $label) {
            $result = $runner->run('seller', $listingId, $label, $this->owner());
            $this->assertNotSame('mls_details_fact', $result['outcome_category'] ?? null, "contact row '{$label}' was answered");
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** @return array{0:int, 1:MlsSupplementalDetails} */
    private function importedListing(string $slug, string $role, string $model): array
    {
        $raw     = json_decode((string) file_get_contents(base_path("tests/fixtures/mls/bridge/{$slug}.json")), true);
        $details = MlsSupplementalDetails::fromRecord($raw, $role);

        $listing = $model::forceCreate(['user_id' => $this->owner->id, 'title' => "MLS {$slug}", 'is_draft' => false]);
        $listing->saveMeta('property_type', $role === 'landlord' ? 'Residential Property' : 'Residential');
        $listing->saveMeta(MlsQuickImportDraftWriter::META_PROPERTY_DETAILS, json_encode($details->toArray()));

        return [$listing->id, MlsSupplementalDetails::fromStored(json_encode($details->toArray()))];
    }

    /**
     * Facts rows whose label names exactly one value — a label shared by rows with different
     * values is refused by the matcher, by design. label => value
     *
     * @return array<string, string>
     */
    private function uniqueFactRows(MlsSupplementalDetails $details): array
    {
        $byLabel = [];
        foreach ($details->group('facts') as $section) {
            foreach ($section['rows'] as $row) {
                if (trim($row['value']) !== '') {
                    $byLabel[AskAiKnowledgeBaseQuestionMatcher::normalize($row['label'])][$row['value']] = $row['label'];
                }
            }
        }

        $out = [];
        foreach ($byLabel as $values) {
            if (count($values) === 1) {
                $out[reset($values)] = (string) array_key_first($values);
            }
        }

        return $out;
    }

    private function owner(): array
    {
        return ['viewer_scope' => AskAiViewerAuthorizationService::SCOPE_OWNER, 'requester_user_id' => $this->owner->id];
    }

    private function answer(array $result): string
    {
        return (string) ($result['final_response']['answer'] ?? '');
    }
}
