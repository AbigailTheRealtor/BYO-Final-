<?php

namespace Tests\Feature\FairHousing;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\OfferAuction;
use App\Models\RentalQualificationCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Fair Housing Phase 2 — the screening surfaces are RENDERED, not just read.
 *
 * WHY THIS FILE EXISTS. Every other Phase 2 display assertion inspects Blade
 * source as a string. That is enough to prove a retired option is absent from a
 * file and nothing else: it cannot tell whether the file still compiles. Phase 2
 * shipped four references written `\\App\\Support\\...` instead of
 * `\App\Support\...`, which is invalid PHP, and the landlord public listing page
 * — a route with no auth middleware — returned HTTP 500 to every visitor while
 * the entire Fair Housing suite stayed green.
 *
 * So these tests drive the real routes through Laravel and Blade with stale
 * retired values in the database, and assert both that the page renders and that
 * the retired values are not on it.
 */
class LandlordScreeningRenderTest extends TestCase
{
    use DatabaseTransactions;

    /** Historical values a listing may still hold; none may reach a rendered page. */
    private const STALE_SCREENING = [
        'employment_requirement'              => 'Employed',
        'custom_employment_requirement'       => 'Must be W-2 employed at the same employer',
        'employment_verification_requirement' => 'Required',
        'criminal_background_requirement'     => 'No criminal background',
        'eviction_history_requirement'        => 'Case-by-case review',
        'bankruptcy_requirement'              => 'Case-by-case review',
        'credit_score_flexibility'            => 'Compensating factors considered',
    ];

    // =====================================================================
    // Landlord public listing page — no auth middleware
    // =====================================================================

    /** @test */
    public function the_landlord_public_view_renders_for_an_anonymous_visitor(): void
    {
        $auction = $this->makeListing();

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200);
    }

    /** @test */
    public function the_landlord_public_view_renders_when_the_listing_holds_stale_retired_values(): void
    {
        $auction = $this->makeListing(self::STALE_SCREENING);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200);
    }

    /** @test */
    public function the_landlord_public_view_shows_no_retired_screening_value(): void
    {
        $auction  = $this->makeListing(self::STALE_SCREENING);
        $response = $this->get(route('offer.listing.landlord.view', $auction->id));

        $response->assertStatus(200);

        foreach ([
            'Employment Requirement',
            'Employment Verification',
            'Must be W-2 employed at the same employer',
            'No criminal background',
            'Case-by-case review',
            'Compensating factors considered',
        ] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
    }

    /** @test */
    public function the_landlord_public_view_shows_a_current_criminal_policy(): void
    {
        $auction = $this->makeListing([
            'criminal_background_requirement' => 'Individualized review of convictions',
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('Individualized review of convictions', false);
    }

    // =====================================================================
    // Rental qualification page — the second publication of the same policy
    // =====================================================================

    /**
     * @test
     *
     * The assertions here are deliberately scoped to strings that can only be the
     * LANDLORD's published policy. The same page also carries the applicant's own
     * self-disclosure form, which legitimately asks "Employment verification
     * available" and offers the applicant the option "No criminal background" —
     * different fields, deferred to a later phase, and untouched by Phase 2. A
     * blunt assertDontSee on those strings would fail on the applicant control and
     * tempt someone to "fix" the wrong side of the fence.
     */
    public function the_qualification_page_publishes_no_retired_landlord_policy(): void
    {
        $auction  = $this->makeListing(self::STALE_SCREENING);
        $response = $this->get(route('offer.listing.landlord.qualification.check', $auction->id));

        $response->assertStatus(200);

        foreach ([
            // Landlord policy row labels.
            'Employment requirement',
            'rqc-req-label">Employment verification',
            // Values only a landlord policy could produce.
            'Must be W-2 employed at the same employer',
            'Compensating factors considered',
            'Case-by-case review',
            // The suppressed blanket ban must not appear as a landlord policy row.
            'Criminal history policy',
        ] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
    }

    /** @test */
    public function the_qualification_page_still_offers_the_applicant_their_own_disclosure(): void
    {
        // Containment, from the other direction: Phase 2 changed only the landlord
        // policy this page republishes. The applicant's own controls stay put.
        $auction  = $this->makeListing(self::STALE_SCREENING);
        $response = $this->get(route('offer.listing.landlord.qualification.check', $auction->id));

        $response->assertStatus(200);
        $response->assertSee('name="criminal_background"', false);
        $response->assertSee('Employment verification available', false);
    }

    /** @test */
    public function the_qualification_page_shows_a_current_landlord_criminal_policy(): void
    {
        $auction  = $this->makeListing([
            'criminal_background_requirement' => 'Individualized review of convictions',
        ]);
        $response = $this->get(route('offer.listing.landlord.qualification.check', $auction->id));

        $response->assertStatus(200);
        $response->assertSee('Individualized review of convictions', false);
    }

    // =====================================================================
    // Rental qualification REVIEW page — the landlord's applicant scorecard
    //
    // This surface was missed by Phase 2's first pass. It did not merely display
    // the retired employment requirement: it scored the applicant pass/review by
    // matching their reported employment status against it, and treated any
    // criminal disclosure other than a clean record as something to flag. That is
    // the retired gate being APPLIED to a real person.
    // =====================================================================

    /** @test */
    public function the_review_page_renders_for_the_owner_with_stale_retired_values(): void
    {
        [$owner, $auction, $check] = $this->makeReview(self::STALE_SCREENING);

        $this->actingAs($owner)
            ->get(route('offer.listing.landlord.qualification.review', [$auction->id, $check->id]))
            ->assertStatus(200);
    }

    /** @test */
    public function the_review_page_does_not_display_a_stale_employment_requirement(): void
    {
        [$owner, $auction, $check] = $this->makeReview(self::STALE_SCREENING);

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.landlord.qualification.review', [$auction->id, $check->id]));

        $response->assertStatus(200);
        $response->assertDontSee('Must be W-2 employed at the same employer', false);
        $response->assertDontSee('Employment requirement', false);
    }

    /**
     * @test
     *
     * The applicant here reports "Retired", which the retired gate would have
     * scored against a landlord requirement of "Employed". No verdict badge may
     * be attached to that row now — not "Meets", not "Below requirement", not
     * "Review".
     */
    public function a_stale_employment_requirement_produces_no_verdict_against_a_retired_applicant(): void
    {
        [$owner, $auction, $check] = $this->makeReview(
            self::STALE_SCREENING,
            ['employment_status' => 'Retired', 'income_source' => 'Pension and Social Security']
        );

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.landlord.qualification.review', [$auction->id, $check->id]));

        $response->assertStatus(200);
        $this->assertRowHasNoVerdict($response->getContent(), 'Income source (applicant-reported)');

        // The applicant's own answer is still shown — it is their submission.
        $response->assertSee('Retired', false);
    }

    /** @test */
    public function a_stale_employment_verification_requirement_produces_no_verdict(): void
    {
        [$owner, $auction, $check] = $this->makeReview(
            self::STALE_SCREENING,
            ['employment_verification_available' => 'No']
        );

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.landlord.qualification.review', [$auction->id, $check->id]));

        $response->assertStatus(200);
        $this->assertRowHasNoVerdict($response->getContent(), 'Employment docs available');
    }

    /** @test */
    public function a_stale_blanket_criminal_policy_is_neither_displayed_nor_scored(): void
    {
        [$owner, $auction, $check] = $this->makeReview(
            ['criminal_background_requirement' => 'No criminal background'],
            ['criminal_background' => 'Criminal background disclosed']
        );

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.landlord.qualification.review', [$auction->id, $check->id]));

        $response->assertStatus(200);
        $this->assertRowHasNoVerdict($response->getContent(), 'Criminal history policy');

        // The landlord's retired blanket ban is not republished as their policy.
        $this->assertStringNotContainsString(
            '>No criminal background<',
            $this->rowFor($response->getContent(), 'Criminal history policy'),
            'The retired blanket ban was rendered as the landlord policy.'
        );
    }

    /** @test */
    public function a_current_criminal_policy_displays_but_still_yields_no_automatic_verdict(): void
    {
        [$owner, $auction, $check] = $this->makeReview(
            ['criminal_background_requirement' => 'Individualized review of convictions'],
            ['criminal_background' => 'Criminal background disclosed']
        );

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.landlord.qualification.review', [$auction->id, $check->id]));

        $response->assertStatus(200);

        $row = $this->rowFor($response->getContent(), 'Criminal history policy');
        $this->assertStringContainsString('Individualized review of convictions', $row);
        $this->assertStringContainsString('Criminal background disclosed', $row);

        // Individualized review means a human weighs it. The page must not decide.
        $this->assertRowHasNoVerdict($response->getContent(), 'Criminal history policy');
    }

    /** @test */
    public function the_legitimate_income_comparison_still_functions(): void
    {
        [$owner, $auction, $check] = $this->makeReview(
            ['income_qualification_method' => '3x Rent', 'income_verification_requirement' => 'Required'],
            ['monthly_household_income' => '9000', 'income_verification_available' => 'Yes']
        );

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.landlord.qualification.review', [$auction->id, $check->id]));

        $response->assertStatus(200);
        $response->assertSee('9,000', false);

        // Income documentation is still compared and still returns a verdict.
        $this->assertStringContainsString('Meets', $this->rowFor($response->getContent(), 'Income docs available'));
    }

    // =====================================================================

    /** Extract the single <tr> containing a comparison-row label. */
    private function rowFor(string $html, string $label): string
    {
        $at = strpos($html, '>' . $label . '<');
        $this->assertNotFalse($at, "Comparison row '{$label}' not found on the page.");

        $start = strrpos(substr($html, 0, $at), '<tr');
        $end   = strpos($html, '</tr>', $at);

        return substr($html, $start, $end - $start);
    }

    /** A row may carry no pass/fail/review judgement — only the neutral N/A marker. */
    private function assertRowHasNoVerdict(string $html, string $label): void
    {
        $row = $this->rowFor($html, $label);

        foreach (['Meets', 'Below requirement', 'Below (flexible)', '>Review<'] as $verdict) {
            $this->assertStringNotContainsString(
                $verdict,
                $row,
                "Row '{$label}' carries the verdict '{$verdict}'; this comparison must not produce one."
            );
        }
    }

    private function makeReview(array $screening = [], array $applicant = []): array
    {
        $auction = $this->makeListing($screening);
        $owner   = User::find($auction->user_id);

        $check = RentalQualificationCheck::create(array_merge([
            'landlord_listing_id' => $auction->id,
            'name'                => 'Applicant Example',
            'email'               => 'applicant@example.com',
            'status'              => 'submitted',
        ], $applicant));

        return [$owner, $auction, $check];
    }

    private function makeListing(array $screening = []): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'agent']);

        $auction = LandlordAgentAuction::create([
            'user_id'     => $owner->id,
            'title'       => 'Phase 2 Screening Render Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $rows = [
            'workflow_type'        => 'offer_listing',
            'first_name'           => 'Test',
            'last_name'            => 'Agent',
            'phone_number'         => '5551234567',
            'email'                => 'agent@example.com',
            'desired_lease_length' => json_encode(['12 Months']),
        ] + $screening;

        foreach ($rows as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        $offerAuction = OfferAuction::create(['user_id' => $owner->id]);
        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'linked_offer_auction_id',
            'meta_value'                => (string) $offerAuction->id,
        ]);

        return $auction;
    }
}
