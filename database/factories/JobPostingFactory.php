<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\WorkMode;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();
        $minSalary = fake()->numberBetween(3, 20) * 100000;

        return [
            'company_id' => Company::factory(),
            'posted_by_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 999999),
            'description' => fake()->paragraphs(3, true),
            'responsibilities' => fake()->paragraphs(2, true),
            'requirements' => fake()->paragraphs(2, true),
            'location' => fake()->city(),
            'work_mode' => fake()->randomElement(WorkMode::cases()),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'experience_level' => fake()->numberBetween(0, 3).'-'.fake()->numberBetween(4, 10).' years',
            'min_salary' => $minSalary,
            'max_salary' => $minSalary + fake()->numberBetween(1, 10) * 100000,
            'salary_negotiable' => fake()->boolean(),
            'department' => fake()->randomElement(['Engineering', 'Sales', 'Marketing', 'Operations', 'Finance']),
            'vacancies' => fake()->numberBetween(1, 5),
            'status' => JobStatus::Draft,
            'application_deadline' => fake()->dateTimeBetween('+1 week', '+2 months'),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JobStatus::Published,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JobStatus::Closed,
        ]);
    }
}
