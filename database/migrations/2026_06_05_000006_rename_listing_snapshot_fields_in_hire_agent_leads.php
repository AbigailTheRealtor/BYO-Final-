<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hire_agent_leads', function (Blueprint $table) {
            $table->renameColumn('listing_title', 'source_listing_title');
            $table->renameColumn('listing_url',   'source_listing_url');
        });
    }

    public function down(): void
    {
        Schema::table('hire_agent_leads', function (Blueprint $table) {
            $table->renameColumn('source_listing_title', 'listing_title');
            $table->renameColumn('source_listing_url',   'listing_url');
        });
    }
};
