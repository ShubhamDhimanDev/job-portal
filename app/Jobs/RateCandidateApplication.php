<?php

namespace App\Jobs;

use App\Actions\Candidates\ExtractDocumentText;
use App\Actions\Candidates\NormalizeSkills;
use App\Ai\Agents\CandidateRatingAgent;
use App\Enums\AiRatingStatus;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files;
use Throwable;

class RateCandidateApplication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public JobApplication $jobApplication) {}

    /**
     * The skills the AI found, used to fill the candidate's skills only while
     * they have none of their own: a recruiter's edits survive a re-rate.
     *
     * @param  array<string, mixed>  $profile
     * @return array<int, string>|null
     */
    private function skillsFrom(array $profile): ?array
    {
        $skills = app(NormalizeSkills::class)->handle($profile['skills'] ?? []);

        return $skills === [] ? null : $skills;
    }

    public function handle(): void
    {
        if ($this->jobApplication->resume_path === null || $this->jobApplication->job_posting_id === null) {
            return;
        }

        $this->jobApplication->update(['ai_status' => AiRatingStatus::Processing]);

        Log::info('Rating candidate application', [
            'job_application_id' => $this->jobApplication->id,
            'job_posting_id' => $this->jobApplication->job_posting_id,
        ]);

        try {
            $agent = new CandidateRatingAgent($this->jobApplication->jobPosting, $this->jobApplication);
            $isPdf = strtolower(pathinfo($this->jobApplication->resume_path, PATHINFO_EXTENSION)) === 'pdf';

            $response = $isPdf
                ? $agent->prompt(
                    'Evaluate the attached resume for this job.',
                    attachments: [Files\Document::fromStorage($this->jobApplication->resume_path, disk: 'local')],
                    provider: config('services.candidate_rating.provider'),
                    model: config('services.candidate_rating.model'),
                )
                : $agent->prompt(
                    "Evaluate this candidate's resume for this job. Resume text follows:\n\n".app(ExtractDocumentText::class)->handle($this->jobApplication->resume_path),
                    provider: config('services.candidate_rating.provider'),
                    model: config('services.candidate_rating.model'),
                );

            $this->jobApplication->update([
                'skills' => $this->jobApplication->skills ?? $this->skillsFrom($response['profile']),
                'ai_status' => AiRatingStatus::Completed,
                'ai_score' => $response['rating']['score'],
                'ai_reasoning' => $response['rating']['reasoning'],
                'ai_strengths' => $response['rating']['strengths'] ?? [],
                'ai_gaps' => $response['rating']['gaps'] ?? [],
                'ai_profile' => $response['profile'],
                'ai_rated_at' => now(),
                'ai_error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Candidate application rating failed', [
                'job_application_id' => $this->jobApplication->id,
                'job_posting_id' => $this->jobApplication->job_posting_id,
                'exception' => $e,
            ]);

            $this->jobApplication->update([
                'ai_status' => AiRatingStatus::Failed,
                'ai_error' => $e->getMessage(),
            ]);
        }
    }
}
