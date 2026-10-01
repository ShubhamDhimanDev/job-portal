<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportCandidatesRequest extends FormRequest
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
        return [
            'spreadsheet' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'archive' => ['nullable', 'file', 'mimes:zip', 'max:102400'],
            'rate_with_ai' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spreadsheet.mimes' => 'The candidate list must be a CSV or XLSX file.',
            'archive.mimes' => 'The resumes archive must be a ZIP file.',
            'archive.max' => 'The resumes archive must be smaller than 100MB.',
        ];
    }
}
