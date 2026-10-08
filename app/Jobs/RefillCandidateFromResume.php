<?php

namespace App\Jobs;

use App\Actions\Candidates\ParseResume;
use App\Models\JobApplication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class RefillCandidateFromResume implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public JobApplication $jobApplication) {}

    /**
     * Read the candidate's current resume and refill their details from it,
     * then queue the AI rating, which works from those details. A resume
     * that can't be read leaves the candidate as they were and is still rated.
     */
    public function handle(ParseResume $parseResume): void
    {
        $application = $this->jobApplication;

        if ($application->resume_path === null) {
            return;
        }

        try {
            $application->fill($this->details($application, $parseResume->handle($application->resume_path)))->save();
        } catch (Throwable $e) {
            Log::error('Refilling candidate details from the resume failed', [
                'job_application_id' => $application->id,
                'exception' => $e,
            ]);
        }

        if ($application->job_posting_id !== null) {
            RateCandidateApplication::dispatch($application);
        }
    }

    /**
     * The details found in the resume that are usable. Each one is checked on
     * its own, so a missing phone number doesn't stop the experience from
     * being refilled, and an email another candidate already has on the same
     * job is left alone.
     *
     * @param  array{name: string, email: string, phone: string, profile: array<string, mixed>}  $parsed
     * @return array<string, mixed>
     */
    private function details(JobApplication $application, array $parsed): array
    {
        $validator = Validator::make($parsed, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'regex:'.JobApplication::PHONE_PATTERN],
        ]);

        $contact = collect($parsed)
            ->only(['name', 'email', 'phone'])
            ->except($validator->errors()->keys());

        if ($contact->has('email') && $this->emailBelongsToAnotherCandidate($application, $contact->get('email'))) {
            $contact->forget('email');
        }

        return [...$parsed['profile'], ...$contact->all()];
    }

    private function emailBelongsToAnotherCandidate(JobApplication $application, string $email): bool
    {
        return JobApplication::query()
            ->whereKeyNot($application->getKey())
            ->where('job_posting_id', $application->job_posting_id)
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->exists();
    }
}
