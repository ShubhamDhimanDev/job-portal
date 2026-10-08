<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Candidates\AttachCandidateResume;
use App\Actions\Candidates\CreateCandidateApplication;
use App\Actions\Candidates\DeleteCandidateApplications;
use App\Actions\Candidates\FindDuplicateCandidates;
use App\Actions\Candidates\NormalizeSkills;
use App\Actions\Candidates\RenderResumePreview;
use App\Ai\Agents\CandidateRatingAgent;
use App\Concerns\FiltersCandidates;
use App\Concerns\ValidatesCandidateProfile;
use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\InterviewType;
use App\Enums\NoticePeriodFilter;
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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
        'admin_notes',
    ];

    public function index(Request $request, FindDuplicateCandidates $findDuplicateCandidates): Response
    {
        $filters = $this->candidateFilters($request->query());
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
                'code' => $application->code,
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
                'job_posting' => $application->jobPosting === null ? null : [
                    'id' => $application->jobPosting->id,
                    'code' => $application->jobPosting->code,
                    'title' => $application->jobPosting->title,
                ],
                'company_name' => $application->jobPosting?->company?->name,
                'has_resume' => $application->resume_path !== null,
                'resume_filename' => $application->resume_path !== null ? basename($application->resume_path) : null,
                'ai_status' => $application->ai_status->value,
                'ai_status_label' => $application->ai_status->label(),
                'ai_score' => $application->ai_score,
                'ai_reasoning' => $application->ai_reasoning,
                'ai_strengths' => $application->ai_strengths,
                'ai_gaps' => $application->ai_gaps,
                'ai_profile' => $application->ai_profile,
                'skills' => $application->skills ?? [],
                'ai_error' => $application->ai_error,
            ]);

        return Inertia::render('admin/candidates/index', [
            'candidates' => $candidates,
            'jobPostings' => JobPosting::query()
                ->orderBy('title')
                ->get(['id', 'code', 'title']),
            'statuses' => collect(ApplicationStatus::cases())
                ->map(fn (ApplicationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
            'genders' => $this->enumOptions(Gender::cases()),
            'interviewTypes' => $this->enumOptions(InterviewType::cases()),
            'noticePeriods' => $this->enumOptions(NoticePeriodFilter::cases()),
            'skillOptions' => $this->skillOptions(),
            'duplicateCount' => count($findDuplicateCandidates->redundantIds()),
            'filters' => [
                'job_posting_id' => $filters['job_posting_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'search' => $filters['search'] ?? null,
                'duplicates' => $showingDuplicates ? '1' : null,
                'experience_min' => $filters['experience_min'] ?? null,
                'experience_max' => $filters['experience_max'] ?? null,
                'salary_basis' => $filters['salary_basis'] ?? null,
                'salary_min' => $filters['salary_min'] ?? null,
                'salary_max' => $filters['salary_max'] ?? null,
                'notice_period' => $filters['notice_period'] ?? null,
                'skills' => $filters['skills'] ?? [],
                'skills_match' => ($filters['skills_match'] ?? null) === 'any' ? 'any' : null,
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
                ->get(['id', 'code', 'title']),
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
        if ($jobApplication->job_posting_id === null) {
            return back()->with('error', 'Assign this candidate to a job before rating.');
        }

        if ($jobApplication->resume_path === null) {
            return back()->with('error', 'Upload a resume before rating this candidate.');
        }

        RateCandidateApplication::dispatch($jobApplication);

        return back()->with('success', 'Re-rating this candidate now - refresh in a few seconds.');
    }

    public function update(Request $request, JobApplication $jobApplication, NormalizeSkills $normalizeSkills): RedirectResponse
    {
        $targetJobId = $request->input('job_posting_id', $jobApplication->job_posting_id);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:'.CandidateRatingAgent::MAX_COMMENT_LENGTH],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('job_applications', 'email')
                    ->where(fn ($query) => $query->where('job_posting_id', $targetJobId))
                    ->ignore($jobApplication->id),
            ],
            'phone' => ['sometimes', 'required', 'string', 'max:50', 'regex:'.JobApplication::PHONE_PATTERN],
            'job_posting_id' => ['sometimes', 'nullable', 'integer', Rule::exists('job_postings', 'id')],
            'skills' => ['sometimes', 'array', 'max:'.NormalizeSkills::MAX_SKILLS],
            'skills.*' => ['nullable', 'string', 'max:'.NormalizeSkills::MAX_LENGTH],
            ...$this->candidateProfileRules(),
        ], [
            'skills.max' => 'A candidate can have at most '.NormalizeSkills::MAX_SKILLS.' skills.',
            'skills.*.max' => 'Each skill can be at most '.NormalizeSkills::MAX_LENGTH.' characters.',
            'email.unique' => 'This email is already added to the selected job.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number (starting with 6-9, optional +91).',
        ]);

        if (array_key_exists('skills', $validated)) {
            $validated['skills'] = $normalizeSkills->handle($validated['skills']);
        }

        $jobApplication->update($validated);

        if ($jobApplication->wasChanged(['job_posting_id', ...self::RATING_FIELDS])
            && $jobApplication->job_posting_id !== null
            && $jobApplication->resume_path !== null) {
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

        return back()->with('success', $jobApplication->job_posting_id === null
            ? 'Resume uploaded - the candidate details are being refilled from it.'
            : 'Resume uploaded - the candidate details are being refilled from it and the AI rating is running.');
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

    /**
     * Show the resume inline for the preview popup: PDFs stream as-is, other
     * formats are rendered to HTML locked down by a sandboxing policy.
     */
    public function preview(JobApplication $jobApplication, RenderResumePreview $renderResumePreview): SymfonyResponse
    {
        $path = $jobApplication->resume_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        if ($renderResumePreview->isPdf($path)) {
            return Storage::disk('local')->response($path, Str::slug($jobApplication->name).'.pdf', [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response($renderResumePreview->html($path, $jobApplication->name), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "sandbox allow-popups allow-popups-to-escape-sandbox; default-src 'none'; img-src data:; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
     * Every skill any candidate has, with how many candidates have it, most
     * common first. Skills are counted without regard to case, under the
     * spelling they were first seen with.
     *
     * @return array<int, array{name: string, count: int}>
     */
    private function skillOptions(): array
    {
        $normalizeSkills = app(NormalizeSkills::class);
        $options = [];

        JobApplication::query()->whereNotNull('skills')->pluck('skills')->each(function (array $skills) use (&$options, $normalizeSkills): void {
            foreach (array_unique(array_map($normalizeSkills->matchKey(...), $skills)) as $position => $key) {
                $options[$key] ??= ['name' => $skills[$position], 'count' => 0];
                $options[$key]['count']++;
            }
        });

        usort($options, fn (array $a, array $b): int => [$b['count'], mb_strtolower($a['name'])] <=> [$a['count'], mb_strtolower($b['name'])]);

        return array_slice($options, 0, 500);
    }

    /**
     * @param  array<int, Gender|InterviewType|NoticePeriodFilter>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            fn (Gender|InterviewType|NoticePeriodFilter $case): array => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
