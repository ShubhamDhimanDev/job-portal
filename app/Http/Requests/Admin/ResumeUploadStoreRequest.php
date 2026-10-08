<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResumeUploadStoreRequest extends FormRequest
{
    /**
     * Legacy .doc files are detected as msword, or as a generic OLE container
     * when they were saved by a tool other than Word.
     *
     * @var array<int, string>
     */
    private const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.ms-office',
        'application/x-ole-storage',
        'application/CDFV2',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip-compressed',
    ];

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
            'upload' => ['required', 'file', 'extensions:pdf,doc,docx,zip', 'mimetypes:'.implode(',', self::ACCEPTED_MIME_TYPES), 'max:102400'],
            'job_posting_id' => ['nullable', 'integer', Rule::exists('job_postings', 'id')],
            'rate_with_ai' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'upload.extensions' => 'Upload a PDF, DOC or DOCX resume, or a ZIP of them.',
            'upload.mimetypes' => 'Upload a PDF, DOC or DOCX resume, or a ZIP of them.',
            'upload.max' => 'The upload must be smaller than 100MB.',
        ];
    }
}
