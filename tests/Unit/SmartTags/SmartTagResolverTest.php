<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\TagEvidence;
use App\Services\SmartTags\SmartTagResolver;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

class SmartTagResolverTest extends TestCase
{
    private const RS = SmartTagContext::ResidentialSale;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    private function ev(string $tag, SmartTagSource $source, SmartTagState $state = SmartTagState::Present, SmartTagContext $context = self::RS): TagEvidence
    {
        return new TagEvidence($tag, $state, $source, $context, 80, null, 'test');
    }

    /** @test */
    public function structured_no_outranks_manual_and_description_present(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('private_pool', SmartTagSource::StructuredNativeListing, SmartTagState::Absent),
            $this->ev('private_pool', SmartTagSource::ManualListingOwner),
            $this->ev('private_pool', SmartTagSource::NativeListingDescription),
        ], self::RS);

        $pool = $resolution->assignments['private_pool'];
        $this->assertSame(SmartTagState::Absent, $pool->state);
        $this->assertSame(SmartTagSource::StructuredNativeListing, $pool->winningSource);
        $this->assertTrue($pool->hasConflict);
    }

    /** @test */
    public function structured_mls_outranks_mls_remarks(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('fireplace', SmartTagSource::MlsRemarks),
            $this->ev('fireplace', SmartTagSource::StructuredMls, SmartTagState::Absent),
        ], self::RS);

        $this->assertSame(SmartTagState::Absent, $resolution->assignments['fireplace']->state);
    }

    /** @test */
    public function manual_and_structured_evidence_produce_one_canonical_assignment(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('updated_kitchen', SmartTagSource::StructuredNativeListing),
            $this->ev('updated_kitchen', SmartTagSource::ManualListingOwner),
            $this->ev('updated_kitchen', SmartTagSource::NativeListingDescription),
        ], self::RS);

        $this->assertCount(1, $resolution->assignments);
        $this->assertSame(SmartTagSource::StructuredNativeListing, $resolution->assignments['updated_kitchen']->winningSource);
        $this->assertFalse($resolution->assignments['updated_kitchen']->hasConflict);
    }

    /** @test */
    public function absence_from_a_description_or_owner_is_never_accepted(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('private_pool', SmartTagSource::NativeListingDescription, SmartTagState::Absent),
            $this->ev('garage', SmartTagSource::ManualListingOwner, SmartTagState::Absent),
            // Not negatable: even structured data may not assert absence.
            $this->ev('quartz_countertops', SmartTagSource::StructuredNativeListing, SmartTagState::Absent),
        ], self::RS);

        $this->assertSame([], $resolution->assignments, 'Unknown, not no');
    }

    /** @test */
    public function evidence_from_another_context_or_for_an_inapplicable_tag_is_ignored(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('updated_kitchen', SmartTagSource::StructuredNativeListing, SmartTagState::Present, SmartTagContext::ResidentialLease),
            $this->ev('loading_dock', SmartTagSource::StructuredNativeListing),
            $this->ev('not_a_real_tag', SmartTagSource::StructuredNativeListing),
        ], self::RS);

        $this->assertSame([], $resolution->assignments);
        $this->assertSame([], SmartTagResolver::resolve([$this->ev('garage', SmartTagSource::StructuredMls)], null)->assignments);
    }

    /** @test */
    public function a_stronger_source_wins_a_cross_tag_conflict_and_records_it(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('fully_updated', SmartTagSource::StructuredNativeListing),
            $this->ev('fixer_upper', SmartTagSource::NativeListingDescription),
        ], self::RS);

        $this->assertSame(['fully_updated'], array_keys($resolution->assignments));
        $this->assertTrue($resolution->assignments['fully_updated']->hasConflict);
        $this->assertSame(['fixer_upper'], $resolution->assignments['fully_updated']->conflictTags);
        $this->assertSame(['fixer_upper'], $resolution->droppedForConflict);
    }

    /** @test */
    public function equal_strength_contradictions_cancel_to_unknown(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('move_in_ready', SmartTagSource::NativeListingDescription),
            $this->ev('fixer_upper', SmartTagSource::NativeListingDescription),
            $this->ev('quartz_countertops', SmartTagSource::NativeListingDescription),
        ], self::RS);

        $this->assertSame(['quartz_countertops'], array_keys($resolution->assignments));
        $this->assertSame(['fixer_upper', 'move_in_ready'], $resolution->droppedForConflict);
    }

    /** @test */
    public function mutually_exclusive_furnishing_is_resolved_by_source_strength(): void
    {
        $resolution = SmartTagResolver::resolve([
            $this->ev('unfurnished', SmartTagSource::StructuredNativeListing, SmartTagState::Present, SmartTagContext::ResidentialLease),
            $this->ev('furnished', SmartTagSource::NativeListingDescription, SmartTagState::Present, SmartTagContext::ResidentialLease),
        ], SmartTagContext::ResidentialLease);

        $this->assertSame(['unfurnished'], array_keys($resolution->assignments));
    }

    /** @test */
    public function resolution_is_order_independent(): void
    {
        $evidence = [
            $this->ev('fully_updated', SmartTagSource::StructuredNativeListing),
            $this->ev('fixer_upper', SmartTagSource::NativeListingDescription),
            $this->ev('garage', SmartTagSource::ManualListingOwner),
            $this->ev('garage', SmartTagSource::StructuredNativeListing, SmartTagState::Absent),
        ];

        $forward = SmartTagResolver::resolve($evidence, self::RS);
        $reverse = SmartTagResolver::resolve(array_reverse($evidence), self::RS);

        $this->assertEquals($forward, $reverse);
    }
}
