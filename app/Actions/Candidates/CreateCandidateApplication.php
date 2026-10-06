<?php

namespace App\Actions\Candidates;

use App\Jobs\RateCandidateApplication;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateCandidateApplication
{
    /**
     * Create the application, storing the resume when there is one. The AI
     * rating is only queued for candidates that have a resume to rate.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        JobPosting $jobPosting,
        array $attributes,
        UploadedFile|string|null $resume = null,
        bool $rateWithAi = true,
    ): JobApplication {
        $application = $jobPosting->applications()->create([
            ...$attributes,
            'resume_path' => $resume === null ? null : $this->storeResume($jobPosting, $resume),
        ]);

        if ($rateWithAi && $application->resume_path !== null) {
            RateCandidateApplication::dispatch($application);
        }

        return $application;
    }

    private function storeResume(JobPosting $jobPosting, UploadedFile|string $resume): string
    {
        $directory = "resumes/{$jobPosting->id}";

        if ($resume instanceof UploadedFile) {
            $extension = $resume->extension() ?: $resume->getClientOriginalExtension();

            return $resume->storeAs($directory, Str::uuid().".{$extension}", 'local');
        }

        $extension = strtolower(pathinfo($resume, PATHINFO_EXTENSION));

        return Storage::disk('local')->putFileAs(
            $directory,
            new File($resume),
            Str::uuid().".{$extension}",
        );
    }
}
