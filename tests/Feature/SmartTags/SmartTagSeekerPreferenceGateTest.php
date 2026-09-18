<?php

namespace Tests\Feature\SmartTags;

use App\Models\BuyerCriteriaAuction;
use App\Models\SmartTagSeekerPreference;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceResult;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The seeker-preference feature gate, and the deletion cleanup that is
 * deliberately NOT behind it.
 *
 * The flag is never enabled globally — every "on" case sets it in test-local
 * config only.
 */
class SmartTagSeekerPreferenceGateTest extends TestCase
{
    use RefreshDatabase;

    private SmartTagSeekerPreferenceWriter $writer;
    private SmartTagSeekerPreferenceReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->writer = app(SmartTagSeekerPreferenceWriter::class);
        $this->reader = app(SmartTagSeekerPreferenceReader::class);
    }

    private function enableFeature(bool $on = true): void
    {
        config()->set('smart_tags_wiring.seeker_preferences_enabled', $on);
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

    private function renderPicker(string $role, array $selected = []): string
    {
        return View::make('partials.smart-tags._seeker-picker', [
            'stRole' => $role, 'stSelected' => $selected,
        ])->render();
    }

    // ── the default ─────────────────────────────────────────────────────────

    /** @test */
    public function the_feature_ships_disabled(): void
    {
        $this->assertFalse(
            SmartTagSeekerPreferenceGate::enabled(),
            'SMART_TAGS_SEEKER_PREFERENCES_ENABLED must default to false'
        );
        $this->assertFalse(SmartTagSeekerPreferenceGate::writesEnabled());
    }

    /**
     * Fail-closed: only a real boolean true opens the gate, so a config that did
     * not load, an absent key, or a truthy string all read as OFF.
     *
     * @test
     * @dataProvider nonBooleanTruthyValues
     */
    public function only_a_real_boolean_true_opens_the_gate(mixed $value): void
    {
        config()->set('smart_tags_wiring.seeker_preferences_enabled', $value);

        $this->assertFalse(SmartTagSeekerPreferenceGate::enabled());
    }

    public static function nonBooleanTruthyValues(): array
    {
        return [
            'string true' => ['true'], 'string one' => ['1'], 'int one' => [1],
            'string on' => ['on'], 'string yes' => ['yes'], 'null' => [null],
            'string off' => ['off'], 'false' => [false], 'empty' => [''],
        ];
    }

    // ── 1-2. FLAG OFF: pickers absent ───────────────────────────────────────

    /** @test */
    public function the_buyer_picker_is_absent_when_the_flag_is_off(): void
    {
        $this->enableFeature(false);

        $html = $this->renderPicker('buyer');

        $this->assertStringNotContainsString('name="smart_tags[]"', $html);
        $this->assertStringNotContainsString('data-smart-tag-picker', $html);
        $this->assertSame('', trim($html));
    }

    /** @test */
    public function the_tenant_picker_is_absent_when_the_flag_is_off(): void
    {
        $this->enableFeature(false);

        $html = $this->renderPicker('tenant');

        $this->assertStringNotContainsString('name="smart_tags[]"', $html);
        $this->assertSame('', trim($html));
    }

    // ── 3-5. FLAG OFF: criteria still save, no rows created ─────────────────

    /** @test */
    public function a_buyer_criteria_still_saves_with_the_flag_off_and_writes_no_preference_rows(): void
    {
        $this->enableFeature(false);
        $owner = $this->user();

        $criteria = $this->buyerCriteria($owner);

        // The criteria record itself is unaffected.
        $this->assertDatabaseHas('buyer_criteria_auctions', ['id' => $criteria->id]);
        $this->assertSame('Residential Property', $criteria->get->property_type);

        $result = $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);

        $this->assertFalse($result->accepted);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_FEATURE_DISABLED, $result->refusalReason);
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    /** @test */
    public function a_tenant_criteria_still_saves_with_the_flag_off_and_writes_no_preference_rows(): void
    {
        $this->enableFeature(false);
        $owner = $this->user('tenant');

        $criteria = $this->tenantCriteria($owner);

        $this->assertDatabaseHas('tenant_criteria_auctions', ['id' => $criteria->id]);

        $result = $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);

        $this->assertFalse($result->accepted);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_FEATURE_DISABLED, $result->refusalReason);
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    // ── 6. THE ONE THAT MATTERS: off must not erase ─────────────────────────

    /**
     * Turning the feature off must never be what deletes a customer's data.
     *
     * This is why "disabled" is a SKIPPED write and not replaceSelections([]):
     * the empty-array form would look identical at the call site and would clear
     * every stored selection on the next save.
     *
     * @test
     */
    public function editing_while_the_flag_is_off_does_not_erase_existing_selections(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        // Stored while the feature was on.
        $this->enableFeature(true);
        $this->writer->replaceSelections($criteria, ['private_pool', 'garage'], $owner->id);
        $this->assertCount(2, $this->reader->keysFor($criteria));

        // Feature withdrawn. The customer edits their criteria repeatedly.
        $this->enableFeature(false);
        $this->writer->replaceSelections($criteria, [], $owner->id);
        $this->writer->replaceSelections($criteria, ['walk_in_closet'], $owner->id);

        $this->assertEqualsCanonicalizing(
            ['private_pool', 'garage'],
            $this->reader->keysFor($criteria),
            'a disabled feature must leave stored selections exactly as they were'
        );

        // And they are still there when it comes back.
        $this->enableFeature(true);
        $this->assertEqualsCanonicalizing(['private_pool', 'garage'], $this->reader->keysFor($criteria));
    }

    // ── 7. malformed input cannot bypass the disabled state ─────────────────

    /** @test */
    public function malformed_input_cannot_bypass_the_disabled_state(): void
    {
        $this->enableFeature(false);
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        foreach ([
            ['private_pool'], [], ['Private Pool'], ['accessible_features'],
            [123, null, ['nested']], ['../../etc/passwd'], ['private_pool', 'private_pool'],
        ] as $payload) {
            $result = $this->writer->replaceSelections($criteria, $payload, $owner->id);

            $this->assertFalse($result->accepted);
            $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_FEATURE_DISABLED, $result->refusalReason);
        }

        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    // ── 8-13. FLAG ON (test-local only) ─────────────────────────────────────

    /** @test */
    public function the_buyer_picker_renders_when_the_flag_is_on(): void
    {
        $this->enableFeature(true);

        $html = $this->renderPicker('buyer');

        $this->assertStringContainsString('name="smart_tags[]"', $html);
        $this->assertStringContainsString('data-smart-tag-picker', $html);
    }

    /** @test */
    public function the_tenant_picker_renders_when_the_flag_is_on(): void
    {
        $this->enableFeature(true);

        $this->assertStringContainsString('name="smart_tags[]"', $this->renderPicker('tenant'));
    }

    /** @test */
    public function selections_persist_restore_and_deselect_when_the_flag_is_on(): void
    {
        $this->enableFeature(true);
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        // persist
        $this->writer->replaceSelections($criteria, ['private_pool', 'garage'], $owner->id);
        $this->assertEqualsCanonicalizing(['private_pool', 'garage'], $this->reader->keysFor($criteria));

        // restore on edit — the picker renders them checked
        $html = $this->renderPicker('buyer', $this->reader->keysFor($criteria));
        $this->assertMatchesRegularExpression('/value="private_pool"[^>]*checked/', $html);

        // deselect one
        $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);
        $this->assertSame(['private_pool'], $this->reader->keysFor($criteria));

        // deselect all
        $this->writer->replaceSelections($criteria, [], $owner->id);
        $this->assertSame([], $this->reader->keysFor($criteria));
    }

    /** @test */
    public function context_and_compliance_validation_still_applies_when_the_flag_is_on(): void
    {
        $this->enableFeature(true);
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner, 'Residential Property');

        $result = $this->writer->replaceSelections(
            $criteria,
            ['loading_dock', 'accessible_features', 'playground', 'leasing_55_plus', 'not_a_tag', 'private_pool'],
            $owner->id
        );

        $this->assertSame(['private_pool'], $result->storedKeys());
        $this->assertSame(0, SmartTagSeekerPreference::where('tag_key', '!=', 'private_pool')->count());
    }

    /** @test */
    public function ownership_is_still_enforced_when_the_flag_is_on(): void
    {
        $this->enableFeature(true);
        $owner = $this->user();
        $intruder = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $result = $this->writer->replaceSelections($criteria, ['private_pool'], $intruder->id);

        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NOT_OWNER, $result->refusalReason);
        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    // ── PURGE: not gated ────────────────────────────────────────────────────

    /** @test */
    public function deleting_a_buyer_criteria_purges_its_preference_rows(): void
    {
        $this->enableFeature(true);
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);
        $this->writer->replaceSelections($criteria, ['private_pool', 'garage'], $owner->id);
        $this->assertSame(2, SmartTagSeekerPreference::count());

        $criteria->delete();

        $this->assertSame(0, SmartTagSeekerPreference::count(), 'no orphan rows may remain');
    }

    /** @test */
    public function deleting_a_tenant_criteria_purges_its_preference_rows(): void
    {
        $this->enableFeature(true);
        $owner = $this->user('tenant');
        $criteria = $this->tenantCriteria($owner);
        $key = array_key_first(SmartTagTaxonomy::forContext(
            \App\Support\SmartTags\SmartTagContext::ResidentialLease,
            SmartTagTaxonomy::SURFACE_SEEKER
        ));
        $this->writer->replaceSelections($criteria, [$key], $owner->id);
        $this->assertSame(1, SmartTagSeekerPreference::count());

        $criteria->delete();

        $this->assertSame(0, SmartTagSeekerPreference::count());
    }

    /** @test */
    public function deleting_one_criteria_leaves_another_records_selections_alone(): void
    {
        $this->enableFeature(true);
        $owner = $this->user();
        $a = $this->buyerCriteria($owner);
        $b = $this->buyerCriteria($owner);

        $this->writer->replaceSelections($a, ['private_pool'], $owner->id);
        $this->writer->replaceSelections($b, ['garage'], $owner->id);

        $a->delete();

        $this->assertSame([], $this->reader->keysFor($a));
        $this->assertSame(['garage'], $this->reader->keysFor($b));
    }

    /**
     * The scenario the asymmetry exists for: rows written while the feature was
     * on, the record deleted later with the feature off.
     *
     * @test
     */
    public function purge_runs_even_when_the_feature_flag_is_disabled(): void
    {
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);

        $this->enableFeature(true);
        $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);
        $this->assertSame(1, SmartTagSeekerPreference::count());

        // Feature withdrawn, then the record is deleted.
        $this->enableFeature(false);
        $this->assertTrue(SmartTagSeekerPreferenceGate::purgeAlwaysAllowed());

        $criteria->delete();

        $this->assertSame(0, SmartTagSeekerPreference::count(),
            'switching a feature off must not be what mints orphan rows');
    }

    /** @test */
    public function an_unsaved_or_unsupported_subject_cannot_purge_unrelated_rows(): void
    {
        $this->enableFeature(true);
        $owner = $this->user();
        $criteria = $this->buyerCriteria($owner);
        $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);

        // id 0 / negative must be a no-op, not an unscoped delete.
        $this->assertSame(0, $this->writer->purge(SmartTagSeekerSubjectType::BuyerCriteria, 0));
        $this->assertSame(0, $this->writer->purge(SmartTagSeekerSubjectType::BuyerCriteria, -1));

        // The wrong subject type for the same id must not reach buyer rows.
        $this->assertSame(0, $this->writer->purge(SmartTagSeekerSubjectType::TenantCriteria, $criteria->id));

        $this->assertSame(1, SmartTagSeekerPreference::count());
        $this->assertSame(['private_pool'], $this->reader->keysFor($criteria));
    }

    /** @test */
    public function deleting_a_criteria_with_no_selections_is_harmless(): void
    {
        $this->enableFeature(true);
        $owner = $this->user();
        $a = $this->buyerCriteria($owner);
        $b = $this->buyerCriteria($owner);
        $this->writer->replaceSelections($b, ['garage'], $owner->id);

        $a->delete();

        $this->assertSame(['garage'], $this->reader->keysFor($b));
    }
}
