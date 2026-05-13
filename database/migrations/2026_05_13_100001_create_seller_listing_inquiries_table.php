<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerListingInquiriesTable extends Migration
{
    public function up()
    {
        Schema::create('seller_listing_inquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auction_id');
            $table->string('type', 32); // 'question' or 'showing'
            $table->string('name', 191);
            $table->string('email', 191);
            $table->string('phone', 64)->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time', 32)->nullable();
            $table->text('message')->nullable();
            $table->text('question')->nullable();
            $table->string('status', 32)->default('new');
            $table->string('source', 64)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('auction_id');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seller_listing_inquiries');
    }
}
