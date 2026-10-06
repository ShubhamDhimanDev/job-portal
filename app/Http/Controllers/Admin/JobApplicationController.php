<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Candidates\AttachCandidateResume;
use App\Actions\Candidates\CreateCandidateApplication;
use App\Actions\Candidates\DeleteCandidateApplications;
use App\Actions\Candidates\FindDuplicateCandidates;
use App\Concerns\FiltersCandidates;
use App\Concerns\ValidatesCandidateProfile;
use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\InterviewType;
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
    use FiltersCandidates, ValidatesCandidateProfile;

    /**
     * Candidate fields the AI rating reads; changing one re-runs the rating.
     */
    private const RATING_FIELDS = [
        'total_experience',
        'relevant_experience',
        'current_designation',
        'current_company',
        'current_ctc',
        'expected_ctc',
        'notice_period',
    ];

    public function index(Request $request, FindDuplicateCandidates $findDuplicateCandidates): Response
    {
        /** @var array{job_posting_id?: int|string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null, duplicates?: string|null} $filters */
        $filters = $request->only(['job_posting_id', 'status', 'date_from', 'date_to', 'search', 'duplicates']);
        $sort = $request->string('sort')->toString();

        $query = $this->applyCandidateFilters(
            JobApplication::query()->with('jobPosting.company'),
            $filters
        );

        $showingDuplicates = filter_var($filters['duplicates'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $candidates = ($sort === 'ai_score'
            ? $query->orderByRaw('ai_score IS NULL')->orderByDesc('ai_score')
            : ($showingDuplicates ? $query->orderBy('email') : $query->orderByDesc('created_at'))
        )
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JobApplication $application): array => [
                'id' => $application->id,
                'name' => $application->name,
                'email' => $application->email,
                'phone' => $application->phone,
                'gender' => $application->gender?->value,
                'date_of_birth' => $application->date_of_birth?->format('Y-m-d'),
                'total_experience' => $application->total_experience,
                'relevant_experience' => $application->relevant_experience,
                'current_company' => $application->current_company,
                'industry_type' => $application->industry_type,
                'current_designation' => $application->current_designation,
                'current_location' => $application->current_location,
                'current_ctc' => $application->current_ctc,
                'expected_ctc' => $application->expected_ctc,
                'notice_period' => $application->notice_period,
                'interview_type' => $application->interview_type?->value,
                'interview_type_label' => $application->interview_type?->label(),
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
            'genders' => $this->enumOptions(Gender::cases()),
            'interviewTypes' => $this->enumOptions(InterviewType::cases()),
            'duplicateCount' => count($findDuplicateCandidates->redundantIds()),
            'filters' => [
                'job_posting_id' => $filters['job_posting_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'search' => $filters['search'] ?? null,
                'duplicates' => $showingDuplicates ? '1' : null,
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
            'genders' => $this->enumOptions(Gender::cases()),
            'interviewTypes' => $this->enumOptions(InterviewType::cases()),
        ]);
    }

    public function store(
        AdminCandidateStoreRequest $request,
        CreateCandidateApplication $createCandidateApplication,
    ): RedirectResponse {
        $createCandidateApplication->handle(
            JobPosting::query()->findOrFail($request->validated('job_posting_id')),
            $request->safe()->only(['name', 'email', 'phone', 'cover_note', ...$this->candidateProfileFields()]),
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
            ...$this->candidateProfileRules(),
        ], [
            'email.unique' => 'This email is already added to the selected job.',
        ]);

        $jobApplication->update($validated);

        if ($jobApplication->wasChanged(['job_posting_id', ...self::RATING_FIELDS]) && $jobApplication->resume_path !== null) {
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

    public function destroy(JobApplication $jobApplication, DeleteCandidateApplications $deleteCandidateApplications): RedirectResponse
    {
        $deleteCandidateApplications->handle([$jobApplication->id]);

        return back()->with('success', 'Candidate deleted.');
    }

    public function destroyDuplicates(
        FindDuplicateCandidates $findDuplicateCandidates,
        DeleteCandidateApplications $deleteCandidateApplications,
    ): RedirectResponse {
        $deleted = $deleteCandidateApplications->handle($findDuplicateCandidates->redundantIds());

        if ($deleted === 0) {
            return back()->with('success', 'No duplicate candidates found.');
        }

        return back()->with('success', "Deleted {$deleted} duplicate ".Str::plural('candidate', $deleted).', keeping the oldest of each.');
    }

    /**
     * @param  array<int, Gender|InterviewType>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            fn (Gender|InterviewType $case): array => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
