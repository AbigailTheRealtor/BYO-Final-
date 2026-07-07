<?php

namespace App\Helpers;

/**
 * Phase 10 (roadmap items B2.1 + B2.2) — Property-type-aware placeholders.
 *
 * Single shared source for the free-text placeholder shown in:
 *   - Create Offer "Description" fields   (context: 'create', B2.2)
 *   - Hire Agent   "Additional Details"   (context: 'hire',   B2.1)
 *
 * for all four roles (seller, buyer, landlord, tenant). The placeholder text is
 * PRESENTATIONAL ONLY — it never affects the bound value ($additional_details),
 * validation, persistence, or submission. It is recomputed on each Livewire
 * render, so it updates reactively when the user changes the property type
 * (every consuming form binds property_type with a non-deferred wire:model).
 *
 * Format (per roadmap): "Enter [title] (e.g., [property-type-specific example])".
 *
 * Property types supported (S12): Residential, Income, Commercial,
 * Business Opportunity, Vacant Land, Commercial Lease, Residential Lease.
 * Sale roles (seller/buyer) use the sale value-space
 * (Residential/Income/Commercial/Business/Opportunity/Vacant Land); lease roles
 * (landlord/tenant) use the lease value-space (Residential Property/Commercial
 * Property). Because each role owns its own example set, the lease vs sale
 * distinction is carried by the role, not by a duplicate type key.
 */
class PropertyTypePlaceholderHelper
{
    /** Section title per context+role (the "[title]" token). */
    protected static array $titles = [
        'create' => [
            'seller'   => 'property description',   // #31: lowercase per owner decision
            'buyer'    => 'buyer description',       // #31
            'landlord' => 'rental description',      // #31
            'tenant'   => 'tenant description',      // #31: Tenant = "tenant description" (owner decision, not "rental")
        ],
        'hire' => [
            'seller'   => 'additional details',      // #30: lowercase
            'buyer'    => 'additional details',      // #30
            'landlord' => 'additional details',      // #30
            'tenant'   => 'additional details',      // #30
        ],
    ];

    /**
     * Property-type-specific examples per context+role+type, each with a
     * 'default' fallback used when the type is blank or unrecognized.
     */
    protected static array $examples = [
        'create' => [
            'seller' => [
                'residential' => 'Beautifully updated 3-bed home with open floor plan, updated kitchen, and large backyard close to top-rated schools',
                'income'      => '12-unit apartment building, fully leased, strong cap rate, with recent roof and HVAC upgrades',
                'commercial'  => 'Class A office space, recent HVAC upgrade, high-traffic location with ample parking',
                'business'    => 'Established restaurant with loyal clientele, turnkey equipment, and a transferable lease',
                'vacant_land' => '2-acre cleared parcel, residential zoning, public utilities available at the street',
                'default'     => 'Updated 4BR/3BA home with a new roof, open floor plan, and two-car garage close to top schools',
            ],
            'buyer' => [
                'residential' => 'Growing family looking for a 4-bed home with a fenced yard, good schools, and a short commute downtown',
                'income'      => 'Investor seeking a stabilized multi-family property with strong cash flow and value-add upside',
                'commercial'  => 'Looking for Class B office or retail space with good visibility and on-site parking',
                'business'    => 'Seeking an established, cash-flowing business with trained staff and a transferable lease',
                'vacant_land' => 'Looking for a buildable residential lot with utilities available and flexible zoning',
                'default'     => 'Growing family looking for a 4-bed home with a fenced yard, good schools, and a short commute downtown',
            ],
            'landlord' => [
                'residential' => 'Spacious 2-bed unit with in-unit laundry, covered parking, and updated kitchen; pet-friendly for small dogs',
                'commercial'  => '2,500 SqFt of open, ADA-compliant leasable space on a high-traffic corner; ideal for retail or office use',
                'default'     => 'Spacious 2-bed unit with in-unit laundry, covered parking, and updated kitchen; pet-friendly for small dogs',
            ],
            'tenant' => [
                'residential' => 'Quiet professional seeking a pet-friendly 2-bed apartment near South Tampa, budget up to $2,200/mo, prefer a 12-month lease',
                'commercial'  => 'Small business seeking 1,500 SqFt of retail space with street visibility and parking, flexible on lease length',
                'default'     => 'Quiet professional seeking a pet-friendly 2-bed apartment near South Tampa, budget up to $2,200/mo, prefer a 12-month lease',
            ],
        ],
        'hire' => [
            'seller' => [
                'residential' => 'Preferred closing timeline, property is currently occupied, recent renovations completed',
                'income'      => 'Rent roll and current occupancy, preferred closing timeline, financials available on request',
                'commercial'  => 'Current tenant and lease status, preferred closing timeline, recent building improvements',
                'business'    => 'Reason for selling, whether staff will stay on, preferred closing timeline',
                'vacant_land' => 'Zoning and survey status, access and available utilities, preferred closing timeline',
                'default'     => 'Preferred closing timeline, property is currently occupied, recent renovations completed',
            ],
            'buyer' => [
                'residential' => 'Preferred closing timeline, pre-approved or cash buyer, property must be move-in ready',
                'income'      => 'Target cap rate, financing in place, open to value-add properties',
                'commercial'  => 'Intended use, financing status, preferred timeline to occupy',
                'business'    => 'Industry preferences, financing status, willing to sign an NDA',
                'vacant_land' => 'Intended use, preferred zoning, financing or cash status',
                'default'     => 'Preferred closing timeline, pre-approved or cash buyer, property must be move-in ready',
            ],
            'landlord' => [
                'residential' => 'Desired lease start date, preferred tenant profile, pet policy flexibility',
                'commercial'  => 'Desired lease start date, preferred tenant use, willingness to offer a build-out allowance',
                'default'     => 'Desired lease start date, preferred tenant profile, pet policy flexibility',
            ],
            'tenant' => [
                'residential' => 'Flexible move-in date, need a pet-friendly property, open to a short-term lease',
                'commercial'  => 'Flexible move-in date, required square footage and use, open to a short-term lease',
                'default'     => 'Flexible move-in date, need a pet-friendly property, open to a short-term lease',
            ],
        ],
    ];

    /**
     * Build the full placeholder string for a field.
     *
     * @param  string       $role          seller|buyer|landlord|tenant
     * @param  string       $context       create|hire
     * @param  string|null  $propertyType  the current property_type value (may be blank)
     */
    public static function placeholder(string $role, string $context, ?string $propertyType): string
    {
        $context = in_array($context, ['create', 'hire'], true) ? $context : 'create';
        $role    = in_array($role, ['seller', 'buyer', 'landlord', 'tenant'], true) ? $role : 'seller';

        $title   = static::$titles[$context][$role];
        $roleMap = static::$examples[$context][$role];
        $key     = static::normalizeType($propertyType);
        $example = $roleMap[$key] ?? $roleMap['default'];

        return "Enter {$title} (e.g., {$example})";
    }

    /**
     * Map a raw property_type value (either the sale or lease value-space) to a
     * canonical example key. Unknown/blank values fall through to the role's
     * 'default' example.
     */
    protected static function normalizeType(?string $propertyType): string
    {
        $value = trim((string) $propertyType);

        return match ($value) {
            'Residential', 'Residential Property'                        => 'residential',
            'Commercial', 'Commercial Property'                          => 'commercial',
            'Income'                                                     => 'income',
            'Business', 'Opportunity', 'Business Only',
            'Real Estate Building and Business'                          => 'business',
            'Vacant Land'                                                => 'vacant_land',
            default                                                      => 'default',
        };
    }
}
