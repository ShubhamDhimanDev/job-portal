<?php

use App\Enums\ResumeUploadItemStatus;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\ResumeUpload;
use App\Models\ResumeUploadItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('candidates and jobs get a code built from their id', function () {
    $job = JobPosting::factory()->create();
    $candidate = JobApplication::factory()->create(['job_posting_id' => $job->id]);

    expect($job->code)->toBe(sprintf('JOB-%05d', $job->id))
        ->and($candidate->code)->toBe(sprintf('CAN-%05d', $candidate->id))
        ->and($job->fresh()->code)->toBe($job->code)
        ->and($candidate->fresh()->code)->toBe($candidate->code);
});

test('codes are unique and keep growing past five digits', function () {
    $codes = JobApplication::factory()->unassigned()->count(3)->create()->pluck('code');

    expect($codes->unique())->toHaveCount(3);
    expect(JobApplication::referenceCodeFor(7))->toBe('CAN-00007')
        ->and(JobApplication::referenceCodeFor(123456))->toBe('CAN-123456')
        ->and(JobPosting::referenceCodeFor(12))->toBe('JOB-00012');
});

test('a duplicated job gets its own code', function () {
    $job = JobPosting::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post("/admin/job-postings/{$job->slug}/duplicate")
        ->assertRedirect();

    $copy = JobPosting::query()->where('title', "{$job->title} (Copy)")->firstOrFail();

    expect($copy->code)->not->toBe($job->code)->toBe(sprintf('JOB-%05d', $copy->id));
});

test('the candidates list sends candidate and job codes and the job options carry codes', function () {
    $job = JobPosting::factory()->create();
    $candidate = JobApplication::factory()->create(['job_posting_id' => $job->id]);

    $this->actingAs(User::factory()->create())
        ->get('/admin/candidates')
        ->assertInertia(fn (Assert $page) => $page
            ->where('candidates.data.0.code', $candidate->code)
            ->where('candidates.data.0.job_posting.code', $job->code)
            ->where('jobPostings.0.code', $job->code)
        );
});

test('candidates can be searched by candidate code or job code', function () {
    $jobA = JobPosting::factory()->create();
    $jobB = JobPosting::factory()->create();
    $inA = JobApplication::factory()->create(['job_posting_id' => $jobA->id, 'name' => 'In A']);
    $inB = JobApplication::factory()->create(['job_posting_id' => $jobB->id, 'name' => 'In B']);
    $unassigned = JobApplication::factory()->unassigned()->create(['name' => 'Floating']);

    $search = function (string $term): array {
        $names = [];

        test()->actingAs(User::factory()->create())
            ->get('/admin/candidates?search='.urlencode($term))
            ->assertInertia(function (Assert $page) use (&$names): void {
                $names = collect($page->toArray()['props']['candidates']['data'])->pluck('name')->sort()->values()->all();
            });

        return $names;
    };

    expect($search($inB->code))->toBe(['In B'])
        ->and($search($unassigned->code))->toBe(['Floating'])
        ->and($search($jobA->code))->toBe(['In A'])
        ->and(strtolower($search(strtolower($jobB->code))[0]))->toBe('in b');
});

test('job postings list shows and searches the job code', function () {
    $job = JobPosting::factory()->for(Company::factory())->create(['title' => 'Alpha']);
    JobPosting::factory()->for(Company::factory())->create(['title' => 'Beta']);

    $this->actingAs(User::factory()->create())
        ->get('/admin/job-postings?search='.$job->code)
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobPostings.data', 1)
            ->where('jobPostings.data.0.code', $job->code)
            ->where('jobPostings.data.0.title', 'Alpha')
        );
});

test('upload reports show the job code and the code of each candidate added', function () {
    $job = JobPosting::factory()->create();
    $candidate = JobApplication::factory()->create(['job_posting_id' => $job->id]);
    $upload = ResumeUpload::factory()->create(['job_posting_id' => $job->id]);
    ResumeUploadItem::factory()->create([
        'resume_upload_id' => $upload->id,
        'job_application_id' => $candidate->id,
    ]);
    ResumeUploadItem::factory()->create([
        'resume_upload_id' => $upload->id,
        'status' => ResumeUploadItemStatus::Failed,
    ]);

    $admin = User::factory()->create();

    $this->actingAs($admin)->get('/admin/resume-uploads')
        ->assertInertia(fn (Assert $page) => $page->where('uploads.data.0.job_code', $job->code));

    $this->actingAs($admin)->get("/admin/resume-uploads/{$upload->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('upload.job_code', $job->code)
            ->where('items.data.0.candidate_code', $candidate->code)
            ->where('items.data.1.candidate_code', null)
        );
});

test('the public job pages carry the job code', function () {
    $job = JobPosting::factory()->published()->create();

    $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('jobs.data.0.code', $job->code));
    $this->get("/jobs/{$job->slug}")->assertInertia(fn (Assert $page) => $page->where('job.code', $job->code));
});

test('candidates added from a resume upload or the public form get a code', function () {
    Storage::fake('local');
    Bus::fake();

    $job = JobPosting::factory()->published()->create();

    $this->post("/jobs/{$job->slug}/apply", [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9876543210',
        'resume' => UploadedFile::fake()->create('resume.pdf', 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $candidate = JobApplication::query()->firstOrFail();

    expect($candidate->code)->toBe(sprintf('CAN-%05d', $candidate->id));
});
