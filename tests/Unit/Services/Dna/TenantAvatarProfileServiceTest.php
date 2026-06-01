<?php

namespace Tests\Unit\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use App\Services\Dna\TenantAvatarProfileService;
use App\Services\Dna\TenantAvatarService;
use PHPUnit\Framework\TestCase;

/**
 * TenantAvatarProfileServiceTest
 *
 * Verifies the TenantAvatarProfileService orchestration layer.
 * Uses a mock TenantAvatarService to avoid touching the database.
 * No database connection is required.
 */
class TenantAvatarProfileServiceTest extends TestCase
{
    private function makeProfile(array $attributes): BuyerTenantDnaProfile
    {
        $profile = new BuyerTenantDnaProfile();
        foreach ($attributes as $key => $value) {
            $profile->$key = $value;
        }
        return $profile;
    }

    /** @test */
    public function it_silently_no_ops_for_non_tenant_profiles_without_calling_avatar_service(): void
    {
        $avatarService = $this->createMock(TenantAvatarService::class);
        $avatarService->expects($this->never())->method('generate');

        $service = new TenantAvatarProfileService($avatarService);

        $buyerProfile = $this->makeProfile([
            'listing_type' => 'buyer',
            'listing_id'   => 1,
        ]);

        $service->compute($buyerProfile);
    }

    /** @test */
    public function it_silently_no_ops_for_seller_profiles_without_calling_avatar_service(): void
    {
        $avatarService = $this->createMock(TenantAvatarService::class);
        $avatarService->expects($this->never())->method('generate');

        $service = new TenantAvatarProfileService($avatarService);

        $sellerProfile = $this->makeProfile([
            'listing_type' => 'seller',
            'listing_id'   => 2,
        ]);

        $service->compute($sellerProfile);
    }

    /** @test */
    public function it_silently_no_ops_when_listing_type_is_empty_without_calling_avatar_service(): void
    {
        $avatarService = $this->createMock(TenantAvatarService::class);
        $avatarService->expects($this->never())->method('generate');

        $service = new TenantAvatarProfileService($avatarService);

        $profile = $this->makeProfile([
            'listing_type' => '',
            'listing_id'   => 3,
        ]);

        $service->compute($profile);
    }
}
