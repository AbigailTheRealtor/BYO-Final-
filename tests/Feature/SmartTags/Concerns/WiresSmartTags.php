<?php

namespace Tests\Feature\SmartTags\Concerns;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Models\User;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagTaxonomy;
use Livewire\Livewire;

/**
 * Shared setup for the Phase 2 wiring tests: the activation gates, publishing a
 * real listing through the real wizard, and counting what came out.
 */
trait WiresSmartTags
{
    /**
     * Open the activation gates for this test.
     *
     * Set through the config repository rather than the environment, because
     * config/smart_tags_wiring.php reads env() once at load and PHPUnit has
     * already loaded it by the time a test runs.
     */
    protected function enableSmartTags(bool $master = true, bool $bridge = true): void
    {
        config(['smart_tags_wiring' => ['enabled' => $master, 'bridge_enabled' => $bridge]]);
        SmartTagConfig::flush();
        SmartTagTaxonomy::flush();
    }

    /** The shipped posture: both gates closed. */
    protected function disableSmartTags(): void
    {
        $this->enableSmartTags(false, false);
    }

    /**
     * @return array{evidence: int, assignments: int, states: int}
     */
    protected function smartTagRowCounts(): array
    {
        return [
            'evidence'    => SmartTagEvidence::query()->count(),
            'assignments' => SmartTagAssignment::query()->count(),
            'states'      => SmartTagDerivationState::query()->count(),
        ];
    }

    /** @return string[] tag keys resolved PRESENT for this listing */
    protected function presentTags(string $listingType, int $listingId): array
    {
        return SmartTagAssignment::query()
            ->where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('state', 'present')
            ->orderBy('tag_key')
            ->pluck('tag_key')
            ->all();
    }

    /** @return string[] evidence tag keys for one source */
    protected function evidenceFor(string $listingType, int $listingId, string $source): array
    {
        return SmartTagEvidence::query()
            ->where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('source', $source)
            ->orderBy('tag_key')
            ->pluck('tag_key')
            ->all();
    }

    protected function sellerOwner(): User
    {
        return User::factory()->create(['user_type' => 'seller']);
    }

    protected function landlordOwner(): User
    {
        return User::factory()->create(['user_type' => 'landlord']);
    }

    /**
     * The minimum a Seller Offer Listing needs to PUBLISH, discovered from the
     * real publish validation rather than assumed.
     *
     * @return array<string, mixed>
     */
    protected function sellerPublishFields(string $propertyType, array $extra = []): array
    {
        return array_merge([
            'listing_title' => 'Smart Tags publish',
            'property_type' => $propertyType,
            'first_name'    => 'Ada',
            'last_name'     => 'Lovelace',
            'phone_number'  => '7275550100',
            'email'         => 'ada@example.com',
            'address'       => '123 Smart Tag Way, St Petersburg, FL 33701',
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    protected function landlordPublishFields(string $propertyType, array $extra = []): array
    {
        return array_merge([
            'listing_title'        => 'Smart Tags rental',
            'property_type'        => $propertyType,
            'first_name'           => 'Ada',
            'last_name'            => 'Lovelace',
            'phone_number'         => '7275550100',
            'email'                => 'ada@example.com',
            'address'              => '123 Smart Tag Way, St Petersburg, FL 33701',
            'desired_lease_length' => ['12 Months'],
        ], $extra);
    }

    /**
     * Publish a Seller Offer Listing through the real wizard, exactly as a user does.
     */
    protected function publishSeller(User $owner, string $propertyType, array $extra = []): \App\Models\SellerAgentAuction
    {
        $this->actingAs($owner);

        $component = Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class);

        foreach ($this->sellerPublishFields($propertyType, $extra) as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('store')->assertHasNoErrors();

        $listing = \App\Models\SellerAgentAuction::query()
            ->where('user_id', $owner->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($listing, 'The Seller listing did not publish.');

        return $listing;
    }

    protected function publishLandlord(User $owner, string $propertyType, array $extra = []): \App\Models\LandlordAgentAuction
    {
        $this->actingAs($owner);

        $component = Livewire::test(\App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class);

        foreach ($this->landlordPublishFields($propertyType, $extra) as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('store')->assertHasNoErrors();

        $listing = \App\Models\LandlordAgentAuction::query()
            ->where('user_id', $owner->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($listing, 'The Landlord listing did not publish.');

        return $listing;
    }
}
