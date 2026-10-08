<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ResumeUploadItemStatus;
use App\Enums\ResumeUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResumeUploadStoreRequest;
use App\Jobs\ProcessResumeUpload;
use App\Models\ResumeUpload;
use App\Models\ResumeUploadItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeUploadController extends Controller
{
    public function index(): Response
    {
        $uploads = ResumeUpload::query()
            ->with(['user:id,name', 'jobPosting:id,code,title'])
            ->withCount($this->itemCounts())
            ->latest('id')
            ->paginate(15)
            ->through(fn (ResumeUpload $upload): array => $this->summary($upload));

        return Inertia::render('admin/resume-uploads/index', [
            'uploads' => $uploads,
        ]);
    }

    public function show(ResumeUpload $resumeUpload): Response
    {
        $resumeUpload->load(['user:id,name', 'jobPosting:id,code,title'])->loadCount($this->itemCounts());

        $items = $resumeUpload->items()
            ->with('jobApplication:id,code')
            ->orderBy('id')
            ->paginate(50)
            ->through(fn (ResumeUploadItem $item): array => [
                'id' => $item->id,
                'filename' => $item->filename,
                'candidate_code' => $item->jobApplication?->code,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'name' => $item->name,
                'email' => $item->email,
                'reason' => $item->reason,
            ]);

        return Inertia::render('admin/resume-uploads/show', [
            'upload' => $this->summary($resumeUpload),
            'items' => $items,
        ]);
    }

    public function store(ResumeUploadStoreRequest $request): RedirectResponse
    {
        $file = $request->file('upload');
        $jobPostingId = $request->validated('job_posting_id');

        $resumeUpload = ResumeUpload::query()->create([
            'user_id' => $request->user()->id,
            'job_posting_id' => $jobPostingId,
            'original_filename' => Str::limit($file->getClientOriginalName(), 250, ''),
            'rate_with_ai' => $jobPostingId !== null && $request->boolean('rate_with_ai'),
            'status' => ResumeUploadStatus::Pending,
        ]);

        $resumeUpload->update([
            'upload_path' => $file->storeAs(
                "resume-uploads/{$resumeUpload->id}",
                'upload.'.strtolower($file->getClientOriginalExtension()),
                'local',
            ),
        ]);

        ProcessResumeUpload::dispatch($resumeUpload);

        return back()->with('success', 'Upload started - candidates are added as each resume is read. Follow progress under Upload Reports.');
    }

    public function issues(ResumeUpload $resumeUpload): StreamedResponse
    {
        return response()->streamDownload(function () use ($resumeUpload): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['file', 'result', 'name', 'email', 'reason']);

            $resumeUpload->items()
                ->where('status', '!=', ResumeUploadItemStatus::Created)
                ->orderBy('id')
                ->each(function (ResumeUploadItem $item) use ($handle): void {
                    fputcsv($handle, [$item->filename, $item->status->label(), $item->name, $item->email, $item->reason]);
                });

            fclose($handle);
        }, "resume-upload-{$resumeUpload->id}-issues.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, \Closure(Builder<ResumeUploadItem>): Builder<ResumeUploadItem>>
     */
    private function itemCounts(): array
    {
        return [
            'items as created_count' => fn (Builder $query): Builder => $query->where('status', ResumeUploadItemStatus::Created),
            'items as skipped_count' => fn (Builder $query): Builder => $query->where('status', ResumeUploadItemStatus::Skipped),
            'items as failed_count' => fn (Builder $query): Builder => $query->where('status', ResumeUploadItemStatus::Failed),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ResumeUpload $upload): array
    {
        return [
            'id' => $upload->id,
            'original_filename' => $upload->original_filename,
            'status' => $upload->status->value,
            'status_label' => $upload->status->label(),
            'error' => $upload->error,
            'job_code' => $upload->jobPosting?->code,
            'job_title' => $upload->jobPosting?->title,
            'rate_with_ai' => $upload->rate_with_ai,
            'uploaded_by' => $upload->user?->name,
            'total' => $upload->total,
            'created_count' => $upload->created_count,
            'skipped_count' => $upload->skipped_count,
            'failed_count' => $upload->failed_count,
            'has_issues' => $upload->skipped_count + $upload->failed_count > 0,
            'created_at' => $upload->created_at?->format('M j, Y g:i A'),
        ];
    }
}
