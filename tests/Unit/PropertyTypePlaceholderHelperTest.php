<?php

namespace Tests\Unit;

use App\Helpers\PropertyTypePlaceholderHelper;
use PHPUnit\Framework\TestCase;

/**
 * Phase 10 (B2.1 + B2.2) — property-type-aware placeholders.
 *
 * Verifies the single shared helper produces the correct, distinct placeholder
 * for every affected role x context x property-type combination, so the
 * placeholder updates when the selected property type changes.
 */
class PropertyTypePlaceholderHelperTest extends TestCase
{
    /** Role => the exact property_type values that role's UI offers. */
    private const ROLE_TYPES = [
        'seller'   => ['Residential', 'Income', 'Commercial', 'Business', 'Opportunity', 'Vacant Land'],
        'buyer'    => ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land'],
        'landlord' => ['Residential Property', 'Commercial Property'],
        'tenant'   => ['Residential Property', 'Commercial Property'],
    ];

    private const TITLES = [
        'create' => [
            'seller'   => 'Property Description',
            'buyer'    => 'Buyer Description',
            'landlord' => 'Rental Description',
            'tenant'   => 'Tenant Description',
        ],
        'hire' => [
            'seller'   => 'Additional Details',
            'buyer'    => 'Additional Details',
            'landlord' => 'Additional Details',
            'tenant'   => 'Additional Details',
        ],
    ];

    public function test_format_and_title_for_every_role_and_context(): void
    {
        foreach (['create', 'hire'] as $context) {
            foreach (self::ROLE_TYPES as $role => $types) {
                foreach ($types as $type) {
                    $result = PropertyTypePlaceholderHelper::placeholder($role, $context, $type);

                    $title = self::TITLES[$context][$role];
                    $this->assertStringStartsWith("Enter {$title} (e.g., ", $result,
                        "Wrong title/format for {$context}/{$role}/{$type}");
                    $this->assertStringEndsWith(')', $result);
                    // Never generic (B2.2): the example segment must be non-empty.
                    $this->assertMatchesRegularExpression('/\(e\.g\., .+\)$/', $result,
                        "Empty example for {$context}/{$role}/{$type}");
                }
            }
        }
    }

    public function test_example_changes_with_property_type_for_multi_type_roles(): void
    {
        // Sale roles expose 5+ types; each distinct type must yield a distinct example.
        foreach (['create', 'hire'] as $context) {
            foreach (['seller', 'buyer'] as $role) {
                // Collapse Business/Opportunity (same canonical key) for the distinctness check.
                $distinctInputs = ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land'];
                $examples = [];
                foreach ($distinctInputs as $type) {
                    $examples[$type] = PropertyTypePlaceholderHelper::placeholder($role, $context, $type);
                }
                $this->assertSame(count($distinctInputs), count(array_unique($examples)),
                    "Placeholders are not distinct across property types for {$context}/{$role}");
            }
        }

        // Lease roles: residential vs commercial must differ.
        foreach (['create', 'hire'] as $context) {
            foreach (['landlord', 'tenant'] as $role) {
                $res = PropertyTypePlaceholderHelper::placeholder($role, $context, 'Residential Property');
                $com = PropertyTypePlaceholderHelper::placeholder($role, $context, 'Commercial Property');
                $this->assertNotSame($res, $com,
                    "Residential vs Commercial placeholder identical for {$context}/{$role}");
            }
        }
    }

    public function test_business_and_opportunity_map_to_same_example(): void
    {
        $business    = PropertyTypePlaceholderHelper::placeholder('seller', 'hire', 'Business');
        $opportunity = PropertyTypePlaceholderHelper::placeholder('seller', 'hire', 'Opportunity');
        $this->assertSame($business, $opportunity);
    }

    public function test_blank_or_unknown_type_falls_back_to_default_not_empty(): void
    {
        foreach (['create', 'hire'] as $context) {
            foreach (array_keys(self::ROLE_TYPES) as $role) {
                foreach (['', null, 'Nonsense Type'] as $type) {
                    $result = PropertyTypePlaceholderHelper::placeholder($role, $context, $type);
                    $title  = self::TITLES[$context][$role];
                    $this->assertStringStartsWith("Enter {$title} (e.g., ", $result);
                    $this->assertMatchesRegularExpression('/\(e\.g\., .+\)$/', $result,
                        "Fallback produced empty example for {$context}/{$role}");
                }
            }
        }
    }

    public function test_create_titles_differ_by_role_hire_titles_are_additional_details(): void
    {
        $this->assertStringStartsWith('Enter Property Description (e.g., ',
            PropertyTypePlaceholderHelper::placeholder('seller', 'create', 'Residential'));
        $this->assertStringStartsWith('Enter Buyer Description',
            PropertyTypePlaceholderHelper::placeholder('buyer', 'create', 'Residential'));
        $this->assertStringStartsWith('Enter Rental Description',
            PropertyTypePlaceholderHelper::placeholder('landlord', 'create', 'Residential Property'));
        $this->assertStringStartsWith('Enter Tenant Description',
            PropertyTypePlaceholderHelper::placeholder('tenant', 'create', 'Residential Property'));

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $this->assertStringStartsWith('Enter Additional Details',
                PropertyTypePlaceholderHelper::placeholder($role, 'hire', 'Residential'));
        }
    }

    public function test_invalid_role_or_context_falls_back_safely(): void
    {
        // Should not throw; falls back to seller/create.
        $result = PropertyTypePlaceholderHelper::placeholder('bogus', 'bogus', 'Residential');
        $this->assertStringStartsWith('Enter Property Description', $result);
    }
}
