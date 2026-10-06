<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->decimal('total_experience', 4, 1)->nullable()->after('date_of_birth');
            $table->decimal('relevant_experience', 4, 1)->nullable()->after('total_experience');
            $table->string('current_company')->nullable()->after('relevant_experience');
            $table->string('industry_type')->nullable()->after('current_company');
            $table->string('current_designation')->nullable()->after('industry_type');
            $table->string('current_location')->nullable()->after('current_designation');
            $table->decimal('current_ctc', 10, 2)->nullable()->after('current_location');
            $table->decimal('expected_ctc', 10, 2)->nullable()->after('current_ctc');
            $table->string('notice_period')->nullable()->after('expected_ctc');
            $table->string('interview_type')->nullable()->after('notice_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'date_of_birth',
                'total_experience',
                'relevant_experience',
                'current_company',
                'industry_type',
                'current_designation',
                'current_location',
                'current_ctc',
                'expected_ctc',
                'notice_period',
                'interview_type',
            ]);
        });
    }
};
