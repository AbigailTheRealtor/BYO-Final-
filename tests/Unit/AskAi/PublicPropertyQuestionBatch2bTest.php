<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use Tests\TestCase;

/**
 * Batch 2b — structured question expansion and role parity for "Questions About This
 * Property": landlord year built / taxes / HOA fee, seller pets / pool / garage / zoning /
 * roof type / leasing restrictions / offered financing, landlord zoning / roof type /
 * leasing restrictions / community amenities, HOA frequency normalisation, and the
 * "Other" companion rules.
 *
 * Every answer is a fixed sentence from structured values. Nothing here reaches a model:
 * the service has no constructor dependencies, and the listing-page feature test proves
 * rendering calls no AI service.
 */
class PublicPropertyQuestionBatch2bTest extends TestCase
{
    private AskAiPublicPropertyQuestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiPublicPropertyQuestionService();
    }

    private function entry(string $id): array
    {
        return AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()[$id];
    }

    /** @return string|null the answer, or null when hidden */
    private function answer(string $id, array $listing, array $meta = []): ?string
    {
        $entry = $this->entry($id);

        return $this->service->evaluate($entry, $entry['role'], ['listing' => $listing, 'faq_answers' => []], $meta)['answer'];
    }

    private function reason(string $id, array $listing, array $meta = []): string
    {
        $entry = $this->entry($id);

        return $this->service->evaluate($entry, $entry['role'], ['listing' => $listing, 'faq_answers' => []], $meta)['reason'];
    }

    // =========================================================================
    // Landlord parity
    // =========================================================================

    public function test_landlord_year_built(): void
    {
        $this->assertSame('This property was built in 2004.', $this->answer('landlord_year_built', ['year_built' => '2004']));
        $this->assertNull($this->answer('landlord_year_built', ['year_built' => '04']));
        $this->assertNull($this->answer('landlord_year_built', ['year_built' => '']));
        $this->assertNull($this->answer('landlord_year_built', []));
    }

    public function test_landlord_property_taxes_with_and_without_tax_year(): void
    {
        $this->assertSame(
            'Annual property taxes are $3,120 for tax year 2024.',
            $this->answer('landlord_property_taxes', ['annual_property_taxes' => '3,120', 'tax_year' => '2024'])
        );
        // Narrower wording when the year is absent or not a year.
        $this->assertSame('Annual property taxes are $3,120.', $this->answer('landlord_property_taxes', ['annual_property_taxes' => '3120']));
        $this->assertSame('Annual property taxes are $3,120.', $this->answer('landlord_property_taxes', ['annual_property_taxes' => '3120', 'tax_year' => 'recent']));
        $this->assertNull($this->answer('landlord_property_taxes', ['annual_property_taxes' => 'varies', 'tax_year' => '2024']));
        $this->assertNull($this->answer('landlord_property_taxes', ['tax_year' => '2024']));
    }

    public function test_landlord_hoa_fee_with_normalised_frequency(): void
    {
        $base = ['has_hoa' => 'Yes', 'association_fee_amount' => '175'];

        $this->assertSame('The HOA fee is $175 per month.', $this->answer('landlord_hoa_fee', $base + ['association_fee_frequency' => 'Monthly']));
        $this->assertSame('The HOA fee is $175 per quarter.', $this->answer('landlord_hoa_fee', $base + ['association_fee_frequency' => 'quarterly']));
        $this->assertSame('The HOA fee is $175 every six months.', $this->answer('landlord_hoa_fee', $base + ['association_fee_frequency' => 'semi_annually']));
        $this->assertSame('The HOA fee is a one-time fee of $175.', $this->answer('landlord_hoa_fee', $base + ['association_fee_frequency' => 'One-Time']));
    }

    public function test_landlord_hoa_fee_hidden_or_amount_only_when_it_cannot_be_stated(): void
    {
        // No HOA / stale child amount → hidden.
        $this->assertNull($this->answer('landlord_hoa_fee', ['has_hoa' => 'No', 'association_fee_amount' => '175', 'association_fee_frequency' => 'Monthly']));
        $this->assertNull($this->answer('landlord_hoa_fee', ['association_fee_amount' => '175', 'association_fee_frequency' => 'Monthly']));
        // No usable amount → hidden.
        $this->assertNull($this->answer('landlord_hoa_fee', ['has_hoa' => 'Yes', 'association_fee_frequency' => 'Monthly']));
        $this->assertNull($this->answer('landlord_hoa_fee', ['has_hoa' => 'Yes', 'association_fee_amount' => 'TBD', 'association_fee_frequency' => 'Monthly']));
        // Unusable frequency → the amount alone, never a guessed period.
        foreach (['Bi-Monthly', 'Weekly', 'Other', '', null] as $frequency) {
            $this->assertSame(
                'The HOA fee is $175.',
                $this->answer('landlord_hoa_fee', ['has_hoa' => 'Yes', 'association_fee_amount' => '175', 'association_fee_frequency' => $frequency]),
                'frequency ' . json_encode($frequency)
            );
        }
    }

    // =========================================================================
    // HOA frequency normalisation (both roles share the formatter)
    // =========================================================================

    public static function frequencyProvider(): array
    {
        return [
            'Semi-Annually' => ['Semi-Annually', 'The HOA fee is $250 every six months.'],
            'semi_annually' => ['semi_annually', 'The HOA fee is $250 every six months.'],
            'semi-annually' => ['semi-annually', 'The HOA fee is $250 every six months.'],
            'One-Time'      => ['One-Time', 'The HOA fee is a one-time fee of $250.'],
            'one_time'      => ['one_time', 'The HOA fee is a one-time fee of $250.'],
            'one-time'      => ['one-time', 'The HOA fee is a one-time fee of $250.'],
            'Monthly'       => ['Monthly', 'The HOA fee is $250 per month.'],
            'monthly'       => ['monthly', 'The HOA fee is $250 per month.'],
            'Quarterly'     => ['Quarterly', 'The HOA fee is $250 per quarter.'],
            'Annually'      => ['Annually', 'The HOA fee is $250 per year.'],
            'annually'      => ['annually', 'The HOA fee is $250 per year.'],
            'Bi-Monthly'    => ['Bi-Monthly', 'The HOA fee is $250.'],
        ];
    }

    /**
     * @dataProvider frequencyProvider
     */
    public function test_seller_hoa_frequency_vocabulary_is_normalised(string $frequency, string $expected): void
    {
        $this->assertSame($expected, $this->answer(
            'seller_hoa_fee',
            ['hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => $frequency],
            ['association_fee_frequency' => $frequency]
        ));
    }

    public function test_hoa_other_frequency_uses_the_owners_text_only_when_meaningful(): void
    {
        $listing = ['hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Other'];

        $this->assertSame(
            'The HOA fee is $250 (frequency: Every two months).',
            $this->answer('seller_hoa_fee', $listing, ['association_fee_frequency' => 'Other', 'association_fee_frequency_other' => ' Every  two months '])
        );
        // Blank or placeholder "Other" text → the amount alone.
        foreach (['', '   ', 'N/A', 'unknown', 'Other'] as $blank) {
            $this->assertSame('The HOA fee is $250.', $this->answer('seller_hoa_fee', $listing, ['association_fee_frequency' => 'Other', 'association_fee_frequency_other' => $blank]), json_encode($blank));
        }
        // Text that cannot be restated safely hides the whole question.
        $this->assertNull($this->answer('seller_hoa_fee', $listing, ['association_fee_frequency' => 'Other', 'association_fee_frequency_other' => str_repeat('x', 61)]));
        $this->assertNull($this->answer('seller_hoa_fee', $listing, ['association_fee_frequency' => 'Other', 'association_fee_frequency_other' => "Every\nmonth"]));
        // "Other" text is never read unless "Other" is the selected frequency.
        $this->assertSame(
            'The HOA fee is $250 per month.',
            $this->answer('seller_hoa_fee', ['hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Monthly'], ['association_fee_frequency' => 'Monthly', 'association_fee_frequency_other' => 'Every two months'])
        );
        // Landlord reads the same companion.
        $this->assertSame(
            'The HOA fee is $175 (frequency: Twice a year).',
            $this->answer('landlord_hoa_fee', ['has_hoa' => 'Yes', 'association_fee_amount' => '175', 'association_fee_frequency' => 'Other'], ['association_fee_frequency' => 'Other', 'association_fee_frequency_other' => 'Twice a year'])
        );
    }

    // =========================================================================
    // Seller structured additions
    // =========================================================================

    public function test_seller_pets_allowed(): void
    {
        $this->assertSame('Pets are allowed at this property.', $this->answer('seller_pets_allowed', ['pets_allowed' => 'Yes']));
        $this->assertSame("Pets are not allowed under the property's pet policy. Assistance animals are handled separately under applicable law.", $this->answer('seller_pets_allowed', ['pets_allowed' => 'no']));
        $this->assertNull($this->answer('seller_pets_allowed', ['pets_allowed' => 'Small dogs only']));
        $this->assertNull($this->answer('seller_pets_allowed', []));
    }

    public function test_seller_pool(): void
    {
        $this->assertSame('This property has a pool.', $this->answer('seller_pool', ['pool' => 'Yes']));
        $this->assertSame('This property does not have a pool.', $this->answer('seller_pool', ['pool' => 'No']));
        foreach (['Optional', 'Community', '1', ''] as $value) {
            $this->assertNull($this->answer('seller_pool', ['pool' => $value]), json_encode($value));
        }
    }

    public function test_seller_garage_states_only_yes_or_no(): void
    {
        $this->assertSame('This property has a garage.', $this->answer('seller_garage', ['garage' => 'Yes']));
        $this->assertSame('This property does not have a garage.', $this->answer('seller_garage', ['garage' => 'No']));
        // A space count (the "Other" fallback) is never turned into an answer.
        foreach (['2', '2 car', 'Attached'] as $value) {
            $this->assertNull($this->answer('seller_garage', ['garage' => $value]), json_encode($value));
        }
    }

    public function test_zoning_is_restated_only_when_it_is_a_real_short_designation(): void
    {
        foreach (['seller_zoning', 'landlord_zoning'] as $id) {
            $this->assertSame('The zoning is listed as RS-60.', $this->answer($id, ['zoning' => ' RS-60 ']));
            foreach (['N/A', 'unknown', 'TBD', 'See Remarks', '-', str_repeat('Z', 61), "RS-60\nsecond line"] as $bad) {
                $this->assertNull($this->answer($id, ['zoning' => $bad]), "{$id} " . json_encode($bad));
            }
        }
    }

    public function test_roof_type_uses_stored_selections_and_other_only_when_selected(): void
    {
        foreach (['seller_roof_type', 'landlord_roof_type'] as $id) {
            $this->assertSame('Roof type listed for this property: Shingle.', $this->answer($id, ['roof_type' => 'Shingle'], ['roof_type' => json_encode(['Shingle'])]));
            $this->assertSame('Roof types listed for this property: Tile, Slate.', $this->answer($id, ['roof_type' => 'Tile, Slate'], ['roof_type' => json_encode(['Tile', 'Other']), 'other_roof_type' => 'Slate']));

            // Stale "Other" text with "Other" no longer selected: the context string still
            // carries it; the answer does not.
            $this->assertSame('Roof type listed for this property: Metal.', $this->answer($id, ['roof_type' => 'Metal, Clay barrel'], ['roof_type' => json_encode(['Metal']), 'other_roof_type' => 'Clay barrel']));

            // "Other" with no usable text is dropped; "Other" alone with none is hidden.
            $this->assertSame('Roof type listed for this property: Tile.', $this->answer($id, ['roof_type' => 'Tile'], ['roof_type' => json_encode(['Tile', 'Other']), 'other_roof_type' => '']));
            $this->assertNull($this->answer($id, ['roof_type' => 'x'], ['roof_type' => json_encode(['Other']), 'other_roof_type' => 'n/a']));

            // Unsafe "Other" text hides the question.
            $this->assertSame('other_companion_unsafe', $this->reason($id, ['roof_type' => 'Tile'], ['roof_type' => json_encode(['Tile', 'Other']), 'other_roof_type' => str_repeat('r', 80)]));

            // No stored selections at all → hidden, even if the context has a string.
            $this->assertNull($this->answer($id, ['roof_type' => 'Shingle'], []));
        }
    }

    public function test_leasing_restrictions_only_yes_or_no_and_only_with_an_hoa(): void
    {
        $cases = [
            'seller_leasing_restrictions'   => ['rental_restrictions', 'hoa_association'],
            'landlord_leasing_restrictions' => ['leasing_restrictions', 'has_hoa'],
        ];

        foreach ($cases as $id => [$key, $hoaKey]) {
            $this->assertSame('The listing indicates there are leasing restrictions.', $this->answer($id, [$key => 'Yes', $hoaKey => 'Yes']));
            $this->assertSame('The listing indicates there are no leasing restrictions.', $this->answer($id, [$key => 'No', $hoaKey => 'Yes']));

            foreach (['Unknown', 'Not Applicable', 'N/A', '', 'Some'] as $value) {
                $this->assertNull($this->answer($id, [$key => $value, $hoaKey => 'Yes']), "{$id} " . json_encode($value));
            }
            // Shown on the page only inside the HOA block → hidden without an HOA.
            foreach (['No', 'Unknown', null] as $hoa) {
                $this->assertNull($this->answer($id, [$key => 'Yes', $hoaKey => $hoa]), "{$id} hoa " . json_encode($hoa));
            }
        }
    }

    public function test_leasing_restrictions_never_read_the_hoa_minimum_lease_period(): void
    {
        foreach (['seller_leasing_restrictions', 'landlord_leasing_restrictions'] as $id) {
            $entry = $this->entry($id);
            $read  = implode(' ', array_merge([$entry['source_path']], $entry['supporting_paths'], array_values($entry['other_companion'] ?? [])));
            foreach (['lease_length', 'min_lease_period', 'minimum_lease_period', 'desired_lease_length', 'lease_terms'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $read, "{$id} must not read {$forbidden}.");
            }
        }
    }

    // =========================================================================
    // Landlord structured additions
    // =========================================================================

    public function test_landlord_community_amenities(): void
    {
        $listing = ['association_amenities' => 'Clubhouse, Pool', 'has_hoa' => 'Yes'];

        $this->assertSame('Community amenities listed for this property: Clubhouse, Pool.', $this->answer('landlord_association_amenities', $listing, ['association_amenities' => json_encode(['Clubhouse', 'Pool'])]));
        $this->assertSame('Community amenity listed for this property: Dog Park.', $this->answer('landlord_association_amenities', $listing, ['association_amenities' => json_encode(['Other']), 'association_amenities_other' => 'Dog Park']));
        $this->assertNull($this->answer('landlord_association_amenities', ['association_amenities' => 'Clubhouse', 'has_hoa' => 'No'], ['association_amenities' => json_encode(['Clubhouse'])]));
        $this->assertNull($this->answer('landlord_association_amenities', $listing, ['association_amenities' => json_encode(['None'])]));
    }

    public function test_water_view_is_not_in_the_catalog(): void
    {
        // Not published on either listing page (the page's "View" row reads view_preference),
        // and water_view has no editable form control — so there is nothing to restate.
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $this->assertStringNotContainsString('water_view', $entry['source_path'], $id);
            $this->assertStringNotContainsStringIgnoringCase('water view', $entry['question'], $id);
        }
    }

    // =========================================================================
    // Seller financing — public admission
    // =========================================================================

    private function financing(array $selections, ?string $other = null): ?string
    {
        $meta = ['offered_financing' => json_encode($selections)];
        if ($other !== null) {
            $meta['other_financing'] = $other;
        }
        $context = implode(', ', array_filter($selections, fn ($s) => strtolower($s) !== 'other'));

        return $this->answer('seller_offered_financing', ['offered_financing' => $context !== '' ? $context : ($other ?? '')], $meta);
    }

    public function test_financing_one_and_several_types(): void
    {
        $this->assertSame('The seller has indicated they will consider the following financing type: Cash.', $this->financing(['Cash']));
        $this->assertSame(
            'The seller has indicated they will consider the following financing types: Conventional, FHA, VA and Cash.',
            $this->financing(['Conventional', 'FHA', 'VA', 'Cash'])
        );
    }

    public function test_financing_other_is_used_only_when_meaningful(): void
    {
        $this->assertSame(
            'The seller has indicated they will consider the following financing types: Cash and Bitcoin.',
            $this->financing(['Cash', 'Other'], 'Bitcoin')
        );
        // Blank or placeholder "Other" text is dropped.
        $this->assertSame('The seller has indicated they will consider the following financing type: Cash.', $this->financing(['Cash', 'Other'], ''));
        $this->assertSame('The seller has indicated they will consider the following financing type: Cash.', $this->financing(['Cash', 'Other'], 'N/A'));
        // Figures in the "Other" text are a TERM → the whole question is hidden.
        foreach (['Seller financing at 6%', '20% down', '$50,000 down', '5 year balloon'] as $terms) {
            $this->assertNull($this->financing(['Cash', 'Other'], $terms), $terms);
        }
    }

    public function test_financing_empty_list_hides_the_question(): void
    {
        $this->assertNull($this->answer('seller_offered_financing', ['offered_financing' => ''], ['offered_financing' => '[]']));
        $this->assertNull($this->answer('seller_offered_financing', [], []));
        $this->assertNull($this->answer('seller_offered_financing', ['offered_financing' => 'Cash'], ['offered_financing' => json_encode(['Other'])]));
    }

    public function test_restricted_seller_financing_terms_never_reach_the_answer(): void
    {
        $listing = [
            'offered_financing'               => 'Seller Financing, Cash',
            'seller_financing_down_payment'   => '40000',
            'seller_financing_interest_rate'  => '6.125',
            'seller_financing_term'           => '30',
            'minimum_cap_rate'                => '7.25',
            'minimum_annual_net_income'       => '987654',
        ];
        $meta = [
            'offered_financing'       => json_encode(['Seller Financing', 'Cash']),
            'down_payment_amount'     => '40000',
            'seller_financing_amount' => '350000',
            'interest_rate'           => '6.125',
            'balloon_payment'         => '5 years',
        ];

        $answer = $this->answer('seller_offered_financing', $listing, $meta);

        $this->assertSame('The seller has indicated they will consider the following financing types: Seller Financing and Cash.', $answer);
        $this->assertDoesNotMatchRegularExpression('/\d/', $answer);

        // The entry reads offered_financing and other_financing and nothing else.
        $entry = $this->entry('seller_offered_financing');
        $this->assertSame('listing.offered_financing', $entry['source_path']);
        $this->assertSame([], $entry['supporting_paths']);
        $this->assertSame(['selected_in' => 'offered_financing', 'meta_key' => 'other_financing', 'reject_figures' => true], $entry['other_companion']);
    }

    public function test_the_financing_admission_is_narrow(): void
    {
        $context = ['listing' => ['offered_financing' => 'Cash', 'flood_zone_code' => 'AE', 'sale_provision' => 'Short Sale'], 'faq_answers' => []];
        $probe = [
            'role' => 'seller', 'question' => 'Probe?', 'supporting_paths' => [], 'formatter' => 'zoning', 'guards' => [],
        ];

        // Without source_kind admitted_listing the key stays owner_only.
        $this->assertSame('not_public_allowed', $this->service->evaluate($probe + ['source_path' => 'listing.offered_financing'], 'seller', $context, [])['reason']);
        // An admitted_listing entry cannot reach a key that is not on the list …
        $this->assertSame('not_public_allowed', $this->service->evaluate($probe + ['source_kind' => 'admitted_listing', 'source_path' => 'listing.sale_provision'], 'seller', $context, [])['reason']);
        // … nor a RESTRICTED key, whatever the list says.
        $this->assertSame('not_public_allowed', $this->service->evaluate($probe + ['source_kind' => 'admitted_listing', 'source_path' => 'listing.flood_zone_code'], 'seller', $context, [])['reason']);
        $this->assertSame(SnapshotFactVisibility::RESTRICTED, SnapshotFactVisibility::classify('flood_zone_code', 'seller'));
        // A supporting path is never admitted.
        $this->assertSame('supporting_not_public_allowed', $this->service->evaluate(
            array_merge($probe, ['source_kind' => 'listing', 'source_path' => 'listing.zoning', 'supporting_paths' => ['listing.offered_financing']]),
            'seller', ['listing' => ['zoning' => 'RS-60', 'offered_financing' => 'Cash']], []
        )['reason']);
        // Another role cannot use the seller admission.
        $this->assertFalse($this->service->evaluate(
            ['role' => 'landlord'] + $probe + ['source_kind' => 'admitted_listing', 'source_path' => 'listing.offered_financing'],
            'landlord', $context, []
        )['available']);
        // An unknown source kind fails closed.
        $this->assertSame('source_kind_unknown', $this->service->evaluate($probe + ['source_kind' => 'public', 'source_path' => 'listing.zoning'], 'seller', ['listing' => ['zoning' => 'RS-60']], [])['reason']);

        // The AI-context tier is untouched by the admission.
        $this->assertSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::classify('offered_financing', 'seller'));
    }

    // =========================================================================
    // Companion boundary and catalog metadata
    // =========================================================================

    public function test_only_declared_meta_keys_can_reach_an_answer(): void
    {
        $sentinel = 'SENTINEL-UNDECLARED-META';
        $listing = [
            'roof_type' => 'Tile', 'offered_financing' => 'Cash', 'zoning' => 'RS-60',
            'hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Other',
        ];
        $meta = [
            'roof_type'                       => json_encode(['Tile', 'Other']),
            'offered_financing'               => json_encode(['Cash', 'Other']),
            'association_fee_frequency'       => 'Other',
            // Undeclared keys carrying sentinel text:
            'other_preferences'               => $sentinel,
            'additional_details'              => $sentinel,
            'minimum_cap_rate'                => $sentinel,
            'other_appliances'                => $sentinel,
            'listing_ai_faq'                  => json_encode(['roof_age_and_condition' => $sentinel]),
        ];

        foreach ($this->service->forListing('seller', ['listing' => $listing, 'faq_answers' => []], $meta) as $q) {
            $this->assertStringNotContainsString($sentinel, $q['answer'], $q['id']);
        }
    }

    public function test_malformed_companion_declaration_fails_closed(): void
    {
        $entry = $this->entry('seller_roof_type');
        foreach ([
            'not an array',
            ['selected_in' => 'roof_type'],
            ['selected_in' => 'roof type', 'meta_key' => 'other_roof_type'],
            ['selected_in' => 'roof_type', 'meta_key' => '../other'],
        ] as $bad) {
            $entry['other_companion'] = $bad;
            $this->assertSame(
                'other_companion_invalid',
                $this->service->evaluate($entry, 'seller', ['listing' => ['roof_type' => 'Tile']], ['roof_type' => '["Tile"]'])['reason'],
                json_encode($bad)
            );
        }
    }

    public function test_every_catalog_entry_carries_the_batch_2b_metadata(): void
    {
        $orders = [];
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $this->assertContains($entry['source_kind'], ['listing', 'admitted_listing'], $id);
            $this->assertIsString($entry['category'], $id);
            $this->assertNotSame('', $entry['category'], $id);
            $this->assertIsInt($entry['order'], $id);
            $this->assertIsArray($entry['aliases'], $id);
            foreach ($entry['aliases'] as $alias) {
                $this->assertSame(strtolower(trim($alias)), $alias, "{$id} alias '{$alias}' must be lower-case and trimmed.");
            }
            $this->assertArrayNotHasKey($entry['order'], $orders[$entry['role']] ?? [], "{$id}: order {$entry['order']} is used twice for {$entry['role']}.");
            $orders[$entry['role']][$entry['order']] = $id;
        }

        // Only one entry is admitted, and it is seller financing.
        $admitted = array_keys(array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry(),
            fn (array $e) => $e['source_kind'] === 'admitted_listing'
        ));
        $this->assertSame(['seller_offered_financing'], $admitted);
    }

    public function test_questions_render_in_display_order(): void
    {
        $context = ['listing' => [
            'bedrooms' => '2', 'year_built' => '2004', 'zoning' => 'RM-15', 'appliances' => 'Washer',
        ], 'faq_answers' => []];

        $ids = array_column($this->service->forListing('landlord', $context, []), 'id');

        $this->assertSame(['landlord_bedrooms', 'landlord_year_built', 'landlord_appliances', 'landlord_zoning'], $ids);
    }
}
