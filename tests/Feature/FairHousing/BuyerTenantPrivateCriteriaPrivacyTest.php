<?php

namespace Tests\Feature\FairHousing;

use App\Models\BuyerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Support\OfferListing\CriteriaPrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Buyer / Tenant public detail pages — private criteria are owner-only.
 *
 * WHY THIS IS AN ANONYMOUS-ACCESS PROBLEM. routes/web.php places both detail
 * views outside the auth group, deliberately and with a comment saying so, so a
 * visitor can open a card from the public search pages. The controllers gate
 * only archived and draft/unapproved listings. An approved, non-draft listing is
 * served to the open internet in full, so everything the Blade renders is public
 * by default.
 *
 * WHAT WAS LEAKING. A Buyer or Tenant listing belongs to a CONSUMER. Alongside
 * their search criteria these pages published the tenant's monthly household
 * income, their minimum annual net income, their service and support animal
 * status, their accessibility requirements, their own street address and unit,
 * their household size and their phone number; and the buyer's pre-approval
 * status and amount, cash budget, down payment, occupant count, the address of
 * the home they currently live in, and their email.
 *
 * THE DISTINCTION UNDER TEST. A budget is what someone wants to SPEND and stays
 * public — it is the listing. A pre-approval amount, cash reserves, a down
 * payment, a credit score and an income are what a lender or landlord would use
 * to JUDGE them, and are not. These two look similar and are not the same fact,
 * so every method below asserts both halves: the private value is gone AND the
 * public criterion is still there. A test that only asserted the first would
 * pass just as well against a page that had been emptied.
 *
 * NOT A BLADE-TEXT ASSERTION. These drive the real public routes over HTTP,
 * three times each: as a guest, as a signed-in unrelated user, and as the owner.
 * The signed-in non-owner is not redundant — a gate written as `auth()->check()`
 * rather than as an ownership comparison passes the guest case and fails this
 * one, and that is the likeliest way this protection would be got wrong.
 */
class BuyerTenantPrivateCriteriaPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    /* ------------------------------------------------------------------ */
    /* Fixtures                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Tenant private values. Each is a sentinel string rather than a realistic
     * value so a hit in the HTML is unambiguous and cannot be a coincidence of
     * formatting. Money fields still have to parse as money or $fmtMoney()
     * returns null and the row never renders — which would make the assertion
     * pass for the wrong reason — so those carry a distinctive NUMBER.
     */
    private const TENANT_PRIVATE = [
        'monthly_income'             => '91317',
        'minimum_annual_net_income'  => '91319',
        'move_in_funds_available'    => '91321',
        'security_deposit_budget'    => '91323',
        'credit_score_range'         => 'SENTINEL-TENANT-CREDIT-500-549',
        'prior_eviction'             => 'SENTINEL-TENANT-EVICTION',
        'prior_felony'               => 'SENTINEL-TENANT-FELONY',
        'screening_concerns'         => 'SENTINEL-TENANT-SCREENING',
        'service_animal'             => 'SENTINEL-TENANT-SERVICE-ANIMAL',
        'support_animal'             => 'SENTINEL-TENANT-SUPPORT-ANIMAL',
        'accessibility_requirements' => 'SENTINEL-TENANT-ACCESSIBILITY',
        'address'                    => 'SENTINEL-TENANT-PRIVATE-STREET',
        'unit_number'                => 'SENTINEL-TENANT-UNIT',
        'current_status'             => 'SENTINEL-TENANT-CURRENT-STATUS',
        'number_occupant'            => 'SENTINEL-TENANT-OCCUPANTS',
        'commute_destination_zip'    => 'SENTINEL-TENANT-COMMUTE-ZIP',
        'email'                      => 'sentinel-tenant@example.test',
        'phone_number'               => 'SENTINEL-TENANT-PHONE',
        'first_name'                 => 'SentinelTenantFirst',
        'last_name'                  => 'SentinelTenantLast',
    ];

    /** Row labels that must not appear for a non-owner on the tenant page. */
    private const TENANT_PRIVATE_LABELS = [
        'Monthly Income',
        'Min Annual Net Income',
        'Move-In Funds Available',
        'Security Deposit Budget',
        'Credit Score Range',
        'Service Animal',
        'Support Animal',
        'Accessibility Requirements',
        'Current Status',
    ];

    /** Tenant search criteria that MUST survive — this is the listing. */
    private const TENANT_PUBLIC = [
        'budget'                => '82411',
        'property_type'         => 'SENTINEL-TENANT-PROPTYPE',
        'bedrooms'              => 'SENTINEL-TENANT-BEDROOMS',
        'bathrooms'             => 'SENTINEL-TENANT-BATHROOMS',
        'minimum_heated_square' => 'SENTINEL-TENANT-SQFT',
        'min_acreage'           => 'SENTINEL-TENANT-ACREAGE',
        'state'                 => 'SENTINEL-TENANT-STATE',
        'zip_codes'             => 'SENTINEL-TENANT-ZIPS',
        'pets'                  => 'SENTINEL-TENANT-PETS',
        'smoking_preference'    => 'SENTINEL-TENANT-SMOKING',
        'rental_purpose'        => 'SENTINEL-TENANT-RENTALPURPOSE',
    ];

    private const BUYER_PRIVATE = [
        'pre_approved'                  => 'SENTINEL-BUYER-PREAPPROVED',
        'pre_approval_amount'           => '71317',
        'cash_budget'                   => '71319',
        'down_payment_amount'           => '71321',
        'number_occupant'               => 'SENTINEL-BUYER-OCCUPANTS',
        'home_sale_contingency_address' => 'SENTINEL-BUYER-CURRENT-HOME',
        'unit_number'                   => 'SENTINEL-BUYER-UNIT',
        'commute_destination_zip'       => 'SENTINEL-BUYER-COMMUTE-ZIP',
        'email'                         => 'sentinel-buyer@example.test',
        'phone_number'                  => 'SENTINEL-BUYER-PHONE',
        'first_name'                    => 'SentinelBuyerFirst',
        'last_name'                     => 'SentinelBuyerLast',
    ];

    /** Row labels that must not appear for a non-owner on the buyer page. */
    private const BUYER_PRIVATE_LABELS = [
        'Buyer Pre-Approved',
        'Pre-Approval Amount',
        'Cash Budget',
        'Down Payment Amount',
        'Number of Occupants',
    ];

    /**
     * Buyer search criteria that MUST survive — this is the listing.
     *
     * `home_sale_contingency` is deliberately here and set to 'Yes': the Home Sale
     * Contingency block renders only when it resolves to Included or Negotiable,
     * so without it the private address row would be absent because the whole
     * block is absent, and the suppression assertion would pass for the wrong
     * reason. The contingency itself is a term of the offer being sought and is
     * public; the address of the house the buyer lives in is not.
     */
    private const BUYER_PUBLIC = [
        'home_sale_contingency' => 'Yes',
        'maximum_budget'        => '62411',
        'property_type'         => 'SENTINEL-BUYER-PROPTYPE',
        'bedrooms'              => 'SENTINEL-BUYER-BEDROOMS',
        'bathrooms'             => 'SENTINEL-BUYER-BATHROOMS',
        'minimum_heated_square' => 'SENTINEL-BUYER-SQFT',
        'min_acreage'           => 'SENTINEL-BUYER-ACREAGE',
        'property_state'        => 'SENTINEL-BUYER-STATE',
        'pool_needed'           => 'SENTINEL-BUYER-POOL',
        'garage_needed'         => 'SENTINEL-BUYER-GARAGE',
        'target_closing_date'   => 'SENTINEL-BUYER-TIMEFRAME',
        'purchase_purpose'      => 'SENTINEL-BUYER-PURPOSE',
    ];

    private function makeTenantListing(User $owner): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach (self::TENANT_PRIVATE as $k => $v) {
            $auction->saveMeta($k, $v);
        }
        foreach (self::TENANT_PUBLIC as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return $auction;
    }

    private function makeBuyerListing(User $owner): BuyerAgentAuction
    {
        $auction = BuyerAgentAuction::create([
            'user_id'     => $owner->id,
            'title'       => 'Buyer criteria privacy fixture',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach (self::BUYER_PRIVATE as $k => $v) {
            $auction->saveMeta($k, $v);
        }
        foreach (self::BUYER_PUBLIC as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return $auction;
    }

    /* ================================================================== */
    /* TENANT — non-owner                                                  */
    /* ================================================================== */

    /** @test */
    public function a_guest_sees_no_tenant_private_criteria(): void
    {
        $auction = $this->makeTenantListing(User::factory()->create());

        $response = $this->get(route('offer.listing.tenant.view', $auction->id));
        $response->assertOk();

        foreach (self::TENANT_PRIVATE as $key => $value) {
            $response->assertDontSee($value, false);
        }
        foreach (self::TENANT_PRIVATE_LABELS as $label) {
            $response->assertDontSee($label, false);
        }
    }

    /** @test */
    public function a_signed_in_non_owner_sees_no_tenant_private_criteria(): void
    {
        // The gate must be OWNERSHIP, not "is authenticated". A gate written as
        // auth()->check() passes the guest test above and fails here.
        $auction = $this->makeTenantListing(User::factory()->create());

        $response = $this->actingAs(User::factory()->create())
            ->get(route('offer.listing.tenant.view', $auction->id));
        $response->assertOk();

        foreach (self::TENANT_PRIVATE as $key => $value) {
            $response->assertDontSee($value, false);
        }
        foreach (self::TENANT_PRIVATE_LABELS as $label) {
            $response->assertDontSee($label, false);
        }
    }

    /** @test */
    public function a_tenant_service_or_support_animal_is_never_published_as_a_pet_preference(): void
    {
        // Service and support animals are accommodation disclosures, not pets.
        // Publishing them publishes the disability. The pet-friendly requirement
        // itself is a real housing-search criterion and must survive beside them.
        $auction = $this->makeTenantListing(User::factory()->create());

        $response = $this->get(route('offer.listing.tenant.view', $auction->id));

        $response->assertOk();
        $response->assertDontSee('SENTINEL-TENANT-SERVICE-ANIMAL', false);
        $response->assertDontSee('SENTINEL-TENANT-SUPPORT-ANIMAL', false);
        $response->assertDontSee('Service Animal', false);
        $response->assertDontSee('Support Animal', false);
        $response->assertSee('SENTINEL-TENANT-PETS', false);
    }

    /** @test */
    public function tenant_private_values_do_not_reappear_in_the_additional_information_fallback(): void
    {
        // The tenant page ends with an overflow section driven by the $meta array
        // itself. Redacting a key by blanking it rather than removing it would
        // leave the key present and land it here.
        $auction = $this->makeTenantListing(User::factory()->create());

        $response = $this->get(route('offer.listing.tenant.view', $auction->id));

        $response->assertOk();
        $response->assertDontSee('SENTINEL-TENANT-ACCESSIBILITY', false);
        $response->assertDontSee('SENTINEL-TENANT-PRIVATE-STREET', false);
        $response->assertDontSee('91317', false);
    }

    /** @test */
    public function a_guest_still_sees_every_public_tenant_search_criterion(): void
    {
        $auction = $this->makeTenantListing(User::factory()->create());

        $response = $this->get(route('offer.listing.tenant.view', $auction->id));

        $response->assertOk();
        foreach (self::TENANT_PUBLIC as $key => $value) {
            // The rent budget renders formatted as money, so assert the digits.
            $needle = $key === 'budget' ? '82,411' : $value;
            $response->assertSee($needle, false);
        }
    }

    /* ================================================================== */
    /* TENANT — owner                                                      */
    /* ================================================================== */

    /** @test */
    public function the_tenant_owner_still_sees_their_own_private_criteria(): void
    {
        $owner   = User::factory()->create();
        $auction = $this->makeTenantListing($owner);

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.tenant.view', $auction->id));
        $response->assertOk();

        $response->assertSee('SENTINEL-TENANT-ACCESSIBILITY', false);
        $response->assertSee('SENTINEL-TENANT-SERVICE-ANIMAL', false);
        $response->assertSee('SENTINEL-TENANT-PRIVATE-STREET', false);
        $response->assertSee('SENTINEL-TENANT-CREDIT-500-549', false);
        $response->assertSee('91,317', false);  // monthly income, formatted
    }

    /* ================================================================== */
    /* BUYER — non-owner                                                   */
    /* ================================================================== */

    /** @test */
    public function a_guest_sees_no_buyer_private_criteria(): void
    {
        $auction = $this->makeBuyerListing(User::factory()->create());

        $response = $this->get(route('offer.listing.buyer.view', $auction->id));
        $response->assertOk();

        foreach (self::BUYER_PRIVATE as $key => $value) {
            $response->assertDontSee($value, false);
        }
        foreach (self::BUYER_PRIVATE_LABELS as $label) {
            $response->assertDontSee($label, false);
        }
    }

    /** @test */
    public function a_signed_in_non_owner_sees_no_buyer_private_criteria(): void
    {
        $auction = $this->makeBuyerListing(User::factory()->create());

        $response = $this->actingAs(User::factory()->create())
            ->get(route('offer.listing.buyer.view', $auction->id));
        $response->assertOk();

        foreach (self::BUYER_PRIVATE as $key => $value) {
            $response->assertDontSee($value, false);
        }
        foreach (self::BUYER_PRIVATE_LABELS as $label) {
            $response->assertDontSee($label, false);
        }
    }

    /** @test */
    public function the_buyers_own_current_home_address_is_never_published(): void
    {
        // Rendered under Home Sale Contingency as "Property Address" — it is the
        // house the buyer lives in today, not the one they are shopping for.
        $auction = $this->makeBuyerListing(User::factory()->create());

        $response = $this->get(route('offer.listing.buyer.view', $auction->id));

        $response->assertOk();
        // The block itself IS on the page — so the two rows below are suppressed
        // because they are private, not because the section failed to render.
        $response->assertSee('Home Sale Contingency', false);
        $response->assertDontSee('SENTINEL-BUYER-CURRENT-HOME', false);
        $response->assertDontSee('SENTINEL-BUYER-UNIT', false);
    }

    /** @test */
    public function the_public_buyer_budget_survives_while_the_private_qualification_does_not(): void
    {
        // The single distinction this whole change encodes: what a buyer wants to
        // SPEND is the listing; what a lender says they QUALIFY for is not.
        $auction = $this->makeBuyerListing(User::factory()->create());

        $response = $this->get(route('offer.listing.buyer.view', $auction->id));

        $response->assertOk();
        $response->assertSee('62,411', false);      // maximum_budget — public
        $response->assertDontSee('71,317', false);  // pre_approval_amount — private
        $response->assertDontSee('71317', false);
        $response->assertDontSee('71,319', false);  // cash_budget — private
        $response->assertDontSee('71,321', false);  // down_payment_amount — private
    }

    /** @test */
    public function a_guest_still_sees_every_public_buyer_search_criterion(): void
    {
        $auction = $this->makeBuyerListing(User::factory()->create());

        $response = $this->get(route('offer.listing.buyer.view', $auction->id));

        $response->assertOk();
        foreach (self::BUYER_PUBLIC as $key => $value) {
            $needle = match ($key) {
                'maximum_budget'        => '62,411',
                // Stored as Yes/No, published through ContingencyOptionHelper.
                'home_sale_contingency' => 'Included',
                default                 => $value,
            };
            $response->assertSee($needle, false);
        }
    }

    /* ================================================================== */
    /* BUYER — owner                                                       */
    /* ================================================================== */

    /** @test */
    public function the_buyer_owner_still_sees_their_own_private_criteria(): void
    {
        $owner   = User::factory()->create();
        $auction = $this->makeBuyerListing($owner);

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.buyer.view', $auction->id));
        $response->assertOk();

        $response->assertSee('SENTINEL-BUYER-PREAPPROVED', false);
        $response->assertSee('71,317', false);  // pre_approval_amount, formatted
        $response->assertSee('SENTINEL-BUYER-CURRENT-HOME', false);
        $response->assertSee('SENTINEL-BUYER-OCCUPANTS', false);
    }

    /* ================================================================== */
    /* The policy itself                                                   */
    /* ================================================================== */

    /** @test */
    public function the_same_key_can_be_private_for_one_role_and_public_for_the_other(): void
    {
        // minimum_annual_net_income is the TENANT's own income ("Estimated Monthly
        // Net Household Income" sits beside it on the Pre-Screening tab) and the
        // BUYER's required property yield ("the minimum annual net income (after
        // expenses) the property must generate", beside minimum_cap_rate). Same
        // key, two unrelated facts. Deriving one role's list from the other's is
        // exactly the mistake this asserts against.
        $this->assertTrue(CriteriaPrivacyPolicy::isPrivate('tenant', 'minimum_annual_net_income'));
        $this->assertFalse(CriteriaPrivacyPolicy::isPrivate('buyer', 'minimum_annual_net_income'));
    }

    /** @test */
    public function redaction_removes_the_key_rather_than_blanking_it(): void
    {
        // A blanked-but-present key still enters the tenant page's overflow loop,
        // which iterates the meta array itself.
        $out = CriteriaPrivacyPolicy::redactForViewer(
            'tenant',
            ['monthly_income' => '5000', 'budget' => '2500'],
            false
        );

        $this->assertArrayNotHasKey('monthly_income', $out);
        $this->assertArrayHasKey('budget', $out);
    }

    /** @test */
    public function the_owner_receives_the_meta_array_untouched(): void
    {
        $meta = ['monthly_income' => '5000', 'budget' => '2500'];

        $this->assertSame($meta, CriteriaPrivacyPolicy::redactForViewer('tenant', $meta, true));
    }

    /** @test */
    public function a_null_owner_id_does_not_make_a_guest_the_owner(): void
    {
        // A guest's null id and a null user_id both cast to 0 and would compare
        // equal, handing a guest the owner's view of any orphaned listing.
        $this->assertFalse(CriteriaPrivacyPolicy::viewerIsOwner(null, null));
        $this->assertFalse(CriteriaPrivacyPolicy::viewerIsOwner(null, 7));
        $this->assertFalse(CriteriaPrivacyPolicy::viewerIsOwner(7, null));
        $this->assertFalse(CriteriaPrivacyPolicy::viewerIsOwner(7, ''));
        $this->assertTrue(CriteriaPrivacyPolicy::viewerIsOwner(7, 7));
        $this->assertTrue(CriteriaPrivacyPolicy::viewerIsOwner(7, '7'));
    }

    /** @test */
    public function a_legacy_buyer_listing_still_resolves_although_its_identifying_key_is_now_private(): void
    {
        // BuyerOfferListingController::resolveOfferListing() identifies an Offer
        // Listing that pre-dates the workflow_type stamp by the PRESENCE of
        // `pre_approval_amount` or `down_payment_type` — two keys this change
        // makes private. That lookup reads $auction->info() against the database
        // and runs before the redaction, so it is unaffected; this pins that,
        // because redacting the key that decides whether the page exists would
        // 404 every legacy buyer listing for every visitor.
        $owner   = User::factory()->create();
        $auction = BuyerAgentAuction::create([
            'user_id'     => $owner->id,
            'title'       => 'Legacy buyer listing without a workflow_type stamp',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);
        $auction->saveMeta('pre_approval_amount', '71317');
        $auction->saveMeta('maximum_budget', '62411');

        $response = $this->get(route('offer.listing.buyer.view', $auction->id));

        $response->assertOk();
        $response->assertSee('62,411', false);      // the listing resolved and rendered
        $response->assertDontSee('71,317', false);  // and the identifying key stayed private
    }

    /** @test */
    public function the_config_has_exactly_one_reader(): void
    {
        // A second reader is how two ideas of "private" come to disagree. The
        // Blade files must never read this config: a template edit could then
        // change a privacy decision.
        $root = dirname(__DIR__, 3);
        $hits = [];

        foreach (['app', 'resources', 'routes'] as $dir) {
            $rii = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                // Match an actual READ — config('…') or a require of the file —
                // not the filename appearing in a comment. Both controllers name
                // the config in prose to explain why they redact, and prose is
                // documentation, not a second source of truth.
                if (preg_match('/(config\\(|require .*)[\'"]offer_listing_private_criteria/', $contents)) {
                    $hits[] = str_replace($root . '/', '', $file->getPathname());
                }
            }
        }

        sort($hits);
        $this->assertSame(
            ['app/Support/OfferListing/CriteriaPrivacyPolicy.php'],
            $hits,
            'config/offer_listing_private_criteria.php must be read only by CriteriaPrivacyPolicy.'
        );
    }
}
