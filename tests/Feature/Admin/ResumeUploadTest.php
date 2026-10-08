<?php

use App\Ai\Agents\ResumeParserAgent;
use App\Enums\Gender;
use App\Enums\ResumeUploadItemStatus;
use App\Enums\ResumeUploadStatus;
use App\Jobs\ProcessResumeUpload;
use App\Jobs\RateCandidateApplication;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\ResumeUpload;
use App\Models\ResumeUploadItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

/**
 * What the AI parser returns for a resume, with any fields overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function parsedResume(array $overrides = []): array
{
    return [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9111111111',
        'gender' => null,
        'date_of_birth' => null,
        'total_experience_years' => 6.5,
        'current_company' => 'Acme',
        'current_designation' => 'Developer',
        'current_location' => 'Pune',
        'industry_type' => 'IT Services',
        'notice_period' => '30 days',
        ...$overrides,
    ];
}

function docxContents(string $text): string
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($text);
    $path = tempnam(sys_get_temp_dir(), 'resume').'.docx';
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    $contents = file_get_contents($path);
    unlink($path);

    return $contents;
}

function pdfUpload(string $name = 'jane.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 50, 'application/pdf');
}

/**
 * @param  array<string, string>  $files  Entry name => contents.
 */
function resumeZip(array $files): UploadedFile
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

test('guest is redirected away from uploads and their reports', function () {
    $upload = ResumeUpload::factory()->create();

    $this->post('/admin/resume-uploads')->assertRedirect('/login');
    $this->get('/admin/resume-uploads')->assertRedirect('/login');
    $this->get("/admin/resume-uploads/{$upload->id}")->assertRedirect('/login');
    $this->get("/admin/resume-uploads/{$upload->id}/issues")->assertRedirect('/login');
});

test('the old spreadsheet import is gone', function () {
    expect(Route::has('admin.candidates.import.create'))->toBeFalse()
        ->and(Route::has('admin.candidates.import.store'))->toBeFalse()
        ->and(Route::has('admin.candidates.import.template'))->toBeFalse();
});

test('an upload must be a pdf, docx or zip', function () {
    Storage::fake('local');

    $admin = User::factory()->create();

    $this->actingAs($admin)->post('/admin/resume-uploads', [])->assertSessionHasErrors('upload');

    $this->actingAs($admin)->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->create('resume.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('upload');

    $this->actingAs($admin)->post('/admin/resume-uploads', [
        'upload' => pdfUpload(),
        'job_posting_id' => 9999,
    ])->assertSessionHasErrors('job_posting_id');

    expect(ResumeUpload::query()->count())->toBe(0);
});

test('uploading a resume stores it and queues processing', function () {
    Storage::fake('local');
    Queue::fake();

    $job = JobPosting::factory()->create();

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => pdfUpload('Jane Resume.pdf'),
        'job_posting_id' => $job->id,
        'rate_with_ai' => true,
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $upload = ResumeUpload::query()->firstOrFail();

    expect($upload->status)->toBe(ResumeUploadStatus::Pending)
        ->and($upload->original_filename)->toBe('Jane Resume.pdf')
        ->and($upload->job_posting_id)->toBe($job->id)
        ->and($upload->rate_with_ai)->toBeTrue();

    Storage::disk('local')->assertExists($upload->upload_path);
    Queue::assertPushed(ProcessResumeUpload::class);
});

test('ai rating is never requested for an upload without a job', function () {
    Storage::fake('local');
    Queue::fake();

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => pdfUpload(),
        'rate_with_ai' => true,
    ])->assertSessionHasNoErrors();

    $upload = ResumeUpload::query()->firstOrFail();

    expect($upload->job_posting_id)->toBeNull()
        ->and($upload->rate_with_ai)->toBeFalse();
});

test('a single resume without a job adds an unassigned candidate parsed from the resume', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([parsedResume(['gender' => 'female', 'date_of_birth' => '1995-04-12'])]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => pdfUpload(),
    ])->assertSessionHasNoErrors();

    $candidate = JobApplication::query()->firstOrFail();

    expect($candidate)
        ->job_posting_id->toBeNull()
        ->name->toBe('Jane Doe')
        ->email->toBe('jane@example.com')
        ->phone->toBe('9111111111')
        ->gender->toBe(Gender::Female)
        ->date_of_birth->toDateString()->toBe('1995-04-12')
        ->total_experience->toBe(6.5)
        ->current_company->toBe('Acme')
        ->current_designation->toBe('Developer')
        ->current_location->toBe('Pune')
        ->industry_type->toBe('IT Services')
        ->notice_period->toBe('30 days')
        ->notice_period_days->toBe(30)
        ->resume_path->toStartWith('resumes/unassigned/')->toEndWith('.pdf');

    Storage::disk('local')->assertExists($candidate->resume_path);
    Bus::assertNotDispatched(RateCandidateApplication::class);

    $upload = ResumeUpload::query()->firstOrFail();

    expect($upload->status)->toBe(ResumeUploadStatus::Completed)
        ->and($upload->total)->toBe(1)
        ->and($upload->items)->toHaveCount(1)
        ->and($upload->items->first())
        ->status->toBe(ResumeUploadItemStatus::Created)
        ->job_application_id->toBe($candidate->id)
        ->file_path->toBeNull();

    expect(Storage::disk('local')->allFiles('resume-uploads'))->toBe([]);
});

test('a docx resume is read from its text', function () {
    Storage::fake('local');
    ResumeParserAgent::fake([parsedResume()]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->createWithContent('jane.docx', docxContents('Jane Doe - Senior PHP Developer')),
    ])->assertSessionHasNoErrors();

    ResumeParserAgent::assertPrompted(fn ($prompt) => $prompt->contains('Senior PHP Developer'));

    $candidate = JobApplication::query()->firstOrFail();
    expect($candidate->resume_path)->toEndWith('.docx');
});

test('a doc resume is read from its text', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([parsedResume(['name' => 'Rahul Verma', 'email' => 'rahul.verma.test@example.com'])]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->createWithContent('rahul.doc', file_get_contents(base_path('tests/Fixtures/resume-plain.doc'))),
    ])->assertSessionHasNoErrors();

    ResumeParserAgent::assertPrompted(fn ($prompt) => $prompt->contains('Senior Java Developer at Globex Technologies')
        && $prompt->contains('Skills	Java, Spring, AWS'));

    $candidate = JobApplication::query()->firstOrFail();

    expect($candidate->email)->toBe('rahul.verma.test@example.com')
        ->and($candidate->resume_path)->toEndWith('.doc');
    expect(ResumeUploadItem::query()->firstOrFail()->status)->toBe(ResumeUploadItemStatus::Created);
});

test('a doc resume in a zip is added alongside pdf and docx resumes', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([
        parsedResume(['email' => 'one@example.com']),
        parsedResume(['email' => 'two@example.com']),
        parsedResume(['email' => 'three@example.com']),
    ]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => resumeZip([
            'a.pdf' => 'pdf',
            'old/b.doc' => file_get_contents(base_path('tests/Fixtures/resume-unicode.doc')),
            'c.docx' => docxContents('Jane Doe - Developer'),
        ]),
    ])->assertSessionHasNoErrors();

    expect(JobApplication::query()->count())->toBe(3);
    expect(ResumeUploadItem::query()->where('status', ResumeUploadItemStatus::Created)->count())->toBe(3);
    ResumeParserAgent::assertPrompted(fn ($prompt) => $prompt->contains('José Müller'));
});

test('a doc that is really a docx is read as a docx', function () {
    Storage::fake('local');
    ResumeParserAgent::fake([parsedResume()]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->createWithContent('jane.doc', docxContents('Jane Doe - Senior PHP Developer')),
    ])->assertSessionHasNoErrors();

    ResumeParserAgent::assertPrompted(fn ($prompt) => $prompt->contains('Senior PHP Developer'));
    expect(JobApplication::query()->count())->toBe(1);
});

test('a corrupt doc fails its resume without stopping the rest', function () {
    Storage::fake('local');
    ResumeParserAgent::fake([parsedResume()]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => resumeZip([
            'broken.doc' => 'ÐÏà¡±á'.str_repeat(' ', 600),
            'fine.pdf' => 'pdf',
        ]),
    ])->assertSessionHasNoErrors();

    $items = ResumeUploadItem::query()->orderBy('id')->get()->keyBy('filename');

    expect($items['broken.doc'])->status->toBe(ResumeUploadItemStatus::Failed)->reason->toContain('could not be read')
        ->and($items['fine.pdf']->status)->toBe(ResumeUploadItemStatus::Created);
});

test('a doc saved by another tool is accepted whichever office mime type it is detected as', function (string $mimeType) {
    Storage::fake('local');
    Queue::fake();

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->create('resume.doc', 20, $mimeType),
    ])->assertSessionHasNoErrors();

    expect(ResumeUpload::query()->count())->toBe(1);
})->with(['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2']);

test('a resume uploaded for a job is added to it and rated when auto rating is on', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([parsedResume()]);

    $job = JobPosting::factory()->create();

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => pdfUpload(),
        'job_posting_id' => $job->id,
        'rate_with_ai' => true,
    ])->assertSessionHasNoErrors();

    $candidate = JobApplication::query()->firstOrFail();

    expect($candidate->job_posting_id)->toBe($job->id)
        ->and($candidate->resume_path)->toStartWith("resumes/{$job->id}/");

    Bus::assertDispatchedTimes(RateCandidateApplication::class, 1);
});

test('a resume uploaded for a job is not rated when auto rating is off', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([parsedResume()]);

    $job = JobPosting::factory()->create();

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => pdfUpload(),
        'job_posting_id' => $job->id,
        'rate_with_ai' => false,
    ])->assertSessionHasNoErrors();

    expect(JobApplication::query()->where('job_posting_id', $job->id)->count())->toBe(1);
    Bus::assertNotDispatched(RateCandidateApplication::class);
});

test('a zip adds a candidate per resume and reports what could not be added', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);

    JobApplication::factory()->unassigned()->create(['email' => 'dupe@example.com']);

    ResumeParserAgent::fake([
        parsedResume(),
        parsedResume(['name' => 'Bob Slug', 'email' => 'Bob@Example.com', 'phone' => '+91 92222 22222']),
        parsedResume(['name' => 'No Email', 'email' => null]),
        parsedResume(['name' => 'Bad Phone', 'email' => 'badphone@example.com', 'phone' => '12345']),
        parsedResume(['name' => 'Dupe', 'email' => 'dupe@example.com']),
    ]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => resumeZip([
            'jane.pdf' => 'pdf-content',
            'folder/bob.docx' => docxContents('Bob Slug - Developer'),
            'notes.txt' => 'not a resume',
            '__MACOSX/._jane.pdf' => 'junk',
            '.DS_Store' => 'junk',
            'noemail.pdf' => 'pdf-content',
            'badphone.pdf' => 'pdf-content',
            'dupe.pdf' => 'pdf-content',
            'big.pdf' => str_repeat('a', 6 * 1024 * 1024),
            '../escape.txt' => 'evil',
        ]),
    ])->assertSessionHasNoErrors();

    $upload = ResumeUpload::query()->firstOrFail();

    expect($upload->status)->toBe(ResumeUploadStatus::Completed)
        ->and($upload->total)->toBe(8);

    $items = $upload->items()->orderBy('id')->get()->keyBy('filename');

    expect($items['jane.pdf']->status)->toBe(ResumeUploadItemStatus::Created)
        ->and($items['bob.docx']->status)->toBe(ResumeUploadItemStatus::Created)
        ->and($items['notes.txt'])->status->toBe(ResumeUploadItemStatus::Failed)->reason->toContain('PDF, DOC and DOCX')
        ->and($items['noemail.pdf'])->status->toBe(ResumeUploadItemStatus::Failed)->reason->toContain('No email address')
        ->and($items['badphone.pdf'])->status->toBe(ResumeUploadItemStatus::Failed)->reason->toContain('Indian mobile')
        ->and($items['dupe.pdf'])->status->toBe(ResumeUploadItemStatus::Skipped)->reason->toContain('already exists')
        ->and($items['big.pdf'])->status->toBe(ResumeUploadItemStatus::Failed)->reason->toContain('5MB')
        ->and($items['escape.txt']->status)->toBe(ResumeUploadItemStatus::Failed);

    expect(JobApplication::query()->pluck('email')->sort()->values()->all())
        ->toBe(['bob@example.com', 'dupe@example.com', 'jane@example.com']);

    expect(Storage::disk('local')->allFiles('resume-uploads'))->toBe([]);
});

test('a resume is skipped when the email is already on the chosen job but not when it is on another job', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([parsedResume(), parsedResume()]);

    $job = JobPosting::factory()->create();
    $otherJob = JobPosting::factory()->create();
    JobApplication::factory()->create(['job_posting_id' => $otherJob->id, 'email' => 'JANE@example.com']);
    $admin = User::factory()->create();

    $this->actingAs($admin)->post('/admin/resume-uploads', ['upload' => pdfUpload(), 'job_posting_id' => $job->id]);

    expect(JobApplication::query()->where('job_posting_id', $job->id)->count())->toBe(1);

    $this->actingAs($admin)->post('/admin/resume-uploads', ['upload' => pdfUpload(), 'job_posting_id' => $job->id]);

    expect(JobApplication::query()->where('job_posting_id', $job->id)->count())->toBe(1);

    $second = ResumeUpload::query()->latest('id')->firstOrFail();
    expect($second->items->first())
        ->status->toBe(ResumeUploadItemStatus::Skipped)
        ->reason->toBe('Already added to this job.');
});

test('a resume that the ai cannot read fails without stopping the others', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    $calls = 0;
    ResumeParserAgent::fake(function () use (&$calls): array {
        if (++$calls === 1) {
            throw new RuntimeException('Provider unavailable');
        }

        return parsedResume();
    });

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => resumeZip(['first.pdf' => 'pdf', 'second.pdf' => 'pdf']),
    ])->assertSessionHasNoErrors();

    $items = ResumeUpload::query()->firstOrFail()->items()->orderBy('id')->get();

    expect($items[0])->status->toBe(ResumeUploadItemStatus::Failed)->reason->toContain('Provider unavailable')
        ->and($items[1]->status)->toBe(ResumeUploadItemStatus::Created)
        ->and(JobApplication::query()->count())->toBe(1);
});

test('a docx that cannot be opened fails the resume', function () {
    Storage::fake('local');
    ResumeParserAgent::fake([parsedResume()]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->createWithContent('broken.docx', 'this is not a docx'),
    ])->assertSessionHasNoErrors();

    $item = ResumeUploadItem::query()->firstOrFail();

    expect($item)->status->toBe(ResumeUploadItemStatus::Failed)->reason->toContain('could not be read');
    expect(JobApplication::query()->count())->toBe(0);
    ResumeParserAgent::assertNeverPrompted();
});

test('a single resume over 5MB is rejected in the report', function () {
    Storage::fake('local');
    ResumeParserAgent::fake([parsedResume()]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->createWithContent('big.pdf', str_repeat('a', 6 * 1024 * 1024)),
    ])->assertSessionHasNoErrors();

    expect(ResumeUploadItem::query()->firstOrFail())
        ->status->toBe(ResumeUploadItemStatus::Failed)
        ->reason->toContain('5MB');
    expect(ResumeUpload::query()->firstOrFail()->status)->toBe(ResumeUploadStatus::Completed);
    expect(JobApplication::query()->count())->toBe(0);
});

test('a zip that cannot be opened fails the whole upload', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => UploadedFile::fake()->createWithContent('resumes.zip', 'this is not a zip'),
    ])->assertSessionHasNoErrors();

    $upload = ResumeUpload::query()->firstOrFail();

    expect($upload->status)->toBe(ResumeUploadStatus::Failed)
        ->and($upload->error)->toBe('The ZIP file could not be opened.');
    expect(Storage::disk('local')->allFiles('resume-uploads'))->toBe([]);
});

test('a zip with no files in it fails the whole upload', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => resumeZip(['__MACOSX/._junk' => 'junk', 'folder/' => '']),
    ])->assertSessionHasNoErrors();

    $upload = ResumeUpload::query()->firstOrFail();

    expect($upload->status)->toBe(ResumeUploadStatus::Failed)
        ->and($upload->error)->toBe('No resumes were found in the ZIP.');
});

test('invalid optional details from the resume are dropped instead of failing it', function () {
    Storage::fake('local');
    Bus::fake([RateCandidateApplication::class]);
    ResumeParserAgent::fake([parsedResume([
        'gender' => 'robot',
        'date_of_birth' => '2999-01-01',
        'total_experience_years' => 500,
        'current_company' => '  Globex  ',
    ])]);

    $this->actingAs(User::factory()->create())->post('/admin/resume-uploads', [
        'upload' => pdfUpload(),
    ])->assertSessionHasNoErrors();

    expect(JobApplication::query()->firstOrFail())
        ->gender->toBeNull()
        ->date_of_birth->toBeNull()
        ->total_experience->toBeNull()
        ->current_company->toBe('Globex')
        ->current_location->toBe('Pune');
});

test('admin can see the upload reports with their counts', function () {
    $job = JobPosting::factory()->create(['title' => 'Laravel Developer']);
    $upload = ResumeUpload::factory()->create(['job_posting_id' => $job->id, 'rate_with_ai' => true, 'total' => 3]);
    ResumeUploadItem::factory()->create(['resume_upload_id' => $upload->id]);
    ResumeUploadItem::factory()->create(['resume_upload_id' => $upload->id, 'status' => ResumeUploadItemStatus::Skipped]);
    ResumeUploadItem::factory()->create(['resume_upload_id' => $upload->id, 'status' => ResumeUploadItemStatus::Failed]);
    ResumeUpload::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/admin/resume-uploads')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/resume-uploads/index')
            ->has('uploads.data', 2)
            ->where('uploads.data.1.job_title', 'Laravel Developer')
            ->where('uploads.data.1.created_count', 1)
            ->where('uploads.data.1.skipped_count', 1)
            ->where('uploads.data.1.failed_count', 1)
            ->where('uploads.data.1.has_issues', true)
            ->where('uploads.data.0.job_title', null)
        );
});

test('admin can open a single upload report with its files', function () {
    $upload = ResumeUpload::factory()->create(['total' => 2]);
    ResumeUploadItem::factory()->create(['resume_upload_id' => $upload->id, 'filename' => 'jane.pdf']);
    ResumeUploadItem::factory()->create([
        'resume_upload_id' => $upload->id,
        'filename' => 'bad.pdf',
        'status' => ResumeUploadItemStatus::Failed,
        'reason' => 'No email address was found in the resume.',
    ]);

    $this->actingAs(User::factory()->create())
        ->get("/admin/resume-uploads/{$upload->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/resume-uploads/show')
            ->where('upload.id', $upload->id)
            ->has('items.data', 2)
            ->where('items.data.1.filename', 'bad.pdf')
            ->where('items.data.1.status', 'failed')
        );
});

test('the issues csv lists only the files that were not added', function () {
    $upload = ResumeUpload::factory()->create();
    ResumeUploadItem::factory()->create(['resume_upload_id' => $upload->id, 'filename' => 'good.pdf', 'name' => 'Good Person']);
    ResumeUploadItem::factory()->create([
        'resume_upload_id' => $upload->id,
        'filename' => 'bad.pdf',
        'status' => ResumeUploadItemStatus::Failed,
        'name' => null,
        'email' => null,
        'reason' => 'No email address was found in the resume.',
    ]);

    $response = $this->actingAs(User::factory()->create())->get("/admin/resume-uploads/{$upload->id}/issues");

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('file,result,name,email,reason')
        ->toContain('bad.pdf,Failed,,,"No email address was found in the resume."')
        ->not->toContain('good.pdf');
});
