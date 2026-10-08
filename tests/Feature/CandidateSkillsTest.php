<?php

use App\Actions\Candidates\NormalizeSkills;
use App\Ai\Agents\CandidateRatingAgent;
use App\Jobs\RateCandidateApplication;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $skills
 * @return array<string, mixed>
 */
function ratingResponse(array $skills): array
{
    return [
        'profile' => [
            'skills' => $skills,
            'total_experience_years' => 5,
            'education' => [],
            'work_history' => [],
            'certifications' => [],
            'summary' => 'Engineer.',
        ],
        'rating' => ['score' => 7, 'reasoning' => 'Fine.', 'strengths' => [], 'gaps' => []],
    ];
}

function candidateWithStoredResume(array $attributes = []): JobApplication
{
    Storage::fake('local');
    Storage::disk('local')->put('resumes/1/resume.pdf', '%PDF-1.4');

    return JobApplication::factory()
        ->for(JobPosting::factory()->for(Company::factory())->for(User::factory(), 'postedBy'))
        ->create(['resume_path' => 'resumes/1/resume.pdf', ...$attributes]);
}

test('skills are tidied: trimmed, de-duplicated ignoring case, and limited', function (array $input, array $expected) {
    expect((new NormalizeSkills)->handle($input))->toBe($expected);
})->with([
    'trims and collapses spaces' => [['  PHP ', 'Machine   Learning'], ['PHP', 'Machine Learning']],
    'drops blanks and non-strings' => [['PHP', '', '   ', null, 5, ['x']], ['PHP']],
    'keeps the first spelling of a duplicate' => [['Laravel', 'laravel', 'LARAVEL', 'MySQL'], ['Laravel', 'MySQL']],
    'cuts a skill to 50 characters' => [[str_repeat('a', 80)], [str_repeat('a', 50)]],
    'keeps an unicode skill' => [['Ünïcode', 'ünïcode'], ['Ünïcode']],
    'empty' => [[], []],
]);

test('the list is limited to fifty skills', function () {
    $skills = array_map(fn (int $i): string => "Skill {$i}", range(1, 70));

    expect((new NormalizeSkills)->handle($skills))->toHaveCount(50);
});

test('a recruiter can edit a candidate\'s skills', function () {
    Bus::fake();

    $application = JobApplication::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['skills' => ['  PHP ', 'laravel', 'Laravel', 'Team Leadership', '']])
        ->assertSessionHasNoErrors();

    expect($application->refresh()->skills)->toBe(['PHP', 'laravel', 'Team Leadership']);
    Bus::assertNotDispatched(RateCandidateApplication::class);
});

test('removing every skill is kept as an empty list', function () {
    $application = JobApplication::factory()->create(['skills' => ['PHP']]);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['skills' => []])
        ->assertSessionHasNoErrors();

    expect($application->refresh()->skills)->toBe([]);
});

test('saving other details leaves the skills alone', function () {
    $application = JobApplication::factory()->create(['skills' => ['PHP', 'Laravel']]);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['name' => 'Renamed'])
        ->assertSessionHasNoErrors();

    expect($application->refresh())->skills->toBe(['PHP', 'Laravel'])->name->toBe('Renamed');
});

test('invalid skills are rejected', function (array $payload, string $errorKey) {
    $application = JobApplication::factory()->create(['skills' => ['PHP']]);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", $payload)
        ->assertSessionHasErrors($errorKey);

    expect($application->refresh()->skills)->toBe(['PHP']);
})->with([
    'not a list' => [['skills' => 'PHP, Laravel'], 'skills'],
    'too many' => [['skills' => array_map(fn (int $i): string => "Skill {$i}", range(1, 51))], 'skills'],
    'one too long' => [['skills' => ['PHP', str_repeat('a', 51)]], 'skills.1'],
    'not text' => [['skills' => [['nested']]], 'skills.0'],
]);

test('the candidates list sends the skills, empty when there are none', function () {
    JobApplication::factory()->create(['name' => 'With', 'skills' => ['PHP', 'Laravel'], 'created_at' => now()]);
    JobApplication::factory()->create(['name' => 'Without', 'skills' => null, 'created_at' => now()->subDay()]);

    $this->actingAs(User::factory()->create())
        ->get('/admin/candidates')
        ->assertInertia(fn (Assert $page) => $page
            ->where('candidates.data.0.skills', ['PHP', 'Laravel'])
            ->where('candidates.data.1.skills', [])
        );
});

test('the ai rating fills the skills when the candidate has none', function () {
    $application = candidateWithStoredResume();
    CandidateRatingAgent::fake([ratingResponse(['PHP', 'laravel', 'Laravel', ' Redis '])]);

    (new RateCandidateApplication($application))->handle();

    expect($application->refresh())
        ->skills->toBe(['PHP', 'laravel', 'Redis'])
        ->ai_profile->toHaveKey('skills');
});

test('a re-rate keeps skills the recruiter has edited', function () {
    $application = candidateWithStoredResume(['skills' => ['My Own Skill']]);
    CandidateRatingAgent::fake([ratingResponse(['PHP', 'Laravel'])]);

    (new RateCandidateApplication($application))->handle();

    expect($application->refresh())
        ->skills->toBe(['My Own Skill'])
        ->ai_profile->skills->toBe(['PHP', 'Laravel'])
        ->ai_score->toBe(7);
});

test('a recruiter who removed every skill is not given the ai skills back', function () {
    $application = candidateWithStoredResume(['skills' => []]);
    CandidateRatingAgent::fake([ratingResponse(['PHP'])]);

    (new RateCandidateApplication($application))->handle();

    expect($application->refresh()->skills)->toBe([]);
});

test('a rating that found no skills leaves them unset so a later one can fill them', function () {
    $application = candidateWithStoredResume();
    CandidateRatingAgent::fake([ratingResponse([])]);

    (new RateCandidateApplication($application))->handle();

    expect($application->refresh()->skills)->toBeNull();
});
