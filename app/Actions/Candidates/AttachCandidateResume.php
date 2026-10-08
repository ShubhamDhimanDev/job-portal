<?php

namespace App\Actions\Candidates;

use App\Enums\AiRatingStatus;
use App\Jobs\RefillCandidateFromResume;
use App\Models\JobApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachCandidateResume
{
    /**
     * Store a resume for an existing candidate, replacing any previous one.
     * The AI results and skills belonged to the old resume, so they are
     * cleared, and the new resume is queued to refill the candidate's details
     * and be rated.
     */
    public function handle(JobApplication $jobApplication, UploadedFile $resume): JobApplication
    {
        $extension = $resume->extension() ?: $resume->getClientOriginalExtension();

        $path = $resume->storeAs(
            'resumes/'.($jobApplication->job_posting_id ?? 'unassigned'),
            Str::uuid().".{$extension}",
            'local',
        );

        $previousPath = $jobApplication->resume_path;

        $jobApplication->update([
            'resume_path' => $path,
            'ai_status' => AiRatingStatus::Pending,
            'ai_score' => null,
            'ai_reasoning' => null,
            'ai_strengths' => null,
            'ai_gaps' => null,
            'ai_profile' => null,
            'skills' => null,
            'ai_rated_at' => null,
            'ai_error' => null,
        ]);

        if ($previousPath !== null) {
            Storage::disk('local')->delete($previousPath);
        }

        RefillCandidateFromResume::dispatch($jobApplication);

        return $jobApplication;
    }
}
