<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToRentalQualificationChecksTable extends Migration
{
    public function up()
    {
        Schema::table('rental_qualification_checks', function (Blueprint $table) {
            $table->string('employment_status_other', 200)->nullable()->after('employment_status');
            $table->string('income_source', 100)->nullable()->after('employment_status_other');
            $table->string('has_pets', 20)->nullable()->after('income_source');
            $table->text('pet_details')->nullable()->after('has_pets');
            $table->string('smoking', 50)->nullable()->after('pet_details');
            $table->string('criminal_background', 100)->nullable()->after('smoking');
            $table->string('criminal_background_other', 500)->nullable()->after('criminal_background');
            $table->string('landlord_reference_available', 50)->nullable()->after('criminal_background_other');
            $table->string('employment_verification_available', 20)->nullable()->after('landlord_reference_available');
            $table->string('income_verification_available', 20)->nullable()->after('employment_verification_available');
            $table->boolean('consent_to_screening')->nullable()->after('income_verification_available');
            $table->date('desired_move_in_date')->nullable()->after('consent_to_screening');
            $table->text('applicant_profile')->nullable()->after('desired_move_in_date');
        });
    }

    public function down()
    {
        Schema::table('rental_qualification_checks', function (Blueprint $table) {
            $table->dropColumn([
                'employment_status_other',
                'income_source',
                'has_pets',
                'pet_details',
                'smoking',
                'criminal_background',
                'criminal_background_other',
                'landlord_reference_available',
                'employment_verification_available',
                'income_verification_available',
                'consent_to_screening',
                'desired_move_in_date',
                'applicant_profile',
            ]);
        });
    }
}
