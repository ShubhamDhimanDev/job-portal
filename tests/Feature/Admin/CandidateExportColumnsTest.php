<?php

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

uses(RefreshDatabase::class);

function exportedSheet(TestResponse $response): Worksheet
{
    $response->assertOk();

    return IOFactory::load($response->baseResponse->getFile()->getRealPath())->getActiveSheet();
}

/**
 * The first candidate row of the export, keyed by column heading.
 *
 * @return array<string, mixed>
 */
function firstExportedCandidate(): array
{
    $rows = exportedSheet(test()->actingAs(User::factory()->create())->get('/admin/candidates/export'))->toArray();

    return array_combine($rows[0], $rows[1]);
}

test('the export has a column for everything on the list and in the edit form', function () {
    $job = JobPosting::factory()->for(Company::factory(['name' => 'Globex']))->create(['title' => 'Backend Engineer']);

    JobApplication::factory()->create([
        'job_posting_id' => $job->id,
        'name' => 'Priya Sharma',
        'email' => 'priya@example.com',
        'phone' => '9876501234',
        'gender' => 'female',
        'date_of_birth' => '1995-04-12',
        'total_experience' => 6.5,
        'relevant_experience' => 4,
        'current_company' => 'Acme Software',
        'industry_type' => 'IT Services',
        'current_designation' => 'Senior Developer',
        'current_location' => 'Pune',
        'current_ctc' => 1200000,
        'expected_ctc' => 1500000,
        'notice_period' => '30 days',
        'interview_type' => 'virtual',
        'skills' => ['PHP', 'Laravel', 'Machine Learning'],
        'admin_notes' => "Spoke on 3 Oct.\nCan join earlier.",
        'ai_status' => 'completed',
        'ai_score' => 8,
        'ai_reasoning' => 'Strong match on the required stack.',
        'ai_strengths' => ['Laravel depth', 'Team lead'],
        'ai_gaps' => ['No cloud experience', 'Short notice unlikely'],
    ]);

    $row = firstExportedCandidate();

    expect($row)
        ->toMatchArray([
            'Job Title' => 'Backend Engineer',
            'Company' => 'Globex',
            'Candidate Name' => 'Priya Sharma',
            'Email' => 'priya@example.com',
            'Phone' => '9876501234',
            'Gender' => 'Female',
            'Date of Birth' => '1995-04-12',
            'Current Company' => 'Acme Software',
            'Industry Type' => 'IT Services',
            'Current Designation' => 'Senior Developer',
            'Current Location' => 'Pune',
            'Notice Period' => '30 days',
            'Interview Type' => 'Virtual',
            'Skills' => 'PHP, Laravel, Machine Learning',
            'Comment' => "Spoke on 3 Oct.\nCan join earlier.",
            'AI Rating Status' => 'Completed',
            'AI Reasoning' => 'Strong match on the required stack.',
            'AI Strengths' => 'Laravel depth; Team lead',
            'AI Gaps' => 'No cloud experience; Short notice unlikely',
        ])
        ->and((float) $row['Total Experience (Years)'])->toBe(6.5)
        ->and((float) $row['Relevant Experience (Years)'])->toBe(4.0)
        ->and((float) $row['Current CTC'])->toBe(1200000.0)
        ->and((float) $row['Expected CTC'])->toBe(1500000.0)
        ->and((int) $row['AI Fit Score (out of 10)'])->toBe(8);
});

test('anything a candidate does not have is left blank', function () {
    JobApplication::factory()->unassigned()->create([
        'resume_path' => null,
        'cover_note' => null,
        'skills' => null,
        'admin_notes' => null,
    ]);

    $alwaysPresent = ['Candidate Code', 'Candidate Name', 'Email', 'Phone', 'Status', 'Applied Date'];

    expect(array_diff_key(firstExportedCandidate(), array_flip($alwaysPresent)))->each->toBeNull();
});

test('empty skills and ai lists are blank rather than empty text', function () {
    JobApplication::factory()->create(['skills' => [], 'ai_strengths' => [], 'ai_gaps' => []]);

    $row = firstExportedCandidate();

    expect($row['Skills'])->toBeNull()
        ->and($row['AI Strengths'])->toBeNull()
        ->and($row['AI Gaps'])->toBeNull();
});

test('the ai rating status is only shown for a candidate who can be rated', function (array $attributes, ?string $expected) {
    JobApplication::factory()->create(['ai_status' => 'pending', ...$attributes]);

    expect(firstExportedCandidate()['AI Rating Status'])->toBe($expected);
})->with([
    'no job' => [['job_posting_id' => null], null],
    'no resume' => [['resume_path' => null], null],
    'waiting to be rated' => [[], 'Pending'],
    'failed' => [['ai_status' => 'failed'], 'Failed'],
]);

test('text that looks like a formula is exported as text, never as a live formula', function () {
    JobApplication::factory()->create([
        'name' => '=HYPERLINK("http://example.com/steal","Click me")',
        'current_company' => '+cmd|calc',
        'skills' => ['=SUM(1+1)', '@lookup'],
        'admin_notes' => '=1+1',
        'ai_reasoning' => '-2+3',
    ]);

    $sheet = exportedSheet($this->actingAs(User::factory()->create())->get('/admin/candidates/export'));

    $formulaCells = [];
    $texts = [];

    foreach ($sheet->getRowIterator(2, 2) as $row) {
        foreach ($row->getCellIterator() as $cell) {
            if ($cell->isFormula()) {
                $formulaCells[] = $cell->getCoordinate();
            }

            $texts[] = (string) $cell->getValue();
        }
    }

    expect($formulaCells)->toBe([])
        ->and($texts)->toContain('=HYPERLINK("http://example.com/steal","Click me")', '+cmd|calc', '=SUM(1+1), @lookup', '=1+1', '-2+3');
});

test('phone numbers stay text so they keep their leading zero', function () {
    JobApplication::factory()->create(['phone' => '09876501234']);

    expect(firstExportedCandidate()['Phone'])->toBe('09876501234');
});
