<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcknowledgementDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('acknowledgement_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accepted_bid_summary_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('selected_agent_user_id')->nullable();
            $table->string('id_document_path')->nullable();
            $table->string('proof_of_funds_path')->nullable();
            $table->string('pre_approval_letter_path')->nullable();
            $table->string('proof_of_income_path')->nullable();
            $table->text('property_record_link')->nullable();
            $table->timestamps();

            $table->index('accepted_bid_summary_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('acknowledgement_documents');
    }
}
