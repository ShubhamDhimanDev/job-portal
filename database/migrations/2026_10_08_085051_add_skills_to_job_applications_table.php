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
            $table->json('skills')->nullable()->after('ai_profile');
        });

        $normalizeSkills = new NormalizeSkills;

        DB::table('job_applications')
            ->whereNotNull('ai_profile')
            ->select(['id', 'ai_profile'])
            ->chunkById(500, function ($applications) use ($normalizeSkills): void {
                foreach ($applications as $application) {
                    $profile = json_decode($application->ai_profile, true);
                    $skills = $normalizeSkills->handle(is_array($profile['skills'] ?? null) ? $profile['skills'] : []);

                    if ($skills !== []) {
                        DB::table('job_applications')
                            ->where('id', $application->id)
                            ->update(['skills' => json_encode($skills)]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn('skills');
        });
    }
};
