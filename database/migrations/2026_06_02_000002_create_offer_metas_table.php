<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOfferMetasTable extends Migration
{
    public function up()
    {
        Schema::create('offer_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')
                  ->constrained('offers')
                  ->cascadeOnDelete();
            $table->string('meta_key', 100);
            $table->text('meta_value')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'meta_key']);
            $table->index('meta_key');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offer_metas');
    }
}
