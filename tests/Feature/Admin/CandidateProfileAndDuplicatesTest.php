<?php

use App\Ai\Agents\CandidateRatingAgent;
use App\Enums\Gender;
use App\Enums\InterviewType;
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

/**
 * @return array<string, mixed>
 */
function candidateProfilePayload(): array
{
    return [
        'gender' => 'female',
        'date_of_birth' => '1995-04-12',
        'total_experience' => '6.5',
        'relevant_experience' => '4',
        'current_company' => 'Acme Corp',
        'industry_type' => 'IT Services',
        'current_designation' => 'Senior Developer',
        'current_location' => 'Pune',
        'current_ctc' => '1200000',
        'expected_ctc' => '1500000',
        'notice_period' => '30 days',
        'interview_type' => 'virtual',
    ];
}

test('admin can add a candidate with profile details', function () {
    Storage::fake('local');
    Bus::fake();

    $job = JobPosting::factory()->create();

    $this->actingAs(User::factory()->create())->post('/admin/candidates', [
        'job_posting_id' => $job->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9999999999',
        'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ...candidateProfilePayload(),
    ])->assertSessionHasNoErrors();

    $application = JobApplication::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($application)
        ->gender->toBe(Gender::Female)
        ->interview_type->toBe(InterviewType::Virtual)
        ->date_of_birth->toDateString()->toBe('1995-04-12')
        ->total_experience->toBe(6.5)
        ->relevant_experience->toBe(4.0)
        ->current_company->toBe('Acme Corp')
        ->industry_type->toBe('IT Services')
        ->current_designation->toBe('Senior Developer')
        ->current_location->toBe('Pune')
        ->current_ctc->toBe(1200000.0)
        ->expected_ctc->toBe(1500000.0)
        ->notice_period->toBe('30 days');
});

test('profile details are optional when adding a candidate', function () {
    Storage::fake('local');
    Bus::fake();

    $job = JobPosting::factory()->create();

    $this->actingAs(User::factory()->create())->post('/admin/candidates', [
        'job_posting_id' => $job->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '9999999999',
        'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    expect(JobApplication::query()->firstOrFail()->gender)->toBeNull();
});

test('profile details are validated', function () {
    $this->actingAs(User::factory()->create())->post('/admin/candidates', [
        'gender' => 'robot',
        'interview_type' => 'phone',
        'date_of_birth' => '2999-01-01',
        'total_experience' => 'lots',
        'current_ctc' => '-5',
    ])->assertSessionHasErrors(['gender', 'interview_type', 'date_of_birth', 'total_experience', 'current_ctc']);
});

test('admin can edit profile details and they appear in the list', function () {
    $application = JobApplication::factory()->create();

    $this->actingAs($admin = User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", candidateProfilePayload())
        ->assertSessionHasNoErrors();

    expect($application->refresh()->current_company)->toBe('Acme Corp');

    $this->actingAs($admin)->get('/admin/candidates')->assertInertia(fn (Assert $page) => $page
        ->where('candidates.data.0.gender', 'female')
        ->where('candidates.data.0.interview_type', 'virtual')
        ->where('candidates.data.0.interview_type_label', 'Virtual')
        ->where('candidates.data.0.total_experience', 6.5)
        ->where('candidates.data.0.notice_period', '30 days')
        ->has('genders', 3)
        ->has('interviewTypes', 2)
    );
});

test('updating only the status leaves profile details untouched', function () {
    $application = JobApplication::factory()->create(['current_company' => 'Acme Corp']);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['status' => 'shortlisted'])
        ->assertSessionHasNoErrors();

    expect($application->refresh()->current_company)->toBe('Acme Corp');
});

test('admin can delete a candidate and their resume file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('resumes/cv.pdf', 'x');

    $application = JobApplication::factory()->create(['resume_path' => 'resumes/cv.pdf']);

    $this->actingAs(User::factory()->create())
        ->delete("/admin/candidates/{$application->id}")
        ->assertSessionHas('success');

    expect(JobApplication::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing('resumes/cv.pdf');
});

test('guests cannot delete candidates', function () {
    $application = JobApplication::factory()->create();

    $this->delete("/admin/candidates/{$application->id}")->assertRedirect('/login');
    $this->delete('/admin/candidates/duplicates')->assertRedirect('/login');

    expect(JobApplication::query()->count())->toBe(1);
});

test('duplicates are found by email or phone across jobs and the oldest is kept', function () {
    Storage::fake('local');

    $jobA = JobPosting::factory()->create();
    $jobB = JobPosting::factory()->create();

    $original = JobApplication::factory()->create([
        'job_posting_id' => $jobA->id,
        'email' => 'Priya@Example.com',
        'phone' => '+91 98765-43210',
    ]);
    $sameEmail = JobApplication::factory()->create([
        'job_posting_id' => $jobB->id,
        'email' => 'priya@example.com',
        'phone' => '11111 11111',
    ]);
    $samePhone = JobApplication::factory()->create([
        'job_posting_id' => $jobB->id,
        'email' => 'other@example.com',
        'phone' => '9876543210',
    ]);
    $unique = JobApplication::factory()->create(['email' => 'unique@example.com', 'phone' => '2222222222']);

    $admin = User::factory()->create();

    $this->actingAs($admin)->get('/admin/candidates?duplicates=1')->assertInertia(fn (Assert $page) => $page
        ->has('candidates.data', 3)
        ->where('duplicateCount', 2)
        ->where('filters.duplicates', '1')
    );

    $this->actingAs($admin)->delete('/admin/candidates/duplicates')->assertSessionHas('success');

    expect(JobApplication::query()->pluck('id')->all())->toEqualCanonicalizing([$original->id, $unique->id]);
    expect(JobApplication::query()->whereKey([$sameEmail->id, $samePhone->id])->exists())->toBeFalse();
});

test('deleting duplicates with none present changes nothing', function () {
    JobApplication::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->delete('/admin/candidates/duplicates')
        ->assertSessionHas('success', 'No duplicate candidates found.');

    expect(JobApplication::query()->count())->toBe(2);
});

test('the ai rating prompt includes recruiter details but never gender or date of birth', function () {
    $application = JobApplication::factory()->create([
        'total_experience' => 6.5,
        'relevant_experience' => 4,
        'current_designation' => 'Senior Developer',
        'current_company' => 'Acme Corp',
        'expected_ctc' => 1500000,
        'notice_period' => '30 days',
        'gender' => Gender::Female,
        'date_of_birth' => '1995-04-12',
    ]);

    $instructions = (string) (new CandidateRatingAgent($application->jobPosting, $application))->instructions();

    expect($instructions)
        ->toContain('Total experience: 6.5 years')
        ->toContain('Relevant experience: 4 years')
        ->toContain('Current designation: Senior Developer')
        ->toContain('Expected CTC: 1500000')
        ->toContain('Notice period: 30 days')
        ->not->toContain('1995')
        ->not->toContain('Female');
});

test('the ai rating prompt is unchanged when no recruiter details exist', function () {
    $application = JobApplication::factory()->create();

    $instructions = (string) (new CandidateRatingAgent($application->jobPosting, $application))->instructions();

    expect($instructions)->not->toContain('recruiter also entered');
});

test('editing a field the ai reads re-rates a candidate with a resume', function () {
    Bus::fake();

    $application = JobApplication::factory()->create(['total_experience' => 5]);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['total_experience' => '8'])
        ->assertSessionHasNoErrors();

    Bus::assertDispatched(RateCandidateApplication::class);
});

test('saving unchanged or unrelated fields does not re-rate', function () {
    Bus::fake();

    $application = JobApplication::factory()->create(['total_experience' => 5, 'expected_ctc' => 1000000]);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", [
            'total_experience' => '5.0',
            'expected_ctc' => '1000000',
            'current_location' => 'Pune',
            'gender' => 'male',
            'interview_type' => 'virtual',
        ])
        ->assertSessionHasNoErrors();

    Bus::assertNotDispatched(RateCandidateApplication::class);
});
