<?php

namespace Tests\Feature\SmartTags;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagSeekerPreference;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceResult;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeker Smart Tag preferences on Buyer/Tenant OFFER LISTINGS — the records
 * Stellar matching, Match Check and Matching V2 actually read.
 *
 * Same writer, reader, table, policy and gate as the criteria records. These
 * tests hold the write boundary against crafted input, the subject-type
 * boundary against the Hire rows sharing the same tables, and the purge
 * against the feature flag.
 */
class SmartTagSeekerOfferListingPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private SmartTagSeekerPreferenceWriter $writer;
    private SmartTagSeekerPreferenceReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        // TEST-LOCAL only: the feature ships off. Tests that need it off say so.
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        SmartTagTaxonomy::flush();
        $this->writer = app(SmartTagSeekerPreferenceWriter::class);
        $this->reader = app(SmartTagSeekerPreferenceReader::class);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function user(string $type): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function buyerOffer(User $owner, ?string $propertyType = 'Residential', string $workflow = 'offer_listing', array $columns = []): BuyerAgentAuction
    {
        $a = BuyerAgentAuction::create(array_merge(['user_id' => $owner->id, 'title' => 'Buyer offer', 'is_draft' => false], $columns));
        $a->saveMeta('workflow_type', $workflow);
        if ($propertyType !== null) {
            $a->saveMeta('property_type', $propertyType);
        }

        return $a->fresh();
    }

    private function tenantOffer(User $owner, ?string $propertyType = 'Residential Property', string $workflow = 'offer_listing', array $columns = []): TenantAgentAuction
    {
        // TenantAgentAuction is fully guarded, so attributes are set one by one.
        $a = new TenantAgentAuction();
        foreach (array_merge(['user_id' => $owner->id, 'title' => 'Tenant offer', 'is_draft' => false], $columns) as $k => $v) {
            $a->{$k} = $v;
        }
        $a->save();
        $a->saveMeta('workflow_type', $workflow);
        if ($propertyType !== null) {
            $a->saveMeta('property_type', $propertyType);
        }

        return $a->fresh();
    }

    private function buyerCriteria(User $owner, string $propertyType = 'Residential Property', array $columns = []): BuyerCriteriaAuction
    {
        $a = new BuyerCriteriaAuction();
        foreach (array_merge(['user_id' => $owner->id, 'buyer_id' => $owner->id, 'max_price' => 500000, 'title' => 'Criteria'], $columns) as $k => $v) {
            $a->{$k} = $v;
        }
        $a->save();
        $a->saveMeta('property_type', $propertyType);

        return $a->fresh();
    }

    private function tenantCriteria(User $owner, string $propertyType = 'Residential Property'): TenantCriteriaAuction
    {
        $a = new TenantCriteriaAuction();
        $a->user_id = $owner->id;
        $a->save();
        $a->saveMeta('property_type', $propertyType);

        return $a->fresh();
    }

    private function assertSeekerSelectableIn(string $key, SmartTagContext $context): void
    {
        $this->assertArrayHasKey($key, SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_SEEKER),
            "fixture assumption broken: {$key} is not seeker-selectable in {$context->value}");
    }

    // ── 1-7. every Offer Listing property type reaches its canonical context ──

    /**
     * @test
     * @dataProvider offerListingContexts
     */
    public function each_offer_listing_property_type_persists_a_key_in_its_own_context(
        string $role,
        string $propertyType,
        SmartTagContext $expected,
        string $key,
    ): void {
        $this->assertSeekerSelectableIn($key, $expected);
        $owner = $this->user($role);
        $subject = $role === 'buyer' ? $this->buyerOffer($owner, $propertyType) : $this->tenantOffer($owner, $propertyType);

        $result = $this->writer->replaceSelections($subject, [$key], $owner->id);

        $this->assertTrue($result->accepted);
        $this->assertSame($expected, $result->context);
        $this->assertSame([$key], $result->storedKeys());
        $this->assertSame($expected, $this->reader->contextFor($subject));
        $this->assertSame([$key], $this->reader->currentKeysFor($subject));

        $row = SmartTagSeekerPreference::query()->sole();
        $this->assertSame($role . '_offer_listing', $row->subject_type);
        $this->assertSame($role, $row->seeker_role);
        $this->assertSame($expected->value, $row->context);
    }

    public static function offerListingContexts(): array
    {
        return [
            'buyer residential'  => ['buyer', 'Residential', SmartTagContext::ResidentialSale, 'private_pool'],
            'buyer income'       => ['buyer', 'Income', SmartTagContext::IncomeSale, 'separate_electric_meters'],
            'buyer commercial'   => ['buyer', 'Commercial', SmartTagContext::CommercialSale, 'loading_dock'],
            'buyer business'     => ['buyer', 'Business', SmartTagContext::BusinessSale, 'turnkey_business'],
            'buyer land'         => ['buyer', 'Vacant Land', SmartTagContext::LandSale, 'cleared_land'],
            'tenant residential' => ['tenant', 'Residential Property', SmartTagContext::ResidentialLease, 'partially_furnished'],
            'tenant commercial'  => ['tenant', 'Commercial Property', SmartTagContext::CommercialLease, 'vanilla_shell'],
        ];
    }

    // ── context resolution: one API, no cross-vocabulary confusion ──────────

    /** @test */
    public function the_subject_type_not_the_role_chooses_the_vocabulary(): void
    {
        $r = fn (SmartTagSeekerSubjectType $t, string $v) => SmartTagContextResolver::forSeekerSubject($t, $v);

        // `Residential Property` is a SALE on Buyer Criteria and a LEASE on both tenant forms …
        $this->assertSame(SmartTagContext::ResidentialSale, $r(SmartTagSeekerSubjectType::BuyerCriteria, 'Residential Property'));
        $this->assertSame(SmartTagContext::ResidentialLease, $r(SmartTagSeekerSubjectType::TenantCriteria, 'Residential Property'));
        $this->assertSame(SmartTagContext::ResidentialLease, $r(SmartTagSeekerSubjectType::TenantOfferListing, 'Residential Property'));
        // … and is not a Buyer Offer Listing value at all.
        $this->assertNull($r(SmartTagSeekerSubjectType::BuyerOfferListing, 'Residential Property'));

        // `Commercial` is the Buyer Offer Listing spelling; Buyer Criteria says `Commercial Property`.
        $this->assertSame(SmartTagContext::CommercialSale, $r(SmartTagSeekerSubjectType::BuyerOfferListing, 'Commercial'));
        $this->assertNull($r(SmartTagSeekerSubjectType::BuyerCriteria, 'Commercial'));
        $this->assertSame(SmartTagContext::CommercialSale, $r(SmartTagSeekerSubjectType::BuyerCriteria, 'Commercial Property'));

        // A buyer vocabulary string on a tenant subject never becomes a sale context.
        $this->assertNull($r(SmartTagSeekerSubjectType::TenantOfferListing, 'Residential'));
        $this->assertNull($r(SmartTagSeekerSubjectType::TenantOfferListing, 'Vacant Land'));

        // Unknown, blank and lower-case slugs fail closed.
        foreach (SmartTagSeekerSubjectType::cases() as $type) {
            $this->assertNull($r($type, ''));
            $this->assertNull($r($type, 'residential'));
            $this->assertNull(SmartTagContextResolver::forSeekerSubject($type, null));
        }
    }

    /** @test */
    public function every_subject_type_resolves_only_to_contexts_of_its_own_side_of_the_market(): void
    {
        foreach (SmartTagSeekerSubjectType::cases() as $type) {
            foreach ($type->propertyTypeContexts() as $propertyType => $_) {
                $context = SmartTagContextResolver::forSeekerSubject($type, $propertyType);
                $this->assertNotNull($context, "{$type->value} / {$propertyType}");
                $this->assertContains($context, $type->possibleContexts());
            }
        }
    }

    // ── 8. subject-type isolation ───────────────────────────────────────────

    /** @test */
    public function the_same_numeric_id_on_different_subject_types_never_collides(): void
    {
        $owner = $this->user('buyer');
        $offer = $this->buyerOffer($owner, 'Residential');
        $criteria = $this->buyerCriteria($owner, 'Residential Property', ['id' => $offer->id]);
        $this->assertSame($offer->id, $criteria->id, 'fixture must share the numeric id');

        $this->writer->replaceSelections($offer, ['private_pool'], $owner->id);
        $this->writer->replaceSelections($criteria, ['garage'], $owner->id);

        $this->assertSame(['private_pool'], $this->reader->keysFor($offer));
        $this->assertSame(['garage'], $this->reader->keysFor($criteria));

        // Replacing one subject's set leaves the other untouched.
        $this->writer->replaceSelections($offer, [], $owner->id);
        $this->assertSame([], $this->reader->keysFor($offer));
        $this->assertSame(['garage'], $this->reader->keysFor($criteria));
    }

    // ── 9, 12. canonical keys only; replace semantics ───────────────────────

    /** @test */
    public function only_canonical_keys_are_stored_and_resubmission_replaces_the_set(): void
    {
        $owner = $this->user('buyer');
        $offer = $this->buyerOffer($owner, 'Residential');

        $this->writer->replaceSelections($offer, ['private_pool', 'garage', 'private_pool'], $owner->id);
        $this->assertEqualsCanonicalizing(['private_pool', 'garage'],
            SmartTagSeekerPreference::query()->pluck('tag_key')->all());

        // No label or category copy exists on the row.
        $columns = array_keys(SmartTagSeekerPreference::query()->first()->getAttributes());
        $this->assertEqualsCanonicalizing(
            ['id', 'subject_type', 'subject_id', 'user_id', 'seeker_role', 'tag_key', 'context', 'created_at', 'updated_at'],
            $columns
        );

        // Deselection: the submitted set IS the stored set.
        $this->writer->replaceSelections($offer, ['garage'], $owner->id);
        $this->assertSame(['garage'], $this->reader->keysFor($offer));
    }

    // ── 15-18. rejections are server-side re-projections ────────────────────

    /** @test */
    public function wrong_context_keys_are_refused_against_the_stored_property_type(): void
    {
        $buyer = $this->user('buyer');
        $offer = $this->buyerOffer($buyer, 'Residential');
        $result = $this->writer->replaceSelections($offer, ['loading_dock', 'cleared_land', 'partially_furnished', 'private_pool'], $buyer->id);
        $this->assertSame(['private_pool'], $result->storedKeys());
        $this->assertArrayHasKey('loading_dock', $result->rejectedKeys());

        $tenant = $this->user('tenant');
        $lease = $this->tenantOffer($tenant, 'Commercial Property');
        $result = $this->writer->replaceSelections($lease, ['private_pool', 'turnkey_business', 'vanilla_shell'], $tenant->id);
        $this->assertSame(['vanilla_shell'], $result->storedKeys());
    }

    /** @test */
    public function nonexistent_non_seeker_pending_and_prohibited_keys_are_refused(): void
    {
        $owner = $this->user('buyer');
        $offer = $this->buyerOffer($owner, 'Residential');

        $pending = array_keys(array_filter(SmartTagTaxonomy::all(), fn ($d) => $d->isPendingReview()));
        $this->assertNotEmpty($pending, 'fixture assumption: the taxonomy has pending-review tags');

        $refused = array_merge(
            ['not_a_real_tag', 'Private Pool', 42, null, ['nested']],        // nonexistent / malformed
            ['accessible_features', 'playground', 'leasing_55_plus'],       // owner-only / compliance gate
            $pending,                                                       // pending review
            ['family_friendly', 'great_for_families', 'safe_neighborhood',   // protected class / neighbourhood quality
             'low_crime_area', 'top_rated_schools', 'good_school_district', //   / safety / school quality
             'near_schools', 'walkable_neighborhood', 'close_to_shopping'], //   / Location DNA proximity
        );

        $result = $this->writer->replaceSelections($offer, $refused, $owner->id);

        $this->assertTrue($result->accepted);
        $this->assertSame([], $result->storedKeys());
        $this->assertSame(0, SmartTagSeekerPreference::query()->count());
    }

    /** @test */
    public function no_prohibited_or_non_seeker_concept_is_ever_offered_on_an_offer_listing(): void
    {
        foreach ([SmartTagSeekerSubjectType::BuyerOfferListing, SmartTagSeekerSubjectType::TenantOfferListing] as $type) {
            foreach ($type->possibleContexts() as $context) {
                foreach ($this->reader->selectableGroupedForContext($context) as $group) {
                    foreach ($group['tags'] as $key => $definition) {
                        $this->assertTrue($definition->isSeekerSelectable(), "{$key} in {$context->value}");
                        $this->assertFalse($definition->isPendingReview(), "{$key} in {$context->value}");
                        $this->assertNotContains($key, ['accessible_features', 'playground']);
                    }
                }
            }
        }
    }

    // ── 19-20. authorization ────────────────────────────────────────────────

    /** @test */
    public function only_the_owner_can_write_and_guests_and_other_roles_are_refused(): void
    {
        $buyer = $this->user('buyer');
        $tenant = $this->user('tenant');
        $stranger = $this->user('buyer');
        $buyerOffer = $this->buyerOffer($buyer, 'Residential');
        $tenantOffer = $this->tenantOffer($tenant, 'Residential Property');

        $this->assertTrue($this->writer->replaceSelections($buyerOffer, ['private_pool'], $buyer->id)->accepted);
        $this->assertTrue($this->writer->replaceSelections($tenantOffer, ['partially_furnished'], $tenant->id)->accepted);

        foreach ([
            [$buyerOffer, $stranger->id],   // another user
            [$buyerOffer, null],            // guest
            [$buyerOffer, $tenant->id],     // tenant mutating a buyer offer listing
            [$tenantOffer, $buyer->id],     // buyer mutating a tenant offer listing
        ] as [$subject, $actor]) {
            $result = $this->writer->replaceSelections($subject, ['garage'], $actor);
            $this->assertFalse($result->accepted);
            $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NOT_OWNER, $result->refusalReason);
        }

        $this->assertSame(['private_pool'], $this->reader->keysFor($buyerOffer));
        $this->assertSame(['partially_furnished'], $this->reader->keysFor($tenantOffer));
    }

    /** @test */
    public function an_unsaved_subject_creates_no_rows(): void
    {
        $owner = $this->user('buyer');
        $unsaved = new BuyerAgentAuction(['user_id' => $owner->id]);

        $result = $this->writer->replaceSelections($unsaved, ['private_pool'], $owner->id);

        $this->assertFalse($result->accepted);
        $this->assertSame(0, SmartTagSeekerPreference::query()->count());
    }

    /** @test */
    public function hire_agent_rows_in_the_shared_tables_are_not_seeker_subjects(): void
    {
        $buyer = $this->user('buyer');
        $tenant = $this->user('tenant');

        foreach ([
            [$this->buyerOffer($buyer, 'Residential', 'hire_agent'), $buyer],
            [$this->tenantOffer($tenant, 'Residential Property', 'hire_agent'), $tenant],
            [$this->buyerOffer($buyer, 'Residential', ''), $buyer], // unclassified
        ] as [$row, $owner]) {
            $this->assertNull(SmartTagSeekerSubjectType::forModel($row));

            $result = $this->writer->replaceSelections($row, ['private_pool'], $owner->id);
            $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_UNSUPPORTED_SUBJECT, $result->refusalReason);
            $this->assertSame([], $this->reader->keysFor($row));
            $this->assertNull($this->reader->contextFor($row));
        }

        $this->assertSame(0, SmartTagSeekerPreference::query()->count());
    }

    /** @test */
    public function an_offer_listing_with_no_recognised_property_type_stores_nothing(): void
    {
        $owner = $this->user('buyer');

        foreach ([null, '', 'Residential Property', 'residential'] as $propertyType) {
            $offer = $this->buyerOffer($owner, $propertyType);
            $result = $this->writer->replaceSelections($offer, ['private_pool'], $owner->id);
            $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NO_CONTEXT, $result->refusalReason);
        }

        $this->assertSame(0, SmartTagSeekerPreference::query()->count());
    }

    // ── 13-14. the flag ─────────────────────────────────────────────────────

    /** @test */
    public function with_the_feature_off_nothing_is_written_and_nothing_stored_is_erased(): void
    {
        $owner = $this->user('buyer');
        $offer = $this->buyerOffer($owner, 'Residential');
        $this->writer->replaceSelections($offer, ['private_pool'], $owner->id);

        config()->set('smart_tags_wiring.seeker_preferences_enabled', false);

        $cleared = $this->writer->replaceSelections($offer, [], $owner->id);
        $changed = $this->writer->replaceSelections($offer, ['garage'], $owner->id);

        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_FEATURE_DISABLED, $cleared->refusalReason);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_FEATURE_DISABLED, $changed->refusalReason);
        $this->assertSame(['private_pool'], $this->reader->keysFor($offer));
    }

    // ── 21. the reader serves all four subject types through one API ───────

    /** @test */
    public function the_reader_returns_current_canonical_keys_for_all_four_subject_types(): void
    {
        $buyer = $this->user('buyer');
        $tenant = $this->user('tenant');

        $subjects = [
            [$this->buyerCriteria($buyer, 'Residential Property'), $buyer, 'private_pool'],
            [$this->tenantCriteria($tenant, 'Residential Property'), $tenant, 'partially_furnished'],
            [$this->buyerOffer($buyer, 'Residential'), $buyer, 'private_pool'],
            [$this->tenantOffer($tenant, 'Residential Property'), $tenant, 'partially_furnished'],
        ];

        foreach ($subjects as [$subject, $owner, $key]) {
            $this->writer->replaceSelections($subject, [$key], $owner->id);
            $this->assertSame([$key], $this->reader->currentKeysFor($subject), $subject::class);
        }

        // A later property-type change makes a stored key inapplicable: the matcher-facing
        // read drops it, the stored row is left alone.
        $offer = $subjects[2][0];
        $offer->saveMeta('property_type', 'Commercial');
        $offer = $offer->fresh();
        $this->assertSame(['private_pool'], $this->reader->keysFor($offer));
        $this->assertSame([], $this->reader->currentKeysFor($offer));
    }

    // ── 25. criteria records behave exactly as before ───────────────────────

    /** @test */
    public function criteria_preferences_are_unchanged_by_the_offer_listing_subjects(): void
    {
        $buyer = $this->user('buyer');
        $tenant = $this->user('tenant');
        $bc = $this->buyerCriteria($buyer, 'Residential Property');
        $tc = $this->tenantCriteria($tenant, 'Commercial Property');

        $this->assertSame(SmartTagSeekerSubjectType::BuyerCriteria, SmartTagSeekerSubjectType::forModel($bc));
        $this->assertSame(SmartTagSeekerSubjectType::TenantCriteria, SmartTagSeekerSubjectType::forModel($tc));
        $this->assertSame(SmartTagContext::ResidentialSale, $this->reader->contextFor($bc));
        $this->assertSame(SmartTagContext::CommercialLease, $this->reader->contextFor($tc));

        $this->assertSame(['private_pool'], $this->writer->replaceSelections($bc, ['private_pool'], $buyer->id)->storedKeys());
        $this->assertSame(['vanilla_shell'], $this->writer->replaceSelections($tc, ['vanilla_shell'], $tenant->id)->storedKeys());
        $this->assertSame('buyer_criteria', SmartTagSeekerPreference::query()->where('tag_key', 'private_pool')->value('subject_type'));
        $this->assertSame('tenant_criteria', SmartTagSeekerPreference::query()->where('tag_key', 'vanilla_shell')->value('subject_type'));
    }

    // ── 22. purge ───────────────────────────────────────────────────────────

    /** @test */
    public function the_deletion_purge_removes_only_that_subject_types_rows_and_ignores_the_flag(): void
    {
        $owner = $this->user('buyer');
        $offerA = $this->buyerOffer($owner, 'Residential');
        $offerB = $this->buyerOffer($owner, 'Residential');
        $criteria = $this->buyerCriteria($owner, 'Residential Property', ['id' => $offerA->id]);

        $this->writer->replaceSelections($offerA, ['private_pool'], $owner->id);
        $this->writer->replaceSelections($offerB, ['garage'], $owner->id);
        $this->writer->replaceSelections($criteria, ['fenced_yard'], $owner->id);

        config()->set('smart_tags_wiring.seeker_preferences_enabled', false);

        SmartTagSeekerPreferenceWriter::tryPurgeDeleted(BuyerAgentAuction::class, [$offerA->id, 0, -3, 'x']);

        $this->assertSame([], $this->reader->keysFor($offerA));
        $this->assertSame(['garage'], $this->reader->keysFor($offerB));
        $this->assertSame(['fenced_yard'], $this->reader->keysFor($criteria), 'same numeric id, other subject type');

        // Classes that are not seeker subjects are a no-op, never an error.
        SmartTagSeekerPreferenceWriter::tryPurgeDeleted(\App\Models\SellerAgentAuction::class, [$offerB->id]);
        SmartTagSeekerPreferenceWriter::tryPurgeDeleted('Not\\A\\Class', [$offerB->id]);
        $this->assertSame(['garage'], $this->reader->keysFor($offerB));
    }

    // ── 24. seeker preferences never become property evidence ───────────────

    /** @test */
    public function writing_seeker_preferences_touches_no_listing_evidence_or_assignment(): void
    {
        $buyer = $this->user('buyer');
        $tenant = $this->user('tenant');
        $this->writer->replaceSelections($this->buyerOffer($buyer, 'Residential'), ['private_pool'], $buyer->id);
        $this->writer->replaceSelections($this->tenantOffer($tenant, 'Commercial Property'), ['vanilla_shell'], $tenant->id);

        $this->assertSame(2, SmartTagSeekerPreference::query()->count());
        $this->assertSame(0, SmartTagEvidence::query()->count());
        $this->assertSame(0, SmartTagAssignment::query()->count());

        // And no seeker value is a listing type.
        $listingTypes = array_map(fn ($c) => $c->value, \App\Support\SmartTags\SmartTagListingType::cases());
        $this->assertSame([], array_intersect(SmartTagSeekerSubjectType::values(), $listingTypes));
    }
}
