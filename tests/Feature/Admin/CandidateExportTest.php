<?php

use App\Mail\CandidatesExportMail;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * @return array<int, array<int, mixed>>
 */
function readExportRows(TestResponse $response): array
{
    $response->assertOk();

    $file = $response->baseResponse->getFile();
    $spreadsheet = IOFactory::load($file->getRealPath());

    return $spreadsheet->getActiveSheet()->toArray();
}

test('guest cannot download the candidates export', function () {
    $response = $this->get('/admin/candidates/export');

    $response->assertRedirect('/login');
});

test('download returns an xlsx file with the expected headings', function () {
    $admin = User::factory()->create();
    JobApplication::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get('/admin/candidates/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $rows = readExportRows($response);

    expect($rows[0])->toBe([
        'Candidate Code',
        'Job Code',
        'Job Title',
        'Company',
        'Candidate Name',
        'Email',
        'Phone',
        'Status',
        'Applied Date',
        'Resume Filename',
        'Gender',
        'Date of Birth',
        'Total Experience (Years)',
        'Relevant Experience (Years)',
        'Current Company',
        'Industry Type',
        'Current Designation',
        'Current Location',
        'Current CTC',
        'Expected CTC',
        'Notice Period',
        'Interview Type',
        'Skills',
        'Comment',
        'AI Fit Score (out of 10)',
        'AI Rating Status',
        'AI Reasoning',
        'AI Strengths',
        'AI Gaps',
    ]);
    expect($rows)->toHaveCount(3); // heading + 2 candidates
});

test('download respects the current filters', function () {
    $admin = User::factory()->create();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $jobA = JobPosting::factory()->create(['company_id' => $companyA->id, 'title' => 'Backend Engineer']);
    $jobB = JobPosting::factory()->create(['company_id' => $companyB->id, 'title' => 'Frontend Engineer']);

    JobApplication::factory()->create(['job_posting_id' => $jobA->id, 'name' => 'Alice A']);
    JobApplication::factory()->create(['job_posting_id' => $jobA->id, 'name' => 'Alice B']);
    JobApplication::factory()->create(['job_posting_id' => $jobB->id, 'name' => 'Bob C']);

    $response = $this->actingAs($admin)->get('/admin/candidates/export?job_posting_id='.$jobA->id);

    $rows = readExportRows($response);
    $dataRows = collect($rows)->skip(1);

    expect($dataRows)->toHaveCount(2);
    expect($dataRows->pluck(4)->sort()->values()->all())->toBe(['Alice A', 'Alice B']);
    expect($dataRows->pluck(2)->unique()->all())->toBe(['Backend Engineer']);
    expect($dataRows->pluck(1)->unique()->all())->toBe([$jobA->code]);
});

test('guest cannot email the candidates export', function () {
    $response = $this->post('/admin/candidates/email-export', []);

    $response->assertRedirect('/login');
});

test('email export validates required fields', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->post('/admin/candidates/email-export', []);

    $response->assertSessionHasErrors(['to', 'subject', 'message']);
});

test('email sends the filtered export as an attachment to the given recipients', function () {
    Mail::fake();

    $admin = User::factory()->create();
    JobApplication::factory()->count(2)->create();

    $response = $this->actingAs($admin)->post('/admin/candidates/email-export', [
        'to' => ['hiring@example.com', 'manager@example.com'],
        'cc' => ['cc@example.com'],
        'bcc' => ['bcc@example.com'],
        'subject' => 'Candidates export',
        'message' => 'Please review the attached candidates.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Mail::assertSent(CandidatesExportMail::class, function (CandidatesExportMail $mail): bool {
        return $mail->hasTo('hiring@example.com')
            && $mail->hasTo('manager@example.com')
            && $mail->hasCc('cc@example.com')
            && $mail->hasBcc('bcc@example.com')
            && $mail->emailSubject === 'Candidates export'
            && count($mail->rawAttachments) === 1
            && str_ends_with($mail->rawAttachments[0]['name'], '.xlsx');
    });
});

test('email export respects filters when building the attached export', function () {
    Mail::fake();

    $admin = User::factory()->create();

    $jobA = JobPosting::factory()->create();
    $jobB = JobPosting::factory()->create();

    JobApplication::factory()->create(['job_posting_id' => $jobA->id, 'name' => 'Included Candidate']);
    JobApplication::factory()->create(['job_posting_id' => $jobB->id, 'name' => 'Excluded Candidate']);

    $this->actingAs($admin)->post('/admin/candidates/email-export', [
        'to' => ['hiring@example.com'],
        'subject' => 'Candidates export',
        'message' => 'See attached.',
        'job_posting_id' => $jobA->id,
    ])->assertRedirect();

    Mail::assertSent(CandidatesExportMail::class, function (CandidatesExportMail $mail): bool {
        $attachment = $mail->rawAttachments[0];

        $tempPath = sys_get_temp_dir().'/'.uniqid('candidates-export-').'.xlsx';
        file_put_contents($tempPath, $attachment['data']);

        $rows = IOFactory::load($tempPath)->getActiveSheet()->toArray();
        unlink($tempPath);

        $names = collect($rows)->skip(1)->pluck(4)->all();

        return $names === ['Included Candidate'];
    });
});

test('the export includes candidates without a job and can be limited to them', function () {
    $job = JobPosting::factory()->for(Company::factory())->create();
    JobApplication::factory()->create(['job_posting_id' => $job->id, 'name' => 'Assigned One']);
    $unassigned = JobApplication::factory()->unassigned()->create(['name' => 'Floating One']);

    $admin = User::factory()->create();

    $all = readExportRows($this->actingAs($admin)->get('/admin/candidates/export'));
    expect($all)->toHaveCount(3);

    $rows = readExportRows($this->actingAs($admin)->get('/admin/candidates/export?job_posting_id=none'));

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe($unassigned->code)
        ->and($rows[1][1])->toBeNull()
        ->and($rows[1][2])->toBeNull()
        ->and($rows[1][4])->toBe('Floating One');
});

test('the emailed export accepts the unassigned job filter', function () {
    Mail::fake();

    $this->actingAs(User::factory()->create())->post('/admin/candidates/email-export', [
        'to' => ['hr@example.com'],
        'subject' => 'Candidates',
        'message' => 'Attached.',
        'job_posting_id' => 'none',
    ])->assertSessionHasNoErrors();

    Mail::assertSent(CandidatesExportMail::class);

    $this->actingAs(User::factory()->create())->post('/admin/candidates/email-export', [
        'to' => ['hr@example.com'],
        'subject' => 'Candidates',
        'message' => 'Attached.',
        'job_posting_id' => 'bogus',
    ])->assertSessionHasErrors('job_posting_id');
});
