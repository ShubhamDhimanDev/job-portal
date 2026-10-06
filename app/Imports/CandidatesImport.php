<?php

namespace App\Imports;

use App\Actions\Candidates\CreateCandidateApplication;
use App\Enums\Gender;
use App\Enums\InterviewType;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class CandidatesImport implements ToCollection, WithHeadingRow
{
    private const MAX_RESUME_BYTES = 5 * 1024 * 1024;

    private const RESUME_EXTENSIONS = ['pdf', 'doc', 'docx'];

    public int $total = 0;

    public int $created = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /**
     * @var array<int, array{row: int, type: string, name: string, email: string, reason: string}>
     */
    public array $issues = [];

    /**
     * @var array<string, JobPosting|null>
     */
    private array $jobCache = [];

    /**
     * @param  array<string, string>  $resumeFiles  Lowercased filename => absolute path.
     */
    public function __construct(
        private readonly CreateCandidateApplication $createCandidateApplication,
        private readonly array $resumeFiles,
        private readonly bool $rateWithAi,
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $values = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row->all());

            if (collect($values)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            $this->total++;
            $this->importRow($index + 2, $values);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function importRow(int $rowNumber, array $values): void
    {
        $name = (string) ($values['name'] ?? '');
        $email = (string) ($values['email'] ?? '');

        $validator = Validator::make($values, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'max:50'],
            'job' => ['required'],
            'resume_filename' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $this->recordIssue($rowNumber, 'failed', $name, $email, $validator->errors()->first());

            return;
        }

        $jobPosting = $this->resolveJob((string) $values['job']);

        if ($jobPosting === null) {
            $this->recordIssue($rowNumber, 'failed', $name, $email, "Job '{$values['job']}' was not found.");

            return;
        }

        $isDuplicate = JobApplication::query()
            ->where('job_posting_id', $jobPosting->id)
            ->where('email', $email)
            ->exists();

        if ($isDuplicate) {
            $this->recordIssue($rowNumber, 'skipped', $name, $email, 'Already added to this job.');

            return;
        }

        $resumeFilename = trim((string) ($values['resume_filename'] ?? ''));
        $resumePath = $this->resolveResume($resumeFilename, $warning);

        if ($warning !== null) {
            $this->recordIssue($rowNumber, 'warning', $name, $email, "Added without a resume: {$warning}");
        }

        $profile = $this->parseProfile($values, $ignoredFields);

        if ($ignoredFields !== []) {
            $this->recordIssue($rowNumber, 'warning', $name, $email, 'Ignored invalid value for: '.implode(', ', $ignoredFields).'.');
        }

        $this->createCandidateApplication->handle(
            $jobPosting,
            ['name' => $name, 'email' => $email, 'phone' => (string) $values['phone'], ...$profile],
            $resumePath,
            $this->rateWithAi,
        );

        $this->created++;
    }

    /**
     * Read the optional profile columns, dropping any invalid value and
     * naming it in $ignoredFields so the rest of the row can still import.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>|null  $ignoredFields
     * @return array<string, mixed>
     */
    private function parseProfile(array $values, ?array &$ignoredFields): array
    {
        $ignoredFields = [];

        $raw = [
            'gender' => $this->matchEnum(Gender::class, $values['gender'] ?? null),
            'interview_type' => $this->matchEnum(InterviewType::class, $values['interview_type'] ?? null),
            'date_of_birth' => $this->parseDate($values['date_of_birth'] ?? null),
        ];

        foreach (['total_experience', 'relevant_experience', 'current_ctc', 'expected_ctc', 'current_company', 'industry_type', 'current_designation', 'current_location', 'notice_period'] as $field) {
            $raw[$field] = $values[$field] ?? null;
        }

        $validator = Validator::make($raw, [
            'gender' => ['nullable'],
            'interview_type' => ['nullable'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'total_experience' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'relevant_experience' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'current_ctc' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'industry_type' => ['nullable', 'string', 'max:255'],
            'current_designation' => ['nullable', 'string', 'max:255'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'notice_period' => ['nullable', 'string', 'max:100'],
        ]);

        $invalid = array_keys($validator->errors()->toArray());

        foreach (['gender', 'interview_type', 'date_of_birth'] as $field) {
            if (filled($values[$field] ?? null) && $raw[$field] === null && ! in_array($field, $invalid, true)) {
                $invalid[] = $field;
            }
        }

        $ignoredFields = $invalid;

        return collect($raw)
            ->except($invalid)
            ->map(fn (mixed $value): mixed => $value === '' ? null : (is_scalar($value) ? $value : null))
            ->filter(fn (mixed $value): bool => $value !== null)
            ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->all();
    }

    /**
     * Match a cell against an enum's values or labels, ignoring case and separators.
     *
     * @param  class-string<Gender|InterviewType>  $enum
     */
    private function matchEnum(string $enum, mixed $cell): ?string
    {
        $normalise = fn (string $text): string => preg_replace('/[^a-z]/', '', strtolower($text)) ?? '';

        if (! is_string($cell) || $normalise($cell) === '') {
            return null;
        }

        foreach ($enum::cases() as $case) {
            if ($normalise($cell) === $normalise($case->value) || $normalise($cell) === $normalise($case->label())) {
                return $case->value;
            }
        }

        return null;
    }

    /**
     * Excel stores dates as serial numbers; CSV files carry plain date text.
     */
    private function parseDate(mixed $cell): ?string
    {
        if (is_numeric($cell)) {
            return Date::excelToDateTimeObject((float) $cell)->format('Y-m-d');
        }

        if (! is_string($cell) || trim($cell) === '') {
            return null;
        }

        $timestamp = strtotime(trim($cell));

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * Find the resume for a row, describing why in $warning when it can't be used.
     */
    private function resolveResume(string $filename, ?string &$warning): ?string
    {
        $warning = null;

        if ($filename === '') {
            $warning = 'no resume filename given.';

            return null;
        }

        $path = $this->resumeFiles[strtolower(basename($filename))] ?? null;

        if ($path === null) {
            $warning = "'{$filename}' was not found in the ZIP.";

            return null;
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::RESUME_EXTENSIONS, true)) {
            $warning = 'resume must be a PDF, DOC, or DOCX file.';

            return null;
        }

        if (filesize($path) > self::MAX_RESUME_BYTES) {
            $warning = 'resume must be smaller than 5MB.';

            return null;
        }

        return $path;
    }

    private function resolveJob(string $reference): ?JobPosting
    {
        return $this->jobCache[$reference] ??= JobPosting::query()
            ->where('slug', $reference)
            ->when(ctype_digit($reference), fn ($query) => $query->orWhere('id', (int) $reference))
            ->first();
    }

    private function recordIssue(int $row, string $type, string $name, string $email, string $reason): void
    {
        match ($type) {
            'skipped' => $this->skipped++,
            'failed' => $this->failed++,
            default => null,
        };

        $this->issues[] = compact('row', 'type', 'name', 'email', 'reason');
    }
}
