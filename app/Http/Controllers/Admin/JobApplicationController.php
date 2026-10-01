<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Candidates\AttachCandidateResume;
use App\Actions\Candidates\CreateCandidateApplication;
use App\Concerns\FiltersCandidates;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCandidateStoreRequest;
use App\Jobs\RateCandidateApplication;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationController extends Controller
{
    use FiltersCandidates;

    public function index(Request $request): Response
    {
        /** @var array{job_posting_id?: int|string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null} $filters */
        $filters = $request->only(['job_posting_id', 'status', 'date_from', 'date_to', 'search']);
        $sort = $request->string('sort')->toString();

        $query = $this->applyCandidateFilters(
            JobApplication::query()->with('jobPosting.company'),
            $filters
        );

        $candidates = ($sort === 'ai_score'
            ? $query->orderByRaw('ai_score IS NULL')->orderByDesc('ai_score')
            : $query->orderByDesc('created_at')
        )
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JobApplication $application): array => [
                'id' => $application->id,
                'name' => $application->name,
                'email' => $application->email,
                'phone' => $application->phone,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'admin_notes' => $application->admin_notes,
                'applied_at' => $application->created_at?->format('M j, Y g:i A'),
                'job_posting' => [
                    'id' => $application->jobPosting->id,
                    'title' => $application->jobPosting->title,
                ],
                'company_name' => $application->jobPosting->company?->name,
                'has_resume' => $application->resume_path !== null,
                'resume_filename' => $application->resume_path !== null ? basename($application->resume_path) : null,
                'ai_status' => $application->ai_status->value,
                'ai_status_label' => $application->ai_status->label(),
                'ai_score' => $application->ai_score,
                'ai_reasoning' => $application->ai_reasoning,
                'ai_strengths' => $application->ai_strengths,
                'ai_gaps' => $application->ai_gaps,
                'ai_profile' => $application->ai_profile,
                'ai_error' => $application->ai_error,
            ]);

        return Inertia::render('admin/candidates/index', [
            'candidates' => $candidates,
            'jobPostings' => JobPosting::query()
                ->orderBy('title')
                ->get(['id', 'title']),
            'statuses' => collect(ApplicationStatus::cases())
                ->map(fn (ApplicationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
            'filters' => [
                'job_posting_id' => $filters['job_posting_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'search' => $filters['search'] ?? null,
                'sort' => $sort !== '' ? $sort : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/candidates/create', [
            'jobPostings' => JobPosting::query()
                ->orderBy('title')
                ->get(['id', 'title']),
        ]);
    }

    public function store(
        AdminCandidateStoreRequest $request,
        CreateCandidateApplication $createCandidateApplication,
    ): RedirectResponse {
        $createCandidateApplication->handle(
            JobPosting::query()->findOrFail($request->validated('job_posting_id')),
            $request->safe()->only(['name', 'email', 'phone', 'cover_note']),
            $request->file('resume'),
        );

        return to_route('admin.candidates.index')->with('success', 'Candidate added.');
    }

    public function rate(JobApplication $jobApplication): RedirectResponse
    {
        if ($jobApplication->resume_path === null) {
            return back()->with('error', 'Upload a resume before rating this candidate.');
        }

        RateCandidateApplication::dispatch($jobApplication);

        return back()->with('success', 'Re-rating this candidate now - refresh in a few seconds.');
    }

    public function update(Request $request, JobApplication $jobApplication): RedirectResponse
    {
        $targetJobId = $request->input('job_posting_id', $jobApplication->job_posting_id);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
            'admin_notes' => ['sometimes', 'nullable', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('job_applications', 'email')
                    ->where(fn ($query) => $query->where('job_posting_id', $targetJobId))
                    ->ignore($jobApplication->id),
            ],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'job_posting_id' => ['sometimes', 'required', 'integer', Rule::exists('job_postings', 'id')],
        ], [
            'email.unique' => 'This email is already added to the selected job.',
        ]);

        $jobApplication->update($validated);

        if ($jobApplication->wasChanged('job_posting_id') && $jobApplication->resume_path !== null) {
            RateCandidateApplication::dispatch($jobApplication);
        }

        return back()->with('success', 'Candidate updated.');
    }

    public function uploadResume(
        Request $request,
        JobApplication $jobApplication,
        AttachCandidateResume $attachCandidateResume,
    ): RedirectResponse {
        $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ], [
            'resume.mimes' => 'Resume must be a PDF, DOC, or DOCX file.',
            'resume.max' => 'Resume must be smaller than 5MB.',
        ]);

        $attachCandidateResume->handle($jobApplication, $request->file('resume'));

        return back()->with('success', 'Resume uploaded - AI rating is running.');
    }

    public function resume(JobApplication $jobApplication): StreamedResponse
    {
        if ($jobApplication->resume_path === null || ! Storage::disk('local')->exists($jobApplication->resume_path)) {
            abort(404);
        }

        $extension = pathinfo($jobApplication->resume_path, PATHINFO_EXTENSION);
        $downloadName = Str::slug($jobApplication->name).($extension !== '' ? '.'.$extension : '');

        return Storage::disk('local')->download($jobApplication->resume_path, $downloadName);
    }
}
