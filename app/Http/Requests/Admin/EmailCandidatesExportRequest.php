<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\NoticePeriodFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailCandidatesExportRequest extends FormRequest
{
    /**
     * Authorization is already enforced by the `auth` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['required', 'email'],
            'cc' => ['sometimes', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['sometimes', 'array'],
            'bcc.*' => ['email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],

            // Same filter fields as the candidates list / export, so the emailed
            // export matches whatever is currently filtered on screen.
            'job_posting_id' => ['sometimes', 'nullable', Rule::anyOf([
                ['integer', Rule::exists('job_postings', 'id')],
                [Rule::in(['none'])],
            ])],
            'status' => ['sometimes', 'nullable', Rule::enum(ApplicationStatus::class)],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'experience_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_basis' => ['sometimes', 'nullable', Rule::in(['current', 'expected'])],
            'salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notice_period' => ['sometimes', 'nullable', Rule::enum(NoticePeriodFilter::class)],
            'duplicates' => ['sometimes', 'nullable', 'boolean'],
            'skills' => ['sometimes', 'nullable', 'array', 'max:20'],
            'skills.*' => ['string', 'max:50'],
            'skills_match' => ['sometimes', 'nullable', Rule::in(['all', 'any'])],

            // Only these candidates, when rows were ticked in the list.
            'ids' => ['sometimes', 'nullable', 'array', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
