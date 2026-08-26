<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Landlord Leasing Terms is ONE definition, and these tests fail if it becomes two.
 *
 * THE REGRESSION BEING PINNED
 * ---------------------------
 * MLS Quick Import declared its own Leasing Terms list: thirteen hand-written
 * entries against a canonical tab of sixty-two fields. Nine were canonical
 * leasing terms; the other four belong to different tabs entirely. Fifty-three
 * canonical terms were absent — smoking policy, subletting policy, occupant
 * status, utilities, maintenance responsibility and response time, renewal
 * details, rent escalation, storage, every commercial lease term, and the
 * bidding-period rent fields. A landlord arriving through quick import was never
 * asked any of them.
 *
 * Every test here is structural on purpose. A test that listed the expected
 * fields would be a fourth copy of the list and would pass while the product
 * drifted underneath it.
 */
class LandlordLeasingTermsParityTest extends TestCase
{
    private const CONSUMERS = [
        LandlordOfferListing::class,
        LandlordOfferListingEdit::class,
        LandlordMlsQuickImport::class,
    ];

    /** @test */
    public function every_consumer_uses_the_one_canonical_definition(): void
    {
        foreach (self::CONSUMERS as $class) {
            $this->assertContains(
                'App\Http\Livewire\OfferListing\Concerns\LandlordLeasingTerms',
                class_uses_recursive($class),
                $class . ' must take its Leasing Terms fields from the LandlordLeasingTerms trait.'
            );
        }
    }

    /**
     * @test
     *
     * Quick Import holds every canonical term as a real property, so the shared
     * partial can bind to it.
     */
    public function quick_import_exposes_every_canonical_leasing_term(): void
    {
        $have = array_map(
            fn ($p) => $p->getName(),
            (new ReflectionClass(LandlordMlsQuickImport::class))
                ->getProperties(ReflectionProperty::IS_PUBLIC)
        );

        $missing = array_values(array_diff(
            LandlordOfferListing::landlordLeasingTermsFields(),
            $have
        ));

        $this->assertSame([], $missing, 'Quick Import is missing canonical terms: ' . implode(', ', $missing));
    }

    /** @test */
    public function the_manual_flows_and_quick_import_expose_the_same_terms(): void
    {
        $fields = LandlordOfferListing::landlordLeasingTermsFields();

        foreach ([LandlordOfferListingEdit::class, LandlordMlsQuickImport::class] as $class) {
            $this->assertSame($fields, $class::landlordLeasingTermsFields());
        }
    }

    /** @test */
    public function quick_import_renders_the_canonical_partial(): void
    {
        $this->assertSame(
            'livewire.offer-listing.offer-landlord-tabs.commission-based.lease-terms',
            (new LandlordMlsQuickImport())->canonicalTermsPartial()
        );

        foreach ([
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
        ] as $manual) {
            $this->assertStringContainsString(
                'offer-landlord-tabs.commission-based.lease-terms',
                file_get_contents(base_path($manual)),
                $manual . ' no longer includes the partial Quick Import mirrors.'
            );
        }
    }

    /**
     * @test
     *
     * THE GUARD AGAINST A SHADOW SCHEMA.
     *
     * Quick Import keeps a small schema for questions whose canonical home is a
     * different tab. That is allowed. What is not allowed is a canonical Leasing
     * Term appearing there: it would be a second definition of a field the tab
     * already owns, which is exactly the duplication this work removed.
     */
    public function the_supplementary_schema_contains_no_canonical_leasing_term(): void
    {
        $component = new LandlordMlsQuickImport();
        $canonical = $component::landlordLeasingTermsFields();

        $this->assertTrue($component->usesCanonicalTerms());

        $offenders = array_values(array_intersect(
            array_keys($component->questionSchema()),
            $canonical
        ));

        $this->assertSame(
            [],
            $offenders,
            'Quick Import has grown a private definition of canonical Leasing Terms: '
            . implode(', ', $offenders)
            . '. Add the field to the canonical partial instead.'
        );
    }

    /**
     * @test
     *
     * The terms the old schema could not express are present. Named explicitly
     * because their absence was the user-visible symptom.
     */
    public function the_previously_missing_leasing_terms_are_all_present(): void
    {
        $fields = LandlordOfferListing::landlordLeasingTermsFields();

        foreach ([
            'Smoking policy'         => 'smoking_policy',
            'Subletting policy'      => 'subletting_policy',
            'Occupant status'        => 'occupant_status',
            'Utilities'              => 'utilities',
            'Maintenance by'         => 'maintenance_by',
            'Maintenance response'   => 'maintenance_response_time',
            'Renewal details'        => 'renewal_option_details',
            'Rent escalation'        => 'rent_escalation_terms',
            'Additional lease terms' => 'additional_landlord_lease_terms',
            'Commercial lease type'  => 'commercial_lease_type',
            'CAM / NNN charges'      => 'cam_nnn_additional_rent_charges',
            'Bidding start rent'     => 'starting_rent',
            'Bidding reserve rent'   => 'reserve_rent',
            'Lease-now price'        => 'lease_now_price',
            'Leasing spaces'         => 'leasing_spaces',
            'Terms of lease'         => 'terms_of_lease',
            'Owner pays'             => 'owner_pays',
        ] as $label => $field) {
            $this->assertContains($field, $fields, "{$label} is missing from the canonical definition.");
        }
    }

    /**
     * @test
     *
     * Every canonical field has an intentional persistence disposition.
     */
    public function no_active_canonical_field_is_silently_unpersisted(): void
    {
        $fields       = LandlordOfferListing::landlordLeasingTermsFields();
        $map          = LandlordOfferListing::landlordLeasingTermsMetaMap();
        $notPersisted = LandlordOfferListing::landlordLeasingTermsNotPersisted();

        $unaccounted = array_values(array_diff($fields, array_keys($map), $notPersisted));

        $this->assertSame([], $unaccounted, 'Unaccounted canonical fields: ' . implode(', ', $unaccounted));

        // Landlord has no view-state exceptions; every field is stored.
        $this->assertSame([], $notPersisted);
    }

    /**
     * @test
     *
     * All three flows write through the one routine, and none keeps a private
     * saveMeta line for a canonical field.
     */
    public function all_three_flows_persist_through_the_shared_routine(): void
    {
        $files = [
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/QuickImport/LandlordMlsQuickImport.php',
        ];

        foreach ($files as $file) {
            $this->assertStringContainsString(
                'saveLandlordLeasingTermsMeta($auction)',
                file_get_contents(base_path($file)),
                $file . ' no longer persists Leasing Terms through the shared routine.'
            );
        }

        $map = array_keys(LandlordOfferListing::landlordLeasingTermsMetaMap());

        foreach (array_slice($files, 0, 2) as $file) {
            $source = file_get_contents(base_path($file));

            foreach ($map as $field) {
                $this->assertStringNotContainsString(
                    "saveMeta('{$field}'",
                    $source,
                    "{$file} has a private saveMeta line for the canonical field {$field}."
                );
            }
        }
    }

    /**
     * @test
     *
     * Fields that appear only inside Blade comments in the canonical tab are NOT
     * part of the active surface and must not be pulled into it. Recorded so a
     * later reader does not "helpfully" add them.
     */
    public function commented_out_fields_are_not_treated_as_canonical(): void
    {
        $fields = LandlordOfferListing::landlordLeasingTermsFields();

        foreach (['included_storage_space', 'maintenance_handler', 'storage_space'] as $dead) {
            $this->assertNotContains(
                $dead,
                $fields,
                "{$dead} is commented out in the canonical tab and must not be treated as active."
            );
        }
    }

    /**
     * @test
     *
     * Financing is a sale concept and has no place on the landlord surface.
     */
    public function the_landlord_surface_carries_no_seller_financing_concepts(): void
    {
        $fields = LandlordOfferListing::landlordLeasingTermsFields();

        foreach (['offered_financing', 'other_financing', 'seller_financing_type', 'maximum_budget'] as $sellerField) {
            $this->assertNotContains($sellerField, $fields);
        }
    }
}
