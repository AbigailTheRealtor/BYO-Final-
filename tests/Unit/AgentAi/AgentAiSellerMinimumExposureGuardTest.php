<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AgentAi\Loaders\BuyerCriteriaLoader;
use App\Services\AgentAi\Loaders\LandlordListingLoader;
use App\Services\AgentAi\Loaders\SellerListingLoader;
use App\Services\AgentAi\Loaders\TenantCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentAiSellerMinimumExposureGuardTest — P0.1 defect 1.
 *
 * SellerListingLoader emitted:
 *
 *     'annual_noi' => $infoGet('minimum_annual_net_income'),
 *     'cap_rate'   => $infoGet('minimum_cap_rate'),
 *
 * `minimum_annual_net_income` and `minimum_cap_rate` are the seller's DESIRED MINIMUM
 * figures — the threshold below which they walk away. Emitting them as `annual_noi` and
 * `cap_rate` disclosed a negotiating floor AND asserted a wrong number as the property's
 * actual performance.
 *
 * P0 removed this defect from AskAiContextBuilderService's CANONICAL_SOURCE_MAP but missed
 * it here, because the Agent AI loaders are a SECOND, independent context pipeline that
 * never passes through that map. This loader's registered scope is PublicListingSeller and
 * the routes that reach it (`/agent-ai/*`) carry no auth middleware at all, so this was the
 * wider of the two exposures — contained only by a feature flag.
 *
 * These tests are written to fail if either alias is reintroduced under any spelling, and
 * to fail if the underlying VALUES reach a fragment by some other key name.
 */
class AgentAiSellerMinimumExposureGuardTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Key names that must never appear in an Agent AI context fragment, because each one
     * claims to be an ACTUAL property figure and we hold no actual source for any of them.
     */
    private const FORBIDDEN_ACTUAL_FIGURE_KEYS = [
        'annual_noi',
        'cap_rate',
        'annual_net_income',
        'net_operating_income',
        'current_cap_rate',
        'capitalization_rate',
    ];

    /** Distinctive sentinel values so a leak cannot be confused with any other field. */
    private const SENTINEL_MIN_NOI      = '987654321';
    private const SENTINEL_MIN_CAP_RATE = '13.579';

    private function seedCommercialSellerListing(): SellerAgentAuction
    {
        $user = User::factory()->create();

        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'address'     => '500 Minimums Way',
            'description' => 'Commercial income property.',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        // The property type that opens the commercial/financial block in the loader.
        $listing->saveMeta('property_type', 'Commercial Property');

        // The seller's own negotiating thresholds — legitimately stored, never public.
        $listing->saveMeta('minimum_annual_net_income', self::SENTINEL_MIN_NOI);
        $listing->saveMeta('minimum_cap_rate', self::SENTINEL_MIN_CAP_RATE);

        // A genuine, unrelated financial fact, to prove the block itself still loads and
        // this test is not passing merely because the loader returned nothing.
        $listing->saveMeta('gross_annual_income', '246800');

        return $listing->fresh();
    }

    private function scopeContext(int $listingId, string $listingType = 'seller'): array
    {
        return [
            'scope'        => AgentAiContextScope::PublicListingSeller,
            'agent_id'     => 1,
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
        ];
    }

    /**
     * Recursively flatten a fragment to "key => scalar" pairs so both key names and values
     * can be asserted on regardless of how deeply the loader nests its content.
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    // =========================================================================
    // The removed aliases
    // =========================================================================

    public function test_seller_loader_emits_no_actual_noi_or_cap_rate_key(): void
    {
        $listing = $this->seedCommercialSellerListing();

        $fragment = (new SellerListingLoader())($this->scopeContext($listing->id));

        $this->assertNotNull($fragment, 'The loader must still return a fragment.');

        $flat     = $this->flatten($fragment['content'] ?? []);
        $leafKeys = array_map(
            fn (string $path): string => substr($path, strrpos('.' . $path, '.')),
            array_keys($flat)
        );

        foreach (self::FORBIDDEN_ACTUAL_FIGURE_KEYS as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $leafKeys,
                "SellerListingLoader must not emit '{$forbidden}' — we hold no actual figure "
                . 'for it, and the only candidate source is the seller\'s private minimum.'
            );
        }
    }

    public function test_seller_minimum_values_never_appear_anywhere_in_the_fragment(): void
    {
        $listing = $this->seedCommercialSellerListing();

        $fragment = (new SellerListingLoader())($this->scopeContext($listing->id));
        $this->assertNotNull($fragment);

        // Serialise the whole fragment: this catches a leak under ANY key name, including a
        // key someone adds in future that the list above does not know about.
        $serialized = json_encode($fragment);

        $this->assertStringNotContainsString(
            self::SENTINEL_MIN_NOI,
            $serialized,
            'The seller\'s minimum annual net income must not appear in an Agent AI fragment '
            . 'under any key — it is a negotiating floor, not a property fact.'
        );
        $this->assertStringNotContainsString(
            self::SENTINEL_MIN_CAP_RATE,
            $serialized,
            'The seller\'s minimum cap rate must not appear in an Agent AI fragment under any key.'
        );
    }

    /**
     * Non-vacuity: prove the commercial/financial block the removed keys lived in is still
     * being populated, so the two assertions above are passing because the aliases are gone
     * rather than because the loader silently returned an empty fragment.
     */
    public function test_the_commercial_financial_block_still_loads_legitimate_facts(): void
    {
        $listing = $this->seedCommercialSellerListing();

        $fragment = (new SellerListingLoader())($this->scopeContext($listing->id));
        $flat     = $this->flatten($fragment['content'] ?? []);

        $this->assertContains(
            '246800',
            array_map('strval', array_values($flat)),
            'gross_annual_income must still load — otherwise this test class proves nothing.'
        );
    }

    // =========================================================================
    // Whole-pipeline sweep
    // =========================================================================

    /**
     * The same defect must not exist in any of the other three loaders. Each is invoked for
     * a listing id that does not exist, which is enough to assert the shape of what they
     * *can* emit is checked by the static guard below; this test pins the live ones we can
     * seed cheaply and asserts the loaders exist and are individually addressable.
     */
    public function test_no_agent_ai_loader_declares_an_actual_figure_alias_in_source(): void
    {
        $dir   = app_path('Services/AgentAi');
        $files = [];
        $it    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotEmpty($files, 'Expected to find Agent AI source files to scan.');

        foreach ($files as $path) {
            $src = file_get_contents($path);

            // Strip comments so the explanatory note left in place of the removed mapping
            // (which necessarily names both meta keys) does not trip the scan.
            $stripped = $this->stripPhpComments($src);

            foreach (['minimum_annual_net_income', 'minimum_cap_rate'] as $minimumKey) {
                foreach (self::FORBIDDEN_ACTUAL_FIGURE_KEYS as $actualKey) {
                    // Matches "'annual_noi' => ... minimum_annual_net_income ..." on one line,
                    // which is the exact shape of the defect.
                    $pattern = "/'" . preg_quote($actualKey, '/') . "'\s*=>[^\n]*"
                        . preg_quote($minimumKey, '/') . '/';

                    $this->assertDoesNotMatchRegularExpression(
                        $pattern,
                        $stripped,
                        basename($path) . " maps '{$actualKey}' to '{$minimumKey}'. A desired "
                        . 'minimum must never be emitted as the property\'s actual figure.'
                    );
                }
            }
        }
    }

    private function stripPhpComments(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }

    /**
     * The other three loaders must not emit an actual-figure key either. Seeding a full
     * listing for each role is out of proportion here; invoking them with a missing listing
     * proves they are wired and returns null, while the source scan above is what actually
     * pins the absence of the alias across all four.
     */
    public function test_other_loaders_are_addressable_and_return_null_for_a_missing_listing(): void
    {
        foreach ([
            LandlordListingLoader::class,
            BuyerCriteriaLoader::class,
            TenantCriteriaLoader::class,
        ] as $loaderClass) {
            $loader = new $loaderClass();
            $this->assertNull(
                $loader($this->scopeContext(999_999_999)),
                $loaderClass . ' must return null for a listing that does not exist.'
            );
        }
    }
}
