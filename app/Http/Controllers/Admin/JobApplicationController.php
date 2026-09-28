<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\FiltersCandidates;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
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

        $candidates = $this->applyCandidateFilters(
            JobApplication::query()->with('jobPosting.company'),
            $filters
        )
            ->orderByDesc('created_at')
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
                'applied_at' => $application->created_at?->format('Y-m-d H:i'),
                'job_posting' => [
                    'id' => $application->jobPosting->id,
                    'title' => $application->jobPosting->title,
                ],
                'company_name' => $application->jobPosting->company?->name,
                'resume_filename' => basename($application->resume_path),
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
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function update(Request $request, JobApplication $jobApplication): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
            'admin_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $jobApplication->update($validated);

        return back()->with('success', 'Candidate updated.');
    }

    public function resume(JobApplication $jobApplication): StreamedResponse
    {
        if (! Storage::disk('local')->exists($jobApplication->resume_path)) {
            abort(404);
        }

        $extension = pathinfo($jobApplication->resume_path, PATHINFO_EXTENSION);
        $downloadName = Str::slug($jobApplication->name).($extension !== '' ? '.'.$extension : '');

        return Storage::disk('local')->download($jobApplication->resume_path, $downloadName);
    }
}
