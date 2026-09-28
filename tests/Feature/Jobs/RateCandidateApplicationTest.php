<?php

use App\Ai\Agents\CandidateRatingAgent;
use App\Enums\AiRatingStatus;
use App\Jobs\RateCandidateApplication;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

function fakeJobPosting(): JobPosting
{
    return JobPosting::factory()
        ->for(Company::factory())
        ->for(User::factory(), 'postedBy')
        ->create();
}

test('rates a pdf resume and stores the parsed profile and score', function () {
    Storage::fake('local');

    $jobPosting = fakeJobPosting();
    $resume = UploadedFile::fake()->create('resume.pdf', 50, 'application/pdf');
    $path = $resume->storeAs("resumes/{$jobPosting->id}", 'resume.pdf', 'local');

    $application = JobApplication::factory()->for($jobPosting)->create([
        'resume_path' => $path,
        'ai_status' => AiRatingStatus::Pending,
    ]);

    CandidateRatingAgent::fake([
        [
            'profile' => [
                'skills' => ['PHP', 'Laravel'],
                'total_experience_years' => 5,
                'education' => [],
                'work_history' => [],
                'certifications' => [],
                'summary' => 'Solid backend engineer.',
            ],
            'rating' => [
                'score' => 8,
                'reasoning' => 'Strong match on required skills.',
                'strengths' => ['Laravel experience'],
                'gaps' => ['No cloud experience'],
            ],
        ],
    ]);

    (new RateCandidateApplication($application))->handle();

    $application->refresh();

    expect($application->ai_status)->toBe(AiRatingStatus::Completed)
        ->and($application->ai_score)->toBe(8)
        ->and($application->ai_reasoning)->toBe('Strong match on required skills.')
        ->and($application->ai_strengths)->toBe(['Laravel experience'])
        ->and($application->ai_gaps)->toBe(['No cloud experience'])
        ->and($application->ai_profile['skills'])->toBe(['PHP', 'Laravel'])
        ->and($application->ai_rated_at)->not->toBeNull()
        ->and($application->ai_error)->toBeNull();

    CandidateRatingAgent::assertPrompted(
        fn ($prompt) => $prompt->contains('attached resume')
    );
});

test('rates a docx resume by extracting its text instead of attaching it', function () {
    Storage::fake('local');

    $jobPosting = fakeJobPosting();

    // A minimal real .docx so PhpWord can actually parse it.
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Jane Doe - Senior PHP Developer with 6 years of experience.');
    $tempPath = tempnam(sys_get_temp_dir(), 'resume').'.docx';
    IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);

    $path = "resumes/{$jobPosting->id}/resume.docx";
    Storage::disk('local')->put($path, file_get_contents($tempPath));
    unlink($tempPath);

    $application = JobApplication::factory()->for($jobPosting)->create([
        'resume_path' => $path,
        'ai_status' => AiRatingStatus::Pending,
    ]);

    CandidateRatingAgent::fake([
        [
            'profile' => [
                'skills' => ['PHP'],
                'total_experience_years' => 6,
                'education' => [],
                'work_history' => [],
                'certifications' => [],
                'summary' => 'Senior PHP developer.',
            ],
            'rating' => [
                'score' => 7,
                'reasoning' => 'Good experience level.',
                'strengths' => [],
                'gaps' => [],
            ],
        ],
    ]);

    (new RateCandidateApplication($application))->handle();

    $application->refresh();

    expect($application->ai_status)->toBe(AiRatingStatus::Completed)
        ->and($application->ai_score)->toBe(7);

    CandidateRatingAgent::assertPrompted(
        fn ($prompt) => $prompt->contains('Jane Doe')
    );
});

test('marks the application as failed when rating cannot complete', function () {
    Storage::fake('local');

    $jobPosting = fakeJobPosting();

    $application = JobApplication::factory()->for($jobPosting)->create([
        'resume_path' => "resumes/{$jobPosting->id}/missing.docx",
        'ai_status' => AiRatingStatus::Pending,
    ]);

    (new RateCandidateApplication($application))->handle();

    $application->refresh();

    expect($application->ai_status)->toBe(AiRatingStatus::Failed)
        ->and($application->ai_error)->not->toBeNull()
        ->and($application->ai_score)->toBeNull();
});
