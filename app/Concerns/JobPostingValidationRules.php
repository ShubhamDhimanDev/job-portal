<?php

namespace App\Concerns;

use App\Enums\EmploymentType;
use App\Enums\WorkMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait JobPostingValidationRules
{
    /**
     * Get the validation rules used to validate a job posting.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function jobPostingRules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'responsibilities' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'work_mode' => ['required', Rule::enum(WorkMode::class)],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'experience_level' => ['nullable', 'string', 'max:255'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_negotiable' => ['boolean'],
            'department' => ['nullable', 'string', 'max:255'],
            'vacancies' => ['required', 'integer', 'min:1'],
            'application_deadline' => ['nullable', 'date'],
        ];
    }
}
