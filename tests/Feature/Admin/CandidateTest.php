<?php

use App\Enums\AiRatingStatus;
use App\Enums\ApplicationStatus;
use App\Jobs\RateCandidateApplication;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest is redirected away from the candidates list', function () {
    $response = $this->get('/admin/candidates');

    $response->assertRedirect('/login');
});

test('admin can view the candidates list', function () {
    $admin = User::factory()->create();
    JobApplication::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get('/admin/candidates');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/candidates/index')
        ->has('candidates.data', 3)
        ->has('jobPostings')
        ->has('statuses')
    );
});

test('candidates list filters by job posting', function () {
    $admin = User::factory()->create();

    $jobA = JobPosting::factory()->create();
    $jobB = JobPosting::factory()->create();

    JobApplication::factory()->count(2)->create(['job_posting_id' => $jobA->id]);
    JobApplication::factory()->count(3)->create(['job_posting_id' => $jobB->id]);

    $response = $this->actingAs($admin)->get('/admin/candidates?job_posting_id='.$jobA->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/candidates/index')
        ->has('candidates.data', 2)
        ->where('filters.job_posting_id', (string) $jobA->id)
    );
});

test('candidates list filters by status', function () {
    $admin = User::factory()->create();

    JobApplication::factory()->count(2)->create(['status' => ApplicationStatus::Shortlisted]);
    JobApplication::factory()->count(4)->create(['status' => ApplicationStatus::New]);

    $response = $this->actingAs($admin)->get('/admin/candidates?status=shortlisted');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/candidates/index')
        ->has('candidates.data', 2)
    );
});

test('candidates list filters by search term matching name, email, or phone', function () {
    $admin = User::factory()->create();

    JobApplication::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
    JobApplication::factory()->create(['name' => 'Rohit Verma', 'email' => 'rohit@example.com']);

    $response = $this->actingAs($admin)->get('/admin/candidates?search=priya');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/candidates/index')
        ->has('candidates.data', 1)
        ->where('candidates.data.0.name', 'Priya Sharma')
    );
});

test('admin can update a candidate status and notes', function () {
    $admin = User::factory()->create();
    $application = JobApplication::factory()->create(['status' => ApplicationStatus::New]);

    $response = $this->actingAs($admin)->patch("/admin/candidates/{$application->id}", [
        'status' => ApplicationStatus::Shortlisted->value,
        'admin_notes' => 'Strong candidate, schedule interview.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Shortlisted)
        ->admin_notes->toBe('Strong candidate, schedule interview.');
});

test('admin can download a candidate resume', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $company = Company::factory()->create();
    $jobPosting = JobPosting::factory()->create(['company_id' => $company->id]);

    $path = 'resumes/'.$jobPosting->id.'/test-resume.pdf';
    Storage::disk('local')->put($path, 'fake-pdf-contents');

    $application = JobApplication::factory()->create([
        'job_posting_id' => $jobPosting->id,
        'resume_path' => $path,
    ]);

    $response = $this->actingAs($admin)->get("/admin/candidates/{$application->id}/resume");

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

test('resume download 404s when the file is missing on disk', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $application = JobApplication::factory()->create([
        'resume_path' => 'resumes/does-not-exist.pdf',
    ]);

    $response = $this->actingAs($admin)->get("/admin/candidates/{$application->id}/resume");

    $response->assertNotFound();
});

test('resume download 404s for a non-existent candidate id', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->get('/admin/candidates/999999/resume');

    $response->assertNotFound();
});

test('candidates list can be sorted by ai score, highest first, with unrated last', function () {
    $admin = User::factory()->create();

    $low = JobApplication::factory()->create(['ai_status' => AiRatingStatus::Completed, 'ai_score' => 3]);
    $high = JobApplication::factory()->create(['ai_status' => AiRatingStatus::Completed, 'ai_score' => 9]);
    $unrated = JobApplication::factory()->create(['ai_status' => AiRatingStatus::Pending, 'ai_score' => null]);

    $response = $this->actingAs($admin)->get('/admin/candidates?sort=ai_score');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/candidates/index')
        ->where('candidates.data.0.id', $high->id)
        ->where('candidates.data.1.id', $low->id)
        ->where('candidates.data.2.id', $unrated->id)
        ->where('filters.sort', 'ai_score')
    );
});

test('admin can trigger a re-rate for a candidate', function () {
    Bus::fake();

    $admin = User::factory()->create();
    $application = JobApplication::factory()->create(['ai_status' => AiRatingStatus::Failed]);

    $response = $this->actingAs($admin)->post("/admin/candidates/{$application->id}/rate");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Bus::assertDispatched(
        RateCandidateApplication::class,
        fn (RateCandidateApplication $job) => $job->jobApplication->is($application)
    );
});

test('candidates without a resume are flagged in the list', function () {
    JobApplication::factory()->create(['resume_path' => null]);

    $this->actingAs(User::factory()->create())->get('/admin/candidates')
        ->assertInertia(fn (Assert $page) => $page
            ->where('candidates.data.0.has_resume', false)
            ->where('candidates.data.0.resume_filename', null)
        );
});

test('downloading a missing resume is not found', function () {
    $application = JobApplication::factory()->create(['resume_path' => null]);

    $this->actingAs(User::factory()->create())
        ->get("/admin/candidates/{$application->id}/resume")
        ->assertNotFound();
});

test('rating a candidate without a resume is refused', function () {
    Bus::fake();

    $application = JobApplication::factory()->create(['resume_path' => null]);

    $this->actingAs(User::factory()->create())
        ->post("/admin/candidates/{$application->id}/rate")
        ->assertSessionHas('error');

    Bus::assertNotDispatched(RateCandidateApplication::class);
});

test('admin can edit candidate details', function () {
    Bus::fake();

    $application = JobApplication::factory()->create(['resume_path' => null]);

    $this->actingAs(User::factory()->create())->patch("/admin/candidates/{$application->id}", [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'phone' => '12345',
    ])->assertSessionHasNoErrors();

    $application->refresh();
    expect($application->name)->toBe('New Name');
    expect($application->email)->toBe('new@example.com');
    expect($application->phone)->toBe('12345');
    Bus::assertNotDispatched(RateCandidateApplication::class);
});

test('editing a candidate rejects an email already used on the same job', function () {
    $job = JobPosting::factory()->create();
    JobApplication::factory()->create(['job_posting_id' => $job->id, 'email' => 'taken@example.com']);
    $application = JobApplication::factory()->create(['job_posting_id' => $job->id]);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

test('saving a candidate with its own unchanged email is allowed', function () {
    $application = JobApplication::factory()->create(['email' => 'same@example.com']);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['name' => 'Renamed', 'email' => 'same@example.com'])
        ->assertSessionHasNoErrors();
});

test('moving a candidate to another job re-rates them when they have a resume', function () {
    Bus::fake();

    $application = JobApplication::factory()->create();
    $otherJob = JobPosting::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['job_posting_id' => $otherJob->id])
        ->assertSessionHasNoErrors();

    expect($application->refresh()->job_posting_id)->toBe($otherJob->id);
    Bus::assertDispatched(RateCandidateApplication::class);
});

test('admin can upload a resume for a candidate without one', function () {
    Storage::fake('local');
    Bus::fake();

    $application = JobApplication::factory()->create(['resume_path' => null]);

    $this->actingAs(User::factory()->create())->post("/admin/candidates/{$application->id}/resume", [
        'resume' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $application->refresh();
    expect($application->resume_path)->toStartWith("resumes/{$application->job_posting_id}/");
    Storage::disk('local')->assertExists($application->resume_path);
    Bus::assertDispatched(
        RateCandidateApplication::class,
        fn (RateCandidateApplication $rating) => $rating->jobApplication->is($application)
    );
});

test('uploading a new resume replaces the old file', function () {
    Storage::fake('local');
    Bus::fake();

    Storage::disk('local')->put('resumes/old.pdf', 'old');
    $application = JobApplication::factory()->create(['resume_path' => 'resumes/old.pdf']);

    $this->actingAs(User::factory()->create())->post("/admin/candidates/{$application->id}/resume", [
        'resume' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    Storage::disk('local')->assertMissing('resumes/old.pdf');
    Storage::disk('local')->assertExists($application->refresh()->resume_path);
});

test('resume upload validates the file type', function () {
    Storage::fake('local');

    $application = JobApplication::factory()->create(['resume_path' => null]);

    $this->actingAs(User::factory()->create())->post("/admin/candidates/{$application->id}/resume", [
        'resume' => UploadedFile::fake()->create('cv.png', 100, 'image/png'),
    ])->assertSessionHasErrors('resume');
});
