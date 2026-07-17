<?php

namespace Tests\Feature\MoneyPrecision;

use App\Models\AgentServiceAuctionBid;
use App\Models\BuyerAgentAuctionBid;
use App\Models\PropertyAuctionBid;
use App\Models\SellerAgentAuctionBid;
use App\Models\SellerServiceAuction;
use App\Models\SellerServiceAuctionBid;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batch B1.3 (Money Precision) — Part 1 persistence test.
 *
 * The DECIMAL migration is only half of the guarantee; the model `decimal:2`
 * casts are what make every read return a value normalised to exactly two
 * decimal places regardless of driver. These tests exercise the casts both in
 * memory and through a real save/reload round-trip.
 */
class MoneyColumnCastPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0:class-string,1:string}> */
    public static function moneyCastProvider(): array
    {
        return [
            'PropertyAuctionBid.price'             => [PropertyAuctionBid::class, 'price'],
            'PropertyAuctionBid.escrow_amount'     => [PropertyAuctionBid::class, 'escrow_amount'],
            'SellerAgentAuctionBid.brokerage'      => [SellerAgentAuctionBid::class, 'brokerage'],
            'SellerAgentAuctionBid.price'          => [SellerAgentAuctionBid::class, 'price'],
            'SellerAgentAuctionBid.price_percent'  => [SellerAgentAuctionBid::class, 'price_percent'],
            'BuyerAgentAuctionBid.brokerage'       => [BuyerAgentAuctionBid::class, 'brokerage'],
            'BuyerAgentAuctionBid.price'           => [BuyerAgentAuctionBid::class, 'price'],
            'BuyerAgentAuctionBid.price_percent'   => [BuyerAgentAuctionBid::class, 'price_percent'],
            'AgentServiceAuctionBid.price'         => [AgentServiceAuctionBid::class, 'price'],
            'SellerServiceAuction.price'           => [SellerServiceAuction::class, 'price'],
            'SellerServiceAuctionBid.brokerage'    => [SellerServiceAuctionBid::class, 'brokerage'],
            'SellerServiceAuctionBid.price'        => [SellerServiceAuctionBid::class, 'price'],
        ];
    }

    /**
     * @dataProvider moneyCastProvider
     */
    public function test_money_attribute_is_cast_to_two_decimal_places(string $modelClass, string $attribute): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();

        // Excess precision is rounded to the cent on read.
        $model->{$attribute} = 1234.567;
        $this->assertSame('1234.57', $model->{$attribute});

        // Whole numbers gain the fixed two-decimal scale.
        $model->{$attribute} = 1000;
        $this->assertSame('1000.00', $model->{$attribute});

        // Null passes through untouched (nullable columns stay nullable).
        $model->{$attribute} = null;
        $this->assertNull($model->{$attribute});
    }

    public function test_decimal_precision_survives_a_real_save_and_reload(): void
    {
        // seller_service_auction_bids has no FK constraints, so it round-trips
        // cleanly on the in-memory SQLite connection.
        $id = DB::table('seller_service_auction_bids')->insertGetId([
            'seller_service_auction_id' => 1,
            'user_id'                   => 1,
            'name'                      => 'Test Agent',
            'phone'                     => '5550000',
            'email'                     => 'agent@example.test',
            'brokerage'                 => 1234.567,   // -> 1234.57
            'license_no'                => 'LIC-1',
            'price_in'                  => '$',
            'price'                     => 7.899,      // -> 7.90
            'additional_details'        => '',
            'card'                      => '',
            'video'                     => '',
            'audio'                     => '',
            'note'                      => '',
            'accepted'                  => false,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        $bid = SellerServiceAuctionBid::findOrFail($id);

        $this->assertSame('1234.57', (string) $bid->brokerage);
        $this->assertSame('7.90', (string) $bid->price);
    }
}
