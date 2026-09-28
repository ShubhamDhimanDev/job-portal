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
        'Job Title',
        'Company',
        'Candidate Name',
        'Email',
        'Phone',
        'Status',
        'Applied Date',
        'Resume Filename',
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
    expect($dataRows->pluck(2)->sort()->values()->all())->toBe(['Alice A', 'Alice B']);
    expect($dataRows->pluck(0)->unique()->all())->toBe(['Backend Engineer']);
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

        $names = collect($rows)->skip(1)->pluck(2)->all();

        return $names === ['Included Candidate'];
    });
});
