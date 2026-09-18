<?php

namespace Tests\Feature\Security;

use App\Models\BridgeProperty;
use App\Services\ListingImport\Mls\MlsFieldCatalog;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use Tests\TestCase;

/**
 * Phase 3A added ONE field to the Stellar result card's allowlisted view model:
 * `bridge_property_id`. This file is the argument that it is safe.
 *
 * WHY A SECURITY TEST FOR AN INTEGER. `BuyerResultViewMapper` is an allowlist
 * over a 553-field licensed feed, and the risk in touching one is never the
 * field you meant to add — it is the ones that come with it when a `return`
 * array grows. So this seeds a record whose every restricted, internal and
 * contact field is populated with a value that could not appear by accident,
 * maps it, and asserts that what came out is still only what was cleared.
 *
 * WHAT THE FIELD IS. Our own primary key for a row in our own database. It is
 * not the MLS ListingKey, not a parcel number, and reveals nothing about the
 * listing. It exists because the shared subsystems that address a listing take
 * a (listing_type, listing_id) pair, and a card that carried only the MLS key
 * would force the browser to submit one as an identity — which the preference
 * write controller deliberately refuses.
 */
class StellarBridgeIdentityProjectionTest extends TestCase
{
    /** The Bridge row id is present, and it is the row id. @test */
    public function the_mapper_carries_the_bridge_row_id(): void
    {
        $listing = $this->listing(['id' => 4242, 'list_price' => 500000]);

        $card = (new BuyerResultViewMapper())->mapOne(
            new BuyerMatchResult('STELLAR-IDENTITY-1', 71, ['location' => 18], $listing)
        );

        $this->assertArrayHasKey('bridge_property_id', $card);
        $this->assertSame(4242, $card['bridge_property_id']);
        $this->assertIsInt($card['bridge_property_id']);
    }

    /**
     * A row with no id yields null rather than 0. `(int) null` is 0, and 0 is a
     * listing id the write controller would reject — but it would reject it as
     * a malformed request rather than as "this card has no identity", which is
     * the truth.
     *
     * @test
     */
    public function a_row_without_an_id_projects_null_not_zero(): void
    {
        $card = (new BuyerResultViewMapper())->mapOne(
            new BuyerMatchResult('STELLAR-IDENTITY-2', 50, [], $this->listing())
        );

        $this->assertNull($card['bridge_property_id']);
    }

    /** The row id is not the MLS key, and neither is substituted for the other. @test */
    public function the_row_id_and_the_mls_listing_key_stay_distinct(): void
    {
        $card = (new BuyerResultViewMapper())->mapOne(
            new BuyerMatchResult('STELLAR-IDENTITY-3', 50, [], $this->listing(['id' => 77]))
        );

        $this->assertSame('STELLAR-IDENTITY-3', $card['listing_key']);
        $this->assertSame(77, $card['bridge_property_id']);
        $this->assertNotSame($card['listing_key'], $card['bridge_property_id']);
    }

    /**
     * THE ALLOWLIST STILL HOLDS. Every restricted, internal and contact field
     * in the catalog is seeded with a distinctive value; none of them may reach
     * the card.
     *
     * Asserted against MlsFieldCatalog rather than a list written here, so a
     * field added to the catalog is covered without anybody remembering to.
     *
     * @test
     */
    public function no_prohibited_mls_field_reaches_the_result_card(): void
    {
        // RESTRICTED and INTERNAL are `field => reason`; CONTACTS is grouped
        // `group => [field => label]`, so it is flattened one level. The field
        // NAMES are the keys in all three.
        $contacts = [];
        foreach (MlsFieldCatalog::CONTACTS as $group) {
            $contacts = array_merge($contacts, array_keys($group));
        }

        $prohibited = array_values(array_unique(array_merge(
            array_keys(MlsFieldCatalog::RESTRICTED),
            array_keys(MlsFieldCatalog::INTERNAL),
            $contacts,
        )));

        $this->assertNotEmpty($prohibited, 'the catalog must actually name prohibited fields');
        $this->assertContains('PublicRemarks', $prohibited, 'the seed must include the fields that matter most');
        $this->assertContains('ListAgentEmail', $prohibited);

        $raw = [];
        foreach ($prohibited as $field) {
            $raw[$field] = 'PROHIBITED-VALUE-' . $field;
        }

        $listing = $this->listing(['id' => 99, 'list_price' => 425000], $raw);

        $card = (new BuyerResultViewMapper())->mapOne(
            new BuyerMatchResult('STELLAR-IDENTITY-4', 64, ['location' => 18], $listing)
        );

        $serialised = json_encode($card);

        $this->assertIsString($serialised);
        $this->assertStringNotContainsString(
            'PROHIBITED-VALUE-',
            $serialised,
            'a prohibited MLS field reached the result card'
        );

        foreach ($prohibited as $field) {
            $this->assertArrayNotHasKey($field, $card, "{$field} must not be a card key");
        }

        // raw_json itself is never handed to the view.
        $this->assertArrayNotHasKey('raw_json', $card);
    }

    /**
     * The card's key set grew by exactly the identity field and nothing else.
     *
     * A frozen list is the point here: an allowlist that can quietly gain a key
     * is not an allowlist, and this is the assertion that makes adding one a
     * deliberate, reviewed edit.
     *
     * @test
     */
    public function the_card_key_set_is_exactly_what_was_cleared(): void
    {
        $card = (new BuyerResultViewMapper())->mapOne(
            new BuyerMatchResult('STELLAR-IDENTITY-5', 50, [], $this->listing(['id' => 5]))
        );

        $expected = [
            'address',
            'baths',
            'beds',
            'bridge_property_id',
            'category_bars',
            'caution_flags',
            'city',
            'city_state_zip',
            'hero_photo_url',
            'important_places',
            'latitude',
            'listing_key',
            'longitude',
            'missing_data',
            'price_display',
            'property_sub_type',
            'property_type',
            'score_display',
            'sqft',
            'total_score',
            'tradeoffs',
            'why_this_matches',
        ];

        $actual = array_keys($card);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'the result card allowlist changed — adding a key here is a licensing decision'
        );
    }

    /**
     * The Stellar service layer must still know nothing about preferences.
     * Phase 3A added an identity field, never a consumption of one.
     *
     * @test
     */
    public function the_stellar_service_layer_still_references_no_listing_preference(): void
    {
        $hits = trim((string) shell_exec(sprintf(
            'cd %s && grep -rlE %s app/Services/Stellar 2>/dev/null',
            escapeshellarg(base_path()),
            escapeshellarg('ListingPreference'),
        )));

        $this->assertSame(
            '',
            $hits,
            'no Stellar service may reference a listing preference; the card reads it in Blade'
        );
    }

    private function listing(array $attrs = [], ?array $rawJson = null): BridgeProperty
    {
        $p = new BridgeProperty();

        foreach ($attrs as $k => $v) {
            $p->{$k} = $v;
        }

        if ($rawJson !== null) {
            $p->raw_json = json_encode($rawJson);
        }

        return $p;
    }
}
