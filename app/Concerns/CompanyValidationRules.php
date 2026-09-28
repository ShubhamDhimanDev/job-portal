<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait CompanyValidationRules
{
    /**
     * Get the validation rules used to validate a company record.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function companyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
