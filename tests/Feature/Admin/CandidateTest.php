<?php

use App\Enums\ApplicationStatus;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
