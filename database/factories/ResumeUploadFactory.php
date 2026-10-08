<?php

namespace Database\Factories;

use App\Enums\ResumeUploadStatus;
use App\Models\ResumeUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeUpload>
 */
class ResumeUploadFactory extends Factory
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
            'job_posting_id' => null,
            'original_filename' => fake()->word().'.zip',
            'upload_path' => null,
            'rate_with_ai' => false,
            'status' => ResumeUploadStatus::Completed,
            'total' => 0,
            'error' => null,
        ];
    }
}
