<?php

namespace App\Http\Requests\Admin;

use App\Concerns\JobPostingValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class JobPostingStoreRequest extends FormRequest
{
    use JobPostingValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->jobPostingRules();
    }
}
