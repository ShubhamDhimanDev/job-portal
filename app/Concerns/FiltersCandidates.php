<?php

namespace App\Concerns;

use App\Actions\Candidates\FindDuplicateCandidates;
use App\Enums\NoticePeriodFilter;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Builder;

trait FiltersCandidates
{
    /**
     * Apply the shared candidate filter set (job, status, date range, search,
     * experience, salary, notice period) used by both the admin candidates
     * list and the Excel export.
     *
     * Experience is total experience in years. Salary is compared against
     * expected CTC unless `salary_basis` is "current". Notice period matches
     * candidates who can join within the chosen option. Candidates missing the
     * filtered value are left out, and unusable values are ignored.
     *
     * @param  Builder<JobApplication>  $query
     * @param  array{job_posting_id?: int|string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null, duplicates?: string|bool|null, experience_min?: int|float|string|null, experience_max?: int|float|string|null, salary_basis?: string|null, salary_min?: int|float|string|null, salary_max?: int|float|string|null, notice_period?: string|null}  $filters
     * @return Builder<JobApplication>
     */
    protected function applyCandidateFilters(Builder $query, array $filters): Builder
    {
        $salaryColumn = ($filters['salary_basis'] ?? null) === 'current' ? 'current_ctc' : 'expected_ctc';
        $noticePeriod = NoticePeriodFilter::tryFrom((string) ($filters['notice_period'] ?? ''));

        return $query
            ->when(
                filled($filters['job_posting_id'] ?? null),
                fn (Builder $q): Builder => $q->where('job_posting_id', $filters['job_posting_id'])
            )
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $q): Builder => $q->where('status', $filters['status'])
            )
            ->when(
                filled($filters['date_from'] ?? null),
                fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $filters['date_to'])
            )
            ->when(
                is_numeric($filters['experience_min'] ?? null),
                fn (Builder $q): Builder => $q->where('total_experience', '>=', $filters['experience_min'])
            )
            ->when(
                is_numeric($filters['experience_max'] ?? null),
                fn (Builder $q): Builder => $q->where('total_experience', '<=', $filters['experience_max'])
            )
            ->when(
                is_numeric($filters['salary_min'] ?? null),
                fn (Builder $q): Builder => $q->where($salaryColumn, '>=', $filters['salary_min'])
            )
            ->when(
                is_numeric($filters['salary_max'] ?? null),
                fn (Builder $q): Builder => $q->where($salaryColumn, '<=', $filters['salary_max'])
            )
            ->when(
                $noticePeriod !== null,
                fn (Builder $q): Builder => $q->where('notice_period_days', '<=', $noticePeriod->maxDays())
            )
            ->when(
                filter_var($filters['duplicates'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn (Builder $q): Builder => $q->whereIn('id', app(FindDuplicateCandidates::class)->duplicateIds())
            )
            ->when(
                filled($filters['search'] ?? null),
                function (Builder $q) use ($filters): Builder {
                    $search = $filters['search'];

                    return $q->where(function (Builder $sub) use ($search): void {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }
            );
    }
}
