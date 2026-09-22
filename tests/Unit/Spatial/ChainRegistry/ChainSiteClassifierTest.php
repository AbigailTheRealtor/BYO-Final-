<?php

namespace Tests\Unit\Spatial\ChainRegistry;

use App\Services\Spatial\ChainRegistry\ChainMatcher;
use App\Services\Spatial\ChainRegistry\ChainMatchInput;
use App\Services\Spatial\ChainRegistry\ChainMembership;
use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\ChainRegistry\ChainSiteClassification as Site;
use App\Services\Spatial\ChainRegistry\ChainSiteClassifier;
use App\Services\Spatial\ChainRegistry\UnknownChainKey;
use PHPUnit\Framework\TestCase;

/**
 * The site-level contract PR 5's site builder must honour (design §9, decisions 2, 6, 10).
 * Members are produced by the real matcher, then handed over as if already grouped into one
 * site — grouping itself is not this layer's job. Pure; no container.
 */
class ChainSiteClassifierTest extends TestCase
{
    private ChainMatcher $matcher;
    private ChainSiteClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $registry = ChainRegistry::fromArray(require __DIR__ . '/../../../../config/poi_chain_registry.php');
        $this->matcher = new ChainMatcher($registry);
        $this->classifier = new ChainSiteClassifier($registry);
    }

    private function member(string $brandKey, string $name, string $category, ?string $brand = null): ChainMembership
    {
        $m = $this->matcher->match(new ChainMatchInput($name, $brand, null, $category))->membership($brandKey);
        $this->assertNotNull($m, "{$name} / {$category} should match {$brandKey}");

        return $m;
    }

    public function test_store_with_fuel_carries_no_visible_suffix(): void
    {
        $site = $this->classifier->classify('seven_eleven', [
            $this->member('seven_eleven', '7-Eleven', 'convenience_store'),
            $this->member('seven_eleven', '7-Eleven', 'gas_station'),
        ]);

        $this->assertSame(Site::STATUS_STOREFRONT, $site->status);
        $this->assertSame(Site::SITE_FORMAT_STORE_WITH_FUEL, $site->siteFormat);
        $this->assertTrue($site->hasFuel);
        $this->assertNull($site->displayQualifier, 'no "with fuel" suffix in v1 (decision 10)');
        $this->assertTrue($site->qualifiesForStorefrontQuery());
    }

    public function test_wawa_store_plus_fuel(): void
    {
        $site = $this->classifier->classify('wawa', [
            $this->member('wawa', 'Wawa', 'gas_station'),
            $this->member('wawa', 'Wawa', 'convenience_store'),
        ]);
        $this->assertSame(Site::STATUS_STOREFRONT, $site->status);
        $this->assertSame(Site::SITE_FORMAT_STORE_WITH_FUEL, $site->siteFormat);
        $this->assertNull($site->displayQualifier);
    }

    public function test_seven_eleven_fuel_only_is_explicitly_labelled(): void
    {
        $site = $this->classifier->classify('seven_eleven', [$this->member('seven_eleven', '7-Eleven Fuel', 'gas_station')]);

        $this->assertSame(Site::STATUS_FUEL_ONLY, $site->status);
        $this->assertSame(Site::SITE_FORMAT_FUEL_ONLY, $site->siteFormat);
        $this->assertSame('Fuel only', $site->displayQualifier);
        $this->assertFalse($site->qualifiesForStorefrontQuery(), 'fuel-only is not a convenience-store storefront');
        $this->assertTrue($site->qualifiesForGenericBrandQuery());
    }

    public function test_lone_fuel_is_unsupported_for_wawa_racetrac_speedway(): void
    {
        foreach (['wawa' => 'Wawa', 'racetrac' => 'RaceTrac', 'speedway' => 'Speedway'] as $key => $name) {
            $site = $this->classifier->classify($key, [$this->member($key, $name, 'gas_station')]);
            $this->assertSame(Site::STATUS_UNSUPPORTED_FORMAT, $site->status, $key);
            $this->assertFalse($site->qualifiesForStorefrontQuery(), $key);
            $this->assertFalse($site->qualifiesForGenericBrandQuery(), $key);
            $this->assertNull($site->displayQualifier, $key);
        }
    }

    public function test_department_only_site_is_storefront_unconfirmed(): void
    {
        foreach ([
            ['publix', 'Publix Pharmacy', 'pharmacy'],
            ['winn_dixie', 'Winn-Dixie Pharmacy', 'pharmacy'],
            ['walmart', 'Walmart', 'grocery_store'],
        ] as [$key, $name, $category]) {
            $site = $this->classifier->classify($key, [$this->member($key, $name, $category)]);
            $this->assertSame(Site::STATUS_STOREFRONT_UNCONFIRMED, $site->status, $key);
            $this->assertFalse($site->qualifiesForStorefrontQuery(), "{$key}: storefront-only queries exclude it");
            $this->assertFalse($site->qualifiesForGenericBrandQuery(), "{$key}: retained internally only");
        }
    }

    public function test_department_folds_into_a_storefront_site(): void
    {
        $site = $this->classifier->classify('publix', [
            $this->member('publix', 'Publix Pharmacy', 'pharmacy'),
            $this->member('publix', 'Publix Super Market', 'grocery_store'),
        ]);
        $this->assertSame(Site::STATUS_STOREFRONT, $site->status);
        $this->assertSame('supermarket', $site->siteFormat);
    }

    public function test_walmart_supercenter_label_survives_a_grocery_department(): void
    {
        $site = $this->classifier->classify('walmart', [
            $this->member('walmart', 'Walmart', 'grocery_store'),
            $this->member('walmart', 'Walmart', 'superstore'),
            $this->member('walmart', 'Walmart Supercenter', 'superstore'),
        ]);
        $this->assertSame(Site::STATUS_STOREFRONT, $site->status);
        $this->assertSame('supercenter', $site->siteFormat, 'the specific format is representative');
        $this->assertSame('Supercenter', $site->displayQualifier);
    }

    public function test_input_order_does_not_change_the_answer(): void
    {
        $members = [
            $this->member('walmart', 'Walmart', 'superstore'),
            $this->member('walmart', 'Walmart Supercenter', 'superstore'),
            $this->member('walmart', 'Walmart Pharmacy', 'pharmacy'),
        ];
        $forward = $this->classifier->classify('walmart', $members);
        $reverse = $this->classifier->classify('walmart', array_reverse($members));
        $this->assertEquals($forward, $reverse);
    }

    public function test_co_branded_row_yields_one_site_per_chain(): void
    {
        $result = $this->matcher->match(new ChainMatchInput('7-Eleven / Speedway', null, null, 'convenience_store'));
        $seven = $this->classifier->classify('seven_eleven', [$result->membership('seven_eleven')]);
        $speedway = $this->classifier->classify('speedway', [$result->membership('speedway')]);

        // A chain-specific query sees the site under each key ...
        $this->assertTrue($seven->qualifiesForStorefrontQuery());
        $this->assertTrue($speedway->qualifiesForStorefrontQuery());
        // ... and both memberships name each other, so a unique-site count can collapse them.
        $this->assertSame(['speedway'], $result->membership('seven_eleven')->coBrandWith);
        $this->assertSame(['seven_eleven'], $result->membership('speedway')->coBrandWith);
    }

    public function test_a_membership_whose_role_disagrees_with_its_format_is_refused(): void
    {
        // Reviewer D2: a "storefront" grocery_department used to classify as a storefront site.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('disagrees');
        $this->classifier->classify('walmart', [new ChainMembership('walmart', 'storefront', 'grocery_department', 'name_alias')]);
    }

    public function test_an_unknown_role_cannot_be_constructed(): void
    {
        // Reviewer D2: a bogus role used to classify as "Fuel only".
        $this->expectException(\InvalidArgumentException::class);
        new ChainMembership('seven_eleven', 'bogus', 'store', 'name_alias');
    }

    public function test_mixed_chains_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->classifier->classify('seven_eleven', [
            $this->member('seven_eleven', '7-Eleven', 'convenience_store'),
            $this->member('wawa', 'Wawa', 'convenience_store'),
        ]);
    }

    public function test_empty_site_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->classifier->classify('wawa', []);
    }

    public function test_unknown_chain_is_refused(): void
    {
        $this->expectException(UnknownChainKey::class);
        $this->classifier->classify('greenwise', [$this->member('publix', 'Publix', 'grocery_store')]);
    }
}
