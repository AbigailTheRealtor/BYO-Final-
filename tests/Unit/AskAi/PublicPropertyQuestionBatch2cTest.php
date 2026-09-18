<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use Tests\TestCase;

/**
 * Batch 2c — deterministic composite questions: "HOA fees & what do they cover?" with its
 * narrower fallback "What are the HOA fees?", the seller CDD question, structured pet-policy
 * limits on a "Yes" pet answer, and the taxes amount / tax-year pattern.
 *
 * Every answer is a fixed sentence from declared structured sources; a composite needs every
 * required part, and a narrower entry replaces it — never renders beside it — when it cannot.
 */
class PublicPropertyQuestionBatch2cTest extends TestCase
{
    private AskAiPublicPropertyQuestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiPublicPropertyQuestionService();
    }

    /** @return array<string,string> id => answer, for the whole role catalog */
    private function answers(string $role, array $listing, array $meta = []): array
    {
        $out = [];
        foreach ($this->service->forListing($role, ['listing' => $listing + ['property_type' => 'Residential'], 'faq_answers' => []], $meta) as $q) {
            $out[$q['id']] = $q['answer'];
        }

        return $out;
    }

    private function sellerHoa(array $listing = [], array $meta = []): array
    {
        return $this->answers(
            'seller',
            array_merge(['hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Monthly', 'association_fee_includes' => 'x'], $listing),
            array_merge(['association_fee_frequency' => 'Monthly'], $meta)
        );
    }

    // =========================================================================
    // HOA fee + coverage composite
    // =========================================================================

    public function test_1_fee_frequency_and_several_includes_give_the_full_composite(): void
    {
        $answers = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Water', 'Sewer', 'Trash', 'Insurance'])]);

        $this->assertSame('The HOA fee is $250 per month and includes water, sewer, trash and insurance.', $answers['seller_hoa_fee_coverage']);
    }

    public function test_2_fee_frequency_and_one_include_give_the_full_composite(): void
    {
        $answers = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Cable TV'])]);

        $this->assertSame('The HOA fee is $250 per month and includes cable TV.', $answers['seller_hoa_fee_coverage']);

        $two = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Common Area Maintenance', 'Pest Control'])]);
        $this->assertSame('The HOA fee is $250 per month and includes common area maintenance and pest control.', $two['seller_hoa_fee_coverage']);
    }

    public function test_3_missing_or_unusable_frequency_never_guesses_a_period(): void
    {
        foreach ([null, '', 'Bi-Monthly', 'Other'] as $frequency) {
            $answers = $this->sellerHoa(['hoa_payment_schedule' => $frequency], ['association_fee_frequency' => $frequency, 'association_fee_includes' => json_encode(['Water'])]);
            $this->assertSame('The HOA fee is $250 and includes water.', $answers['seller_hoa_fee_coverage'], json_encode($frequency));
        }

        $normalised = $this->sellerHoa(['hoa_payment_schedule' => 'semi_annually'], ['association_fee_frequency' => 'semi_annually', 'association_fee_includes' => json_encode(['Water'])]);
        $this->assertSame('The HOA fee is $250 every six months and includes water.', $normalised['seller_hoa_fee_coverage']);

        $oneTime = $this->sellerHoa(['hoa_payment_schedule' => 'One-Time'], ['association_fee_frequency' => 'One-Time', 'association_fee_includes' => json_encode(['Water'])]);
        $this->assertSame('The HOA fee is a one-time fee of $250 and includes water.', $oneTime['seller_hoa_fee_coverage']);

        $other = $this->sellerHoa(['hoa_payment_schedule' => 'Other'], ['association_fee_frequency' => 'Other', 'association_fee_frequency_other' => 'Twice a year', 'association_fee_includes' => json_encode(['Water'])]);
        $this->assertSame('The HOA fee is $250 (frequency: Twice a year) and includes water.', $other['seller_hoa_fee_coverage']);
    }

    public function test_4_fee_only_gives_the_narrower_fee_question(): void
    {
        $answers = $this->sellerHoa(['association_fee_includes' => null], []);

        $this->assertArrayNotHasKey('seller_hoa_fee_coverage', $answers);
        $this->assertSame('The HOA fee is $250 per month.', $answers['seller_hoa_fee']);
    }

    public function test_5_fee_with_empty_coverage_gives_the_narrower_fee_question(): void
    {
        foreach (['[]', json_encode(['Other']), json_encode(['None']), ''] as $includes) {
            $answers = $this->sellerHoa([], ['association_fee_includes' => $includes]);

            $this->assertArrayNotHasKey('seller_hoa_fee_coverage', $answers, $includes);
            $this->assertSame('The HOA fee is $250 per month.', $answers['seller_hoa_fee'], $includes);
        }
    }

    public function test_6_coverage_without_a_valid_fee_hides_the_composite_and_invents_no_fee(): void
    {
        foreach ([null, '', 'TBD', '0', '-250'] as $fee) {
            $answers = $this->sellerHoa(['hoa_fee' => $fee], ['association_fee_includes' => json_encode(['Water', 'Trash'])]);

            $this->assertArrayNotHasKey('seller_hoa_fee_coverage', $answers, json_encode($fee));
            $this->assertArrayNotHasKey('seller_hoa_fee', $answers, json_encode($fee));
        }
    }

    public function test_7_no_hoa_hides_every_hoa_question(): void
    {
        foreach (['No', 'Unknown', null] as $hasHoa) {
            $answers = $this->answers(
                'seller',
                ['hoa_association' => $hasHoa, 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Monthly', 'association_fee_includes' => 'Water', 'rental_restrictions' => 'Yes'],
                ['association_fee_frequency' => 'Monthly', 'association_fee_includes' => json_encode(['Water'])]
            );
            foreach (['seller_hoa_fee', 'seller_hoa_fee_coverage', 'seller_leasing_restrictions'] as $id) {
                $this->assertArrayNotHasKey($id, $answers, "{$id} with has_hoa " . json_encode($hasHoa));
            }

            $landlord = $this->answers(
                'landlord',
                ['has_hoa' => $hasHoa, 'association_fee_amount' => '175', 'association_fee_frequency' => 'Monthly', 'association_fee_includes' => 'Water', 'association_amenities' => 'Pool'],
                ['association_fee_frequency' => 'Monthly', 'association_fee_includes' => json_encode(['Water']), 'association_amenities' => json_encode(['Pool'])]
            );
            foreach (['landlord_hoa_fee', 'landlord_hoa_fee_coverage', 'landlord_association_amenities'] as $id) {
                $this->assertArrayNotHasKey($id, $landlord, "{$id} with has_hoa " . json_encode($hasHoa));
            }
        }
    }

    public function test_8_other_include_with_meaningful_text_is_included_as_written(): void
    {
        $answers = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Water', 'Other']), 'association_fee_includes_other' => 'Valet Trash']);

        $this->assertSame('The HOA fee is $250 per month and includes water and Valet Trash.', $answers['seller_hoa_fee_coverage']);
    }

    public function test_9_stale_other_text_is_ignored_when_other_is_not_selected(): void
    {
        $answers = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Water']), 'association_fee_includes_other' => 'Golf membership']);

        $this->assertSame('The HOA fee is $250 per month and includes water.', $answers['seller_hoa_fee_coverage']);
        $this->assertStringNotContainsString('Golf', implode(' ', $answers));
    }

    public function test_10_invalid_other_text_fails_closed(): void
    {
        // Blank / placeholder "Other" text: dropped; with nothing else covered the composite
        // is unavailable and the narrower fee question answers.
        $placeholder = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Other']), 'association_fee_includes_other' => 'N/A']);
        $this->assertArrayNotHasKey('seller_hoa_fee_coverage', $placeholder);
        $this->assertSame('The HOA fee is $250 per month.', $placeholder['seller_hoa_fee']);

        // Unsafe "Other" text (too long, multi-line): the composite is hidden — never partially
        // stated — and the narrower fee question answers.
        foreach ([str_repeat('x', 61), "Water\nand power"] as $unsafe) {
            $answers = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Water', 'Other']), 'association_fee_includes_other' => $unsafe]);
            $this->assertArrayNotHasKey('seller_hoa_fee_coverage', $answers, json_encode($unsafe));
            $this->assertSame('The HOA fee is $250 per month.', $answers['seller_hoa_fee'], json_encode($unsafe));
        }
    }

    public function test_11_the_full_composite_suppresses_its_narrower_duplicate(): void
    {
        $answers = $this->sellerHoa([], ['association_fee_includes' => json_encode(['Water'])]);

        $this->assertArrayHasKey('seller_hoa_fee_coverage', $answers);
        $this->assertArrayNotHasKey('seller_hoa_fee', $answers);

        // The narrower entry is still evaluable on its own — it is the fallback, not removed.
        $entry  = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()['seller_hoa_fee'];
        $result = $this->service->evaluate($entry, 'seller', ['listing' => ['property_type' => 'Residential', 'hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Monthly']], ['association_fee_frequency' => 'Monthly']);
        $this->assertTrue($result['available']);

        // The composite takes the narrower question's place in the display order.
        $ids = array_column($this->service->forListing('seller', ['listing' => ['property_type' => 'Residential', 
            'hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Monthly', 'association_fee_includes' => 'Water',
            'year_built' => '1998', 'total_acreage' => '1/4 to less than 1/2 acre',
        ]], ['association_fee_frequency' => 'Monthly', 'association_fee_includes' => json_encode(['Water'])]), 'id');
        // The listing is in an HOA, so the association question answers too — after acreage.
        $this->assertSame(['seller_year_built', 'seller_hoa_fee_coverage', 'seller_total_acreage', 'seller_association_details'], $ids);
    }

    public function test_12_landlord_composite_works_through_its_new_context_key(): void
    {
        $this->assertArrayHasKey('association_fee_includes', AskAiContextBuilderService::CANONICAL_SOURCE_MAP['landlord']);
        $this->assertSame('association_fee_includes', AskAiContextBuilderService::CANONICAL_SOURCE_MAP['landlord']['association_fee_includes']);
        $this->assertSame(SnapshotFactVisibility::PUBLIC_ALLOWED, SnapshotFactVisibility::classify('association_fee_includes', 'landlord'));

        $answers = $this->answers(
            'landlord',
            ['has_hoa' => 'Yes', 'association_fee_amount' => '175', 'association_fee_frequency' => 'Quarterly', 'association_fee_includes' => 'Water, Grounds Maintenance'],
            ['association_fee_frequency' => 'Quarterly', 'association_fee_includes' => json_encode(['Water', 'Grounds Maintenance'])]
        );
        $this->assertSame('The HOA fee is $175 per quarter and includes water and grounds maintenance.', $answers['landlord_hoa_fee_coverage']);
        $this->assertArrayNotHasKey('landlord_hoa_fee', $answers);

        $narrow = $this->answers('landlord', ['has_hoa' => 'Yes', 'association_fee_amount' => '175', 'association_fee_frequency' => 'Quarterly'], ['association_fee_frequency' => 'Quarterly']);
        $this->assertSame('The HOA fee is $175 per quarter.', $narrow['landlord_hoa_fee']);
        $this->assertArrayNotHasKey('landlord_hoa_fee_coverage', $narrow);
    }

    // =========================================================================
    // CDD
    // =========================================================================

    private function cdd(array $listing): ?string
    {
        return $this->answers('seller', $listing)['seller_cdd_fee'] ?? null;
    }

    public function test_13_no_cdd_is_restated_as_nothing_listed(): void
    {
        $this->assertSame('There is no CDD fee listed for this property.', $this->cdd(['has_cdd' => 'No']));
        $this->assertSame('There is no CDD fee listed for this property.', $this->cdd(['has_cdd' => 'no', 'annual_cdd_fee' => '1200']));
    }

    public function test_14_cdd_yes_with_an_amount_states_the_annual_fee(): void
    {
        $this->assertSame('The annual CDD fee is $1,200.', $this->cdd(['has_cdd' => 'Yes', 'annual_cdd_fee' => '1,200']));
        $this->assertSame('The annual CDD fee is $1,845.50.', $this->cdd(['has_cdd' => 'Yes', 'annual_cdd_fee' => '1845.5']));
    }

    public function test_15_cdd_yes_without_a_valid_amount_gives_the_safe_partial_answer(): void
    {
        foreach ([null, '', 'varies', '0', '-100'] as $fee) {
            $this->assertSame('This property is listed as having a CDD.', $this->cdd(['has_cdd' => 'Yes', 'annual_cdd_fee' => $fee]), json_encode($fee));
        }
    }

    public function test_16_blank_or_unknown_cdd_hides_the_question(): void
    {
        foreach (['Unknown', '', null, 'Maybe', 'N/A'] as $hasCdd) {
            $this->assertNull($this->cdd(['has_cdd' => $hasCdd, 'annual_cdd_fee' => '1200']), json_encode($hasCdd));
        }
        // Landlord has_cdd is owner_only: no landlord CDD question exists.
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            if ($entry['role'] === 'landlord') {
                $this->assertStringNotContainsString('cdd', $entry['source_path'] . implode(' ', $entry['supporting_paths']), $id);
            }
        }
    }

    // =========================================================================
    // Pets
    // =========================================================================

    private function sellerPets(array $listing): ?string
    {
        return $this->answers('seller', $listing)['seller_pets_allowed'] ?? null;
    }

    public function test_17_yes_with_structured_limits_adds_deterministic_detail(): void
    {
        $this->assertSame('Pets are allowed at this property. Up to 2 pets are permitted.', $this->sellerPets(['pets_allowed' => 'Yes', 'number_of_pets_allowed' => '2']));
        $this->assertSame('Pets are allowed at this property. Up to 1 pet is permitted.', $this->sellerPets(['pets_allowed' => 'Yes', 'number_of_pets_allowed' => '1']));
        $this->assertSame(
            'Pets are allowed at this property. Up to 2 pets are permitted. The maximum weight per pet is 50 lbs.',
            $this->sellerPets(['pets_allowed' => 'Yes', 'number_of_pets_allowed' => ' 2 ', 'max_pet_weight' => '50 lbs'])
        );
        // Ambiguous detail is left out; the simple answer stands.
        foreach (['2-3', 'two', 'no limit', '0', '2 dogs'] as $count) {
            $this->assertSame('Pets are allowed at this property.', $this->sellerPets(['pets_allowed' => 'Yes', 'number_of_pets_allowed' => $count]), $count);
        }
        foreach (['about 40', '40-60', 'small', '0'] as $weight) {
            $this->assertSame('Pets are allowed at this property.', $this->sellerPets(['pets_allowed' => 'Yes', 'max_pet_weight' => $weight]), $weight);
        }
    }

    public function test_18_no_keeps_the_assistance_animal_safe_wording_unchanged(): void
    {
        $expected = "Pets are not allowed under the property's pet policy. Assistance animals are handled separately under applicable law.";

        $this->assertSame($expected, $this->sellerPets(['pets_allowed' => 'No', 'number_of_pets_allowed' => '2', 'max_pet_weight' => '50']));
        $this->assertSame($expected, $this->answers('landlord', ['pet_policy' => 'No'])['landlord_pets_allowed']);
        $this->assertSame('Pets are allowed at this property.', $this->answers('landlord', ['pet_policy' => 'Yes'])['landlord_pets_allowed']);
    }

    public function test_19_service_and_support_animal_data_can_never_enter_the_answer(): void
    {
        $listing = [
            'pets_allowed'              => 'Yes',
            'number_of_pets_allowed'    => '2',
            'service_animal'            => 'SENTINEL-SERVICE-ANIMAL',
            'support_animal'            => 'SENTINEL-SUPPORT-ANIMAL',
            'emotional_support_animal'  => 'SENTINEL-ESA',
            'accessibility_requirements' => 'SENTINEL-ACCESSIBILITY',
            'pet_restrictions'          => 'SENTINEL-RESTRICTION-TEXT',
            'type_of_pets'              => 'SENTINEL-TYPES',
        ];
        $meta = [
            'service_animal' => 'SENTINEL-SERVICE-ANIMAL', 'support_animal' => 'SENTINEL-SUPPORT-ANIMAL',
            'breed_of_pets'  => 'SENTINEL-BREED', 'breed_restrictions' => 'SENTINEL-BREED-RESTRICTION',
        ];

        foreach ($this->service->forListing('seller', ['listing' => $listing + ['property_type' => 'Residential'], 'faq_answers' => []], $meta) as $q) {
            $this->assertStringNotContainsString('SENTINEL', $q['answer'], $q['id']);
        }

        $entry = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()['seller_pets_allowed'];
        $this->assertSame(['listing.number_of_pets_allowed', 'listing.max_pet_weight'], $entry['supporting_paths']);
    }

    public function test_20_tenant_or_applicant_pet_data_can_never_enter_a_property_answer(): void
    {
        $tenantShaped = [
            'pets_allowed'    => 'Yes',
            'pets_detail'     => 'SENTINEL-TENANT-PETS',
            'pets_breed'      => 'SENTINEL-TENANT-BREED',
            'pets_weight'     => '80',
            'pet_information' => 'SENTINEL-PET-INFORMATION',
        ];

        // Batch 2d: a criteria role DOES answer now, and the guarantee is sharper for it.
        // The tenant pets question states the housing requirement from `pets_allowed` and
        // nothing else — no applicant pet detail, breed or weight, and no assistance animal.
        foreach (['tenant', 'buyer'] as $criteriaRole) {
            foreach ($this->service->forListing($criteriaRole, ['listing' => $tenantShaped + ['property_type' => 'Residential']], []) as $q) {
                $this->assertStringNotContainsString('SENTINEL', $q['answer'], $q['id']);
                $this->assertStringNotContainsString('80', $q['answer'], $q['id']);
                $this->assertStringNotContainsStringIgnoringCase('animal', $q['answer'], $q['id']);
            }
        }

        $seller = $this->answers('seller', $tenantShaped);
        $this->assertSame('Pets are allowed at this property.', $seller['seller_pets_allowed']);
        $this->assertStringNotContainsString('80', $seller['seller_pets_allowed']);

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $read = implode(' ', array_merge([$entry['source_path']], $entry['supporting_paths']));
            foreach (['pets_detail', 'pets_breed', 'pets_weight', 'pet_information', 'service_animal', 'support_animal'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $read, "{$id} must not read {$forbidden}.");
            }
        }
    }

    // =========================================================================
    // Taxes
    // =========================================================================

    public function test_21_to_23_taxes_follow_the_amount_and_optional_year_pattern(): void
    {
        foreach (['seller' => 'seller_property_taxes', 'landlord' => 'landlord_property_taxes'] as $role => $id) {
            $this->assertSame('Annual property taxes are $1,856 for tax year 2025.', $this->answers($role, ['annual_property_taxes' => '1856', 'tax_year' => '2025'])[$id]);
            $this->assertSame('Annual property taxes are $1,856.', $this->answers($role, ['annual_property_taxes' => '1856'])[$id]);
            $this->assertArrayNotHasKey($id, $this->answers($role, ['tax_year' => '2025']));
            $this->assertArrayNotHasKey($id, $this->answers($role, ['annual_property_taxes' => '', 'tax_year' => '2025']));
        }

        // One tax question per role — no duplicate composite was created.
        $taxEntries = array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry(),
            fn (array $e) => str_contains($e['source_path'], 'annual_property_taxes')
        );
        $this->assertSame(['seller_property_taxes', 'landlord_property_taxes'], array_keys($taxEntries));
    }

    // =========================================================================
    // Privacy and catalog integrity
    // =========================================================================

    public function test_24_seller_minimums_are_absent_from_every_composite(): void
    {
        $listing = [
            'hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Monthly', 'association_fee_includes' => 'Water',
            'has_cdd' => 'Yes', 'annual_cdd_fee' => '1200', 'pets_allowed' => 'Yes', 'number_of_pets_allowed' => '2',
            'minimum_cap_rate' => '7.25', 'minimum_annual_net_income' => '987654',
        ];
        $meta = ['association_fee_frequency' => 'Monthly', 'association_fee_includes' => json_encode(['Water']), 'minimum_cap_rate' => '7.25'];

        foreach ($this->answers('seller', $listing, $meta) as $id => $answer) {
            foreach (['7.25', '987654', '987,654'] as $leak) {
                $this->assertStringNotContainsString($leak, $answer, $id);
            }
        }
    }

    public function test_25_knowledge_base_answers_are_still_not_a_public_source(): void
    {
        $context = ['listing' => ['property_type' => 'Residential', 'hoa_association' => 'Yes', 'hoa_fee' => '250'], 'faq_answers' => [
            'hoa_community_highlights' => ['answer_text' => 'SENTINEL-KB-HOA'],
            'roof_age_and_condition'   => ['answer_text' => 'SENTINEL-KB-ROOF'],
        ]];
        $meta = ['listing_ai_faq' => json_encode(['hoa_community_highlights' => 'SENTINEL-KB-HOA'])];

        foreach ($this->service->forListing('seller', $context, $meta) as $q) {
            $this->assertStringNotContainsString('SENTINEL-KB', $q['answer'], $q['id']);
            $this->assertStringStartsWith('listing.', $q['source_path']);
        }

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $declared = json_encode([$entry['source_path'], $entry['supporting_paths'], $entry['other_companion'] ?? null, $entry['other_companions'] ?? null]);
            $this->assertStringNotContainsString('faq', $declared, $id);
            $this->assertStringNotContainsString('listing_ai_faq', $declared, $id);
        }
    }

    /**
     * SUPERSEDED AND REPLACED by Batch 2d.
     *
     * Batch 2c's rule was "the criteria roles are excluded from this surface entirely".
     * They have their own approved surface now, so what is asserted instead is the property
     * half that never changed: a PROPERTY composite — the HOA and CDD questions this batch
     * added — is not reachable from a criteria role, whatever the listing happens to carry.
     */
    public function test_26_property_composites_stay_out_of_the_criteria_roles(): void
    {
        $criteria = ['listing' => ['property_type' => 'Residential', 
            'hoa_association' => 'Yes', 'hoa_fee' => '250', 'association_fee_includes' => 'Water', 'has_cdd' => 'Yes',
            'max_price' => '450000', 'max_hoa_fee' => '300', 'pets_allowed' => 'Yes',
        ]];
        $meta = ['association_fee_includes' => json_encode(['Water'])];

        // An alias role still answers nothing at all.
        foreach (['buyer_agent_auction', 'tenant_criteria_auction'] as $role) {
            $this->assertSame([], $this->service->forListing($role, $criteria, $meta), $role);
        }

        // For the real criteria roles, no HOA / CDD / fee question appears — none of those
        // keys is in either criteria catalog, so none can resolve.
        foreach (['buyer', 'tenant'] as $role) {
            foreach ($this->service->forListing($role, $criteria, $meta) as $q) {
                foreach (['hoa', 'cdd', 'association', 'fee'] as $forbidden) {
                    $this->assertStringNotContainsStringIgnoringCase($forbidden, $q['source_path'], "{$q['id']} read a property fee source.");
                    $this->assertStringNotContainsStringIgnoringCase($forbidden, $q['answer'], "{$q['id']} published a property fee.");
                }
            }
        }

        // And every composite added by this batch belongs to a property role.
        foreach (['seller_hoa_fee_coverage', 'landlord_hoa_fee_coverage', 'seller_cdd_fee'] as $id) {
            $entry = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()[$id] ?? null;
            $this->assertNotNull($entry, $id);
            $this->assertContains($entry['role'], ['seller', 'landlord'], $id);
        }
    }

    public function test_narrower_of_references_are_well_formed(): void
    {
        $catalog = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry();

        $pairs = [];
        foreach ($catalog as $id => $entry) {
            if (!isset($entry['narrower_of'])) {
                continue;
            }
            $richer = $entry['narrower_of'];
            $this->assertArrayHasKey($richer, $catalog, "{$id}: narrower_of '{$richer}' must exist.");
            $this->assertSame($entry['role'], $catalog[$richer]['role'], "{$id}: narrower_of must be the same role.");
            $this->assertArrayNotHasKey('narrower_of', $catalog[$richer], "{$richer}: a composite declares no narrower_of (one level only).");
            $this->assertNotSame($id, $richer);
            $pairs[$id] = $richer;
        }

        // Pinned so a narrower/richer pair cannot be added or lost unnoticed. The four
        // Batch 2d entries are the criteria surface's own fallbacks: an areas question that
        // names only counties when no city is listed, and a features question that carries
        // the remaining structured lists when the richest source is empty.
        $this->assertSame([
            'seller_hoa_fee'                => 'seller_hoa_fee_coverage',
            // Universal-deterministic batches: the garage answer folds into parking, and the
            // acreage band stays the lead where a lot-size composite would say less.
            'seller_garage'                 => 'seller_parking',
            'landlord_hoa_fee'              => 'landlord_hoa_fee_coverage',
            'buyer_search_areas_counties'   => 'buyer_search_areas',
            'buyer_view_preference'         => 'buyer_property_features',
            'tenant_search_areas_counties'  => 'tenant_search_areas',
            'tenant_appliances'             => 'tenant_property_features',
            'seller_lot_size'               => 'seller_total_acreage',
            // Landlord: renewal and pet fee are asked on both forms; each yields to the
            // composite that also states it wherever that composite renders.
            'landlord_renewal_option'       => 'landlord_lease_terms',
            'landlord_pet_fee'              => 'landlord_pets_allowed',
        ], $pairs);
    }

    public function test_a_narrower_of_naming_nothing_suppresses_nothing(): void
    {
        $service = new class extends AskAiPublicPropertyQuestionService {};
        $entry   = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()['seller_hoa_fee'];
        $entry['narrower_of'] = 'does_not_exist';

        // Evaluated alone, the narrower entry is unaffected by its narrower_of.
        $result = $service->evaluate($entry, 'seller', ['listing' => ['property_type' => 'Residential', 'hoa_association' => 'Yes', 'hoa_fee' => '250']], []);
        $this->assertSame('The HOA fee is $250.', $result['answer']);
    }

    public function test_declaring_both_companion_forms_is_malformed(): void
    {
        $entry = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()['seller_hoa_fee_coverage'];
        $entry['other_companion'] = ['selected_in' => 'association_fee_includes', 'meta_key' => 'association_fee_includes_other'];

        $result = $this->service->evaluate($entry, 'seller', ['listing' => ['property_type' => 'Residential', 'hoa_association' => 'Yes', 'hoa_fee' => '250', 'association_fee_includes' => 'Water']], ['association_fee_includes' => '["Water"]']);
        $this->assertSame('other_companion_invalid', $result['reason']);
    }
}
