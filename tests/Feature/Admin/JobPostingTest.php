<?php

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\WorkMode;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function validJobPostingPayload(Company $company, array $overrides = []): array
{
    return array_merge([
        'company_id' => $company->id,
        'title' => 'Senior Backend Engineer',
        'description' => 'We are looking for a senior backend engineer.',
        'responsibilities' => 'Build and maintain APIs.',
        'requirements' => '5+ years of PHP experience.',
        'location' => 'Bengaluru',
        'work_mode' => WorkMode::Hybrid->value,
        'employment_type' => EmploymentType::FullTime->value,
        'experience_level' => '5-8 years',
        'min_salary' => 1500000,
        'max_salary' => 2500000,
        'salary_negotiable' => true,
        'department' => 'Engineering',
        'vacancies' => 2,
        'application_deadline' => now()->addMonth()->format('Y-m-d'),
    ], $overrides);
}

test('guest is redirected away from job posting admin routes', function () {
    $this->get('/admin/job-postings')->assertRedirect('/login');
    $this->get('/admin/job-postings/create')->assertRedirect('/login');
    $this->post('/admin/job-postings')->assertRedirect('/login');
});

test('authenticated admin can view the job postings index', function () {
    $user = User::factory()->create();
    JobPosting::factory()->count(3)->create();

    $response = $this->actingAs($user)->get('/admin/job-postings');

    $response->assertOk();
});

test('authenticated admin can create a job posting on behalf of a company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $response = $this->actingAs($user)->post('/admin/job-postings', validJobPostingPayload($company));

    $jobPosting = JobPosting::query()->firstOrFail();

    $response->assertRedirect(route('admin.job-postings.edit', $jobPosting));
    expect($jobPosting->title)->toBe('Senior Backend Engineer');
    expect($jobPosting->company_id)->toBe($company->id);
    expect($jobPosting->status)->toBe(JobStatus::Draft);
    expect($jobPosting->posted_by_id)->toBe($user->id);
});

test('posted_by_id is always the acting admin, never a submitted value', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($user)->post('/admin/job-postings', validJobPostingPayload($company, [
        'posted_by_id' => $otherUser->id,
    ]));

    $jobPosting = JobPosting::query()->firstOrFail();

    expect($jobPosting->posted_by_id)->toBe($user->id);
    expect($jobPosting->posted_by_id)->not->toBe($otherUser->id);
});

test('job posting creation requires a valid company', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/admin/job-postings', validJobPostingPayload(
        Company::factory()->make(['id' => 999999])
    ));

    $response->assertSessionHasErrors('company_id');
    expect(JobPosting::query()->count())->toBe(0);
});

test('authenticated admin can edit and update a job posting', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $jobPosting = JobPosting::factory()->for($company)->create(['posted_by_id' => $user->id]);

    $this->actingAs($user)->get(route('admin.job-postings.edit', $jobPosting))->assertOk();

    $response = $this->actingAs($user)->put(
        route('admin.job-postings.update', $jobPosting),
        validJobPostingPayload($company, ['title' => 'Updated Job Title'])
    );

    $response->assertRedirect();
    expect($jobPosting->fresh()->title)->toBe('Updated Job Title');
});

test('authenticated admin can publish a draft job posting', function () {
    $user = User::factory()->create();
    $jobPosting = JobPosting::factory()->create(['status' => JobStatus::Draft]);

    $response = $this->actingAs($user)->post(route('admin.job-postings.publish', $jobPosting));

    $response->assertRedirect();
    expect($jobPosting->fresh()->status)->toBe(JobStatus::Published);
});

test('authenticated admin can close a published job posting', function () {
    $user = User::factory()->create();
    $jobPosting = JobPosting::factory()->published()->create();

    $response = $this->actingAs($user)->post(route('admin.job-postings.close', $jobPosting));

    $response->assertRedirect();
    expect($jobPosting->fresh()->status)->toBe(JobStatus::Closed);
});

test('authenticated admin can duplicate a job posting as a new draft', function () {
    $user = User::factory()->create();
    $originalPoster = User::factory()->create();
    $jobPosting = JobPosting::factory()->published()->create([
        'title' => 'Product Manager',
        'posted_by_id' => $originalPoster->id,
    ]);

    $response = $this->actingAs($user)->post(route('admin.job-postings.duplicate', $jobPosting));

    $response->assertRedirect();
    expect(JobPosting::query()->count())->toBe(2);

    $duplicate = JobPosting::query()->where('id', '!=', $jobPosting->id)->firstOrFail();

    expect($duplicate->title)->toBe('Product Manager (Copy)');
    expect($duplicate->slug)->not->toBe($jobPosting->slug);
    expect($duplicate->status)->toBe(JobStatus::Draft);
    expect($duplicate->posted_by_id)->toBe($user->id);
    expect($jobPosting->fresh()->status)->toBe(JobStatus::Published);
});

test('authenticated admin can delete a job posting', function () {
    $user = User::factory()->create();
    $jobPosting = JobPosting::factory()->create();

    $response = $this->actingAs($user)->delete(route('admin.job-postings.destroy', $jobPosting));

    $response->assertRedirect(route('admin.job-postings.index'));
    expect(JobPosting::query()->count())->toBe(0);
});
