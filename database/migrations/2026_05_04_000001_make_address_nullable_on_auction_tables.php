<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeAddressNullableOnAuctionTables extends Migration
{
    public function up()
    {
        Schema::table('buyer_agent_auctions', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
        });

        Schema::table('seller_agent_auctions', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('buyer_agent_auctions', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
        });

        Schema::table('seller_agent_auctions', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
        });
    }
}
