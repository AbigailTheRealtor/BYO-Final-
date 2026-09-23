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
use App\Support\AskAi\PublicAnswerPiiScreen;
use App\Support\OfferListing\PublicProviderTextPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tier-2 MLS Details facts on the free-text path, built from the committed Bridge fixtures
 * through the real MlsSupplementalDetails::fromRecord(), so the rows are exactly the ones a
 * listing page renders under "MLS Details".
 *
 * Ask AI does not decide which MLS facts are public; the existing display policy does:
 *   - only the `facts` group exists (MlsFieldCatalog::PROPERTY_FACTS, fail-closed);
 *   - a non-owner gets nothing when the feed forbids displaying the listing
 *     (MlsDisplayPermissions::listingDisplayable());
 *   - a non-owner's answer must also pass the public-answer screens (Fair Housing, personal
 *     data, the withheld street address) — a screened row is REFUSED, never paraphrased;
 *   - the owner reads their own imported facts as stored, as they could before.
 * No model is reachable.
 */
class AskAiMlsDetailsViewerPathTest extends TestCase
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

    /**
     * @dataProvider scopes
     */
    public function test_every_viewer_gets_every_mls_details_fact_by_its_label(string $scope): void
    {
        $runner   = app(AskAiRunnerV2Service::class);
        $failures = [];
        $asked    = 0;

        $screened = 0;

        foreach (self::FIXTURES as $slug => [$role, $model]) {
            [$listingId, $details] = $this->importedListing($slug, $role, $model);

            foreach ($this->uniqueFactRows($details) as $label => $value) {
                $expected = $label . ': ' . rtrim($value, '.') . '.';
                $public   = PublicProviderTextPolicy::isPublishable($expected) && PublicAnswerPiiScreen::isPublishable($expected);
                $answered = $scope === AskAiViewerAuthorizationService::SCOPE_OWNER || $public;
                $screened += $answered ? 0 : 1;

                foreach ([$label, "What is the {$label}?", 'What does the listing state for ' . $label . '?'] as $asking) {
                    $asked++;
                    $answer = $this->answer($runner->run($role, $listingId, $asking, $this->scopeOptions($scope)));
                    if ($answered && $answer !== $expected) {
                        $failures[] = "{$slug} '{$asking}': got '" . mb_substr($answer, 0, 90) . "'";
                    }
                    if (!$answered && (str_contains($answer, $value) || $answer === $expected)) {
                        $failures[] = "{$slug} '{$asking}': a screened row reached a {$scope} viewer";
                    }
                }
            }
        }

        $this->assertGreaterThan(300, $asked, 'Too few MLS Details rows reached the runner — the proof would be hollow.');
        if ($scope !== AskAiViewerAuthorizationService::SCOPE_OWNER) {
            // The fixtures carry at least one row the street-address screen refuses ("MLS
            // Area: 33710 - St Pete"), so the non-owner screen is proven to be ON here.
            $this->assertGreaterThan(0, $screened, 'No MLS row was screened for a non-owner — the screen is not proven.');
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public static function scopes(): array
    {
        return [
            'owner'      => [AskAiViewerAuthorizationService::SCOPE_OWNER],
            'public'     => [AskAiViewerAuthorizationService::SCOPE_PUBLIC],
            'authorized' => [AskAiViewerAuthorizationService::SCOPE_AUTHORIZED],
        ];
    }

    public function test_a_listing_the_feed_forbids_displaying_answers_no_mls_fact_to_a_non_owner(): void
    {
        $runner = app(AskAiRunnerV2Service::class);
        [$listingId, $details] = $this->importedListing('residential', 'seller', SellerAgentAuction::class);
        SellerAgentAuction::find($listingId)->saveMeta(MlsQuickImportDraftWriter::META_DISPLAY_PERMISSIONS, json_encode([
            'idx_participation' => false, 'entire_listing_display' => false, 'address_display' => false,
            'automated_valuation_display' => false, 'consumer_comment_display' => false,
        ]));

        $rows = $this->uniqueFactRows($details);
        $this->assertNotEmpty($rows);
        foreach ($rows as $label => $value) {
            foreach ([AskAiViewerAuthorizationService::SCOPE_PUBLIC, AskAiViewerAuthorizationService::SCOPE_AUTHORIZED] as $scope) {
                $result = $runner->run('seller', $listingId, $label, $this->scopeOptions($scope));
                $this->assertNotSame('ready', $result['status'], "{$scope} '{$label}' was answered on a non-displayable listing");
                $this->assertStringNotContainsString($label . ': ', $this->answer($result));
            }
        }

        // The owner still reads their own imported facts.
        [$label, $value] = [array_key_first($rows), reset($rows)];
        $this->assertSame($label . ': ' . rtrim($value, '.') . '.',
            $this->answer($runner->run('seller', $listingId, $label, $this->scopeOptions(AskAiViewerAuthorizationService::SCOPE_OWNER))));
    }

    public function test_an_mls_row_restating_a_withheld_street_address_is_refused_to_a_non_owner(): void
    {
        $runner  = app(AskAiRunnerV2Service::class);
        $listing = SellerAgentAuction::forceCreate(['user_id' => $this->owner->id, 'title' => 'Withheld', 'is_draft' => false]);
        $listing->saveMeta('property_type', 'Residential');
        $listing->saveMeta('address', '4821 Pelican Cove Lane');
        $listing->saveMeta(MlsQuickImportDraftWriter::META_LISTING_KEY, 'LK-WITHHELD');
        $listing->saveMeta(MlsQuickImportDraftWriter::META_DISPLAY_PERMISSIONS, json_encode([
            'idx_participation' => true, 'entire_listing_display' => true, 'address_display' => false,
        ]));
        $listing->saveMeta(MlsQuickImportDraftWriter::META_PROPERTY_DETAILS, json_encode(['version' => MlsSupplementalDetails::VERSION, 'sections' => [
            ['title' => 'Location', 'group' => 'facts', 'rows' => [['key' => 'Directions', 'label' => 'Directions', 'value' => 'Turn onto Pelican Cove Lane']]],
            ['title' => 'Interior', 'group' => 'facts', 'rows' => [['key' => 'Flooring', 'label' => 'Flooring', 'value' => 'Tile']]],
        ]]));

        $public = $this->scopeOptions(AskAiViewerAuthorizationService::SCOPE_PUBLIC);
        $this->assertSame('Flooring: Tile.', $this->answer($runner->run('seller', $listing->id, 'Flooring', $public)));
        $directions = $runner->run('seller', $listing->id, 'Directions', $public);
        $this->assertNotSame('ready', $directions['status']);
        $this->assertStringNotContainsString('Pelican Cove', $this->answer($directions));

        $this->assertSame('Directions: Turn onto Pelican Cove Lane.', $this->answer(
            $runner->run('seller', $listing->id, 'Directions', $this->scopeOptions(AskAiViewerAuthorizationService::SCOPE_OWNER))));
    }

    public function test_a_paraphrase_of_a_label_is_not_matched(): void
    {
        $runner = app(AskAiRunnerV2Service::class);
        [$listingId] = $this->importedListing('residential', 'seller', SellerAgentAuction::class);

        // "MLS Area" is a label; "which mls region" is not — no fuzzy match, a refusal.
        $result = $runner->run('seller', $listingId, 'which mls region is it in', $this->scopeOptions(AskAiViewerAuthorizationService::SCOPE_PUBLIC));
        $this->assertNotSame('ready', $result['status']);
        $this->assertSame(AskAiRunnerV2Service::DETERMINISTIC_UNANSWERABLE, $this->answer($result));
    }

    public function test_a_label_the_page_prints_twice_states_every_value_it_shows(): void
    {
        $runner  = app(AskAiRunnerV2Service::class);
        $listing = SellerAgentAuction::forceCreate(['user_id' => $this->owner->id, 'title' => 'MLS twice', 'is_draft' => false]);
        $listing->saveMeta('property_type', 'Residential');
        $listing->saveMeta(MlsQuickImportDraftWriter::META_PROPERTY_DETAILS, json_encode(['version' => MlsSupplementalDetails::VERSION, 'sections' => [
            ['title' => 'Exterior', 'group' => 'facts', 'rows' => [['key' => 'ParkingFeatures', 'label' => 'Parking Features', 'value' => 'Driveway']]],
            ['title' => 'Garage',   'group' => 'facts', 'rows' => [['key' => 'ParkingFeaturesGarage', 'label' => 'Parking Features', 'value' => 'Attached']]],
            ['title' => 'Interior', 'group' => 'facts', 'rows' => [['key' => 'Flooring', 'label' => 'Flooring', 'value' => 'Tile'],
                                                                   ['key' => 'FlooringDup', 'label' => 'Flooring', 'value' => 'Tile']]],
        ]]));

        $parking = $this->answer($runner->run('seller', $listing->id, 'Parking Features', $this->scopeOptions(AskAiViewerAuthorizationService::SCOPE_PUBLIC)));
        $this->assertSame('Parking Features: Driveway (Exterior); Attached (Garage).', $parking);

        $flooring = $this->answer($runner->run('seller', $listing->id, 'flooring', $this->scopeOptions(AskAiViewerAuthorizationService::SCOPE_PUBLIC)));
        $this->assertSame('Flooring: Tile.', $flooring);
    }

    public function test_contact_and_bookkeeping_rows_are_never_answered(): void
    {
        $runner = app(AskAiRunnerV2Service::class);
        [$listingId, $details] = $this->importedListing('residential', 'seller', SellerAgentAuction::class);

        $contactRows = [];
        foreach (['contacts', 'related', 'listing'] as $group) {
            foreach ($details->group($group) as $section) {
                foreach ($section['rows'] as $row) {
                    $contactRows[$row['label']] = (string) $row['value'];
                }
            }
        }
        $this->assertNotEmpty($contactRows, 'The fixture carries no contact rows — the guard would be vacuous.');

        $factLabels = array_map([AskAiKnowledgeBaseQuestionMatcher::class, 'normalize'], array_keys($this->uniqueFactRows($details)));
        foreach ($contactRows as $label => $value) {
            if (in_array(AskAiKnowledgeBaseQuestionMatcher::normalize($label), $factLabels, true)) {
                continue; // the same label is also a displayed FACT; that row is what answers
            }
            foreach ([AskAiViewerAuthorizationService::SCOPE_OWNER, AskAiViewerAuthorizationService::SCOPE_PUBLIC] as $scope) {
                $result = $runner->run('seller', $listingId, $label, $this->scopeOptions($scope));
                $this->assertNotSame('public_card', $result['outcome_category'] ?? null, "{$scope}: contact row '{$label}' was answered from the card");
                if ($value !== '' && strlen($value) > 3) {
                    $this->assertStringNotContainsString($value, $this->answer($result), "{$scope}: contact value for '{$label}' reached an answer");
                }
            }
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
     * Facts rows whose label names exactly one value (a label shared by rows with different
     * values is stated with every value — test_a_label_the_page_prints_twice_…). label => value
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

    private function scopeOptions(string $scope): array
    {
        return $scope === AskAiViewerAuthorizationService::SCOPE_OWNER
            ? ['viewer_scope' => $scope, 'requester_user_id' => $this->owner->id]
            : ['viewer_scope' => $scope];
    }

    private function answer(array $result): string
    {
        return (string) ($result['final_response']['answer'] ?? '');
    }
}
