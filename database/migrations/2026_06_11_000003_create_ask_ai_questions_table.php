<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ask_ai_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snapshot_id')->index();
            $table->string('canonical_key', 120)->nullable()->index();
            $table->string('field_type', 30)->default('faq');
            $table->string('keyword_route_status', 40)->nullable();
            $table->text('label')->nullable();
            $table->text('sample_question')->nullable();
            $table->text('sample_question_2')->nullable();
            $table->timestamps();

            $table->foreign('snapshot_id')
                  ->references('id')
                  ->on('ask_ai_knowledge_snapshots')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_ai_questions');
    }
};
