<?php

namespace App\Actions\Candidates;

use App\Enums\AiRatingStatus;
use App\Jobs\RateCandidateApplication;
use App\Models\JobApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachCandidateResume
{
    /**
     * Store a resume for an existing candidate, replacing any previous one,
     * and queue a fresh AI rating against it.
     */
    public function handle(JobApplication $jobApplication, UploadedFile $resume): JobApplication
    {
        $extension = $resume->extension() ?: $resume->getClientOriginalExtension();

        $path = $resume->storeAs(
            "resumes/{$jobApplication->job_posting_id}",
            Str::uuid().".{$extension}",
            'local',
        );

        $previousPath = $jobApplication->resume_path;

        $jobApplication->update([
            'resume_path' => $path,
            'ai_status' => AiRatingStatus::Pending,
            'ai_error' => null,
        ]);

        if ($previousPath !== null) {
            Storage::disk('local')->delete($previousPath);
        }

        RateCandidateApplication::dispatch($jobApplication);

        return $jobApplication;
    }
}
