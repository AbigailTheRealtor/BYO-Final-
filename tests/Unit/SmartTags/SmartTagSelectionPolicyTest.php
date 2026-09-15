<?php

namespace Tests\Unit\SmartTags;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagSelectionResult as R;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

class SmartTagSelectionPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    private function owner(array $keys, ?SmartTagContext $context = SmartTagContext::ResidentialSale, array $answered = []): R
    {
        return SmartTagSelectionPolicy::project($keys, $context, SmartTagTaxonomy::SURFACE_OWNER, $answered);
    }

    /** @test */
    public function canonical_applicable_keys_are_accepted_once_in_request_order(): void
    {
        $result = $this->owner(['quartz_countertops', 'updated_kitchen', 'quartz_countertops']);

        $this->assertSame(['quartz_countertops', 'updated_kitchen'], $result->accepted);
        $this->assertSame([], $result->rejected);
    }

    /** @test */
    public function arbitrary_free_text_and_unknown_keys_are_rejected(): void
    {
        $result = $this->owner(['Quartz Countertops', 'gourmet kitchen!', 'granite_everything', 42, ['nested'], null, 'quartz_countertops']);

        $this->assertSame(['quartz_countertops'], $result->accepted);
        $this->assertSame(R::REASON_NOT_A_KEY, $result->rejected['Quartz Countertops']);
        $this->assertSame(R::REASON_NOT_A_KEY, $result->rejected['gourmet kitchen!']);
        $this->assertSame(R::REASON_UNKNOWN_KEY, $result->rejected['granite_everything']);
        $this->assertArrayNotHasKey('granite_everything', array_flip($result->accepted));
    }

    /** @test */
    public function wrong_context_keys_are_rejected(): void
    {
        $land = $this->owner(['updated_kitchen', 'loading_dock', 'cleared_land'], SmartTagContext::LandSale);
        $this->assertSame(['cleared_land'], $land->accepted);
        $this->assertSame(R::REASON_NOT_APPLICABLE, $land->rejected['updated_kitchen']);
        $this->assertSame(R::REASON_NOT_APPLICABLE, $land->rejected['loading_dock']);

        $residential = $this->owner(['loading_dock', 'turnkey_business'], SmartTagContext::ResidentialSale);
        $this->assertSame([], $residential->accepted);
    }

    /** @test */
    public function no_context_rejects_everything(): void
    {
        $result = $this->owner(['quartz_countertops'], null);

        $this->assertSame([], $result->accepted);
        $this->assertSame(R::REASON_NO_CONTEXT, $result->rejected['quartz_countertops']);
    }

    /** @test */
    public function keys_answered_by_property_details_cannot_be_selected_by_the_owner(): void
    {
        $result = $this->owner(['waterfront', 'dock'], SmartTagContext::ResidentialSale, ['waterfront']);

        $this->assertSame(['dock'], $result->accepted);
        $this->assertSame(R::REASON_ANSWERED_BY_PROPERTY_DETAILS, $result->rejected['waterfront']);
    }

    /** @test */
    public function pending_review_and_seeker_restrictions_are_enforced_per_surface(): void
    {
        $owner = $this->owner(['gated_community', 'playground', 'accessible_features']);
        $this->assertSame(['playground', 'accessible_features'], $owner->accepted);
        $this->assertSame(R::REASON_PENDING_REVIEW, $owner->rejected['gated_community']);

        $seeker = SmartTagSelectionPolicy::project(['playground', 'accessible_features', 'private_pool'], SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER);
        $this->assertSame(['private_pool'], $seeker->accepted);
        $this->assertSame(R::REASON_NOT_SELECTABLE, $seeker->rejected['playground']);
        $this->assertSame(R::REASON_NOT_SELECTABLE, $seeker->rejected['accessible_features']);
    }

    /** @test */
    public function prohibited_concepts_are_refused_as_such(): void
    {
        $result = $this->owner(['family_friendly', 'safe_neighborhood', 'great_schools', 'retirees', 'young_professionals']);

        $this->assertSame([], $result->accepted);
        foreach (['family_friendly', 'safe_neighborhood', 'great_schools', 'retirees', 'young_professionals'] as $key) {
            $this->assertSame(R::REASON_PROHIBITED, $result->rejected[$key], $key);
        }
    }

    /** @test */
    public function an_unknown_surface_accepts_nothing(): void
    {
        $result = SmartTagSelectionPolicy::project(['private_pool'], SmartTagContext::ResidentialSale, 'admin');
        $this->assertSame([], $result->accepted);
    }
}
