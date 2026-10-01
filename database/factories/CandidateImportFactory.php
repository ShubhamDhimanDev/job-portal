<?php

namespace Database\Factories;

use App\Enums\CandidateImportStatus;
use App\Models\CandidateImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateImport>
 */
class CandidateImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => CandidateImportStatus::Completed,
            'rate_with_ai' => true,
            'spreadsheet_path' => 'imports/'.fake()->uuid().'/candidates.csv',
            'archive_path' => null,
            'total' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'issues' => [],
        ];
    }
}
