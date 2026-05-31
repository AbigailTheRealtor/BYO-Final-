<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateByaReviewLogsTable extends Migration
{
    public function up()
    {
        Schema::create('bya_review_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_compatibility_score_id');
            $table->unsignedBigInteger('reviewer_user_id');
            $table->enum('status', [
                'pending_review',
                'in_review',
                'approved',
                'approved_with_notes',
                'flagged',
                'rejected',
            ]);
            $table->text('notes')->nullable();
            $table->json('fair_housing_checklist')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('listing_compatibility_score_id')
                  ->references('id')
                  ->on('listing_compatibility_scores')
                  ->onDelete('restrict');

            $table->foreign('reviewer_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            $table->index('listing_compatibility_score_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bya_review_logs');
    }
}
