<?php

namespace Tests\Feature\SmartTags;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The picker surface: what a Buyer/Tenant is OFFERED.
 *
 * Rendered in isolation rather than through the 1,800-line criteria wizards —
 * this asserts the partial's own contract (projected from the taxonomy, correct
 * contexts, nothing compliance-blocked), which is the part that could leak. The
 * write boundary is covered by SmartTagSeekerPreferenceTest, and it is the
 * authority regardless of what this renders.
 */
class SmartTagSeekerPickerSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The seeker-preference feature is OFF by default and is enabled here in
        // TEST-LOCAL config only — this suite exercises the feature itself. The
        // gate's own behaviour (off, and fail-closed parsing) is covered by
        // SmartTagSeekerPreferenceGateTest.
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        SmartTagTaxonomy::flush();
    }

    private function render(string $role, array $selected = []): string
    {
        return View::make('partials.smart-tags._seeker-picker', [
            'stRole'     => $role,
            'stSelected' => $selected,
        ])->render();
    }

    /** @test */
    public function the_buyer_picker_offers_only_canonical_seeker_selectable_tags(): void
    {
        $html = $this->render('buyer');

        $this->assertStringContainsString('name="smart_tags[]"', $html);

        preg_match_all('/name="smart_tags\[\]" value="([a-z0-9_]+)"/', $html, $m);
        $offered = $m[1];

        $this->assertNotEmpty($offered);

        foreach ($offered as $key) {
            $definition = SmartTagTaxonomy::get($key);
            $this->assertNotNull($definition, "{$key} is not a canonical Smart Tag");
            $this->assertTrue($definition->isSeekerSelectable(), "{$key} is not seeker-selectable");

            // Every offered key must be applicable to at least one BUYER context.
            $saleContexts = [
                SmartTagContext::ResidentialSale, SmartTagContext::IncomeSale,
                SmartTagContext::CommercialSale, SmartTagContext::BusinessSale,
                SmartTagContext::LandSale,
            ];
            $applicable = array_filter($saleContexts, fn ($c) => $definition->appliesTo($c));
            $this->assertNotEmpty($applicable, "{$key} applies to no buyer context");
        }
    }

    /** @test */
    public function the_tenant_picker_offers_only_lease_applicable_tags(): void
    {
        $html = $this->render('tenant');

        preg_match_all('/name="smart_tags\[\]" value="([a-z0-9_]+)"/', $html, $m);
        $this->assertNotEmpty($m[1]);

        foreach ($m[1] as $key) {
            $definition = SmartTagTaxonomy::get($key);
            $this->assertNotNull($definition);
            $this->assertTrue(
                $definition->appliesTo(SmartTagContext::ResidentialLease)
                || $definition->appliesTo(SmartTagContext::CommercialLease),
                "{$key} applies to no tenant context"
            );
        }
    }

    /** @test */
    public function no_compliance_blocked_concept_is_ever_offered(): void
    {
        foreach (['buyer', 'tenant'] as $role) {
            $html = $this->render($role);

            foreach (['accessible_features', 'playground', 'leasing_55_plus'] as $blocked) {
                $this->assertStringNotContainsString(
                    'value="' . $blocked . '"',
                    $html,
                    "{$blocked} must never be offered to a {$role}"
                );
            }
        }
    }

    /** @test */
    public function a_buyer_only_tag_is_absent_from_the_tenant_picker(): void
    {
        // turnkey_home is residential.sale only.
        $this->assertStringNotContainsString('value="turnkey_home"', $this->render('tenant'));
        $this->assertStringContainsString('value="turnkey_home"', $this->render('buyer'));
    }

    /** @test */
    public function existing_selections_are_restored_as_checked(): void
    {
        $html = $this->render('buyer', ['private_pool']);

        $this->assertMatchesRegularExpression(
            '/value="private_pool"[^>]*checked/',
            $html,
            'a stored selection should render checked'
        );
        $this->assertDoesNotMatchRegularExpression('/value="garage"[^>]*checked/', $html);
    }

    /** @test */
    public function options_carry_the_contexts_they_apply_to_for_client_side_filtering(): void
    {
        $html = $this->render('buyer');

        // loading_dock is commercial/business/land, never residential.sale.
        preg_match('/data-tag-key="loading_dock"\s+data-tag-label="[^"]*"\s+data-tag-contexts="([^"]*)"/s', $html, $m);
        $this->assertNotEmpty($m, 'loading_dock option should be rendered with its contexts');
        $this->assertStringNotContainsString('residential.sale', $m[1]);
        $this->assertStringContainsString('commercial.sale', $m[1]);
    }

    /** @test */
    public function the_partial_hard_codes_no_tag_list(): void
    {
        $source = file_get_contents(
            base_path('resources/views/partials/smart-tags/_seeker-picker.blade.php')
        );

        // The only way options may appear is by projecting the taxonomy.
        $this->assertStringContainsString('SmartTagTaxonomy::forContext', $source);
        $this->assertStringContainsString('SURFACE_SEEKER', $source);

        // A parallel vocabulary would show up as literal canonical keys in the
        // template. The context maps are referenced, never transcribed.
        foreach (['private_pool', 'quartz_countertops', 'loading_dock', 'walk_in_closet'] as $key) {
            $this->assertStringNotContainsString(
                "'{$key}'",
                $source,
                "the picker must not hard-code the tag key {$key}"
            );
        }
    }

    /** @test */
    public function the_criteria_wizards_include_the_shared_picker(): void
    {
        foreach ([
            'buyer_criteria/add', 'buyer_criteria/edit',
            'tenant_criteria/add', 'tenant_criteria/edit',
        ] as $view) {
            $source = file_get_contents(base_path("resources/views/{$view}.blade.php"));

            $this->assertStringContainsString(
                "partials.smart-tags._seeker-picker",
                $source,
                "{$view} should include the shared picker"
            );
        }
    }
}
