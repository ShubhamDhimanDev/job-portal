<?php

use App\Ai\Agents\ResumeParserAgent;
use App\Enums\AiRatingStatus;
use App\Enums\Gender;
use App\Jobs\RateCandidateApplication;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * What the AI parser returns for the new resume, with any fields overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newResumeDetails(array $overrides = []): array
{
    return [
        'name' => 'Hari Ram',
        'email' => 'hari.new@example.com',
        'phone' => '9876501234',
        'gender' => 'male',
        'date_of_birth' => '1990-05-10',
        'total_experience_years' => 14,
        'current_company' => 'DotSolved',
        'current_designation' => 'Sr. Software Tester',
        'current_location' => 'Chennai',
        'industry_type' => 'IT Services',
        'notice_period' => '60 days',
        ...$overrides,
    ];
}

function replaceResume(JobApplication $application): void
{
    test()->actingAs(User::factory()->create())
        ->post("/admin/candidates/{$application->id}/resume", [
            'resume' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
}

function candidateWithOldDetails(array $attributes = []): JobApplication
{
    return JobApplication::factory()->create([
        'resume_path' => null,
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'phone' => '9111111111',
        'total_experience' => 2,
        'relevant_experience' => 1.5,
        'current_company' => 'Old Co',
        'current_designation' => 'Old Role',
        'current_location' => 'Pune',
        'current_ctc' => 500000,
        'expected_ctc' => 700000,
        'interview_type' => 'virtual',
        ...$attributes,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

test('replacing a resume refills the candidate details from it and then rates them', function () {
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([newResumeDetails()]);

    $application = candidateWithOldDetails();

    replaceResume($application);

    expect($application->refresh())
        ->name->toBe('Hari Ram')
        ->email->toBe('hari.new@example.com')
        ->phone->toBe('9876501234')
        ->gender->toBe(Gender::Male)
        ->date_of_birth->toDateString()->toBe('1990-05-10')
        ->total_experience->toBe(14.0)
        ->current_company->toBe('DotSolved')
        ->current_designation->toBe('Sr. Software Tester')
        ->current_location->toBe('Chennai')
        ->industry_type->toBe('IT Services')
        ->notice_period->toBe('60 days')
        ->notice_period_days->toBe(60);

    Bus::assertDispatched(
        RateCandidateApplication::class,
        fn (RateCandidateApplication $rating) => $rating->jobApplication->is($application),
    );
});

test('details the resume does not contain are left as they were', function () {
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([newResumeDetails([
        'gender' => null,
        'date_of_birth' => null,
        'total_experience_years' => null,
        'current_company' => '',
        'current_location' => null,
    ])]);

    $application = candidateWithOldDetails();

    replaceResume($application);

    expect($application->refresh())
        ->current_company->toBe('Old Co')
        ->current_location->toBe('Pune')
        ->total_experience->toBe(2.0)
        ->current_designation->toBe('Sr. Software Tester')
        ->relevant_experience->toBe(1.5)
        ->current_ctc->toBe(500000.0)
        ->expected_ctc->toBe(700000.0)
        ->interview_type->value->toBe('virtual');
});

test('an email another candidate already has on the same job is not taken, but the rest is refilled', function () {
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([newResumeDetails(['email' => 'Taken@Example.com'])]);

    $job = JobPosting::factory()->create();
    JobApplication::factory()->create(['job_posting_id' => $job->id, 'email' => 'taken@example.com']);
    $application = candidateWithOldDetails(['job_posting_id' => $job->id]);

    replaceResume($application);

    expect($application->refresh())
        ->email->toBe('old@example.com')
        ->name->toBe('Hari Ram')
        ->current_company->toBe('DotSolved');
});

test('the same email on a different job is fine', function () {
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([newResumeDetails(['email' => 'shared@example.com'])]);

    JobApplication::factory()->create(['email' => 'shared@example.com']);
    $application = candidateWithOldDetails();

    replaceResume($application);

    expect($application->refresh()->email)->toBe('shared@example.com');
});

test('each contact detail is checked on its own', function () {
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([newResumeDetails(['phone' => '12345', 'email' => 'not-an-email', 'name' => ''])]);

    $application = candidateWithOldDetails();

    replaceResume($application);

    expect($application->refresh())
        ->phone->toBe('9111111111')
        ->email->toBe('old@example.com')
        ->name->toBe('Old Name')
        ->current_company->toBe('DotSolved')
        ->total_experience->toBe(14.0);
});

test('the ai results and skills of the old resume are cleared as soon as it is replaced', function () {
    Bus::fake();

    $application = candidateWithOldDetails([
        'resume_path' => 'resumes/1/old.pdf',
        'ai_status' => AiRatingStatus::Completed,
        'ai_score' => 8,
        'ai_reasoning' => 'Great.',
        'ai_strengths' => ['PHP'],
        'ai_gaps' => ['Go'],
        'ai_profile' => ['skills' => ['PHP', 'Laravel']],
        'skills' => ['PHP', 'Laravel', 'Edited by hand'],
        'ai_rated_at' => now(),
    ]);

    replaceResume($application);

    expect($application->refresh())
        ->ai_status->toBe(AiRatingStatus::Pending)
        ->ai_score->toBeNull()
        ->ai_reasoning->toBeNull()
        ->ai_strengths->toBeNull()
        ->ai_gaps->toBeNull()
        ->ai_profile->toBeNull()
        ->skills->toBeNull()
        ->ai_rated_at->toBeNull();
});

test('a candidate without a job is refilled but not rated', function () {
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([newResumeDetails()]);

    $application = candidateWithOldDetails(['job_posting_id' => null]);

    replaceResume($application);

    expect($application->refresh()->name)->toBe('Hari Ram');
    Bus::assertNotDispatched(RateCandidateApplication::class);
});

test('a resume the parser cannot read leaves the details alone and is still rated', function () {
    Bus::fake([RateCandidateApplication::class]);
    Log::spy();
    ResumeParserAgent::fake(fn () => throw new RuntimeException('Provider unavailable'));

    $application = candidateWithOldDetails();

    replaceResume($application);

    expect($application->refresh())
        ->name->toBe('Old Name')
        ->current_company->toBe('Old Co');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Refilling candidate details'));
    Bus::assertDispatched(RateCandidateApplication::class);
});
