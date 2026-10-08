<?php

namespace App\Concerns;

use App\Actions\Candidates\FindDuplicateCandidates;
use App\Actions\Candidates\NormalizeSkills;
use App\Enums\NoticePeriodFilter;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

trait FiltersCandidates
{
    /**
     * Every filter the candidates list understands. The Excel export and the
     * emailed export read the very same set, so what is exported is always
     * what the list is showing.
     *
     * @var array<int, string>
     */
    public const FILTER_KEYS = [
        'job_posting_id', 'status', 'date_from', 'date_to', 'search', 'duplicates',
        'experience_min', 'experience_max', 'salary_basis', 'salary_min', 'salary_max', 'notice_period',
        'skills', 'skills_match',
    ];

    public const MAX_EXPORT_CANDIDATES = 500;

    private const MAX_SKILL_FILTERS = 20;

    /**
     * Pick the filters out of a request. Skills are tidied, and for an export
     * the ticked candidates are read from `ids` (a comma-separated string or a
     * list); no ids means every candidate that matches the filters.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function candidateFilters(array $input, bool $withSelection = false): array
    {
        $filters = Arr::only($input, self::FILTER_KEYS);

        if (array_key_exists('skills', $filters)) {
            $filters['skills'] = array_slice(app(NormalizeSkills::class)->handle(Arr::wrap($filters['skills'])), 0, self::MAX_SKILL_FILTERS);
        }

        if (isset($filters['skills_match']) && ! in_array($filters['skills_match'], ['all', 'any'], true)) {
            unset($filters['skills_match']);
        }

        if ($withSelection && filled($input['ids'] ?? null)) {
            $raw = is_array($input['ids']) ? $input['ids'] : explode(',', (string) $input['ids']);

            $ids = array_values(array_unique(array_map(
                'intval',
                array_filter($raw, fn (mixed $id): bool => (is_int($id) || is_string($id)) && ctype_digit((string) $id)),
            )));

            if (count($ids) > self::MAX_EXPORT_CANDIDATES) {
                throw ValidationException::withMessages(['ids' => 'Select at most '.self::MAX_EXPORT_CANDIDATES.' candidates to export.']);
            }

            $filters['ids'] = $ids;
        }

        return $filters;
    }

    /**
     * Apply the shared candidate filter set (job, status, date range, search,
     * experience, salary, notice period) used by both the admin candidates
     * list and the Excel export. Search also matches the candidate code and
     * the job code. A job of "none" matches candidates that are
     * not assigned to a job.
     *
     * Skills match candidates who have all of the chosen skills, or any of
     * them when `skills_match` is "any", ignoring case and matching part of a
     * skill too ("java" finds "JavaScript"). `ids` limits the
     * result to those candidates (used when an export has ticked rows).
     *
     * Experience is total experience in years. Salary is compared against
     * expected CTC unless `salary_basis` is "current". Notice period matches
     * candidates who can join within the chosen option. Candidates missing the
     * filtered value are left out, and unusable values are ignored.
     *
     * @param  Builder<JobApplication>  $query
     * @param  array{job_posting_id?: int|string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null, duplicates?: string|bool|null, experience_min?: int|float|string|null, experience_max?: int|float|string|null, salary_basis?: string|null, salary_min?: int|float|string|null, salary_max?: int|float|string|null, notice_period?: string|null, skills?: array<int, string>|null, skills_match?: string|null, ids?: array<int, int>|null}  $filters
     * @return Builder<JobApplication>
     */
    protected function applyCandidateFilters(Builder $query, array $filters): Builder
    {
        $normalizeSkills = app(NormalizeSkills::class);
        $skillKeys = array_map(
            fn (string $skill): string => $normalizeSkills->matchKey($skill),
            $normalizeSkills->handle(Arr::wrap($filters['skills'] ?? [])),
        );
        $salaryColumn = ($filters['salary_basis'] ?? null) === 'current' ? 'current_ctc' : 'expected_ctc';
        $noticePeriod = NoticePeriodFilter::tryFrom((string) ($filters['notice_period'] ?? ''));

        return $query
            ->when(
                isset($filters['ids']) && is_array($filters['ids']),
                fn (Builder $q): Builder => $q->whereIn('id', $filters['ids'])
            )
            ->when(
                $skillKeys !== [],
                fn (Builder $q): Builder => $this->whereHasSkills($q, $skillKeys, ($filters['skills_match'] ?? 'all') === 'any')
            )
            ->when(
                ($filters['job_posting_id'] ?? null) === 'none',
                fn (Builder $q): Builder => $q->whereNull('job_posting_id')
            )
            ->when(
                filled($filters['job_posting_id'] ?? null) && $filters['job_posting_id'] !== 'none',
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
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhereHas('jobPosting', fn (Builder $job): Builder => $job->where('code', 'like', "%{$search}%"));
                    });
                }
            );
    }

    /**
     * Keep candidates that have every one of the skills (or any of them),
     * matched anywhere within their skills in the derived search column, so
     * "react" also finds "React.js". LIKE wildcards inside a skill name are
     * escaped so "C_" or "100%" only match themselves.
     *
     * @param  Builder<JobApplication>  $query
     * @param  array<int, string>  $skillKeys
     * @return Builder<JobApplication>
     */
    private function whereHasSkills(Builder $query, array $skillKeys, bool $matchAny): Builder
    {
        return $query->where(function (Builder $group) use ($skillKeys, $matchAny): void {
            foreach ($skillKeys as $key) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $key).'%';

                $matchAny
                    ? $group->orWhereRaw("skills_search like ? escape '!'", [$pattern])
                    : $group->whereRaw("skills_search like ? escape '!'", [$pattern]);
            }
        });
    }
}
