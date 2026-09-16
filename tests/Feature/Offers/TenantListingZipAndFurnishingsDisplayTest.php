<?php

namespace Tests\Feature\Offers;

use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Two pre-existing display defects on the public Tenant listing page.
 *
 * ZIP CODES — READ THE KEY THAT IS ACTUALLY WRITTEN. Every Tenant component persists
 * `saveMeta('zipCodes', json_encode($this->zipCodes))`; the string `zip_codes` is written
 * nowhere in the application. The view read the snake_case spelling, so the "ZIP Codes" row
 * was permanently blank for every listing the current form has ever produced — and because
 * `zipCodes` is listed in the view's own $knownKeys, the "Additional Information" fallback
 * did not surface it either. The criterion was public, stored, and invisible.
 *
 * FURNISHINGS — SAY WHAT THE VALUE IS. `tenant_require` holds a furnishings answer from a
 * select captioned "Furnishings Needed:", and the page published it as "Tenant Requirements"
 * — a requirement about the PERSON, which this listing never made. That is the conflation
 * Fair Housing Phase 3 removed from the landlord and agent views for this same key.
 *
 * BOTH ARE DISPLAY-ONLY. No stored value, meta key, component property or context key
 * changes, and neither touches what is public: the ZIP criterion was already public and the
 * furnishings value was already rendered. The tests below therefore assert the privacy
 * boundary is unmoved in the same breath as the fix, because a "make it visible" change is
 * exactly where a privacy regression would hide.
 */
class TenantListingZipAndFurnishingsDisplayTest extends TestCase
{
    use DatabaseTransactions;

    /** Private values that must stay owner-only however the location block is rendered. */
    private const PRIVATE = [
        'monthly_income'             => '91317',
        'address'                    => 'SENTINEL-TENANT-PRIVATE-STREET',
        'unit_number'                => 'SENTINEL-TENANT-UNIT',
        'service_animal'             => 'SENTINEL-TENANT-SERVICE-ANIMAL',
        'accessibility_requirements' => 'SENTINEL-TENANT-ACCESSIBILITY',
        'credit_score_range'         => 'SENTINEL-TENANT-CREDIT',
    ];

    private function listing(array $meta, ?User $owner = null): TenantAgentAuction
    {
        $owner ??= User::factory()->create();
        $listing = TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);
        $listing->saveMeta('workflow_type', 'offer_listing');

        foreach ($meta + self::PRIVATE as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    private function page(array $meta, ?User $as = null, ?User $owner = null): string
    {
        $listing = $this->listing($meta, $owner);
        $request = $as ? $this->actingAs($as) : $this;

        return $request->get(route('offer.listing.tenant.view', $listing->id))->assertOk()->getContent();
    }

    /**
     * The Location DNA map embeds GeoJSON coordinate arrays, and a five-digit run inside
     * "-82.80337721000" is not a ZIP code. Strip those before searching for figures.
     */
    private function withoutGeometry(string $html): string
    {
        return preg_replace('/\[\s*-?\d+\.\d+\s*,\s*-?\d+\.\d+\s*\][,\s]*/', ' ', $html) ?? $html;
    }

    /* ================================================================== */
    /* A. ZIP codes                                                        */
    /* ================================================================== */

    /** @test */
    public function a_non_owner_sees_the_stored_zip_criteria(): void
    {
        // Stored exactly as the components store it: the camelCase key, JSON-encoded.
        $html = $this->withoutGeometry($this->page([
            'zipCodes' => json_encode(['33772', '33776']),
            'cities'   => json_encode(['Seminole']),
        ]));

        $this->assertStringContainsString('ZIP Codes', $html);
        $this->assertStringContainsString('33772, 33776', $html);
    }

    /** @test */
    public function the_zip_row_renders_exactly_once_and_is_not_repeated_by_the_overflow(): void
    {
        // The old snake_case read is also why nothing duplicated: now that the row renders,
        // the fallback keys and the Additional Information section must not produce a second.
        $html = $this->withoutGeometry($this->page([
            'zipCodes'     => json_encode(['33772', '33776']),
            // A legacy row could carry these too; they are fallbacks, never additions.
            'zip_codes'    => '33772, 33776',
            'property_zip' => '33772',
        ]));

        $this->assertSame(1, substr_count($html, 'ZIP Codes'), 'The ZIP Codes row must render once.');
        $this->assertSame(1, substr_count($html, '33772, 33776'), 'The ZIP values must appear once.');
        $this->assertStringNotContainsString('Zipcodes', $html);
        $this->assertStringNotContainsString('Zip Codes</span>', $html);
    }

    /** @test */
    public function a_legacy_listing_that_stored_a_plain_string_still_renders(): void
    {
        // The canonical key yields nothing, so the legacy spelling is consulted — and only
        // then, which is what keeps the row from appearing twice above.
        $html = $this->withoutGeometry($this->page(['zip_codes' => '34698']));

        $this->assertStringContainsString('ZIP Codes', $html);
        $this->assertStringContainsString('34698', $html);
    }

    /** @test */
    public function a_listing_with_no_zip_criteria_renders_no_zip_row(): void
    {
        $html = $this->withoutGeometry($this->page(['cities' => json_encode(['Seminole'])]));

        $this->assertStringNotContainsString('ZIP Codes', $html);
        $this->assertStringContainsString('Seminole', $html);
    }

    /** @test */
    public function making_the_zip_row_visible_did_not_widen_anything_else(): void
    {
        // The change is a read-key correction inside the location block. Everything the
        // privacy prerequisite made owner-only must stay owner-only, for a guest and for a
        // signed-in non-owner alike.
        $meta = ['zipCodes' => json_encode(['33772']), 'cities' => json_encode(['Seminole'])];

        foreach ([null, User::factory()->create()] as $viewer) {
            $html = $this->withoutGeometry($this->page($meta, $viewer));

            $this->assertStringContainsString('33772', $html);
            foreach (self::PRIVATE as $key => $value) {
                if ($key === 'monthly_income') {
                    $this->assertStringNotContainsString('91,317', $html);
                    continue;
                }
                $this->assertStringNotContainsString($value, $html, "'{$key}' leaked.");
            }
            $this->assertStringNotContainsString('Monthly Income', $html);
            $this->assertStringNotContainsString('Accessibility Requirements', $html);
        }
    }

    /* ================================================================== */
    /* B. Furnishings label                                                */
    /* ================================================================== */

    /** @test */
    public function the_furnishings_value_renders_under_a_furnishings_label(): void
    {
        $html = $this->page(['tenant_require' => json_encode(['Furnished'])]);

        $this->assertStringContainsString('Furnishings Needed', $html);
        $this->assertStringContainsString('Furnished', $html);
    }

    /** @test */
    public function the_furnishings_value_is_no_longer_published_as_a_tenant_requirement(): void
    {
        // "Tenant Requirements" over a furnishings answer announces a requirement about the
        // person that the listing never made — the conflation Fair Housing Phase 3 removed
        // from the landlord and agent views for this same key.
        $html = $this->page(['tenant_require' => json_encode(['Unfurnished'])]);

        $this->assertStringContainsString('Furnishings Needed', $html);
        $this->assertStringNotContainsString('Tenant Requirements', $html);
        $this->assertStringNotContainsString('Tenant Type', $html);
    }

    /** @test */
    public function the_label_change_did_not_alter_the_stored_value_or_its_key(): void
    {
        $listing = $this->listing(['tenant_require' => json_encode(['Turnkey'])]);

        $this->assertSame(json_encode(['Turnkey']), $listing->info('tenant_require'));
        $this->get(route('offer.listing.tenant.view', $listing->id))->assertOk();
        $this->assertSame(json_encode(['Turnkey']), $listing->fresh()->info('tenant_require'));
    }
}
