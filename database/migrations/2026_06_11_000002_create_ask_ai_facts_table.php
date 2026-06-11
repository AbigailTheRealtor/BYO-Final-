<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ask_ai_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snapshot_id')->index();
            $table->string('canonical_key', 120)->nullable()->index();
            $table->text('value')->nullable();
            $table->string('visibility', 20)->default('public_allowed');
            $table->timestamps();

            $table->foreign('snapshot_id')
                  ->references('id')
                  ->on('ask_ai_knowledge_snapshots')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_ai_facts');
    }
};
