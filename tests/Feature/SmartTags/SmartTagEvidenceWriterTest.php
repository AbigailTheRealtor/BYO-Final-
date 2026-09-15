<?php

namespace Tests\Feature\SmartTags;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Services\SmartTags\Derivation\TagEvidence;
use App\Services\SmartTags\SmartTagEvidenceWriter;
use App\Services\SmartTags\SmartTagWriteRefused;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SmartTagEvidenceWriterTest extends TestCase
{
    use DatabaseTransactions;

    private SmartTagEvidenceWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->writer = app(SmartTagEvidenceWriter::class);
    }

    private function ev(string $tag, SmartTagSource $source, SmartTagState $state = SmartTagState::Present, SmartTagContext $context = SmartTagContext::ResidentialSale): TagEvidence
    {
        return new TagEvidence($tag, $state, $source, $context, 90, 'field', 'rule.test');
    }

    /** @test */
    public function the_mls_remarks_writer_is_blocked(): void
    {
        $this->assertFalse(SmartTagEvidenceWriter::MLS_REMARKS_PERSISTENCE_APPROVED);

        try {
            $this->writer->replaceDerived(
                new SmartTagListingRef(SmartTagListingType::Bridge, 11),
                SmartTagContext::ResidentialSale,
                SmartTagSource::MlsRemarks,
                [$this->ev('quartz_countertops', SmartTagSource::MlsRemarks)],
                'v1',
            );
            $this->fail('Remarks-derived evidence was accepted');
        } catch (SmartTagWriteRefused $e) {
            $this->assertSame('mls_remarks_not_approved', $e->reason);
        }

        $this->assertSame(0, SmartTagEvidence::query()->count());
        $this->assertSame(0, SmartTagAssignment::query()->count());
    }

    /** @test */
    public function manual_evidence_cannot_be_written_through_the_derived_writer(): void
    {
        $this->expectException(SmartTagWriteRefused::class);
        $this->writer->replaceDerived(
            new SmartTagListingRef(SmartTagListingType::SellerAgent, 1),
            SmartTagContext::ResidentialSale, SmartTagSource::ManualListingOwner,
            [$this->ev('garage', SmartTagSource::ManualListingOwner)], 'v1',
        );
    }

    /** @test */
    public function sources_and_contexts_must_fit_the_listing_type(): void
    {
        foreach ([
            [SmartTagListingType::Bridge, SmartTagContext::ResidentialSale, SmartTagSource::StructuredNativeListing, 'source_not_allowed_for_listing_type'],
            [SmartTagListingType::SellerAgent, SmartTagContext::ResidentialSale, SmartTagSource::StructuredMls, 'source_not_allowed_for_listing_type'],
            [SmartTagListingType::LandlordAgent, SmartTagContext::ResidentialSale, SmartTagSource::StructuredNativeListing, 'context_not_possible_for_listing_type'],
        ] as [$type, $context, $source, $reason]) {
            try {
                $this->writer->replaceDerived(new SmartTagListingRef($type, 3), $context, $source, [], 'v1');
                $this->fail("{$type->value}/{$source->value} was accepted");
            } catch (SmartTagWriteRefused $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
    }

    /** @test */
    public function unknown_prohibited_wrong_context_and_invalid_absent_evidence_is_rejected(): void
    {
        $listing = new SmartTagListingRef(SmartTagListingType::SellerAgent, 5);
        $source = SmartTagSource::NativeListingDescription;

        $result = $this->writer->replaceDerived($listing, SmartTagContext::ResidentialSale, $source, [
            $this->ev('quartz_countertops', $source),
            $this->ev('family_friendly', $source),
            $this->ev('invented_tag', $source),
            $this->ev('loading_dock', $source),
            $this->ev('private_pool', $source, SmartTagState::Absent),
            $this->ev('garage', SmartTagSource::StructuredNativeListing),
            $this->ev('fireplace', $source, SmartTagState::Present, SmartTagContext::IncomeSale),
        ], 'v1');

        $this->assertSame(['quartz_countertops'], $result->written);
        $this->assertSame(SmartTagEvidenceWriter::REJECT_PROHIBITED, $result->rejected['family_friendly']);
        $this->assertSame(SmartTagEvidenceWriter::REJECT_UNKNOWN_TAG, $result->rejected['invented_tag']);
        $this->assertSame(SmartTagEvidenceWriter::REJECT_NOT_APPLICABLE, $result->rejected['loading_dock']);
        $this->assertSame(SmartTagEvidenceWriter::REJECT_ABSENT_NOT_ALLOWED, $result->rejected['private_pool']);
        $this->assertSame(SmartTagEvidenceWriter::REJECT_SOURCE_MISMATCH, $result->rejected['garage']);
        $this->assertSame(SmartTagEvidenceWriter::REJECT_CONTEXT_MISMATCH, $result->rejected['fireplace']);

        $this->assertSame(['quartz_countertops'], SmartTagEvidence::query()->pluck('tag_key')->all());
    }

    /** @test */
    public function replacing_one_source_never_touches_another_sources_evidence(): void
    {
        $listing = new SmartTagListingRef(SmartTagListingType::SellerAgent, 21);

        SmartTagEvidence::query()->create([
            'listing_type' => 'seller_agent', 'listing_id' => 21, 'tag_key' => 'kitchen_island', 'context' => 'residential.sale',
            'source' => 'manual_listing_owner', 'state' => 'present', 'confidence' => 80, 'set_by_user_id' => 1,
        ]);

        $this->writer->replaceDerived($listing, SmartTagContext::ResidentialSale, SmartTagSource::StructuredNativeListing,
            [$this->ev('garage', SmartTagSource::StructuredNativeListing)], 'v1');
        $this->writer->replaceDerived($listing, SmartTagContext::ResidentialSale, SmartTagSource::StructuredNativeListing,
            [$this->ev('carport', SmartTagSource::StructuredNativeListing)], 'v2');

        $this->assertEqualsCanonicalizing(['kitchen_island', 'carport'],
            SmartTagEvidence::query()->where('listing_id', 21)->pluck('tag_key')->all());
        $this->assertEqualsCanonicalizing(['kitchen_island', 'carport'],
            SmartTagAssignment::query()->where('listing_id', 21)->pluck('tag_key')->all());
    }

    /** @test */
    public function structured_and_description_evidence_resolve_to_one_assignment_with_conflict_recorded(): void
    {
        $listing = new SmartTagListingRef(SmartTagListingType::SellerAgent, 30);

        $this->writer->replaceDerived($listing, SmartTagContext::ResidentialSale, SmartTagSource::StructuredNativeListing,
            [$this->ev('waterfront', SmartTagSource::StructuredNativeListing, SmartTagState::Absent)], 'v1');
        $this->writer->replaceDerived($listing, SmartTagContext::ResidentialSale, SmartTagSource::NativeListingDescription,
            [$this->ev('waterfront', SmartTagSource::NativeListingDescription)], 'v1');

        $rows = SmartTagAssignment::query()->where('listing_id', 30)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('absent', $rows[0]->state);
        $this->assertSame('structured_native_listing', $rows[0]->winning_source);
        $this->assertTrue($rows[0]->has_conflict);
    }
}
