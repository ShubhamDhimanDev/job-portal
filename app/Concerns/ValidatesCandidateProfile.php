<?php

namespace App\Concerns;

use App\Enums\Gender;
use App\Enums\InterviewType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesCandidateProfile
{
    /**
     * Rules for the optional profile details collected about a candidate.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function candidateProfileRules(): array
    {
        return [
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'total_experience' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'relevant_experience' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'industry_type' => ['nullable', 'string', 'max:255'],
            'current_designation' => ['nullable', 'string', 'max:255'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'current_ctc' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notice_period' => ['nullable', 'string', 'max:100'],
            'interview_type' => ['nullable', Rule::enum(InterviewType::class)],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function candidateProfileFields(): array
    {
        return array_keys($this->candidateProfileRules());
    }
}
