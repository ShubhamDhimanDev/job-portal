<?php

use App\Actions\Candidates\ParseNoticePeriodDays;
use App\Mail\CandidatesExportMail;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $query
 * @return array<int, string>
 */
function filteredCandidateNames(array $query): array
{
    $names = [];

    test()->actingAs(User::factory()->create())
        ->get('/admin/candidates?'.http_build_query($query))
        ->assertInertia(function (Assert $page) use (&$names): void {
            $names = collect($page->toArray()['props']['candidates']['data'])->pluck('name')->sort()->values()->all();
        });

    return $names;
}

test('notice periods are parsed into days', function (?string $text, ?int $days) {
    expect((new ParseNoticePeriodDays)->handle($text))->toBe($days);
})->with([
    'immediate' => ['Immediate', 0],
    'immediate joiner' => ['Immediate joiner', 0],
    'zero' => ['0', 0],
    'days' => ['30 days', 30],
    'days without a space' => ['45days', 45],
    'bare number' => ['60', 60],
    'working days' => ['15 working days', 15],
    'weeks' => ['2 weeks', 14],
    'month' => ['1 month', 30],
    'months' => ['2 Months', 60],
    'abbreviated months' => ['3 mths', 90],
    'decimal months' => ['1.5 months', 45],
    'range uses the upper end' => ['1-2 months', 60],
    'text range' => ['2 to 3 months', 90],
    'no duration' => ['Negotiable', null],
    'date-like text' => ['30th Oct', null],
    'blank' => ['  ', null],
    'null' => [null, null],
]);

test('the numeric notice period follows the typed notice period', function () {
    $application = JobApplication::factory()->create(['notice_period' => '2 months']);

    expect($application->refresh()->notice_period_days)->toBe(60);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['notice_period' => 'Immediate'])
        ->assertSessionHasNoErrors();

    expect($application->refresh()->notice_period_days)->toBe(0);

    $this->actingAs(User::factory()->create())
        ->patch("/admin/candidates/{$application->id}", ['notice_period' => ''])
        ->assertSessionHasNoErrors();

    expect($application->refresh()->notice_period_days)->toBeNull();
});

test('candidates can be filtered by experience range', function () {
    JobApplication::factory()->create(['name' => 'Junior', 'total_experience' => 1]);
    JobApplication::factory()->create(['name' => 'Mid', 'total_experience' => 4.5]);
    JobApplication::factory()->create(['name' => 'Senior', 'total_experience' => 9]);
    JobApplication::factory()->create(['name' => 'Unknown', 'total_experience' => null]);

    expect(filteredCandidateNames(['experience_min' => 2, 'experience_max' => 5]))->toBe(['Mid']);
    expect(filteredCandidateNames(['experience_min' => 4.5]))->toBe(['Mid', 'Senior']);
    expect(filteredCandidateNames(['experience_max' => 4.5]))->toBe(['Junior', 'Mid']);
});

test('candidates can be filtered by expected salary by default', function () {
    JobApplication::factory()->create(['name' => 'Low', 'expected_ctc' => 500000, 'current_ctc' => 900000]);
    JobApplication::factory()->create(['name' => 'High', 'expected_ctc' => 1500000, 'current_ctc' => 400000]);
    JobApplication::factory()->create(['name' => 'Unknown']);

    expect(filteredCandidateNames(['salary_min' => 400000, 'salary_max' => 800000]))->toBe(['Low']);
    expect(filteredCandidateNames(['salary_min' => 1000000]))->toBe(['High']);
});

test('candidates can be filtered by current salary', function () {
    JobApplication::factory()->create(['name' => 'Low', 'expected_ctc' => 500000, 'current_ctc' => 900000]);
    JobApplication::factory()->create(['name' => 'High', 'expected_ctc' => 1500000, 'current_ctc' => 400000]);

    expect(filteredCandidateNames(['salary_basis' => 'current', 'salary_min' => 800000]))->toBe(['Low']);
    expect(filteredCandidateNames(['salary_basis' => 'current', 'salary_max' => 500000]))->toBe(['High']);
});

test('candidates can be filtered by notice period', function () {
    JobApplication::factory()->create(['name' => 'Now', 'notice_period' => 'Immediate']);
    JobApplication::factory()->create(['name' => 'Fortnight', 'notice_period' => '15 days']);
    JobApplication::factory()->create(['name' => 'Month', 'notice_period' => '1 month']);
    JobApplication::factory()->create(['name' => 'Six weeks', 'notice_period' => '45 days']);
    JobApplication::factory()->create(['name' => 'Quarter', 'notice_period' => '3 months']);
    JobApplication::factory()->create(['name' => 'Long', 'notice_period' => '6 months']);
    JobApplication::factory()->create(['name' => 'Unparsed', 'notice_period' => 'Negotiable']);
    JobApplication::factory()->create(['name' => 'Blank', 'notice_period' => null]);

    expect(filteredCandidateNames(['notice_period' => 'immediate']))->toBe(['Now']);
    expect(filteredCandidateNames(['notice_period' => '15_days']))->toBe(['Fortnight', 'Now']);
    expect(filteredCandidateNames(['notice_period' => '1_month']))->toBe(['Fortnight', 'Month', 'Now']);
    expect(filteredCandidateNames(['notice_period' => '2_months']))->toBe(['Fortnight', 'Month', 'Now', 'Six weeks']);
    expect(filteredCandidateNames(['notice_period' => '3_months']))->toBe(['Fortnight', 'Month', 'Now', 'Quarter', 'Six weeks']);
});

test('experience, salary and notice period filters combine', function () {
    JobApplication::factory()->create(['name' => 'Match', 'total_experience' => 5, 'expected_ctc' => 1000000, 'notice_period' => '1 month']);
    JobApplication::factory()->create(['name' => 'Too senior', 'total_experience' => 12, 'expected_ctc' => 1000000, 'notice_period' => '1 month']);
    JobApplication::factory()->create(['name' => 'Too expensive', 'total_experience' => 5, 'expected_ctc' => 3000000, 'notice_period' => '1 month']);
    JobApplication::factory()->create(['name' => 'Too slow', 'total_experience' => 5, 'expected_ctc' => 1000000, 'notice_period' => '3 months']);

    expect(filteredCandidateNames([
        'experience_min' => 3,
        'experience_max' => 8,
        'salary_min' => 500000,
        'salary_max' => 1500000,
        'notice_period' => '1_month',
    ]))->toBe(['Match']);
});

test('unusable filter values are ignored', function () {
    JobApplication::factory()->count(2)->create();

    expect(filteredCandidateNames([
        'experience_min' => 'lots',
        'salary_max' => 'abc',
        'salary_basis' => 'bonus',
        'notice_period' => 'whenever',
    ]))->toHaveCount(2);
});

test('the applied filters and notice period options are sent back to the page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/candidates?experience_min=2&experience_max=5&salary_basis=current&salary_min=100&salary_max=900&notice_period=1_month')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.experience_min', '2')
            ->where('filters.experience_max', '5')
            ->where('filters.salary_basis', 'current')
            ->where('filters.salary_min', '100')
            ->where('filters.salary_max', '900')
            ->where('filters.notice_period', '1_month')
            ->has('noticePeriods', 5)
            ->where('noticePeriods.0', ['value' => 'immediate', 'label' => 'Immediate joiner'])
        );
});

test('the export respects the experience, salary and notice period filters', function () {
    JobApplication::factory()->create(['name' => 'Included', 'total_experience' => 5, 'expected_ctc' => 1000000, 'notice_period' => '15 days']);
    JobApplication::factory()->create(['name' => 'Excluded', 'total_experience' => 12, 'expected_ctc' => 1000000, 'notice_period' => '15 days']);

    $response = $this->actingAs(User::factory()->create())
        ->get('/admin/candidates/export?experience_max=8&salary_min=500000&notice_period=1_month');

    $rows = IOFactory::load($response->baseResponse->getFile()->getRealPath())->getActiveSheet()->toArray();

    expect(collect($rows)->skip(1)->pluck(4)->all())->toBe(['Included']);
});

test('the emailed export accepts the new filters and rejects invalid ones', function () {
    Mail::fake();

    JobApplication::factory()->create(['name' => 'Included', 'total_experience' => 5]);
    JobApplication::factory()->create(['name' => 'Excluded', 'total_experience' => 12]);

    $admin = User::factory()->create();
    $payload = ['to' => ['hiring@example.com'], 'subject' => 'Export', 'message' => 'See attached.'];

    $this->actingAs($admin)
        ->post('/admin/candidates/email-export', [...$payload, 'notice_period' => 'whenever', 'salary_basis' => 'bonus', 'experience_min' => 'lots'])
        ->assertSessionHasErrors(['notice_period', 'salary_basis', 'experience_min']);

    $this->actingAs($admin)
        ->post('/admin/candidates/email-export', [...$payload, 'experience_max' => 8])
        ->assertSessionHasNoErrors();

    Mail::assertSent(CandidatesExportMail::class, function (CandidatesExportMail $mail): bool {
        $path = sys_get_temp_dir().'/'.uniqid('candidates-export-').'.xlsx';
        file_put_contents($path, $mail->rawAttachments[0]['data']);

        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        return collect($rows)->skip(1)->pluck(4)->all() === ['Included'];
    });
});
