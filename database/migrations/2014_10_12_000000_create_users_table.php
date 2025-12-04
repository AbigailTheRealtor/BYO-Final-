<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name');
            $table->string('short_id');
            $table->string('user_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('user_type',['admin','buyer','seller','buyer_agent','seller_agent']);
            // $table->string('account_type')->nullable();
            $table->string('mls_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->json('search_preferences')->nullable();
            $table->string('language')->nullable();
            $table->integer('country_id')->nullable();
            $table->integer('state_id')->nullable();
            $table->text('address1')->nullable();
            $table->text('address2')->nullable();
            $table->integer('city_id')->nullable();
            $table->string('town')->nullable();
            $table->string('zip')->nullable();
            $table->string('avatar')->nullable();
            $table->string('cover_photo')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_super')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
