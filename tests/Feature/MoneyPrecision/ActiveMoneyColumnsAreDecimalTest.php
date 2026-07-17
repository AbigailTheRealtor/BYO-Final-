<?php

namespace Tests\Feature\MoneyPrecision;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Batch B1.3 (Money Precision) — Part 1 migration test.
 *
 * Verifies that 2026_07_17_000001_convert_active_money_columns_to_decimal has
 * converted every targeted active money/percentage column from binary float to
 * fixed-precision DECIMAL. dbal reports SQLite NUMERIC columns as 'decimal', so
 * asserting the resolved column type is 'decimal' proves the ->change() ran
 * (the original float/double columns resolve as 'float'/'double').
 */
class ActiveMoneyColumnsAreDecimalTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0:string,1:string}> */
    public static function moneyColumnProvider(): array
    {
        return [
            'property_auction_bids.price'            => ['property_auction_bids', 'price'],
            'property_auction_bids.escrow_amount'    => ['property_auction_bids', 'escrow_amount'],
            'seller_agent_auction_bids.brokerage'    => ['seller_agent_auction_bids', 'brokerage'],
            'seller_agent_auction_bids.price'        => ['seller_agent_auction_bids', 'price'],
            'seller_agent_auction_bids.price_percent'=> ['seller_agent_auction_bids', 'price_percent'],
            'buyer_agent_auction_bids.brokerage'     => ['buyer_agent_auction_bids', 'brokerage'],
            'buyer_agent_auction_bids.price'         => ['buyer_agent_auction_bids', 'price'],
            'buyer_agent_auction_bids.price_percent' => ['buyer_agent_auction_bids', 'price_percent'],
            'agent_service_auction_bids.price'       => ['agent_service_auction_bids', 'price'],
            'seller_service_auctions.price'          => ['seller_service_auctions', 'price'],
            'seller_service_auction_bids.brokerage'  => ['seller_service_auction_bids', 'brokerage'],
            'seller_service_auction_bids.price'      => ['seller_service_auction_bids', 'price'],
        ];
    }

    /**
     * @dataProvider moneyColumnProvider
     */
    public function test_active_money_column_is_decimal(string $table, string $column): void
    {
        $this->assertTrue(
            Schema::hasColumn($table, $column),
            "Expected {$table}.{$column} to exist."
        );

        $this->assertSame(
            'decimal',
            Schema::getColumnType($table, $column),
            "Expected {$table}.{$column} to be DECIMAL after the B1.3 money-precision migration."
        );
    }

    public function test_out_of_scope_float_columns_are_left_untouched(): void
    {
        // buyer_agent_auctions.concession is a dormant EAV-bypassed native column,
        // explicitly excluded from B1.3. It must remain a floating-point type.
        $this->assertNotSame(
            'decimal',
            Schema::getColumnType('buyer_agent_auctions', 'concession'),
            'buyer_agent_auctions.concession is out of B1.3 scope and must not be converted.'
        );
    }
}
