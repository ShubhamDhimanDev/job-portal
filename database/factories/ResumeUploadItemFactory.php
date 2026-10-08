<?php

namespace Database\Factories;

use App\Enums\ResumeUploadItemStatus;
use App\Models\ResumeUpload;
use App\Models\ResumeUploadItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeUploadItem>
 */
class ResumeUploadItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resume_upload_id' => ResumeUpload::factory(),
            'job_application_id' => null,
            'filename' => fake()->word().'.pdf',
            'file_path' => null,
            'status' => ResumeUploadItemStatus::Created,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'reason' => null,
        ];
    }
}
