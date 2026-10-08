<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'resume_path' => 'resumes/'.fake()->uuid().'.pdf',
            'cover_note' => fake()->optional()->paragraph(),
            'status' => ApplicationStatus::New,
            'admin_notes' => null,
        ];
    }

    /**
     * A candidate who is not assigned to any job.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes): array => ['job_posting_id' => null]);
    }
}
