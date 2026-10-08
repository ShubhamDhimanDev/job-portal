<?php

namespace App\Actions\Candidates;

use App\Ai\Agents\ResumeParserAgent;
use App\Concerns\ValidatesCandidateProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Files;
use RuntimeException;

class ParseResume
{
    use ValidatesCandidateProfile;

    public function __construct(private readonly ExtractDocumentText $extractDocumentText) {}

    /**
     * Read a resume stored on the local disk with the AI parser and return the
     * candidate fields it found. Optional profile values that fail the usual
     * candidate validation are dropped rather than failing the whole resume.
     *
     * @return array{name: string, email: string, phone: string, profile: array<string, mixed>}
     */
    public function handle(string $path): array
    {
        $isPdf = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
        $agent = new ResumeParserAgent;

        $response = $isPdf
            ? $agent->prompt(
                'Extract the candidate details from the attached resume.',
                attachments: [Files\Document::fromStorage($path, disk: 'local')],
                provider: config('services.resume_parsing.provider'),
                model: config('services.resume_parsing.model'),
            )
            : $agent->prompt(
                "Extract the candidate details from this resume. Resume text follows:\n\n".$this->readText($path),
                provider: config('services.resume_parsing.provider'),
                model: config('services.resume_parsing.model'),
            );

        return [
            'name' => $this->clean($response['name'] ?? null) ?? '',
            'email' => strtolower($this->clean($response['email'] ?? null) ?? ''),
            'phone' => $this->clean($response['phone'] ?? null) ?? '',
            'profile' => $this->validProfile($response->toArray()),
        ];
    }

    private function readText(string $path): string
    {
        $text = $this->extractDocumentText->handle($path);

        if ($text === '') {
            throw new RuntimeException('No readable text was found in the document.');
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function validProfile(array $parsed): array
    {
        $values = array_filter([
            'gender' => $this->clean($parsed['gender'] ?? null),
            'date_of_birth' => $this->clean($parsed['date_of_birth'] ?? null),
            'total_experience' => is_numeric($parsed['total_experience_years'] ?? null) ? $parsed['total_experience_years'] : null,
            'current_company' => $this->clean($parsed['current_company'] ?? null),
            'current_designation' => $this->clean($parsed['current_designation'] ?? null),
            'current_location' => $this->clean($parsed['current_location'] ?? null),
            'industry_type' => $this->clean($parsed['industry_type'] ?? null),
            'notice_period' => $this->clean($parsed['notice_period'] ?? null),
        ], fn (mixed $value): bool => $value !== null);

        $validator = Validator::make($values, Arr::only($this->candidateProfileRules(), array_keys($values)));

        return Arr::except($values, array_keys($validator->errors()->toArray()));
    }

    private function clean(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
