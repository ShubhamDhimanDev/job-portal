<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ValidatesCandidateProfile;
use App\Models\JobApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCandidateStoreRequest extends FormRequest
{
    use ValidatesCandidateProfile;

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
            ...$this->candidateProfileRules(),
            'job_posting_id' => ['required', 'integer', Rule::exists('job_postings', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('job_applications', 'email')
                    ->where(fn ($query) => $query->where('job_posting_id', $this->input('job_posting_id'))),
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
            'email.unique' => 'This candidate has already been added to the selected job.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number (starting with 6-9, optional +91).',
            'resume.mimes' => 'Resume must be a PDF, DOC, or DOCX file.',
            'resume.max' => 'Resume must be smaller than 5MB.',
        ];
    }
}
