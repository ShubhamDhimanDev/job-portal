<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\WorkMode;
use App\Http\Requests\JobApplicationStoreRequest;
use App\Jobs\RateCandidateApplication;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class JobBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'keyword' => $request->string('keyword')->trim()->toString(),
            'location' => $request->string('location')->trim()->toString(),
            'employment_type' => $request->string('employment_type')->toString(),
            'work_mode' => $request->string('work_mode')->toString(),
        ];

        $jobs = JobPosting::query()
            ->with('company')
            ->where('status', JobStatus::Published)
            ->when($filters['keyword'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('title', 'like', "%{$filters['keyword']}%")
                        ->orWhere('description', 'like', "%{$filters['keyword']}%");
                });
            })
            ->when($filters['location'] !== '', fn ($query) => $query->where('location', 'like', "%{$filters['location']}%"))
            ->when($filters['employment_type'] !== '', fn ($query) => $query->where('employment_type', $filters['employment_type']))
            ->when($filters['work_mode'] !== '', fn ($query) => $query->where('work_mode', $filters['work_mode']))
            ->latest('created_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (JobPosting $jobPosting): array => $this->transformJobSummary($jobPosting));

        return Inertia::render('jobs/index', [
            'jobs' => $jobs,
            'filters' => $filters,
            'employmentTypes' => $this->optionsFor(EmploymentType::cases()),
            'workModes' => $this->optionsFor(WorkMode::cases()),
        ]);
    }

    public function show(JobPosting $jobPosting): Response
    {
        abort_unless($jobPosting->status === JobStatus::Published, 404);

        $jobPosting->load('company');

        return Inertia::render('jobs/show', [
            'job' => $this->transformJobDetail($jobPosting),
        ]);
    }

    public function apply(JobApplicationStoreRequest $request, JobPosting $jobPosting): RedirectResponse
    {
        abort_unless($jobPosting->status === JobStatus::Published, 404);

        $resume = $request->file('resume');
        $extension = $resume->extension() ?: $resume->getClientOriginalExtension();

        $path = $resume->storeAs(
            "resumes/{$jobPosting->id}",
            Str::uuid().".{$extension}",
            'local',
        );

        $application = $jobPosting->applications()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'resume_path' => $path,
            'cover_note' => $request->validated('cover_note'),
        ]);

        RateCandidateApplication::dispatch($application);

        return back()->with('success', "Thanks, we've received your application.");
    }

    /**
     * @return array<string, mixed>
     */
    private function transformJobSummary(JobPosting $jobPosting): array
    {
        return [
            'title' => $jobPosting->title,
            'slug' => $jobPosting->slug,
            'company_name' => $jobPosting->company?->name,
            'location' => $jobPosting->location,
            'department' => $jobPosting->department,
            'work_mode' => $jobPosting->work_mode->value,
            'work_mode_label' => $jobPosting->work_mode->label(),
            'employment_type' => $jobPosting->employment_type->value,
            'employment_type_label' => $jobPosting->employment_type->label(),
            'salary_display' => $this->salaryDisplay($jobPosting),
            'application_deadline' => $jobPosting->application_deadline?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformJobDetail(JobPosting $jobPosting): array
    {
        return [
            ...$this->transformJobSummary($jobPosting),
            'description' => $jobPosting->description,
            'responsibilities' => $jobPosting->responsibilities,
            'requirements' => $jobPosting->requirements,
            'experience_level' => $jobPosting->experience_level,
            'vacancies' => $jobPosting->vacancies,
        ];
    }

    private function salaryDisplay(JobPosting $jobPosting): ?string
    {
        $hasRange = $jobPosting->min_salary !== null && $jobPosting->max_salary !== null;

        if ($hasRange) {
            $range = '₹'.number_format((float) $jobPosting->min_salary).' - ₹'.number_format((float) $jobPosting->max_salary);

            return $jobPosting->salary_negotiable ? "{$range} (Negotiable)" : $range;
        }

        return $jobPosting->salary_negotiable ? 'Negotiable' : null;
    }

    /**
     * @param  array<int, EmploymentType>|array<int, WorkMode>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function optionsFor(array $cases): array
    {
        return array_map(
            fn (EmploymentType|WorkMode $case): array => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
