<?php

namespace Tests\Feature\SmartTags;

use App\Models\BuyerCriteriaAuction;
use App\Models\SmartTagSeekerPreference;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceResult;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use App\Support\SmartTags\SmartTagSelectionResult;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeker side of Smart Tags: a Buyer or Tenant choosing canonical tags.
 *
 * Every assertion here is about the WRITE boundary, because hiding an option in
 * Blade is a rendering decision and this surface must hold against a crafted
 * POST.
 */
class SmartTagSeekerPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private SmartTagSeekerPreferenceWriter $writer;
    private SmartTagSeekerPreferenceReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        // The seeker-preference feature is OFF by default and is enabled here in
        // TEST-LOCAL config only — this suite exercises the feature itself. The
        // gate's own behaviour (off, and fail-closed parsing) is covered by
        // SmartTagSeekerPreferenceGateTest.
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        SmartTagTaxonomy::flush();
        $this->writer = app(SmartTagSeekerPreferenceWriter::class);
        $this->reader = app(SmartTagSeekerPreferenceReader::class);
    }

    private function user(string $type = 'buyer'): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function buyerCriteria(User $owner, string $propertyType = 'Residential Property'): BuyerCriteriaAuction
    {
        $a = new BuyerCriteriaAuction();
        $a->user_id = $owner->id;
        $a->buyer_id = $owner->id;
        $a->max_price = 500000;
        $a->title = 'Test buyer criteria';
        $a->save();
        $a->saveMeta('property_type', $propertyType);
        $a->refresh();

        return $a;
    }

    private function tenantCriteria(User $owner, string $propertyType = 'Residential Property'): TenantCriteriaAuction
    {
        $a = new TenantCriteriaAuction();
        $a->user_id = $owner->id;
        $a->save();
        $a->saveMeta('property_type', $propertyType);
        $a->refresh();

        return $a;
    }

    /** A key that is genuinely seeker-selectable in the given context. */
    private function seekerKeyIn(SmartTagContext $context): string
    {
        $tags = SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_SEEKER);
        $this->assertNotEmpty($tags, "no seeker-selectable tags in {$context->value}");

        return (string) array_key_first($tags);
    }

    // ── 1. canonical key persistence ────────────────────────────────────────

    /** @test */
    public function a_buyer_selection_persists_the_canonical_key(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $result = $this->writer->replaceSelections($criteria, ['private_pool', 'garage'], $owner->id);

        $this->assertTrue($result->accepted);
        $this->assertEqualsCanonicalizing(['private_pool', 'garage'], $result->storedKeys());

        $row = SmartTagSeekerPreference::where('tag_key', 'private_pool')->firstOrFail();
        $this->assertSame('buyer_criteria', $row->subject_type);
        $this->assertSame($criteria->id, $row->subject_id);
        $this->assertSame($owner->id, $row->user_id);
        $this->assertSame('buyer', $row->seeker_role);
        $this->assertSame('residential.sale', $row->context);

        // The taxonomy is NOT copied onto the row.
        $this->assertArrayNotHasKey('label', $row->getAttributes());
        $this->assertArrayNotHasKey('category', $row->getAttributes());
    }

    // ── 2. duplicate prevention ─────────────────────────────────────────────

    /** @test */
    public function selecting_the_same_tag_twice_stores_one_row(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $this->writer->replaceSelections($criteria, ['private_pool', 'private_pool'], $owner->id);
        $this->assertSame(1, SmartTagSeekerPreference::where('tag_key', 'private_pool')->count());

        // Re-submitting an unchanged selection is idempotent.
        $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);
        $this->assertSame(1, SmartTagSeekerPreference::where('tag_key', 'private_pool')->count());
    }

    // ── 3-7. every Buyer context ────────────────────────────────────────────

    /**
     * @test
     * @dataProvider buyerContexts
     */
    public function every_buyer_context_can_store_a_selection(string $propertyType, string $expectedContext): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner, $propertyType);

        $context = SmartTagContext::from($expectedContext);
        $key = $this->seekerKeyIn($context);

        $result = $this->writer->replaceSelections($criteria, [$key], $owner->id);

        $this->assertTrue($result->accepted, "context {$expectedContext} should accept {$key}");
        $this->assertSame([$key], $result->storedKeys());
        $this->assertSame($expectedContext, SmartTagSeekerPreference::firstOrFail()->context);
    }

    public static function buyerContexts(): array
    {
        return [
            'residential' => ['Residential Property', 'residential.sale'],
            'income'      => ['Income Property', 'income.sale'],
            'commercial'  => ['Commercial Property', 'commercial.sale'],
            'business'    => ['Business Opportunity', 'business.sale'],
            'land'        => ['Vacant Land', 'land.sale'],
        ];
    }

    // ── 8-9. every Tenant context ───────────────────────────────────────────

    /**
     * @test
     * @dataProvider tenantContexts
     */
    public function every_tenant_context_can_store_a_selection(string $propertyType, string $expectedContext): void
    {
        $owner = $this->user('tenant');
        $criteria = $this->tenantCriteria($owner, $propertyType);

        $key = $this->seekerKeyIn(SmartTagContext::from($expectedContext));

        $result = $this->writer->replaceSelections($criteria, [$key], $owner->id);

        $this->assertTrue($result->accepted);
        $this->assertSame([$key], $result->storedKeys());

        $row = SmartTagSeekerPreference::firstOrFail();
        $this->assertSame('tenant_criteria', $row->subject_type);
        $this->assertSame('tenant', $row->seeker_role);
        $this->assertSame($expectedContext, $row->context);
    }

    public static function tenantContexts(): array
    {
        return [
            'residential lease' => ['Residential Property', 'residential.lease'],
            'commercial lease'  => ['Commercial Property', 'commercial.lease'],
        ];
    }

    /** @test */
    public function the_same_property_type_string_means_sale_for_buyers_and_lease_for_tenants(): void
    {
        $buyer = $this->user();
        $tenant = $this->user('tenant');

        $b = $this->buyerCriteria($buyer, 'Residential Property');
        $t = $this->tenantCriteria($tenant, 'Residential Property');

        $this->assertSame(SmartTagContext::ResidentialSale, $this->reader->contextFor($b));
        $this->assertSame(SmartTagContext::ResidentialLease, $this->reader->contextFor($t));
    }

    // ── 10-11. projection and context filtering ─────────────────────────────

    /** @test */
    public function the_picker_is_projected_from_the_canonical_taxonomy_only(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $grouped = $this->reader->selectableGroupedFor($criteria);
        $this->assertNotEmpty($grouped);

        $canonical = array_keys(SmartTagTaxonomy::all());

        foreach ($grouped as $slug => $group) {
            $this->assertArrayHasKey('label', $group);
            foreach ($group['tags'] as $key => $definition) {
                $this->assertContains($key, $canonical, "{$key} is not a canonical tag");
                $this->assertTrue($definition->isSeekerSelectable(), "{$key} is not seeker-selectable");
                $this->assertTrue($definition->appliesTo(SmartTagContext::ResidentialSale));
                $this->assertSame($slug, $definition->category);
            }
        }
    }

    /** @test */
    public function a_land_criteria_is_not_offered_residential_only_tags(): void
    {
        $owner = $this->user();
        $land = $this->buyerCriteria($owner, 'Vacant Land');

        $offered = [];
        foreach ($this->reader->selectableGroupedFor($land) as $group) {
            $offered = array_merge($offered, array_keys($group['tags']));
        }

        $this->assertNotContains('walk_in_closet', $offered);
        $this->assertNotContains('quartz_countertops', $offered);
    }

    // ── 12-14. server-side rejection ────────────────────────────────────────

    /** @test */
    public function a_nonexistent_key_is_rejected(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $result = $this->writer->replaceSelections($criteria, ['not_a_real_tag', 'private_pool'], $owner->id);

        $this->assertSame(['private_pool'], $result->storedKeys());
        $this->assertSame(SmartTagSelectionResult::REASON_UNKNOWN_KEY, $result->rejectedKeys()['not_a_real_tag']);
        $this->assertDatabaseMissing('smart_tag_seeker_preferences', ['tag_key' => 'not_a_real_tag']);
    }

    /** @test */
    public function a_malformed_value_is_rejected_without_touching_the_database(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $result = $this->writer->replaceSelections(
            $criteria,
            ['Private Pool', 'private-pool', '', 123, ['nested'], 'DROP TABLE'],
            $owner->id
        );

        $this->assertSame([], $result->storedKeys());
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    /** @test */
    public function a_key_from_the_wrong_context_is_rejected(): void
    {
        $owner = $this->user();
        // loading_dock is commercial/business/land, never residential.sale.
        $criteria = $this->buyerCriteria($owner, 'Residential Property');

        $result = $this->writer->replaceSelections($criteria, ['loading_dock'], $owner->id);

        $this->assertSame([], $result->storedKeys());
        $this->assertSame(
            SmartTagSelectionResult::REASON_NOT_APPLICABLE,
            $result->rejectedKeys()['loading_dock']
        );
    }

    /** @test */
    public function a_non_seeker_selectable_tag_is_rejected(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        // Fair Housing: owner-describable, never a seeker preference.
        foreach (['accessible_features', 'playground'] as $key) {
            $definition = SmartTagTaxonomy::get($key);
            $this->assertNotNull($definition, "{$key} should exist in the taxonomy");
            $this->assertFalse($definition->isSeekerSelectable(), "{$key} must not be seeker-selectable");

            $result = $this->writer->replaceSelections($criteria, [$key], $owner->id);

            $this->assertSame([], $result->storedKeys());
            $this->assertArrayHasKey($key, $result->rejectedKeys());
            $this->assertDatabaseMissing('smart_tag_seeker_preferences', ['tag_key' => $key]);
        }
    }

    /** @test */
    public function a_criteria_with_an_unreadable_property_type_stores_nothing(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner, 'Something Nobody Offers');

        $result = $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);

        $this->assertFalse($result->accepted);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NO_CONTEXT, $result->refusalReason);
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    // ── 15. authorization ───────────────────────────────────────────────────

    /** @test */
    public function a_non_owner_cannot_write_another_seekers_preferences(): void
    {
        $owner = $this->user();
        $intruder = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $result = $this->writer->replaceSelections($criteria, ['private_pool'], $intruder->id);

        $this->assertFalse($result->accepted);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NOT_OWNER, $result->refusalReason);
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    /** @test */
    public function a_guest_cannot_write_preferences(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $result = $this->writer->replaceSelections($criteria, ['private_pool'], null);

        $this->assertFalse($result->accepted);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NOT_OWNER, $result->refusalReason);
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    /** @test */
    public function a_null_owner_id_does_not_make_a_guest_the_owner(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        // user_id is NOT NULL in this schema, so the orphan state is produced in
        // memory. The guard must still refuse: a guest's null id and a null
        // owner id both cast to 0 and would otherwise match each other.
        $criteria->user_id = null;

        $result = $this->writer->replaceSelections($criteria, ['private_pool'], null);

        $this->assertFalse($result->accepted);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NOT_OWNER, $result->refusalReason);
    }

    // ── 16-17. edit persistence and deselection ─────────────────────────────

    /** @test */
    public function editing_replaces_the_selection_and_deselection_removes_rows(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $this->writer->replaceSelections($criteria, ['private_pool', 'garage', 'fenced_yard'], $owner->id);
        $this->assertSame(3, SmartTagSeekerPreference::count());

        // Edit: drop garage, keep the rest, add one.
        $this->writer->replaceSelections($criteria, ['private_pool', 'fenced_yard', 'walk_in_closet'], $owner->id);

        $keys = $this->reader->keysFor($criteria);
        $this->assertEqualsCanonicalizing(['private_pool', 'fenced_yard', 'walk_in_closet'], $keys);
        $this->assertDatabaseMissing('smart_tag_seeker_preferences', ['tag_key' => 'garage']);
    }

    /** @test */
    public function submitting_an_empty_selection_clears_everything(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);
        $this->writer->replaceSelections($criteria, [], $owner->id);

        $this->assertSame([], $this->reader->keysFor($criteria));
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    /** @test */
    public function two_criteria_records_keep_separate_selections(): void
    {
        $owner = $this->user();
        $a = $this->buyerCriteria($owner);
        $b = $this->buyerCriteria($owner);

        $this->writer->replaceSelections($a, ['private_pool'], $owner->id);
        $this->writer->replaceSelections($b, ['garage'], $owner->id);

        $this->assertSame(['private_pool'], $this->reader->keysFor($a));
        $this->assertSame(['garage'], $this->reader->keysFor($b));
    }

    // ── 18. reader output ───────────────────────────────────────────────────

    /** @test */
    public function the_reader_answers_in_canonical_keys_for_a_future_matcher(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);
        $this->writer->replaceSelections($criteria, ['garage', 'private_pool'], $owner->id);

        $keys = $this->reader->keysFor($criteria);

        $this->assertContainsOnly('string', $keys);
        foreach ($keys as $key) {
            $this->assertNotNull(SmartTagTaxonomy::get($key));
        }

        // Same answer through the subject-type API, which is what a matcher that
        // holds only ids would use.
        $this->assertSame(
            $keys,
            $this->reader->keysForSubject(SmartTagSeekerSubjectType::BuyerCriteria, $criteria->id)
        );
    }

    /** @test */
    public function current_keys_drop_selections_the_edited_property_type_no_longer_allows(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner, 'Commercial Property');

        $this->writer->replaceSelections($criteria, ['loading_dock'], $owner->id);
        $this->assertSame(['loading_dock'], $this->reader->keysFor($criteria));

        // The seeker switches to Residential. The row is preserved, but a matcher
        // must not score a house against a loading dock.
        $criteria->saveMeta('property_type', 'Residential Property');
        $criteria->refresh();

        $this->assertSame(['loading_dock'], $this->reader->keysFor($criteria), 'stored rows are not deleted on read');
        $this->assertSame([], $this->reader->currentKeysFor($criteria));
    }

    /** @test */
    public function the_reader_returns_nothing_for_an_unsupported_subject(): void
    {
        $this->assertSame([], $this->reader->keysFor(new \App\Models\User()));
        $this->assertNull($this->reader->contextFor(new \App\Models\User()));
    }

    // ── 19-20. no parallel vocabulary, compliance ───────────────────────────

    /** @test */
    public function every_selectable_key_is_a_canonical_smart_tag_in_every_seeker_context(): void
    {
        $canonical = array_keys(SmartTagTaxonomy::all());

        foreach (SmartTagSeekerSubjectType::cases() as $type) {
            foreach ($type->possibleContexts() as $context) {
                foreach ($this->reader->selectableGroupedForContext($context) as $group) {
                    foreach (array_keys($group['tags']) as $key) {
                        $this->assertContains(
                            $key,
                            $canonical,
                            "{$key} offered in {$context->value} is not a canonical Smart Tag"
                        );
                    }
                }
            }
        }
    }

    /** @test */
    public function compliance_blocked_concepts_can_never_be_stored(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        // 55+ is a compliance gate, never an ordinary seeker preference; the
        // accessibility and familial-status tags are owner-describable only.
        $blocked = ['leasing_55_plus', 'accessible_features', 'playground'];

        $result = $this->writer->replaceSelections($criteria, $blocked, $owner->id);

        $this->assertSame([], $result->storedKeys());
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    /** @test */
    public function no_prohibited_concept_is_offered_to_a_seeker_in_any_context(): void
    {
        foreach (SmartTagSeekerSubjectType::cases() as $type) {
            foreach ($type->possibleContexts() as $context) {
                foreach ($this->reader->selectableGroupedForContext($context) as $group) {
                    foreach ($group['tags'] as $key => $definition) {
                        $this->assertTrue($definition->isSeekerSelectable());
                        $this->assertFalse(
                            $definition->isPendingReview(),
                            "{$key} is pending compliance review and must not be offered"
                        );
                    }
                }
            }
        }
    }

    // ── purge ───────────────────────────────────────────────────────────────

    /** @test */
    public function purging_a_criteria_record_removes_only_its_own_rows(): void
    {
        $owner = $this->user();
        $a = $this->buyerCriteria($owner);
        $b = $this->buyerCriteria($owner);

        $this->writer->replaceSelections($a, ['private_pool'], $owner->id);
        $this->writer->replaceSelections($b, ['garage'], $owner->id);

        $this->writer->purge(SmartTagSeekerSubjectType::BuyerCriteria, $a->id);

        $this->assertSame([], $this->reader->keysFor($a));
        $this->assertSame(['garage'], $this->reader->keysFor($b));
    }
}
