<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentDefaultProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('agent_default_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('role_type');
            $table->string('property_type');
            $table->jsonb('profile_data')->default('{}');
            $table->timestamps();
            $table->unique(['user_id', 'role_type', 'property_type'], 'agent_default_profiles_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_default_profiles');
    }
}
