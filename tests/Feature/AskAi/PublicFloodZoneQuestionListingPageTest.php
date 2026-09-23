<?php

namespace Tests\Feature\AskAi;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 2e on the real public Seller and Landlord listing pages.
 *
 * The flood question is answered from the stored FEMA designation and nothing else, it
 * appears once, and it appears in the unified Ask AI card rather than a section of its own.
 *
 * The AI services are not merely unused here — they are bound to doubles that FAIL if called,
 * so a page that reached one would error rather than quietly succeed. That is the difference
 * between "we did not observe a call" and "a call is impossible".
 */
class PublicFloodZoneQuestionListingPageTest extends TestCase
{
    use DatabaseTransactions;

    private function seller(array $meta): SellerAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential'];
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '100 Test Lane',
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Traditional');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function landlord(array $meta): LandlordAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential Property'];
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Test Rental',
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    /**
     * The Ask AI card region of a rendered page, BOUNDED at its closing note.
     *
     * The bound matters. These pages also render their own "Flood Insurance Required" and
     * flood-zone rows in the Tax / Legal / HOA card — page furniture that predates Ask AI and
     * is governed by the Blade template, not by SnapshotFactVisibility. Reading to the end of
     * the document would attribute those rows to this surface and make the leak assertions
     * below answer a question they were not asked.
     */
    private function card(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        $this->assertNotFalse($start, "No Ask AI card for {$role}.");

        $end = strpos($html, 'ask-ai-pq-note', $start);
        if ($end === false) {
            // No questions were available, so the card rendered its empty state instead.
            $end = strpos($html, '-interaction-card-helper', $start);
        }

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    private function sellerCard(array $meta): string
    {
        return $this->card(
            $this->get(route('offer.listing.seller.view', ['id' => $this->seller($meta)->id]))->assertOk()->getContent(),
            'seller'
        );
    }

    private function landlordCard(array $meta): string
    {
        return $this->card(
            $this->get(route('offer.listing.landlord.view', ['id' => $this->landlord($meta)->id]))->assertOk()->getContent(),
            'landlord'
        );
    }

    /* ================================================================== */

    public function test_seller_ae_renders_the_flood_answer_once(): void
    {
        $card = $this->sellerCard(['flood_zone_code' => 'AE']);
        $text = html_entity_decode($card, ENT_QUOTES);

        $this->assertStringContainsString('What flood zone is the property in?', $text);
        $this->assertStringContainsString(
            'This property is in FEMA Flood Zone AE, which is within a Special Flood Hazard Area.',
            $text
        );
        $this->assertSame(1, substr_count($text, 'What flood zone is the property in?'),
            'The flood question must appear exactly once.');
        $this->assertSame(1, substr_count($text, 'data-property-question="seller_flood_zone"'));
    }

    public function test_seller_x_renders_the_lower_risk_wording(): void
    {
        $text = html_entity_decode($this->sellerCard(['flood_zone_code' => 'X']), ENT_QUOTES);

        $this->assertStringContainsString(
            'This property is in FEMA Flood Zone X, which is generally outside the Special Flood '
            . 'Hazard Area and considered lower flood risk. Flood risk is not zero.',
            $text
        );
    }

    public function test_landlord_ve_renders_the_coastal_wording(): void
    {
        $text = html_entity_decode($this->landlordCard(['flood_zone_code' => 'VE']), ENT_QUOTES);

        $this->assertStringContainsString('What flood zone is the property in?', $text);
        $this->assertStringContainsString(
            'This property is in FEMA Flood Zone VE, a coastal high-hazard Special Flood Hazard Area.',
            $text
        );
    }

    public function test_an_unusable_stored_value_renders_no_flood_question(): void
    {
        // The literal string the MLS importer used to store here, plus the form's own
        // non-answers. None of them is a designation and none may be presented as one.
        foreach (['yes', 'no', 'Unknown', 'Other', 'N/A', 'Zone AE', ''] as $stored) {
            $text = html_entity_decode($this->sellerCard(['flood_zone_code' => $stored]), ENT_QUOTES);

            $this->assertStringNotContainsString('What flood zone is the property in?', $text,
                "A flood question rendered for stored value '{$stored}'.");
            $this->assertStringNotContainsStringIgnoringCase('FEMA Flood Zone', $text);
        }
    }

    public function test_no_rendered_answer_makes_a_risk_or_insurance_claim(): void
    {
        foreach (['X', 'AE', 'VE', 'A', 'D'] as $code) {
            $text = html_entity_decode($this->sellerCard(['flood_zone_code' => $code]), ENT_QUOTES);

            foreach ([
                'not in a flood zone', 'not in a flood area', 'no flood risk', 'cannot flood',
                'flood insurance is not required', 'insurance is not required', 'safe from flooding',
            ] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $text,
                    "Zone {$code} page said '{$forbidden}'.");
            }
        }
    }

    public function test_the_other_flood_fields_never_reach_the_page(): void
    {
        // Stored alongside the designation, all still restricted or owner-only.
        $text = html_entity_decode($this->sellerCard([
            'flood_zone_code'        => 'AE',
            'flood_zone_designation' => 'SENTINEL-DESIGNATION',
            'flood_zone_description' => 'SENTINEL-DESCRIPTION',
            'is_in_flood_zone'       => 'SENTINEL-IS-IN-ZONE',
            'flood_insurance_required' => 'SENTINEL-INSURANCE',
        ]), ENT_QUOTES);

        $this->assertStringContainsString('FEMA Flood Zone AE', $text);
        foreach (['SENTINEL-DESIGNATION', 'SENTINEL-DESCRIPTION', 'SENTINEL-IS-IN-ZONE', 'SENTINEL-INSURANCE'] as $leak) {
            $this->assertStringNotContainsString($leak, $text, "'{$leak}' reached the card.");
        }
    }

    /* ================================================================== */
    /* Zero AI / zero network                                              */
    /* ================================================================== */

    public function test_the_page_renders_with_every_ai_service_bound_to_fail(): void
    {
        Http::fake();

        // Not "was not called" — CANNOT be called. Any invocation fails the test outright.
        $this->mock(OpenAiClientService::class)->shouldNotReceive('send');
        $this->mock(AskAiOpenAiAdapterService::class)->shouldNotReceive('generate');
        $this->mock(AskAiIntentNormalizerService::class)->shouldNotReceive('normalize');
        $this->mock(AskAiQuestionClassifierService::class)->shouldNotReceive('classify');
        $this->mock(AskAiKnowledgeSearchService::class)->shouldNotReceive('search');
        $this->mock(AskAiRunnerV2Service::class)->shouldNotReceive('run');

        $seller   = html_entity_decode($this->sellerCard(['flood_zone_code' => 'AE']), ENT_QUOTES);
        $landlord = html_entity_decode($this->landlordCard(['flood_zone_code' => 'VE']), ENT_QUOTES);

        $this->assertStringContainsString('FEMA Flood Zone AE', $seller);
        $this->assertStringContainsString('FEMA Flood Zone VE', $landlord);

        Http::assertNothingSent();
    }

    public function test_revealing_the_flood_answer_cannot_make_a_request(): void
    {
        // The reveal is a native <details> toggle over markup already on the page: the answer
        // text is present before any interaction, and the card carries nothing that could
        // turn a toggle into a request.
        $card = $this->sellerCard(['flood_zone_code' => 'AE']);

        $this->assertStringContainsString('FEMA Flood Zone AE', html_entity_decode($card, ENT_QUOTES));

        foreach (['<script', '<form', 'action=', 'fetch(', 'XMLHttpRequest',
                  'ask-ai/listing-question', 'api/ask-ai/ask', 'agent-ai/'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $card, "The card contains '{$forbidden}'.");
        }
                  // Batch 3 SUPERSEDES the "<input" and "<a" clauses of this list. The card now
                  // carries a typed-question box, so an <input> is expected — and a <button>
                  // with it. What still must hold is stronger and is asserted instead: no
                  // <form> and no action for anything to submit to, the input carries no
                  // `name` so a form could not carry it even if one existed, and no fetch,
                  // XHR or endpoint string appears anywhere in the card.
        $this->assertStringContainsString('data-ask-ai-ask-input', $card);
        $this->assertDoesNotMatchRegularExpression('/<input\\b[^>]*\\bname=/', $card,
            'The typed-question input must carry no name attribute.');
    }
}
