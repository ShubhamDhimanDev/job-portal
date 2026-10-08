<?php

use App\Actions\Candidates\NormalizeSkills;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->text('skills_search')->nullable()->after('skills');
        });

        $normalizeSkills = new NormalizeSkills;

        DB::table('job_applications')
            ->whereNotNull('skills')
            ->select(['id', 'skills'])
            ->chunkById(500, function ($applications) use ($normalizeSkills): void {
                foreach ($applications as $application) {
                    $skills = json_decode($application->skills, true);

                    DB::table('job_applications')
                        ->where('id', $application->id)
                        ->update(['skills_search' => $normalizeSkills->searchColumn(is_array($skills) ? $skills : [])]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn('skills_search');
        });
    }
};
