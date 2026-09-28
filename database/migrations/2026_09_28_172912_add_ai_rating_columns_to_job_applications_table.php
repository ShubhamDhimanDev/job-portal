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
            $table->string('ai_status')->default('pending')->after('admin_notes');
            $table->unsignedTinyInteger('ai_score')->nullable()->after('ai_status');
            $table->text('ai_reasoning')->nullable()->after('ai_score');
            $table->json('ai_strengths')->nullable()->after('ai_reasoning');
            $table->json('ai_gaps')->nullable()->after('ai_strengths');
            $table->json('ai_profile')->nullable()->after('ai_gaps');
            $table->timestamp('ai_rated_at')->nullable()->after('ai_profile');
            $table->text('ai_error')->nullable()->after('ai_rated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn([
                'ai_status',
                'ai_score',
                'ai_reasoning',
                'ai_strengths',
                'ai_gaps',
                'ai_profile',
                'ai_rated_at',
                'ai_error',
            ]);
        });
    }
};
