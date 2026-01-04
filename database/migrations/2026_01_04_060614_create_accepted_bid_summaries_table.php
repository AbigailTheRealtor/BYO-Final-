<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcceptedBidSummariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accepted_bid_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('accepted_bid_id');
            $table->unsignedBigInteger('accepted_counter_id')->nullable();
            $table->unsignedBigInteger('tenant_user_id');
            $table->unsignedBigInteger('agent_user_id');
            $table->longText('summary_html');
            $table->string('summary_pdf_path')->nullable();
            $table->string('tenant_signature_name')->nullable();
            $table->timestamp('tenant_signed_at')->nullable();
            $table->string('tenant_ip_address')->nullable();
            $table->string('agent_signature_name')->nullable();
            $table->timestamp('agent_signed_at')->nullable();
            $table->string('agent_ip_address')->nullable();
            $table->timestamps();
            
            $table->index('listing_id');
            $table->index('accepted_bid_id');
            $table->index('tenant_user_id');
            $table->index('agent_user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accepted_bid_summaries');
    }
}
