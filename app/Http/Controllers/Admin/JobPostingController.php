<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\WorkMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JobPostingStoreRequest;
use App\Http\Requests\Admin\JobPostingUpdateRequest;
use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobPostingController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $jobPostings = JobPosting::query()
            ->with('company')
            ->when(
                filled($status) && JobStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status)
            )
            ->when(filled($search), function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JobPosting $jobPosting) => [
                'id' => $jobPosting->id,
                'code' => $jobPosting->code,
                'slug' => $jobPosting->slug,
                'title' => $jobPosting->title,
                'company' => $jobPosting->company->name,
                'location' => $jobPosting->location,
                'work_mode' => $jobPosting->work_mode->label(),
                'employment_type' => $jobPosting->employment_type->label(),
                'status' => $jobPosting->status->value,
                'status_label' => $jobPosting->status->label(),
                'vacancies' => $jobPosting->vacancies,
                'created_at' => $jobPosting->created_at->format('M j, Y'),
            ]);

        return Inertia::render('admin/job-postings/index', [
            'jobPostings' => $jobPostings,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'statuses' => $this->enumOptions(JobStatus::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/job-postings/create', [
            'companies' => $this->companyOptions(),
            'workModes' => $this->enumOptions(WorkMode::cases()),
            'employmentTypes' => $this->enumOptions(EmploymentType::cases()),
        ]);
    }

    public function store(JobPostingStoreRequest $request): RedirectResponse
    {
        $jobPosting = new JobPosting($request->validated());
        $jobPosting->posted_by_id = $request->user()->id;
        $jobPosting->status = JobStatus::Draft;
        $jobPosting->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job posting created as a draft.']);

        return to_route('admin.job-postings.edit', $jobPosting);
    }

    public function edit(JobPosting $jobPosting): Response
    {
        return Inertia::render('admin/job-postings/edit', [
            'jobPosting' => [
                'id' => $jobPosting->id,
                'slug' => $jobPosting->slug,
                'company_id' => $jobPosting->company_id,
                'title' => $jobPosting->title,
                'description' => $jobPosting->description,
                'responsibilities' => $jobPosting->responsibilities,
                'requirements' => $jobPosting->requirements,
                'location' => $jobPosting->location,
                'work_mode' => $jobPosting->work_mode->value,
                'employment_type' => $jobPosting->employment_type->value,
                'experience_level' => $jobPosting->experience_level,
                'min_salary' => $jobPosting->min_salary,
                'max_salary' => $jobPosting->max_salary,
                'salary_negotiable' => $jobPosting->salary_negotiable,
                'department' => $jobPosting->department,
                'vacancies' => $jobPosting->vacancies,
                'status' => $jobPosting->status->value,
                'status_label' => $jobPosting->status->label(),
                'application_deadline' => $jobPosting->application_deadline?->format('Y-m-d'),
            ],
            'companies' => $this->companyOptions(),
            'workModes' => $this->enumOptions(WorkMode::cases()),
            'employmentTypes' => $this->enumOptions(EmploymentType::cases()),
        ]);
    }

    public function update(JobPostingUpdateRequest $request, JobPosting $jobPosting): RedirectResponse
    {
        $jobPosting->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job posting updated.']);

        return to_route('admin.job-postings.edit', $jobPosting);
    }

    public function destroy(JobPosting $jobPosting): RedirectResponse
    {
        $jobPosting->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job posting deleted.']);

        return to_route('admin.job-postings.index');
    }

    public function publish(JobPosting $jobPosting): RedirectResponse
    {
        $jobPosting->update(['status' => JobStatus::Published]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job posting published.']);

        return back();
    }

    public function close(JobPosting $jobPosting): RedirectResponse
    {
        $jobPosting->update(['status' => JobStatus::Closed]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job posting closed.']);

        return back();
    }

    public function duplicate(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        $copy = $jobPosting->replicate(['slug', 'code']);
        $copy->title = "{$jobPosting->title} (Copy)";
        $copy->status = JobStatus::Draft;
        $copy->posted_by_id = $request->user()->id;
        $copy->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job posting duplicated as a new draft.']);

        return to_route('admin.job-postings.edit', $copy);
    }

    /**
     * @param  array<int, WorkMode|EmploymentType|JobStatus>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function companyOptions(): array
    {
        return Company::query()->orderBy('name')->get(['id', 'name'])->toArray();
    }
}
