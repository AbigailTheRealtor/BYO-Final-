<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use Tests\TestCase;

/**
 * Batch 2e — the public FEMA flood-zone question, and the narrow visibility change under it.
 *
 * WHAT MOVED, AND WHAT DID NOT. `flood_zone_code` left SnapshotFactVisibility's
 * RESTRICTED_KEYS and joined SHARED_PUBLIC_KEYS, by owner decision: it is the designation the
 * seller or landlord chose on their own form, and both public listing pages already print it
 * as "Flood Zone Code" — the AI layer was withholding a fact the page beside it published.
 *
 * It is the ONLY member of that group that moved, and the three that stayed are restricted
 * for different reasons from each other: `flood_zone_designation` and
 * `flood_zone_description` are free-text narrative with no controlled vocabulary, and
 * `is_in_flood_zone` is a BOOLEAN — publishing it invites precisely the "not in a flood zone"
 * claim that no stored value here can support. `flood_insurance_required` keeps whatever
 * classification it had; a lender or insurer requirement is a different claim from a map
 * designation.
 *
 * WHAT THE ANSWER MAY NOT SAY is as much the subject of this file as what it does say. Zone X
 * is where the temptation lives, which is why its sentence ends by stating flood risk is not
 * zero, and why the forbidden-phrase assertions run against every code rather than one.
 */
class PublicFloodZoneQuestionBatch2eTest extends TestCase
{
    private AskAiPublicPropertyQuestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiPublicPropertyQuestionService();
    }

    /** The flood answer for a role, or null when the question is hidden. */
    private function answer(string $role, mixed $code): ?string
    {
        foreach ($this->service->forListing($role, ['listing' => ['property_type' => 'Residential', 'flood_zone_code' => $code]], []) as $q) {
            if (str_ends_with($q['id'], '_flood_zone')) {
                return $q['answer'];
            }
        }

        return null;
    }

    /* ================================================================== */
    /* 1. The visibility change, exactly as scoped                         */
    /* ================================================================== */

    public function test_flood_zone_code_is_public_for_seller_and_landlord(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $this->assertSame(
                SnapshotFactVisibility::PUBLIC_ALLOWED,
                SnapshotFactVisibility::classify('flood_zone_code', $role),
                "flood_zone_code must be public for {$role}."
            );
            $this->assertContains('flood_zone_code', SnapshotFactVisibility::publicKeysForRole($role));
        }
    }

    public function test_buyer_and_tenant_still_have_no_public_facts_at_all(): void
    {
        // D2 is untouched: the change went into SHARED_PUBLIC_KEYS, which publicKeysForRole()
        // merges for seller and landlord only and never reaches a criteria role.
        foreach (['buyer', 'tenant'] as $role) {
            $this->assertSame([], SnapshotFactVisibility::publicKeysForRole($role),
                "{$role} must still publish zero facts.");
            $this->assertNotSame(
                SnapshotFactVisibility::PUBLIC_ALLOWED,
                SnapshotFactVisibility::classify('flood_zone_code', $role)
            );
        }
    }

    /**
     * @dataProvider stillRestricted
     */
    public function test_the_other_flood_and_environmental_fields_remain_restricted(string $key): void
    {
        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            $this->assertSame(
                SnapshotFactVisibility::RESTRICTED,
                SnapshotFactVisibility::classify($key, $role),
                "{$key} must remain RESTRICTED for {$role}."
            );
        }
        $this->assertContains($key, SnapshotFactVisibility::restrictedKeys());
    }

    public static function stillRestricted(): array
    {
        return [
            'narrative designation' => ['flood_zone_designation'],
            'narrative description' => ['flood_zone_description'],
            'boolean flag'          => ['is_in_flood_zone'],
        ];
    }

    public function test_flood_insurance_required_is_public_and_never_restricted(): void
    {
        // Batch 2e made no statement about it. The universal coverage audit (2026-09-24) did:
        // both public listing pages print "Flood Insurance Required" to a guest, so Ask AI states
        // it too — as the owner's stated requirement, under that label, never as a claim about
        // flood risk. It is not restricted.
        foreach (['seller', 'landlord'] as $role) {
            $this->assertSame(
                SnapshotFactVisibility::PUBLIC_ALLOWED,
                SnapshotFactVisibility::classify('flood_insurance_required', $role)
            );
        }
        $this->assertNotContains('flood_insurance_required', SnapshotFactVisibility::restrictedKeys());
    }

    public function test_exactly_the_page_printed_flood_fields_are_public(): void
    {
        // Stated as a property of the whole allow-list rather than of one key. The code (Batch
        // 2e) and the three rows both public pages print beside it — panel, determination date
        // and insurance requirement (universal coverage audit, 2026-09-24). The narrative and
        // boolean designations stay restricted: nothing stored can support "not in a flood zone".
        // The seller page prints no determination date; the landlord page does.
        $expected = [
            'seller'   => ['flood_insurance_required', 'flood_zone_code', 'flood_zone_panel'],
            'landlord' => ['flood_insurance_required', 'flood_zone_code', 'flood_zone_date', 'flood_zone_panel'],
        ];
        foreach (['seller', 'landlord'] as $role) {
            $public = SnapshotFactVisibility::publicKeysForRole($role);
            $flood  = array_values(array_filter($public, static fn ($k) => str_contains($k, 'flood')));
            sort($flood);

            $this->assertSame($expected[$role], $flood,
                "{$role} must publish exactly the flood fields its page prints.");

            foreach (['is_in_flood_zone', 'flood_zone_designation', 'flood_zone_description'] as $key) {
                $this->assertNotContains($key, $public, "{$key} must not be public for {$role}.");
            }
        }
    }

    /* ================================================================== */
    /* 2. Availability                                                     */
    /* ================================================================== */

    /**
     * @dataProvider validCodes
     */
    public function test_a_valid_designation_shows_the_question_for_both_property_roles(string $code): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $this->assertNotNull($this->answer($role, $code), "{$role} / {$code} must show the question.");
        }
    }

    public static function validCodes(): array
    {
        return [
            'X' => ['X'], 'AE' => ['AE'], 'VE' => ['VE'],
            'A' => ['A'], 'AH' => ['AH'], 'AO' => ['AO'], 'V' => ['V'], 'D' => ['D'],
            'A99 (other valid)' => ['A99'],
            'lowercase ae'      => ['ae'],
            'padded'            => ['  ve  '],
        ];
    }

    /**
     * @dataProvider invalidValues
     */
    public function test_an_unusable_value_hides_the_question(mixed $value): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $this->assertNull($this->answer($role, $value),
                "{$role} must hide the question for an unusable value.");
        }
    }

    public static function invalidValues(): array
    {
        return [
            'blank'           => [''],
            'whitespace'      => ['   '],
            'yes'             => ['yes'],
            'Yes'             => ['Yes'],
            'no'              => ['no'],
            'unknown'         => ['unknown'],
            'Unknown'         => ['Unknown'],
            'other'           => ['Other'],
            'flood'           => ['Flood'],
            'n/a'             => ['N/A'],
            'na'              => ['NA'],
            'none'            => ['None'],
            'tbd'             => ['TBD'],
            'prose'           => ['Zone AE'],
            'annotated'       => ['AE - high risk'],
            'sentence'        => ['This property is in a flood zone'],
            'true'            => [true],
            'false'           => [false],
            'numeric one'     => ['1'],
            'numeric zero'    => ['0'],
            'numeric int'     => [1],
        ];
    }

    public function test_buyer_and_tenant_never_get_the_flood_question(): void
    {
        foreach (['buyer', 'tenant'] as $role) {
            foreach (['X', 'AE', 'VE'] as $code) {
                foreach ($this->service->forListing($role, ['listing' => ['property_type' => 'Residential', 'flood_zone_code' => $code]], []) as $q) {
                    $this->assertStringNotContainsString('flood', $q['id']);
                    $this->assertStringNotContainsStringIgnoringCase('flood', $q['answer']);
                }
            }
        }

        // And no criteria catalog entry names the field at all.
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            if (in_array($entry['role'] ?? '', ['buyer', 'tenant'], true)) {
                $this->assertStringNotContainsString('flood', (string) ($entry['source_path'] ?? ''), $id);
            }
        }
    }

    /* ================================================================== */
    /* 3. Exact wording                                                    */
    /* ================================================================== */

    public function test_zone_x_states_lower_risk_without_claiming_no_risk(): void
    {
        $expected = 'This property is in FEMA Flood Zone X, which is generally outside the '
                  . 'Special Flood Hazard Area and considered lower flood risk. Flood risk is not zero.';

        foreach (['seller', 'landlord'] as $role) {
            $this->assertSame($expected, $this->answer($role, 'X'));
        }
    }

    public function test_zone_ae_states_the_special_flood_hazard_area(): void
    {
        $expected = 'This property is in FEMA Flood Zone AE, which is within a Special Flood Hazard Area.';

        foreach (['seller', 'landlord'] as $role) {
            $this->assertSame($expected, $this->answer($role, 'AE'));
        }
    }

    public function test_zone_ve_states_the_coastal_high_hazard_area(): void
    {
        $expected = 'This property is in FEMA Flood Zone VE, a coastal high-hazard Special Flood Hazard Area.';

        foreach (['seller', 'landlord'] as $role) {
            $this->assertSame($expected, $this->answer($role, 'VE'));
        }
    }

    public function test_any_other_valid_code_gets_the_bare_designation_with_no_risk_gloss(): void
    {
        // A, AH, AO, V, D and AR each carry their own meaning, and one generic risk sentence
        // would be wrong for at least one of them. So none is offered.
        $this->assertSame('This property is in FEMA Flood Zone A.', $this->answer('seller', 'A'));
        $this->assertSame('This property is in FEMA Flood Zone AH.', $this->answer('seller', 'AH'));
        $this->assertSame('This property is in FEMA Flood Zone AO.', $this->answer('landlord', 'AO'));
        $this->assertSame('This property is in FEMA Flood Zone D.', $this->answer('landlord', 'D'));
        $this->assertSame('This property is in FEMA Flood Zone A99.', $this->answer('seller', 'a99'));
    }

    public function test_no_answer_ever_makes_a_risk_or_insurance_claim(): void
    {
        $forbidden = [
            'not in a flood zone', 'not in a flood area', 'no flood risk', 'cannot flood',
            'flood insurance is not required', 'insurance is not required', 'safe from flooding',
            'lender', 'base flood elevation', 'you will not need', 'does not require',
        ];

        foreach (['seller', 'landlord'] as $role) {
            foreach (['X', 'AE', 'VE', 'A', 'AH', 'AO', 'V', 'D', 'A99'] as $code) {
                $answer = $this->answer($role, $code);
                $this->assertNotNull($answer);
                foreach ($forbidden as $phrase) {
                    $this->assertStringNotContainsStringIgnoringCase($phrase, $answer,
                        "{$role}/{$code} said '{$phrase}'.");
                }
            }
        }
    }

    /* ================================================================== */
    /* 4. The source boundary                                              */
    /* ================================================================== */

    public function test_the_question_reads_the_canonical_code_and_nothing_else(): void
    {
        foreach (['seller' => 'seller_flood_zone', 'landlord' => 'landlord_flood_zone'] as $role => $id) {
            $entry = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()[$id];

            $this->assertSame('listing.flood_zone_code', $entry['source_path']);
            $this->assertSame([], $entry['supporting_paths']);
            $this->assertSame('listing', $entry['source_kind']);
            $this->assertArrayNotHasKey('other_companion', $entry);
            $this->assertArrayNotHasKey('other_companions', $entry);
            $this->assertSame($role, $entry['role']);
        }
    }

    /**
     * @dataProvider unreadableFloodSources
     */
    public function test_no_other_flood_source_can_answer_the_question(string $key): void
    {
        // Even handed a perfect designation, a restricted or owner-only flood field resolves
        // to nothing — the guarantee holds against the classification, not against the
        // catalog entry happening to name the right key today.
        $entry = [
            'role' => 'seller', 'question' => 'probe', 'source_kind' => 'listing',
            'source_path' => 'listing.' . $key, 'supporting_paths' => [],
            'formatter' => 'flood_zone', 'guards' => [],
        ];
        $result = $this->service->evaluate($entry, 'seller', ['listing' => ['property_type' => 'Residential', $key => 'AE']], []);

        $this->assertFalse($result['available'], "{$key} answered a public question.");
    }

    public static function unreadableFloodSources(): array
    {
        // flood_insurance_required and flood_zone_panel became public seller facts in the
        // universal coverage audit (2026-09-24) — each answers its OWN generated question under
        // its own label, never this designation question. flood_zone_date stays owner_only for
        // the seller (the seller page prints no date), so it still proves the guarantee.
        return array_map(static fn ($k) => [$k], [
            'flood_zone_designation', 'flood_zone_description', 'is_in_flood_zone',
            'flood_zone_date',
        ]);
    }

    public function test_the_surface_still_has_no_path_to_a_model_or_the_network(): void
    {
        // The formatter is a match over a normalised code; the class it calls has no imports,
        // no container access and no I/O.
        $source = file_get_contents((new \ReflectionClass(\App\Support\Listing\FloodZoneCode::class))->getFileName());

        foreach (['openai', 'anthropic', 'Http::', 'Guzzle', 'curl_', 'DB::', 'file_get_contents', 'app('] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $source, "FloodZoneCode contains '{$forbidden}'.");
        }
    }
}
