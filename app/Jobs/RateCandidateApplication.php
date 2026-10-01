<?php

namespace App\Jobs;

use App\Ai\Agents\CandidateRatingAgent;
use App\Enums\AiRatingStatus;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class RateCandidateApplication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public JobApplication $jobApplication) {}

    public function handle(): void
    {
        if ($this->jobApplication->resume_path === null) {
            return;
        }

        $this->jobApplication->update(['ai_status' => AiRatingStatus::Processing]);

        Log::info('Rating candidate application', [
            'job_application_id' => $this->jobApplication->id,
            'job_posting_id' => $this->jobApplication->job_posting_id,
        ]);

        try {
            $agent = new CandidateRatingAgent($this->jobApplication->jobPosting);
            $isPdf = strtolower(pathinfo($this->jobApplication->resume_path, PATHINFO_EXTENSION)) === 'pdf';

            $response = $isPdf
                ? $agent->prompt(
                    'Evaluate the attached resume for this job.',
                    attachments: [Files\Document::fromStorage($this->jobApplication->resume_path, disk: 'local')],
                    provider: config('services.candidate_rating.provider'),
                    model: config('services.candidate_rating.model'),
                )
                : $agent->prompt(
                    "Evaluate this candidate's resume for this job. Resume text follows:\n\n".$this->extractDocxText(),
                    provider: config('services.candidate_rating.provider'),
                    model: config('services.candidate_rating.model'),
                );

            $this->jobApplication->update([
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

    /**
     * Extract plain text from a .doc/.docx resume, since document attachments
     * on AI providers are PDF-native - DOCX isn't a supported attachment type.
     */
    private function extractDocxText(): string
    {
        $phpWord = IOFactory::load(
            Storage::disk('local')->path($this->jobApplication->resume_path)
        );

        $text = '';

        foreach ($phpWord->getSections() as $section) {
            $text .= $this->extractContainerText($section);
        }

        return trim($text);
    }

    private function extractContainerText(AbstractContainer $container): string
    {
        $text = '';

        foreach ($container->getElements() as $element) {
            if ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $text .= $this->extractContainerText($cell).' ';
                    }
                }
            } elseif (method_exists($element, 'getText')) {
                $value = $element->getText();
                $text .= (is_string($value) ? $value : '').PHP_EOL;
            }
        }

        return $text;
    }
}
