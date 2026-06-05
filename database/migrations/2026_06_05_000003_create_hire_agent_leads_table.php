<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hire_agent_leads', function (Blueprint $table) {
            $table->id();

            // Which listing page the lead originated from
            $table->string('listing_type', 32);     // seller_offer / buyer_offer / landlord_offer / tenant_offer
            $table->unsignedBigInteger('listing_id');

            // What the requester chose in the modal
            $table->string('rep_type', 20);          // buyer / seller / landlord / tenant
            $table->string('property_type', 32);

            // Contact info (guest-friendly — no auth required)
            $table->string('requester_name', 191);
            $table->string('requester_email', 191);
            $table->string('requester_phone', 64)->nullable();
            $table->text('message')->nullable();

            // Matched agent (write-once after creation)
            $table->unsignedBigInteger('target_agent_id')->nullable();

            $table->string('status', 20)->default('new'); // new / pending / accepted / declined

            $table->timestamps();

            $table->index(['target_agent_id', 'status']);
            $table->index(['listing_type', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hire_agent_leads');
    }
};
