<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Representation Preferences — what the four detail pages publish, and to whom.
 *
 * THE ROUTES CARRY `web` AND NO AUTH MIDDLEWARE, so the anonymous case is a reachable state
 * rather than a hypothetical, and everything the row builders emitted was on the open web.
 *
 * EVERY ABSENCE ASSERTION IS PAIRED WITH A POSITIVE CONTROL, following the rule
 * HireAgentDetailViewPrivacyTest's own docblock records: an absence test against a page that
 * renders nothing passes for the wrong reason. Each sensitive value is planted alongside a benign
 * one, and the benign one must be visible in the same response.
 */
class HireAgentRepresentationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    /** Planted free text. Distinctive enough that a match cannot be incidental. */
    private const SECRET_TENANT   = 'ZZTENANTSITUATIONZZ';
    private const SECRET_BUYER    = 'ZZBUYERDEALBREAKERZZ';
    private const SECRET_SELLER   = 'ZZSELLERPASTAGENTZZ';
    private const SECRET_LANDLORD = 'ZZLANDLORDNOTESZZ';

    /** Planted public value — the positive control for every absence assertion. */
    private const PUBLIC_MARKER = 'ZZPUBLICNEGOTIATIONZZ';

    // ── Retired field ────────────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider landlordPropertyTypes
     */
    public function stored_preferred_tenant_type_values_never_render_publicly(string $propertyType): void
    {
        [$owner, $listing, $url] = $this->landlordListing($propertyType, [
            'tenant_type_preference'       => 'Individual / Family',
            'tenant_type_preference_other' => 'Long-term professional tenant',
            'negotiation_style'            => self::PUBLIC_MARKER,
        ]);

        foreach ([null, $owner] as $viewer) {
            $response = $viewer ? $this->actingAs($viewer)->get($url) : $this->get($url);
            $response->assertOk();

            $response->assertDontSee('Preferred Tenant Type', false);
            $response->assertDontSee('Individual / Family', false);
            $response->assertDontSee('Long-term professional tenant', false);

            // Positive control — the section rendered, so the absences above mean something.
            $response->assertSee(self::PUBLIC_MARKER, false);
        }
    }

    public static function landlordPropertyTypes(): array
    {
        return [
            'residential' => ['Residential Property'],
            'commercial'  => ['Commercial Property'],
        ];
    }

    /** @test */
    public function the_retired_value_is_withheld_even_from_the_owner(): void
    {
        // Not a privacy rule but a retirement one: the field no longer exists as a question, so
        // there is no audience it should render to. Covered above for both property types; this
        // states it as its own expectation so a future "let the owner still see it" change fails.
        [$owner, , $url] = $this->landlordListing('Residential Property', [
            'tenant_type_preference' => 'Students',
            'negotiation_style'      => self::PUBLIC_MARKER,
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertDontSee('Students', false)
            ->assertSee(self::PUBLIC_MARKER, false);
    }

    // ── Free text: withheld from anonymous, kept for the owner ───────────────

    /** @test */
    public function landlord_free_text_and_screening_posture_are_withheld_from_anonymous_visitors(): void
    {
        [$owner, , $url] = $this->landlordListing('Residential Property', [
            'additional_representation_notes' => self::SECRET_LANDLORD,
            'applicant_screening_approach'    => 'Written criteria, applied uniformly',
            'negotiation_style'               => self::PUBLIC_MARKER,
        ]);

        $guest = $this->get($url)->assertOk();
        $guest->assertDontSee(self::SECRET_LANDLORD, false);
        $guest->assertDontSee('Applicant Screening Approach', false);
        $guest->assertSee(self::PUBLIC_MARKER, false);

        $ownerView = $this->actingAs($owner)->get($url)->assertOk();
        $ownerView->assertSee(self::SECRET_LANDLORD, false);
        $ownerView->assertSee('Applicant Screening Approach', false);
        $ownerView->assertSee(self::PUBLIC_MARKER, false);
    }

    /** @test */
    public function tenant_situation_notes_are_withheld_from_anonymous_visitors(): void
    {
        [$owner, , $url] = $this->tenantListing([
            'concerns_or_barriers'           => self::SECRET_TENANT,
            'additional_compatibility_notes' => 'ZZTENANTNOTESZZ',
            'negotiation_style'              => self::PUBLIC_MARKER,
        ]);

        $guest = $this->get($url)->assertOk();
        $guest->assertDontSee(self::SECRET_TENANT, false);
        $guest->assertDontSee('ZZTENANTNOTESZZ', false);
        $guest->assertSee(self::PUBLIC_MARKER, false);

        $ownerView = $this->actingAs($owner)->get($url)->assertOk();
        $ownerView->assertSee(self::SECRET_TENANT, false);
        $ownerView->assertSee('ZZTENANTNOTESZZ', false);
    }

    /** @test */
    public function buyer_deal_breakers_and_notes_are_withheld_from_anonymous_visitors(): void
    {
        [$owner, , $url] = $this->buyerListing([
            'deal_breakers'                  => self::SECRET_BUYER,
            'additional_compatibility_notes' => 'ZZBUYERNOTESZZ',
            'negotiation_style'              => self::PUBLIC_MARKER,
        ]);

        $guest = $this->get($url)->assertOk();
        $guest->assertDontSee(self::SECRET_BUYER, false);
        $guest->assertDontSee('ZZBUYERNOTESZZ', false);
        $guest->assertSee(self::PUBLIC_MARKER, false);

        $this->actingAs($owner)->get($url)->assertOk()->assertSee(self::SECRET_BUYER, false);
    }

    /** @test */
    public function seller_free_text_and_negotiating_position_are_withheld_from_anonymous_visitors(): void
    {
        [$owner, , $url] = $this->sellerListing([
            'what_did_not_work_before'       => self::SECRET_SELLER,
            'additional_decision_makers'     => 'ZZSELLERDECIDERSZZ',
            'additional_compatibility_notes' => 'ZZSELLERNOTESZZ',
            'post_sale_plan'                 => 'Relocating Out of Area',
            'firm_on_price'                  => 'Flexible — Willing to Negotiate Significantly',
            'negotiation_style'              => self::PUBLIC_MARKER,
        ]);

        $guest = $this->get($url)->assertOk();
        $guest->assertDontSee(self::SECRET_SELLER, false);
        $guest->assertDontSee('ZZSELLERDECIDERSZZ', false);
        $guest->assertDontSee('ZZSELLERNOTESZZ', false);
        // Not Fair Housing — publishing the client's own motivation and price firmness works
        // against the client this listing represents.
        $guest->assertDontSee('Post-Sale Plans', false);
        $guest->assertDontSee('Firm on Asking Price', false);
        $guest->assertSee(self::PUBLIC_MARKER, false);

        $ownerView = $this->actingAs($owner)->get($url)->assertOk();
        $ownerView->assertSee(self::SECRET_SELLER, false);
        $ownerView->assertSee('Post-Sale Plans', false);
        $ownerView->assertSee('Firm on Asking Price', false);
    }

    // ── An agent is not the owner ────────────────────────────────────────────

    /**
     * AUDIENCE_AGENT admits any account whose user_type is agent/buyer_agent/seller_agent — it
     * does not test whether they bid on this listing, and there is no hired-agent tier. Until one
     * exists, "every agent on the platform" is not a safe audience for a tenant's account of
     * their own situation.
     *
     * @test
     */
    public function an_unrelated_agent_does_not_see_the_private_rows(): void
    {
        [, , $url] = $this->tenantListing([
            'concerns_or_barriers' => self::SECRET_TENANT,
            'negotiation_style'    => self::PUBLIC_MARKER,
        ]);

        $agent = User::factory()->create(['user_type' => 'agent']);

        $this->actingAs($agent)->get($url)->assertOk()
            ->assertDontSee(self::SECRET_TENANT, false)
            ->assertSee(self::PUBLIC_MARKER, false);
    }

    /**
     * Widest-match-first audience resolution means a listing owner who ALSO holds an agent
     * account resolves to AUDIENCE_AGENT, not AUDIENCE_OWNER. Gating private rows on the tier
     * would therefore hide a client's own answers from them. The gate asks about ownership.
     *
     * @test
     */
    public function an_owner_who_is_also_an_agent_still_sees_their_own_private_rows(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);

        $listing = $this->makeTenantListing($owner, [
            'concerns_or_barriers' => self::SECRET_TENANT,
            'negotiation_style'    => self::PUBLIC_MARKER,
        ]);

        $this->actingAs($owner)->get(route('tenant.agent.auction.view', $listing->id))
            ->assertOk()
            ->assertSee(self::SECRET_TENANT, false)
            ->assertSee(self::PUBLIC_MARKER, false);
    }

    // ── What must NOT be withheld ────────────────────────────────────────────

    /**
     * The accessibility case, stated as its own expectation because it is the one this whole
     * effort must not break: a stated accessibility need is a legitimate consumer requirement,
     * not sensitive data to be suppressed and not something any future screen may strip.
     *
     * @test
     */
    public function accessibility_features_remains_a_public_representation_priority(): void
    {
        [, , $url] = $this->tenantListing([
            'representation_priorities' => ['Accessibility features', 'School district'],
            'negotiation_style'         => self::PUBLIC_MARKER,
        ]);

        $this->get($url)->assertOk()
            ->assertSee('Accessibility features', false)
            ->assertSee('School district', false);
    }

    /** @test */
    public function ordinary_representation_preferences_stay_public(): void
    {
        [, , $url] = $this->landlordListing('Residential Property', [
            'communication_style'           => 'Email Only',
            'preferred_agent_working_style' => 'Data-Driven & Analytical',
            'negotiation_style'             => self::PUBLIC_MARKER,
        ]);

        $this->get($url)->assertOk()
            ->assertSee('Email Only', false)
            ->assertSee('Data-Driven &amp; Analytical', false)
            ->assertSee(self::PUBLIC_MARKER, false);
    }

    /** @test */
    public function commercial_preferred_business_use_is_public_and_residential_never_renders_it(): void
    {
        [, , $commercialUrl] = $this->landlordListing('Commercial Property', [
            'preferred_business_use' => ['Office', 'Medical / Dental'],
            'negotiation_style'      => self::PUBLIC_MARKER,
        ]);

        $this->get($commercialUrl)->assertOk()
            ->assertSee('Preferred Business Use', false)
            ->assertSee('Medical / Dental', false);

        // A residential listing that still holds the key — possible before the projection ever
        // ran, or if a row were written by some path we have not thought of — must not render it.
        [, , $residentialUrl] = $this->landlordListing('Residential Property', [
            'preferred_business_use' => ['Office'],
            'negotiation_style'      => self::PUBLIC_MARKER,
        ]);

        $this->get($residentialUrl)->assertOk()
            ->assertDontSee('Preferred Business Use', false)
            ->assertSee(self::PUBLIC_MARKER, false);
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{0: User, 1: Model, 2: string} */
    private function landlordListing(string $propertyType, array $landlordBlock): array
    {
        $owner   = User::factory()->create(['user_type' => 'landlord']);
        $listing = LandlordAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Landlord listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('address', '100 Test Street');
        $listing->saveMeta('property_type', $propertyType);
        $listing->saveMeta('compatibility_preferences', json_encode(['landlord_specific' => $landlordBlock]));

        return [$owner, $listing, route('landlord.agent.auction.view', $listing->id)];
    }

    /** @return array{0: User, 1: Model, 2: string} */
    private function tenantListing(array $tenantBlock): array
    {
        $owner   = User::factory()->create(['user_type' => 'tenant']);
        $listing = $this->makeTenantListing($owner, $tenantBlock);

        return [$owner, $listing, route('tenant.agent.auction.view', $listing->id)];
    }

    private function makeTenantListing(User $owner, array $tenantBlock): Model
    {
        $listing = TenantAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Tenant listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('address', '100 Test Street');
        $listing->saveMeta('compatibility_preferences', json_encode(['tenant_specific' => $tenantBlock]));

        return $listing;
    }

    /** @return array{0: User, 1: Model, 2: string} */
    private function buyerListing(array $buyerBlock): array
    {
        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $listing = BuyerAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Buyer listing', 'address' => '100 Test Street',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('compatibility_preferences', json_encode(['buyer_specific' => $buyerBlock]));

        return [$owner, $listing, route('buyer.view-auction', $listing->id)];
    }

    /** @return array{0: User, 1: Model, 2: string} */
    private function sellerListing(array $sellerBlock): array
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Seller listing', 'address' => '100 Test Street',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('compatibility_preferences', json_encode(['seller_specific' => $sellerBlock]));

        return [$owner, $listing, route('seller.agent.auction.detail', $listing->id)];
    }
}
