<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const PREFIXES = [
        'job_postings' => 'JOB',
        'job_applications' => 'CAN',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::PREFIXES as $tableName => $prefix) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('code', 20)->nullable()->unique()->after('id');
            });

            DB::table($tableName)
                ->select('id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName, $prefix): void {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['code' => $prefix.'-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT)]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys(self::PREFIXES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }
};
