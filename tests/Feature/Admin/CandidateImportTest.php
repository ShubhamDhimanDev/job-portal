<?php

use App\Enums\CandidateImportStatus;
use App\Enums\Gender;
use App\Enums\InterviewType;
use App\Jobs\ProcessCandidateImport;
use App\Jobs\RateCandidateApplication;
use App\Models\CandidateImport;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $files  Entry name => contents.
 */
function makeResumeZip(array $files): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return new UploadedFile($path, 'resumes.zip', 'application/zip', null, true);
}

/**
 * @param  array<int, array<int, string>>  $rows
 */
function makeCandidatesCsv(array $rows): UploadedFile
{
    $lines = array_map(fn (array $row) => implode(',', $row), [
        ['name', 'email', 'phone', 'job', 'resume_filename'],
        ...$rows,
    ]);

    return UploadedFile::fake()->createWithContent('candidates.csv', implode("\n", $lines));
}

test('guest is redirected away from the import page', function () {
    $this->get('/admin/candidates/import')->assertRedirect('/login');
    $this->post('/admin/candidates/import')->assertRedirect('/login');
});

test('admin can view the import page with recent imports', function () {
    CandidateImport::factory()->count(2)->create();
    JobPosting::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get('/admin/candidates/import')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/candidates/import')
            ->has('imports', 2)
            ->has('jobPostings', 3)
        );
});

test('the import template is an xlsx with a job dropdown and a hidden jobs sheet', function () {
    $job = JobPosting::factory()->create(['slug' => 'laravel-dev']);

    $response = $this->actingAs(User::factory()->create())->get('/admin/candidates/import/template');

    $response->assertOk();
    $response->assertDownload('candidate-import-template.xlsx');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))
        ->toContain('showDropDown="0"')
        ->toContain('sqref="D2:D1000"');
    $zip->close();

    $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());

    $candidates = $spreadsheet->getSheetByName('Candidates');
    expect($candidates->getCell('D1')->getValue())->toBe('job');
    expect($candidates->getCell('D2')->getDataValidation()->getType())->toBe('list');
    expect($candidates->getCell('D2')->getDataValidation()->getFormula1())->toBe('Jobs!$C$2:$C$2');

    $jobs = $spreadsheet->getSheetByName('Jobs');
    expect($jobs->getSheetState())->toBe('hidden');
    expect($jobs->getCell('A2')->getValue())->toBe($job->id);
    expect($jobs->getCell('C2')->getValue())->toBe('laravel-dev');
});

test('uploading an import queues processing', function () {
    Storage::fake('local');
    Queue::fake();

    $response = $this->actingAs(User::factory()->create())->post('/admin/candidates/import', [
        'spreadsheet' => makeCandidatesCsv([['Jane', 'jane@example.com', '9999999999', 'x', 'jane.pdf']]),
        'archive' => makeResumeZip(['jane.pdf' => 'pdf']),
    ]);

    $response->assertSessionHasNoErrors();

    $import = CandidateImport::query()->firstOrFail();
    expect($import->status)->toBe(CandidateImportStatus::Pending);
    Storage::disk('local')->assertExists($import->spreadsheet_path);
    Storage::disk('local')->assertExists($import->archive_path);

    Queue::assertPushed(ProcessCandidateImport::class);
});

test('import requires a csv or xlsx spreadsheet', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post('/admin/candidates/import', [
        'spreadsheet' => UploadedFile::fake()->create('candidates.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('spreadsheet');
});

test('processing an import creates candidates and reports issues', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);

    $admin = User::factory()->create();
    $job = JobPosting::factory()->create(['slug' => 'laravel-dev']);
    JobApplication::factory()->create(['job_posting_id' => $job->id, 'email' => 'dupe@example.com']);

    $this->actingAs($admin)->post('/admin/candidates/import', [
        'spreadsheet' => makeCandidatesCsv([
            ['Jane Doe', 'jane@example.com', '1111111111', 'laravel-dev', 'Jane.PDF'],
            ['Bob Slug', 'bob@example.com', '2222222222', (string) $job->id, 'bob.docx'],
            ['Dupe', 'dupe@example.com', '3333333333', 'laravel-dev', 'jane.pdf'],
            ['No Job', 'nojob@example.com', '4444444444', 'missing-job', 'jane.pdf'],
            ['No File', 'nofile@example.com', '5555555555', 'laravel-dev', 'ghost.pdf'],
            ['Bad Type', 'bad@example.com', '6666666666', 'laravel-dev', 'notes.txt'],
            ['Bad Email', 'not-an-email', '7777777777', 'laravel-dev', 'jane.pdf'],
        ]),
        'archive' => makeResumeZip([
            'jane.pdf' => 'pdf-content',
            'folder/bob.docx' => 'docx-content',
            'notes.txt' => 'text',
            '../escape.pdf' => 'evil',
        ]),
    ])->assertSessionHasNoErrors();

    $import = CandidateImport::query()->firstOrFail()->refresh();

    expect($import->status)->toBe(CandidateImportStatus::Completed);
    expect($import->total)->toBe(7);
    expect($import->created_count)->toBe(4);
    expect($import->skipped_count)->toBe(1);
    expect($import->failed_count)->toBe(2);
    expect(collect($import->issues)->pluck('row')->all())->toBe([4, 5, 6, 7, 8]);
    expect(collect($import->issues)->where('type', 'warning')->pluck('row')->values()->all())->toBe([6, 7]);

    expect(JobApplication::query()->where('email', 'nofile@example.com')->value('resume_path'))->toBeNull();
    expect(JobApplication::query()->where('email', 'bad@example.com')->value('resume_path'))->toBeNull();

    $jane = JobApplication::query()->where('email', 'jane@example.com')->firstOrFail();
    expect($jane->job_posting_id)->toBe($job->id);
    expect($jane->resume_path)->toStartWith("resumes/{$job->id}/")->toEndWith('.pdf');
    Storage::disk('local')->assertExists($jane->resume_path);

    expect(JobApplication::query()->where('email', 'bob@example.com')->exists())->toBeTrue();

    Bus::assertDispatchedTimes(RateCandidateApplication::class, 2);

    Storage::disk('local')->assertMissing($import->spreadsheet_path);
    Storage::disk('local')->assertMissing($import->archive_path);
});

test('ai rating can be turned off for an import', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);

    JobPosting::factory()->create(['slug' => 'laravel-dev']);

    $this->actingAs(User::factory()->create())->post('/admin/candidates/import', [
        'spreadsheet' => makeCandidatesCsv([['Jane', 'jane@example.com', '1111111111', 'laravel-dev', 'jane.pdf']]),
        'archive' => makeResumeZip(['jane.pdf' => 'pdf']),
        'rate_with_ai' => false,
    ])->assertSessionHasNoErrors();

    expect(JobApplication::query()->count())->toBe(1);
    Bus::assertNotDispatched(RateCandidateApplication::class);
});

test('a row with no resume filename is added without a resume', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);

    JobPosting::factory()->create(['slug' => 'laravel-dev']);

    $this->actingAs(User::factory()->create())->post('/admin/candidates/import', [
        'spreadsheet' => makeCandidatesCsv([['Jane', 'jane@example.com', '1111111111', 'laravel-dev', '']]),
    ])->assertSessionHasNoErrors();

    expect(JobApplication::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
    expect(CandidateImport::query()->firstOrFail()->created_count)->toBe(1);
});

test('import without an archive still adds candidates, without resumes', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);

    JobPosting::factory()->create(['slug' => 'laravel-dev']);

    $this->actingAs(User::factory()->create())->post('/admin/candidates/import', [
        'spreadsheet' => makeCandidatesCsv([['Jane', 'jane@example.com', '1111111111', 'laravel-dev', 'jane.pdf']]),
    ])->assertSessionHasNoErrors();

    $import = CandidateImport::query()->firstOrFail();

    expect($import->created_count)->toBe(1);
    expect($import->failed_count)->toBe(0);
    expect(collect($import->issues)->pluck('type')->all())->toBe(['warning']);
    expect(JobApplication::query()->firstOrFail()->resume_path)->toBeNull();
    Bus::assertNotDispatched(RateCandidateApplication::class);
});

test('admin can download the issues csv of an import', function () {
    $import = CandidateImport::factory()->create([
        'issues' => [['row' => 3, 'type' => 'failed', 'name' => 'Jane', 'email' => 'jane@example.com', 'reason' => 'Job not found']],
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get("/admin/candidates/import/{$import->id}/issues");

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('row,type,name,email,reason')
        ->toContain('3,failed,Jane,jane@example.com,"Job not found"');
});

test('import reads the optional profile columns and ignores invalid ones with a warning', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);

    JobPosting::factory()->create(['slug' => 'laravel-dev']);

    $header = 'name,email,phone,job,resume_filename,gender,date_of_birth,total_experience,relevant_experience,current_company,industry_type,current_designation,current_location,current_ctc,expected_ctc,notice_period,interview_type';
    $csv = implode("\n", [
        $header,
        'Jane Doe,jane@example.com,1111111111,laravel-dev,,female,1995-04-12,6.5,4,Acme,IT,Developer,Pune,1200000,1500000,30 days,Face to Face',
        'Bad Values,bad@example.com,2222222222,laravel-dev,,robot,not-a-date,lots,,,,,,,,,phone',
    ]);

    $this->actingAs(User::factory()->create())->post('/admin/candidates/import', [
        'spreadsheet' => UploadedFile::fake()->createWithContent('candidates.csv', $csv),
        'archive' => makeResumeZip(['x.pdf' => 'pdf']),
    ])->assertSessionHasNoErrors();

    $import = CandidateImport::query()->firstOrFail()->refresh();
    expect($import->created_count)->toBe(2);

    $jane = JobApplication::query()->where('email', 'jane@example.com')->firstOrFail();
    expect($jane)
        ->gender->toBe(Gender::Female)
        ->interview_type->toBe(InterviewType::FaceToFace)
        ->date_of_birth->toDateString()->toBe('1995-04-12')
        ->total_experience->toBe(6.5)
        ->current_company->toBe('Acme')
        ->expected_ctc->toBe(1500000.0)
        ->notice_period->toBe('30 days');

    $bad = JobApplication::query()->where('email', 'bad@example.com')->firstOrFail();
    expect($bad->gender)->toBeNull()
        ->and($bad->total_experience)->toBeNull()
        ->and($bad->interview_type)->toBeNull();

    $ignored = collect($import->issues)->first(fn (array $issue): bool => str_starts_with($issue['reason'], 'Ignored invalid'));

    expect($ignored['reason'])->toContain('gender', 'date_of_birth', 'total_experience', 'interview_type');
});

test('the import template includes the profile columns with dropdowns', function () {
    $response = $this->actingAs(User::factory()->create())->get('/admin/candidates/import/template');

    $candidates = IOFactory::load($response->baseResponse->getFile()->getPathname())->getSheetByName('Candidates');

    expect($candidates->getCell('F1')->getValue())->toBe('gender')
        ->and($candidates->getCell('Q1')->getValue())->toBe('interview_type')
        ->and($candidates->getCell('F2')->getDataValidation()->getFormula1())->toBe('"Male,Female,Other"')
        ->and($candidates->getCell('Q2')->getDataValidation()->getFormula1())->toBe('"Face to Face,Virtual"');
});
