<?php

namespace App\Support\Listing;

/**
 * Option lists the canonical Landlord Leasing Terms partial renders.
 *
 * WHY THIS EXISTS
 * ---------------
 * The partial was never self-contained. It iterates `$ownerPays`,
 * `$rent_includes` and `$tenantPays` as OPTION lists, but defined none of them —
 * it inherited all three from the scope of the page that included it, and only
 * the manual Create page happened to define them.
 *
 * That went unnoticed because the sections holding them are gated on
 * `$property_type === 'Residential Property'` / `'Commercial Property'`, and MLS
 * Quick Import was carrying raw RESO values that matched neither. The moment the
 * property type was normalised correctly those sections opened for the first
 * time and the partial fataled on an undefined variable.
 *
 * The lists are moved here verbatim from the Create page so the partial can
 * define them itself and stop depending on who included it. Same values, one
 * definition, every consumer.
 *
 * NOTE ON `$rent_includes`: the name collides with the Livewire property of the
 * same name, which holds the landlord's SELECTION. Inside the view the option
 * list shadows it; the selection is reached through `$wire.entangle` and
 * `$this->rent_includes`, both of which are unaffected. That collision is
 * pre-existing and is not changed here.
 */
final class LandlordLeaseTermsOptions
{
    /**
     * "Owner Pays" — utilities and services the landlord covers.
     *
     * @return list<array{name: string}>
     */
    public static function ownerPays(): array
    {
        return [
        ['name' => 'Cable TV'],
        ['name' => 'Electricity'],
        ['name' => 'Gas'],
        ['name' => 'Grounds Care'],
        ['name' => 'Insurance'],
        ['name' => 'Internet'],
        ['name' => 'Laundry'],
        ['name' => 'Management'],
        ['name' => 'Pest Control'],
        ['name' => 'Pool Maintenance'],
        ['name' => 'Recreational'],
        ['name' => 'Repairs'],
        ['name' => 'Security'],
        ['name' => 'Sewer'],
        ['name' => 'Taxes'],
        ['name' => 'Telephone'],
        ['name' => 'Trash Collection'],
        ['name' => 'Water'],
        ['name' => 'None'],
        ['name' => 'Other'],
        ];
    }

    /**
     * "Rent Includes" — what the quoted rent already covers.
     *
     * @return list<array{name: string}>
     */
    public static function rentIncludes(): array
    {
        return [
        ['name' => 'Cable TV'],
        ['name' => 'Electricity'],
        ['name' => 'Gas'],
        ['name' => 'Grounds Care'],
        ['name' => 'Insurance'],
        ['name' => 'Internet'],
        ['name' => 'Laundry'],
        ['name' => 'Management'],
        ['name' => 'Pest Control'],
        ['name' => 'Pool Maintenance'],
        ['name' => 'Recreational'],
        ['name' => 'Repairs'],
        ['name' => 'Security'],
        ['name' => 'Sewer'],
        ['name' => 'Taxes'],
        ['name' => 'Telephone'],
        ['name' => 'Trash Collection'],
        ['name' => 'Water'],
        ['name' => 'None'],
        ['name' => 'Other'],
        ];
    }

    /**
     * "Tenant Pays" — utilities and services the tenant is responsible for.
     *
     * @return list<array{name: string}>
     */
    public static function tenantPays(): array
    {
        return [
        ['name' => 'Association Fees'],
        ['name' => 'Capital Expenses'],
        ['name' => 'Common Area Maintenance'],
        ['name' => 'Condominium Fees'],
        ['name' => 'Electricity'],
        ['name' => 'Gas'],
        ['name' => 'Liability Insurance'],
        ['name' => 'Parking Fee'],
        ['name' => 'Pro-Rated'],
        ['name' => 'Property Insurance'],
        ['name' => 'Property Taxes'],
        ['name' => 'Reserves'],
        ['name' => 'Sewer'],
        ['name' => 'Trash Collection'],
        ['name' => 'Water'],
        ['name' => 'None '],
        ['name' => 'Other'],
        ];
    }
}
