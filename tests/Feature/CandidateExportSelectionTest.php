<?php

use App\Ai\Agents\CandidateRatingAgent;
use App\Jobs\RateCandidateApplication;
use App\Mail\CandidatesExportMail;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * The candidate names in an exported workbook, in file order.
 *
 * @return array<int, string>
 */
function namesInWorkbook(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $contents);
    $rows = IOFactory::load($path)->getActiveSheet()->toArray();
    unlink($path);

    return collect($rows)->skip(1)->pluck(4)->sort()->values()->all();
}

/**
 * @return array<int, string>
 */
function exportedNames(string $query = ''): array
{
    /** @var TestResponse $response */
    $response = test()->actingAs(User::factory()->create())->get('/admin/candidates/export'.$query)->assertOk();

    return namesInWorkbook(file_get_contents($response->baseResponse->getFile()->getRealPath()));
}

/**
 * @return array<int, string>
 */
function listedNames(string $query = ''): array
{
    $names = [];

    test()->actingAs(User::factory()->create())
        ->get('/admin/candidates'.$query)
        ->assertInertia(function (Assert $page) use (&$names): void {
            $names = collect($page->toArray()['props']['candidates']['data'])->pluck('name')->sort()->values()->all();
        });

    return $names;
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<int, string>
 */
function emailedNames(array $payload): array
{
    Mail::fake();

    test()->actingAs(User::factory()->create())
        ->post('/admin/candidates/email-export', [
            'to' => ['hr@example.com'],
            'subject' => 'Candidates',
            'message' => 'See attached.',
            ...$payload,
        ])->assertSessionHasNoErrors();

    $names = [];

    Mail::assertSent(CandidatesExportMail::class, function (CandidatesExportMail $mail) use (&$names): bool {
        $names = namesInWorkbook($mail->rawAttachments[0]['data']);

        return true;
    });

    return $names;
}

function candidateNamed(string $name, array $attributes = []): JobApplication
{
    return JobApplication::factory()->create(['name' => $name, ...$attributes]);
}

// ---------------------------------------------------------------- ticking candidates

test('export includes only the ticked candidates', function () {
    $alice = candidateNamed('Alice');
    candidateNamed('Bob');
    $carol = candidateNamed('Carol');

    expect(exportedNames("?ids={$alice->id},{$carol->id}"))->toBe(['Alice', 'Carol']);
});

test('export with nothing ticked exports every candidate', function (string $query) {
    candidateNamed('Alice');
    candidateNamed('Bob');

    expect(exportedNames($query))->toBe(['Alice', 'Bob']);
})->with(['no parameter' => [''], 'blank ids' => ['?ids=']]);

test('ticked candidates are still limited by the filters', function () {
    $job = JobPosting::factory()->for(Company::factory())->create();
    $inJob = candidateNamed('In job', ['job_posting_id' => $job->id]);
    $elsewhere = candidateNamed('Elsewhere');
    candidateNamed('Not ticked', ['job_posting_id' => $job->id]);

    expect(exportedNames("?ids={$inJob->id},{$elsewhere->id}&job_posting_id={$job->id}"))->toBe(['In job']);
});

test('ids that are not numbers are ignored, and ticking only junk exports nothing', function () {
    $alice = candidateNamed('Alice');
    candidateNamed('Bob');

    expect(exportedNames("?ids={$alice->id},abc,-5,1e3,{$alice->id}"))->toBe(['Alice'])
        ->and(exportedNames('?ids=abc,xyz'))->toBe([]);
});

test('too many ticked candidates are refused', function () {
    $ids = implode(',', range(1, 501));

    $this->actingAs(User::factory()->create())
        ->get("/admin/candidates/export?ids={$ids}")
        ->assertSessionHasErrors('ids');
});

test('a ticked selection can be emailed instead of everything', function () {
    $alice = candidateNamed('Alice');
    candidateNamed('Bob');

    expect(emailedNames(['ids' => [$alice->id]]))->toBe(['Alice'])
        ->and(emailedNames([]))->toBe(['Alice', 'Bob']);
});

test('the emailed export rejects a bad selection or skills filter', function (array $payload, string $errorKey) {
    Mail::fake();

    $this->actingAs(User::factory()->create())
        ->post('/admin/candidates/email-export', ['to' => ['hr@example.com'], 'subject' => 'x', 'message' => 'y', ...$payload])
        ->assertSessionHasErrors($errorKey);

    Mail::assertNothingSent();
})->with([
    'too many ids' => [['ids' => range(1, 501)], 'ids'],
    'id not a number' => [['ids' => ['abc']], 'ids.0'],
    'ids not a list' => [['ids' => '1,2'], 'ids'],
    'bad match mode' => [['skills' => ['PHP'], 'skills_match' => 'some'], 'skills_match'],
    'too many skills' => [['skills' => array_map(fn (int $i): string => "S{$i}", range(1, 21))], 'skills'],
]);

// ---------------------------------------------------------------- exporting what is filtered

test('the export of the duplicates view contains only the duplicates', function () {
    candidateNamed('Original', ['email' => 'same@example.com', 'phone' => '9111111111']);
    candidateNamed('Copy', ['email' => 'same@example.com', 'phone' => '9222222222']);
    candidateNamed('Unique', ['email' => 'unique@example.com', 'phone' => '9333333333']);

    expect(exportedNames('?duplicates=1'))->toBe(['Copy', 'Original'])
        ->and(exportedNames())->toBe(['Copy', 'Original', 'Unique'])
        ->and(emailedNames(['duplicates' => '1']))->toBe(['Copy', 'Original']);
});

test('the export matches exactly what the list shows for every filter', function (string $query) {
    $job = JobPosting::factory()->for(Company::factory())->create();

    candidateNamed('Match', ['job_posting_id' => $job->id, 'skills' => ['PHP', 'Laravel'], 'total_experience' => 5, 'status' => 'shortlisted']);
    candidateNamed('Other job', ['skills' => ['PHP', 'Laravel'], 'total_experience' => 5, 'status' => 'shortlisted']);
    candidateNamed('Wrong skills', ['job_posting_id' => $job->id, 'skills' => ['Java'], 'total_experience' => 5, 'status' => 'shortlisted']);
    candidateNamed('Too junior', ['job_posting_id' => $job->id, 'skills' => ['PHP', 'Laravel'], 'total_experience' => 1, 'status' => 'shortlisted']);
    candidateNamed('New status', ['job_posting_id' => $job->id, 'skills' => ['PHP', 'Laravel'], 'total_experience' => 5, 'status' => 'new']);

    $query = str_replace('{job}', (string) $job->id, $query);

    expect(exportedNames($query))->toBe(listedNames($query))->not->toBeEmpty();
})->with([
    'job' => ['?job_posting_id={job}'],
    'skills' => ['?skills[]=PHP&skills[]=Laravel'],
    'everything at once' => ['?job_posting_id={job}&skills[]=php&status=shortlisted&experience_min=3'],
]);

// ---------------------------------------------------------------- skills filter

test('the skills filter wants every chosen skill by default, ignoring case', function () {
    candidateNamed('Both', ['skills' => ['PHP', 'Laravel', 'Redis']]);
    candidateNamed('Only PHP', ['skills' => ['PHP']]);
    candidateNamed('Only Laravel', ['skills' => ['laravel']]);
    candidateNamed('No skills', ['skills' => null]);

    expect(listedNames('?skills[]=php&skills[]=LARAVEL'))->toBe(['Both'])
        ->and(listedNames('?skills[]=PHP'))->toBe(['Both', 'Only PHP'])
        ->and(listedNames('?skills[]=Redis&skills_match=all'))->toBe(['Both']);
});

test('the skills filter can match any of the chosen skills', function () {
    candidateNamed('Both', ['skills' => ['PHP', 'Laravel']]);
    candidateNamed('Only PHP', ['skills' => ['PHP']]);
    candidateNamed('Only Go', ['skills' => ['Go']]);
    candidateNamed('Neither', ['skills' => ['Java']]);

    expect(listedNames('?skills[]=PHP&skills[]=Go&skills_match=any'))->toBe(['Both', 'Only Go', 'Only PHP'])
        ->and(exportedNames('?skills[]=PHP&skills[]=Go&skills_match=any'))->toBe(['Both', 'Only Go', 'Only PHP'])
        ->and(emailedNames(['skills' => ['PHP', 'Go'], 'skills_match' => 'any']))->toBe(['Both', 'Only Go', 'Only PHP']);
});

test('a skill also matches part of another skill', function () {
    candidateNamed('Javascript dev', ['skills' => ['JavaScript']]);
    candidateNamed('Java dev', ['skills' => ['Java']]);
    candidateNamed('React dev', ['skills' => ['React.js', 'Node']]);
    candidateNamed('Spaced', ['skills' => ['Machine Learning']]);

    expect(listedNames('?skills[]=Java'))->toBe(['Java dev', 'Javascript dev'])
        ->and(listedNames('?skills[]=Script'))->toBe(['Javascript dev'])
        ->and(listedNames('?skills[]=react&skills[]=node'))->toBe(['React dev'])
        ->and(listedNames('?skills[]=Python'))->toBe([])
        ->and(listedNames('?skills[]='.urlencode('  machine   learning ')))->toBe(['Spaced'])
        ->and(listedNames('?skills[]='.urlencode('chine lear')))->toBe(['Spaced']);
});

test('wildcard characters in a skill only match themselves', function () {
    candidateNamed('C sharp', ['skills' => ['C#']]);
    candidateNamed('Underscore', ['skills' => ['C_']]);
    candidateNamed('Percent', ['skills' => ['100%']]);
    candidateNamed('Plain C', ['skills' => ['CX']]);

    expect(listedNames('?skills[]='.urlencode('C_')))->toBe(['Underscore'])
        ->and(listedNames('?skills[]='.urlencode('%')))->toBe(['Percent'])
        ->and(listedNames('?skills[]='.urlencode('100%')))->toBe(['Percent'])
        ->and(listedNames('?skills[]='.urlencode('C#')))->toBe(['C sharp']);
});

test('an empty or junk skills filter changes nothing', function () {
    candidateNamed('A', ['skills' => ['PHP']]);
    candidateNamed('B', ['skills' => null]);

    expect(listedNames())->toBe(['A', 'B'])
        ->and(listedNames('?skills[]='))->toBe(['A', 'B'])
        ->and(listedNames('?skills=PHP'))->toBe(['A'])
        ->and(listedNames('?skills[]=PHP&skills_match=bogus'))->toBe(['A']);
});

test('the list sends the skill filters back', function () {
    candidateNamed('One', ['skills' => ['PHP', 'laravel', 'Redis']]);

    $this->actingAs(User::factory()->create())
        ->get('/admin/candidates?skills[]=PHP&skills[]=Redis&skills_match=any')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.skills', ['PHP', 'Redis'])
            ->where('filters.skills_match', 'any')
            ->missing('skillOptions')
        );

    $this->actingAs(User::factory()->create())
        ->get('/admin/candidates?skills[]=PHP&skills_match=all')
        ->assertInertia(fn (Assert $page) => $page->where('filters.skills_match', null));
});

// ---------------------------------------------------------------- the search column stays in step

test('the skills search column follows every way skills change', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);

    $application = candidateNamed('Someone', ['skills' => ['PHP', 'Machine  Learning', 'php']]);
    expect($application->skills_search)->toBe('|php|machine learning|');

    $admin = User::factory()->create();

    $this->actingAs($admin)->patch("/admin/candidates/{$application->id}", ['skills' => ['Go', 'Rust']])->assertSessionHasNoErrors();
    expect($application->refresh()->skills_search)->toBe('|go|rust|');

    $this->actingAs($admin)->patch("/admin/candidates/{$application->id}", ['skills' => []])->assertSessionHasNoErrors();
    expect($application->refresh()->skills_search)->toBeNull();

    $this->actingAs($admin)->patch("/admin/candidates/{$application->id}", ['name' => 'Renamed'])->assertSessionHasNoErrors();
    expect($application->refresh()->skills_search)->toBeNull();

    $application->update(['skills' => ['Kotlin']]);
    $this->actingAs($admin)->post("/admin/candidates/{$application->id}/resume", [
        'resume' => UploadedFile::fake()->create('new.pdf', 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    expect($application->refresh())->skills->toBeNull()->skills_search->toBeNull();
});

test('skills the ai finds become filterable', function () {
    Storage::fake('local');
    Storage::disk('local')->put('resumes/1/resume.pdf', '%PDF-1.4');

    $application = JobApplication::factory()
        ->for(JobPosting::factory()->for(Company::factory())->for(User::factory(), 'postedBy'))
        ->create(['name' => 'Rated', 'resume_path' => 'resumes/1/resume.pdf', 'skills' => null]);

    CandidateRatingAgent::fake([[
        'profile' => ['skills' => ['TypeScript', 'React'], 'total_experience_years' => 4, 'education' => [], 'work_history' => [], 'certifications' => [], 'summary' => 'x'],
        'rating' => ['score' => 7, 'reasoning' => 'Fine.', 'strengths' => [], 'gaps' => []],
    ]]);

    (new RateCandidateApplication($application))->handle();

    expect($application->refresh()->skills_search)->toBe('|typescript|react|')
        ->and(listedNames('?skills[]=react'))->toBe(['Rated']);
});
