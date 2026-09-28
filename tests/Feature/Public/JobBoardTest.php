<?php

use App\Jobs\RateCandidateApplication;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('job listing only shows published jobs', function () {
    $published = JobPosting::factory()->published()->create(['title' => 'Senior Backend Engineer']);
    JobPosting::factory()->create(['title' => 'Draft Role']);
    JobPosting::factory()->closed()->create(['title' => 'Closed Role']);

    $response = $this->get('/jobs');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('jobs/index')
        ->has('jobs.data', 1)
        ->where('jobs.data.0.title', $published->title)
    );
});

test('job listing supports keyword and filter query params', function () {
    JobPosting::factory()->published()->create([
        'title' => 'React Frontend Developer',
        'location' => 'Bengaluru',
    ]);
    JobPosting::factory()->published()->create([
        'title' => 'Payroll Executive',
        'location' => 'Delhi',
    ]);

    $response = $this->get('/jobs?keyword=React');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('jobs/index')
        ->has('jobs.data', 1)
        ->where('jobs.data.0.title', 'React Frontend Developer')
    );
});

test('job detail page 404s for a draft or closed job', function () {
    $draft = JobPosting::factory()->create();
    $closed = JobPosting::factory()->closed()->create();

    $this->get("/jobs/{$draft->slug}")->assertNotFound();
    $this->get("/jobs/{$closed->slug}")->assertNotFound();
});

test('job detail page renders a published job', function () {
    $job = JobPosting::factory()->published()->create();

    $response = $this->get("/jobs/{$job->slug}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('jobs/show')
        ->where('job.slug', $job->slug)
        ->where('job.title', $job->title)
    );
});

test('a candidate can apply to a published job', function () {
    Storage::fake('local');
    Bus::fake();

    $job = JobPosting::factory()->published()->create();
    $resume = UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf');

    $response = $this->post("/jobs/{$job->slug}/apply", [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9999999999',
        'resume' => $resume,
        'cover_note' => 'Excited to apply for this role.',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $application = JobApplication::query()
        ->where('job_posting_id', $job->id)
        ->where('email', 'jane@example.com')
        ->first();

    expect($application)->not->toBeNull();
    expect($application->name)->toBe('Jane Doe');
    expect($application->resume_path)->toStartWith("resumes/{$job->id}/");

    Storage::disk('local')->assertExists($application->resume_path);

    Bus::assertDispatched(
        RateCandidateApplication::class,
        fn (RateCandidateApplication $rating) => $rating->jobApplication->is($application)
    );
});

test('applying to a non-published job is not found', function () {
    Storage::fake('local');

    $job = JobPosting::factory()->create();
    $resume = UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf');

    $response = $this->post("/jobs/{$job->slug}/apply", [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9999999999',
        'resume' => $resume,
    ]);

    $response->assertNotFound();
});

test('a duplicate email for the same job is rejected', function () {
    Storage::fake('local');

    $job = JobPosting::factory()->published()->create();
    JobApplication::factory()->for($job, 'jobPosting')->create(['email' => 'jane@example.com']);

    $resume = UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf');

    $response = $this->post("/jobs/{$job->slug}/apply", [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9999999999',
        'resume' => $resume,
    ]);

    $response->assertSessionHasErrors('email');
    expect(JobApplication::query()->where('job_posting_id', $job->id)->count())->toBe(1);
});

test('an invalid resume mime type is rejected', function () {
    Storage::fake('local');

    $job = JobPosting::factory()->published()->create();
    $resume = UploadedFile::fake()->create('resume.txt', 100, 'text/plain');

    $response = $this->post("/jobs/{$job->slug}/apply", [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9999999999',
        'resume' => $resume,
    ]);

    $response->assertSessionHasErrors('resume');
    expect(JobApplication::query()->where('job_posting_id', $job->id)->exists())->toBeFalse();
});

test('a resume larger than 5MB is rejected', function () {
    Storage::fake('local');

    $job = JobPosting::factory()->published()->create();
    $resume = UploadedFile::fake()->create('resume.pdf', 6000, 'application/pdf');

    $response = $this->post("/jobs/{$job->slug}/apply", [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9999999999',
        'resume' => $resume,
    ]);

    $response->assertSessionHasErrors('resume');
    expect(JobApplication::query()->where('job_posting_id', $job->id)->exists())->toBeFalse();
});
