<?php

namespace App\Exports;

use App\Concerns\FiltersCandidates;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CandidatesExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable, FiltersCandidates;

    /**
     * @param  array{job_posting_id?: int|string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null, duplicates?: string|bool|null}  $filters
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
        ];
    }

    /**
     * @param  JobApplication  $row
     * @return array<int, string|float|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row->jobPosting->title,
            $row->jobPosting->company?->name,
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
        ];
    }
}
