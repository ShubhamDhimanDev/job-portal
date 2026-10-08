<?php

namespace App\Exports;

use App\Concerns\FiltersCandidates;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class CandidatesExport extends DefaultValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    use Exportable, FiltersCandidates;

    /**
     * @param  array<string, mixed>  $filters  Any of the candidates list filters (see FiltersCandidates), plus `ids` to export only those candidates.
     */
    public function __construct(private readonly array $filters = []) {}

    /**
     * @return Builder<JobApplication>
     */
    public function query(): Builder
    {
        return $this->applyCandidateFilters(
            JobApplication::query()->with('jobPosting.company'),
            $this->filters
        )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Candidate Code',
            'Job Code',
            'Job Title',
            'Company',
            'Candidate Name',
            'Email',
            'Phone',
            'Status',
            'Applied Date',
            'Resume Filename',
            'Gender',
            'Date of Birth',
            'Total Experience (Years)',
            'Relevant Experience (Years)',
            'Current Company',
            'Industry Type',
            'Current Designation',
            'Current Location',
            'Current CTC',
            'Expected CTC',
            'Notice Period',
            'Interview Type',
            'Skills',
            'Comment',
            'AI Fit Score (out of 10)',
            'AI Rating Status',
            'AI Reasoning',
            'AI Strengths',
            'AI Gaps',
        ];
    }

    /**
     * Write every piece of text into the sheet as text. Much of it comes from
     * outside (applicants, resumes, the AI), and spreadsheet software would
     * otherwise run anything that starts with "=" as a formula. Phone numbers
     * also keep their leading zeros this way.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * Every field on the candidates list and in the edit form, plus the AI
     * rating. Anything a candidate does not have is left blank.
     *
     * @param  JobApplication  $row
     * @return array<int, string|float|int|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row->code,
            $row->jobPosting?->code,
            $row->jobPosting?->title,
            $row->jobPosting?->company?->name,
            $row->name,
            $row->email,
            $row->phone,
            $row->status->label(),
            $row->created_at?->format('Y-m-d H:i'),
            $row->resume_path !== null ? basename($row->resume_path) : null,
            $row->gender?->label(),
            $row->date_of_birth?->format('Y-m-d'),
            $row->total_experience,
            $row->relevant_experience,
            $row->current_company,
            $row->industry_type,
            $row->current_designation,
            $row->current_location,
            $row->current_ctc,
            $row->expected_ctc,
            $row->notice_period,
            $row->interview_type?->label(),
            $this->joined($row->skills, ', '),
            $this->filled($row->admin_notes),
            $row->ai_score,
            $row->job_posting_id !== null && $row->resume_path !== null ? $row->ai_status->label() : null,
            $this->filled($row->ai_reasoning),
            $this->joined($row->ai_strengths, '; '),
            $this->joined($row->ai_gaps, '; '),
        ];
    }

    /**
     * @param  array<int, mixed>|null  $values
     */
    private function joined(?array $values, string $separator): ?string
    {
        $texts = array_filter(array_map(
            fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $values ?? [],
        ), fn (string $text): bool => $text !== '');

        return $texts === [] ? null : implode($separator, $texts);
    }

    private function filled(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : $text;
    }
}
