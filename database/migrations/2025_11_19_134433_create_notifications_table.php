<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('notifications')) {
            return;
        }
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();               // UUID primary key
            $table->string('type');                      // Notification class
            $table->morphs('notifiable');                // creates notifiable_id + notifiable_type
            $table->text('data');                        // JSON data
            $table->timestamp('read_at')->nullable();    // read timestamp
            $table->timestamps();                        // created_at & updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
