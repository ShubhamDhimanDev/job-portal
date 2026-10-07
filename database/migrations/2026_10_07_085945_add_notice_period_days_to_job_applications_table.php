<?php

use App\Actions\Candidates\ParseNoticePeriodDays;
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
            $table->unsignedSmallInteger('notice_period_days')->nullable()->after('notice_period');
        });

        $parseNoticePeriodDays = new ParseNoticePeriodDays;

        DB::table('job_applications')
            ->whereNotNull('notice_period')
            ->select(['id', 'notice_period'])
            ->chunkById(500, function ($applications) use ($parseNoticePeriodDays): void {
                foreach ($applications as $application) {
                    DB::table('job_applications')
                        ->where('id', $application->id)
                        ->update(['notice_period_days' => $parseNoticePeriodDays->handle($application->notice_period)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn('notice_period_days');
        });
    }
};
