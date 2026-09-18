<?php

namespace Tests\Unit\SmartTags;

use App\Support\SmartTags\OwnerSmartTagSelection;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * The Seller/Landlord picker's FORM STATE, on its own.
 *
 * Extends PHPUnit's TestCase with no application, like the other pure Smart Tag
 * tests: this class must answer without a booted container, because the wizard
 * concern calls it from a Livewire property accessor and a `config()` fault
 * there would surface several frames away as an empty form.
 *
 * Note what is NOT asserted here: context. Sanitising is deliberately weaker
 * than the write boundary — see {@see OwnerSmartTagSelectionTest} in
 * tests/Feature/SmartTags for the projection that actually governs a save.
 */
class OwnerSmartTagSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function it_keeps_canonical_owner_selectable_keys(): void
    {
        $this->assertSame(
            ['updated_kitchen', 'quartz_countertops'],
            OwnerSmartTagSelection::sanitize(['quartz_countertops', 'updated_kitchen']),
            'Both keys are canonical and owner-selectable, and the stored order is the taxonomy display order, not click order.'
        );
    }

    /** @test */
    public function it_drops_everything_that_is_not_a_canonical_owner_selectable_key(): void
    {
        $kept = OwnerSmartTagSelection::sanitize([
            'quartz_countertops',   // real
            'gourmet_kitchen',      // never existed
            'Custom Awesome Tag',   // free text
            'family_friendly',      // a prohibited concept, and not a key
            'gated_community',      // declared, but pending compliance review
            '',
            null,
            42,
            ['nested'],
        ]);

        $this->assertSame(['quartz_countertops'], $kept);
    }

    /** @test */
    public function it_deduplicates(): void
    {
        $this->assertSame(
            ['kitchen_island'],
            OwnerSmartTagSelection::sanitize(['kitchen_island', 'kitchen_island'])
        );
    }

    /** @test */
    public function it_round_trips_through_the_stored_meta_value(): void
    {
        $encoded = OwnerSmartTagSelection::encode(['kitchen_island', 'walk_in_closet']);

        $this->assertJson($encoded);
        $this->assertSame(['kitchen_island', 'walk_in_closet'], OwnerSmartTagSelection::decode($encoded));
    }

    /**
     * The meta accessor JSON-decodes anything decodable, so the value handed
     * back on Edit is an ARRAY, not the string that was written.
     *
     * @test
     */
    public function it_decodes_an_already_decoded_array(): void
    {
        $this->assertSame(['kitchen_island'], OwnerSmartTagSelection::decode(['kitchen_island']));
    }

    /** @test */
    public function an_unreadable_stored_value_is_an_empty_selection_and_never_a_guess(): void
    {
        foreach ([null, '', '   ', 'null', '{"not":"a list"}', '[', 12345, new \stdClass()] as $stored) {
            $this->assertSame([], OwnerSmartTagSelection::decode($stored),
                'An unreadable stored selection must read as none at all.');
        }
    }

    /**
     * NO PARALLEL VOCABULARY. Everything this class can return is a key the
     * canonical taxonomy declares; there is no path by which a form value
     * becomes a new tag.
     *
     * @test
     */
    public function nothing_survives_that_the_canonical_taxonomy_does_not_declare(): void
    {
        $everyKey = array_keys(SmartTagTaxonomy::all());
        $kept = OwnerSmartTagSelection::sanitize(array_merge($everyKey, ['invented_key', 'another_invention']));

        $this->assertNotEmpty($kept);

        foreach ($kept as $key) {
            $definition = SmartTagTaxonomy::get($key);
            $this->assertNotNull($definition, "{$key} is not in the canonical taxonomy.");
            $this->assertTrue($definition->isOwnerSelectable(), "{$key} is not owner-selectable.");
        }
    }

    /** @test */
    public function the_meta_key_is_a_plain_listing_answer(): void
    {
        // Not a table, not a namespaced blob: a sibling of interior_features, so
        // draft saves and the draft loader carry it with everything else.
        $this->assertSame('smart_tag_owner_selections', OwnerSmartTagSelection::META_KEY);
    }
}
