<?php

namespace App\Concerns;

use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Builder;

trait FiltersCandidates
{
    /**
     * Apply the shared candidate filter set (job, status, date range, search)
     * used by both the admin candidates list and the Excel export.
     *
     * @param  Builder<JobApplication>  $query
     * @param  array{job_posting_id?: int|string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null}  $filters
     * @return Builder<JobApplication>
     */
    protected function applyCandidateFilters(Builder $query, array $filters): Builder
    {
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
