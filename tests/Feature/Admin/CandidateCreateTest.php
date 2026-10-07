<?php

use App\Jobs\RateCandidateApplication;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest is redirected away from the add candidate form', function () {
    $this->get('/admin/candidates/create')->assertRedirect('/login');
    $this->post('/admin/candidates')->assertRedirect('/login');
});

test('admin can view the add candidate form', function () {
    JobPosting::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get('/admin/candidates/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/candidates/create')
            ->has('jobPostings', 2)
        );
});

test('admin can add a candidate to a job with a resume', function () {
    Storage::fake('local');
    Bus::fake();

    $job = JobPosting::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post('/admin/candidates', [
        'job_posting_id' => $job->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9876543210',
        'resume' => UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf'),
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/admin/candidates');

    $application = JobApplication::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($application->job_posting_id)->toBe($job->id);
    expect($application->resume_path)->toStartWith("resumes/{$job->id}/");

    Storage::disk('local')->assertExists($application->resume_path);

    Bus::assertDispatched(
        RateCandidateApplication::class,
        fn (RateCandidateApplication $rating) => $rating->jobApplication->is($application)
    );
});

test('adding a candidate validates required fields and resume type', function () {
    Storage::fake('local');

    $response = $this->actingAs(User::factory()->create())->post('/admin/candidates', [
        'job_posting_id' => 9999,
        'name' => '',
        'email' => 'not-an-email',
        'resume' => UploadedFile::fake()->create('resume.png', 100, 'image/png'),
    ]);

    $response->assertSessionHasErrors(['job_posting_id', 'name', 'email', 'phone', 'resume']);
    expect(JobApplication::query()->count())->toBe(0);
});

test('the same email cannot be added twice to a job but can be added to another', function () {
    Storage::fake('local');
    Bus::fake();

    $admin = User::factory()->create();
    $job = JobPosting::factory()->create();
    $otherJob = JobPosting::factory()->create();
    JobApplication::factory()->create(['job_posting_id' => $job->id, 'email' => 'jane@example.com']);

    $payload = fn (JobPosting $target) => [
        'job_posting_id' => $target->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9876543210',
        'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ];

    $this->actingAs($admin)->post('/admin/candidates', $payload($job))
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)->post('/admin/candidates', $payload($otherJob))
        ->assertSessionHasNoErrors();

    expect(JobApplication::query()->where('email', 'jane@example.com')->count())->toBe(2);
});

test('candidate phone numbers must look like real phone numbers', function (string $phone, bool $valid) {
    Storage::fake('local');
    Bus::fake();

    $response = $this->actingAs(User::factory()->create())->post('/admin/candidates', [
        'job_posting_id' => JobPosting::factory()->create()->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => $phone,
        'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('phone');
})->with([
    'plain digits' => ['9876543210', true],
    'country code' => ['+91 98765-43210', true],
    'country code no plus' => ['919876543210', true],
    'trunk zero' => ['09876543210', true],
    'spaced' => ['98765 43210', true],
    'starts with 5' => ['5876543210', false],
    'landline style' => ['(022) 2345 6789', false],
    'letters' => ['abcdefghij', false],
    'too short' => ['987654321', false],
    'too long' => ['98765432101', false],
    'other country code' => ['+1 9876543210', false],
    'mixed junk' => ['98765abc43', false],
]);

test('candidate email must be a valid address', function (string $email) {
    $response = $this->actingAs(User::factory()->create())->post('/admin/candidates', [
        'job_posting_id' => JobPosting::factory()->create()->id,
        'name' => 'Jane Doe',
        'email' => $email,
        'phone' => '9876543210',
        'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('email');
})->with(['no domain' => ['jane@'], 'no at' => ['jane.example.com'], 'spaces' => ['ja ne@example.com']]);
