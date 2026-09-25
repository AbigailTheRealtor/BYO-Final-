<?php

namespace Tests\Feature\SmartTags;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\SmartTags\SmartTagDerivationService;
use App\Services\SmartTags\Seeker\ListingSmartTagIndex;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `pets_allowed` carries a governed compliance notice (config/smart_tags.php):
 * "Assistance animals are not pets and are not governed by pet policies." The config
 * defines a notice as fixed copy that MUST accompany display, and until now nothing
 * rendered it — a tenant who picked "Pets Allowed" could read "Does not list: Pets
 * Allowed" with no word about assistance animals.
 *
 * The notice now travels as data from the Smart Tag definition — never re-typed in a
 * view — onto every customer-facing pet line: the Smart Tag known miss, the unknown
 * ("could not be checked") line, the structured pet-policy tradeoff and caution flag,
 * the detail-page tradeoffs, and both seeker pickers. Tags without a notice are untouched.
 */
class PetsAllowedComplianceNoticeTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        // TEST-LOCAL only: every activation flag ships off.
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        config()->set('smart_tags_wiring.seeker_matching_enabled', true);
        config()->set('smart_tags_wiring.seeker_matching_contexts', ['residential.sale', 'residential.lease']);
    }

    protected function tearDown(): void
    {
        SmartTagTaxonomy::flush();
        parent::tearDown();
    }

    private function notice(): string
    {
        $notice = SmartTagTaxonomy::get('pets_allowed')->complianceNotice;
        $this->assertSame('Assistance animals are not pets and are not governed by pet policies.', $notice,
            'the governed notice itself is not this change\'s to reword');

        return $notice;
    }

    // ── result entries ────────────────────────────────────────────────────────

    /** @test */
    public function a_pets_known_miss_carries_the_notice(): void
    {
        $result = $this->lease(['PetsAllowed' => ['No']], ['pets_allowed']);

        $entry = $this->entry($result->tradeoffs, 'Does not list: Pets Allowed');
        $this->assertSame([$this->notice()], $entry['notices']);
    }

    /** @test */
    public function a_pets_pick_nobody_could_check_carries_the_notice(): void
    {
        // Nothing selected is checkable: the whole selection is unknown.
        $allUnknown = $this->lease([], ['pets_allowed']);
        $this->assertSame([$this->notice()], $this->entry($allUnknown->missingData, 'Feature details not available')['notices']);
        $this->assertSame([], $this->doesNotList($allUnknown), 'an unknown pick is never "Does not list"');

        // Something else checkable, pets unknown: pets is named, with its notice.
        $mixed = $this->lease(['Cooling' => ['Central Air']], ['central_air', 'pets_allowed']);
        $this->assertSame([$this->notice()], $this->entry($mixed->missingData, 'Some selected features could not be checked: Pets Allowed')['notices']);
    }

    /** @test */
    public function does_not_list_still_names_known_misses_only_and_carries_the_notice_once(): void
    {
        // Both checked and missing: one line, both names, the pets notice once.
        $both = $this->lease(['PetsAllowed' => ['No'], 'Cooling' => ['Wall/Window Unit(s)']], ['central_air', 'pets_allowed']);
        $line = $this->doesNotList($both);
        $this->assertCount(1, $line);
        $this->assertStringContainsString('Central Air', $line[0]['label']);
        $this->assertStringContainsString('Pets Allowed', $line[0]['label']);
        $this->assertSame([$this->notice()], $line[0]['notices']);

        // Central air missing, pets unknown: "Does not list" names central air ONLY, and carries
        // no pet notice — that belongs to the unknown line, beside "Pets Allowed".
        $split = $this->lease(['Cooling' => ['Wall/Window Unit(s)']], ['central_air', 'pets_allowed']);
        $line = $this->doesNotList($split);
        $this->assertSame('Does not list: Central Air', $line[0]['label']);
        $this->assertArrayNotHasKey('notices', $line[0]);
        $this->assertSame([$this->notice()], $this->entry($split->missingData, 'Some selected features could not be checked: Pets Allowed')['notices']);
    }

    /** @test */
    public function an_unrelated_tag_gets_no_notice(): void
    {
        $miss = $this->sale(['Cooling' => ['Wall/Window Unit(s)']], ['central_air']);
        $this->assertArrayNotHasKey('notices', $this->entry($miss->tradeoffs, 'Does not list: Central Air'));

        $unknown = $this->sale([], ['central_air']);
        $this->assertArrayNotHasKey('notices', $this->entry($unknown->missingData, 'Feature details not available'));

        foreach (SmartTagTaxonomy::all() as $key => $definition) {
            if ($key !== 'pets_allowed') {
                $this->assertNull($definition->complianceNotice, "{$key} would start carrying a notice");
            }
        }
    }

    /** @test */
    public function the_structured_pet_policy_lines_carry_the_same_notice(): void
    {
        $restricted = $this->build($this->row(['pets_allowed' => 'No'], lease: true), $this->payload(lease: true, extra: ['wants_pet_friendly' => true]));
        $this->assertSame([$this->notice()], $this->entry($restricted->tradeoffs, 'Pet policy restricts pets in this community')['notices']);

        $unconfirmed = $this->build($this->row(['pets_allowed' => null], lease: true), $this->payload(lease: true, extra: ['wants_pet_friendly' => true]));
        $this->assertSame([$this->notice()], $this->entry($unconfirmed->cautionFlags, 'Pet policy not confirmed')['notices']);

        // Every other caution flag keeps its exact shape.
        foreach ($unconfirmed->cautionFlags as $flag) {
            if (! str_starts_with($flag['label'], 'Pet policy')) {
                $this->assertArrayNotHasKey('notices', $flag);
            }
        }
    }

    // ── what reaches the page ──────────────────────────────────────────────────

    /** @test */
    public function the_result_card_renders_the_notice_directly_under_the_pet_line(): void
    {
        $card = (new BuyerResultViewMapper())->mapOne(
            $this->lease(['PetsAllowed' => ['No']], ['pets_allowed'])
        );

        $this->assertSame([$this->notice()], $this->entry($card['tradeoffs'], 'Does not list: Pets Allowed')['notices']);

        $html = Blade::render('<x-stellar.buyer-result-card :card="$card" />', ['card' => $card]);
        $this->assertMatchesRegularExpression(
            '~Does not list: Pets Allowed\s*<small[^>]*data-compliance-notice[^>]*>\s*' . preg_quote(e($this->notice()), '~') . '\s*</small>~',
            $html,
            'the notice sits inside the same line, immediately after the label'
        );
        $this->assertSame(1, substr_count($html, 'data-compliance-notice'));
    }

    /** @test */
    public function the_card_renders_the_notice_on_the_unknown_and_caution_lines_too(): void
    {
        $unknownCard = (new BuyerResultViewMapper())->mapOne($this->lease([], ['pets_allowed']));
        $html = Blade::render('<x-stellar.buyer-result-card :card="$card" />', ['card' => $unknownCard]);
        $this->assertStringContainsString(e($this->notice()), $html);

        $flagCard = (new BuyerResultViewMapper())->mapOne(
            $this->build($this->row(['pets_allowed' => null], lease: true), $this->payload(lease: true, extra: ['wants_pet_friendly' => true]))
        );
        $this->assertSame([$this->notice()], $this->entry($flagCard['caution_flags'], 'Pet policy not confirmed')['notices']);
        $this->assertStringContainsString(e($this->notice()), Blade::render('<x-stellar.buyer-result-card :card="$card" />', ['card' => $flagCard]));
    }

    /** @test */
    public function a_card_with_no_pet_line_renders_no_notice(): void
    {
        $card = (new BuyerResultViewMapper())->mapOne($this->sale(['Cooling' => ['Wall/Window Unit(s)']], ['central_air']));
        $html = Blade::render('<x-stellar.buyer-result-card :card="$card" />', ['card' => $card]);

        $this->assertStringContainsString('Does not list: Central Air', $html);
        $this->assertStringNotContainsString('data-compliance-notice', $html);
        $this->assertStringNotContainsString(e($this->notice()), $html);
    }

    /** @test */
    public function the_detail_page_tradeoffs_render_the_notice_beside_the_pet_line(): void
    {
        $html = Blade::render('<x-stellar.matchmaker-tradeoffs :items="$items" />', ['items' => [
            ['label' => 'Does not list: Pets Allowed', 'notices' => [$this->notice()]],
            ['label' => 'Does not list: Central Air'],
        ]]);

        $this->assertSame(1, substr_count($html, 'data-compliance-notice'));
        $this->assertMatchesRegularExpression('~Pets Allowed\s*<small[^>]*data-compliance-notice~', $html);
    }

    // ── pickers ───────────────────────────────────────────────────────────────

    /** @test */
    public function the_tenant_offer_listing_picker_shows_the_notice_under_pets_allowed_only(): void
    {
        $tenant = Livewire::actingAs(User::factory()->create(['user_type' => 'tenant']))
            ->test(TenantOfferListing::class, ['user_type' => 'tenant'])
            ->set('property_type', 'Residential Property');

        $option = collect($tenant->instance()->seekerSmartTagPanel()['groups'])->flatMap(fn ($g) => $g['options'])->keyBy('key');
        $this->assertSame($this->notice(), $option['pets_allowed']['notice']);
        $this->assertSame([''], $option->except('pets_allowed')->pluck('notice')->unique()->values()->all());

        $html = $tenant->lastRenderedDom;
        $this->assertMatchesRegularExpression('~for="seeker-smart-tag-pets_allowed"[^>]*>\s*Pets Allowed\s*</label>\s*<small[^>]*data-compliance-notice~', $html);
        $this->assertSame(1, substr_count($html, 'data-compliance-notice'));
    }

    /** @test */
    public function a_picker_without_pets_allowed_shows_no_notice(): void
    {
        $buyer = Livewire::actingAs(User::factory()->create(['user_type' => 'buyer']))
            ->test(BuyerOfferListing::class, ['user_type' => 'buyer'])
            ->set('property_type', 'Residential');

        $this->assertStringContainsString('data-seeker-smart-tags', $buyer->lastRenderedDom);
        $this->assertStringNotContainsString('data-compliance-notice', $buyer->lastRenderedDom);
    }

    /** @test */
    public function the_criteria_picker_shows_the_notice_under_pets_allowed_only(): void
    {
        $tenant = View::make('partials.smart-tags._seeker-picker', ['stRole' => 'tenant', 'stSelected' => []])->render();
        $this->assertMatchesRegularExpression('~for="smart-tag-pets_allowed">\s*Pets Allowed\s*</label>\s*<small[^>]*data-compliance-notice~', $tenant);
        $this->assertSame(1, substr_count($tenant, 'data-compliance-notice'));

        $buyer = View::make('partials.smart-tags._seeker-picker', ['stRole' => 'buyer', 'stSelected' => []])->render();
        $this->assertStringNotContainsString('data-compliance-notice', $buyer);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** @param list<array<string, mixed>> $entries */
    private function entry(array $entries, string $labelStart): array
    {
        foreach ($entries as $entry) {
            if (str_starts_with((string) ($entry['label'] ?? ''), $labelStart)) {
                return $entry;
            }
        }

        $this->fail("No entry starting '{$labelStart}' in: " . json_encode(array_column($entries, 'label')));
    }

    /** @return list<array<string, mixed>> */
    private function doesNotList(BuyerMatchResult $result): array
    {
        return array_values(array_filter($result->tradeoffs, fn ($t) => str_starts_with($t['label'], 'Does not list:')));
    }

    /** A lease row derived by the real governed pipeline, scored against Smart Tag picks. */
    private function lease(array $raw, array $picks): BuyerMatchResult
    {
        return $this->build($this->row([], $raw, lease: true), $this->payload(lease: true, extra: ['seeker_smart_tags' => $picks]));
    }

    private function sale(array $raw, array $picks): BuyerMatchResult
    {
        return $this->build($this->row([], $raw), $this->payload(extra: ['seeker_smart_tags' => $picks]));
    }

    private function row(array $columns = [], array $raw = [], bool $lease = false): BridgeProperty
    {
        $this->n++;

        $row = BridgeProperty::create(array_merge([
            'provider'                => 'stellar_bridge',
            'listing_key'             => sprintf('PET-%03d-%s', $this->n, uniqid()),
            'listing_id'              => "PET-{$this->n}-" . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => $lease ? 'Residential Lease' : 'Residential',
            'list_price'              => $lease ? 2000 : 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(array_merge(['IDXParticipationYN' => true, 'LeaseAmountFrequency' => 'Monthly'], $raw)),
        ], $columns));

        app(SmartTagDerivationService::class)->deriveBridge($row);

        return $row->fresh();
    }

    private function payload(bool $lease = false, array $extra = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => [$lease ? 'Residential Lease' : 'Residential'],
            'is_55_plus_eligible' => false,
            'preferred_cities'    => ['Orlando'],
        ], $extra));
    }

    private function build(BridgeProperty $home, BuyerCriteriaPayload $payload): BuyerMatchResult
    {
        $scored = (new BuyerMatchScorer())->score($home, $payload, ListingSmartTagIndex::forCandidates([$home], $payload)->factsFor($home));

        return (new BuyerMatchResultBuilder())->build($scored, $payload);
    }
}
