<?php

namespace App\Jobs;

use App\Actions\Candidates\CreateCandidateApplication;
use App\Actions\Candidates\ParseResume;
use App\Enums\ResumeUploadItemStatus;
use App\Models\JobApplication;
use App\Models\ResumeUploadItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class ImportResumeFile implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public ResumeUploadItem $item) {}

    /**
     * Parse one resume with AI and add the candidate it describes, recording
     * the outcome on the item so it shows in the upload report.
     */
    public function handle(ParseResume $parseResume, CreateCandidateApplication $createCandidateApplication): void
    {
        if ($this->item->status !== ResumeUploadItemStatus::Pending) {
            return;
        }

        $upload = $this->item->resumeUpload;

        try {
            $parsed = $parseResume->handle($this->item->file_path);

            $this->item->fill(['name' => $parsed['name'] ?: null, 'email' => $parsed['email'] ?: null]);

            $validator = Validator::make($parsed, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
                'phone' => ['required', 'string', 'max:50', 'regex:'.JobApplication::PHONE_PATTERN],
            ], [
                'name.required' => 'No name was found in the resume.',
                'email.required' => 'No email address was found in the resume.',
                'email.email' => 'The email address in the resume is not valid.',
                'phone.required' => 'No phone number was found in the resume.',
                'phone.regex' => 'The phone number in the resume is not a valid Indian mobile number.',
            ]);

            if ($validator->fails()) {
                $this->finish(ResumeUploadItemStatus::Failed, implode(' ', $validator->errors()->all()));

                return;
            }

            $isDuplicate = JobApplication::query()
                ->whereRaw('lower(email) = ?', [$parsed['email']])
                ->when($upload->job_posting_id !== null, fn ($query) => $query->where('job_posting_id', $upload->job_posting_id))
                ->exists();

            if ($isDuplicate) {
                $this->finish(ResumeUploadItemStatus::Skipped, $this->duplicateReason($upload->job_posting_id !== null));

                return;
            }

            try {
                $application = $createCandidateApplication->handle(
                    $upload->jobPosting,
                    ['name' => $parsed['name'], 'email' => $parsed['email'], 'phone' => $parsed['phone'], ...$parsed['profile']],
                    Storage::disk('local')->path($this->item->file_path),
                    $upload->rate_with_ai,
                );
            } catch (UniqueConstraintViolationException) {
                $this->finish(ResumeUploadItemStatus::Skipped, $this->duplicateReason(true));

                return;
            }

            $this->item->job_application_id = $application->id;
            $this->finish(ResumeUploadItemStatus::Created);
        } catch (Throwable $e) {
            Log::error('Resume import failed', [
                'resume_upload_item_id' => $this->item->id,
                'exception' => $e,
            ]);

            $this->finish(ResumeUploadItemStatus::Failed, 'The resume could not be read: '.Str::limit($e->getMessage(), 300));
        } finally {
            $upload->completeWhenFinished();
        }
    }

    private function finish(ResumeUploadItemStatus $status, ?string $reason = null): void
    {
        $filePath = $this->item->file_path;

        $this->item->update(['status' => $status, 'reason' => $reason, 'file_path' => null]);

        if ($filePath !== null) {
            Storage::disk('local')->delete($filePath);
        }
    }

    private function duplicateReason(bool $forJob): string
    {
        return $forJob ? 'Already added to this job.' : 'A candidate with this email already exists.';
    }
}
