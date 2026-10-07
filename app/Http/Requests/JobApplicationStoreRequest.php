<?php

namespace App\Http\Requests;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobApplicationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var JobPosting $jobPosting */
        $jobPosting = $this->route('jobPosting');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('job_applications', 'email')
                    ->where(fn ($query) => $query->where('job_posting_id', $jobPosting->id)),
            ],
            'phone' => ['required', 'string', 'max:50', 'regex:'.JobApplication::PHONE_PATTERN],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'cover_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => "You've already applied to this role.",
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number (starting with 6-9, optional +91).',
            'resume.mimes' => 'Resume must be a PDF, DOC, or DOCX file.',
            'resume.max' => 'Resume must be smaller than 5MB.',
        ];
    }
}
