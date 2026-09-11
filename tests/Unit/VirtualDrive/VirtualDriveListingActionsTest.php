<?php

namespace Tests\Unit\VirtualDrive;

use App\Support\VirtualDrive\VirtualDriveListingActions;
use PHPUnit\Framework\TestCase;

class VirtualDriveListingActionsTest extends TestCase
{
    private function listing(array $overrides = []): array
    {
        return array_merge([
            'id'                => 'LK-A',
            'has_photos'        => true,
            'photo_urls'        => ['https://cdn.example.com/a.jpg'],
            'has_video'         => false,
            'video_url'         => null,
            'has_virtual_tour'  => true,
            'virtual_tour_url'  => 'https://tours.example.com/a',
            'canonical_url'     => null,
            'detail_url'        => null,
            'showing_available' => false,
        ], $overrides);
    }

    private function actions(array $listing): array
    {
        return array_column(VirtualDriveListingActions::for($listing), null, 'key');
    }

    /** @test */
    public function the_card_always_offers_the_same_seven_actions_in_the_same_order(): void
    {
        $this->assertSame(
            ['photos', 'video', 'tour', 'details', 'ask', 'save', 'showing'],
            array_column(VirtualDriveListingActions::for($this->listing()), 'key')
        );
    }

    /** @test */
    public function a_bidyouroffer_listing_routes_details_ask_and_showing_to_its_own_page(): void
    {
        $a = $this->actions($this->listing(['canonical_url' => 'https://byo.test/offer-listing/seller/view/130', 'showing_available' => true]));
        $b = $this->actions($this->listing(['id' => 'LK-B', 'canonical_url' => 'https://byo.test/offer-listing/landlord/view/78', 'showing_available' => true]));

        foreach (['details', 'ask', 'showing'] as $key) {
            $this->assertTrue($a[$key]['available']);
            $this->assertSame('https://byo.test/offer-listing/seller/view/130', $a[$key]['url']);
            $this->assertSame('https://byo.test/offer-listing/landlord/view/78', $b[$key]['url']);
        }
    }

    /** @test */
    public function an_unavailable_action_carries_no_url_and_says_why(): void
    {
        $actions = $this->actions($this->listing(['video_url' => 'https://video.example.com/should-not-appear']));

        foreach (['video', 'details', 'ask', 'save', 'showing'] as $key) {
            $this->assertFalse($actions[$key]['available'], $key);
            $this->assertNull($actions[$key]['url'], $key);
            $this->assertNotEmpty($actions[$key]['reason'], $key);
        }
    }

    /** @test */
    public function save_is_never_offered_because_it_does_not_exist(): void
    {
        $actions = $this->actions($this->listing(['canonical_url' => 'https://byo.test/x', 'showing_available' => true]));

        $this->assertFalse($actions['save']['available']);
    }

    /** @test */
    public function photos_need_both_the_flag_and_at_least_one_permitted_url(): void
    {
        $this->assertFalse($this->actions($this->listing(['photo_urls' => []]))['photos']['available']);
        $this->assertFalse($this->actions($this->listing(['has_photos' => false]))['photos']['available']);
        $this->assertTrue($this->actions($this->listing())['photos']['available']);
    }
}
