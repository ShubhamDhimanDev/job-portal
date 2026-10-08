<?php

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

uses(RefreshDatabase::class);

/**
 * Store a resume on the fake disk for a new candidate and return the preview URL.
 */
function previewUrlFor(string $filename, string $contents): string
{
    Storage::fake('local');
    $path = "resumes/1/{$filename}";
    Storage::disk('local')->put($path, $contents);

    $candidate = JobApplication::factory()->create(['resume_path' => $path, 'name' => 'Jane Doe']);

    return "/admin/candidates/{$candidate->id}/resume/preview";
}

function docxBytes(callable $build): string
{
    Settings::setOutputEscapingEnabled(true);
    $phpWord = new PhpWord;
    $build($phpWord->addSection());
    $path = tempnam(sys_get_temp_dir(), 'resume');
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    Settings::setOutputEscapingEnabled(false);
    $bytes = file_get_contents($path);
    unlink($path);

    return $bytes;
}

test('guest cannot preview a resume', function () {
    $this->get('/admin/candidates/1/resume/preview')->assertRedirect('/login');
});

test('a pdf resume is streamed inline for the browser viewer', function () {
    $url = previewUrlFor('resume.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $response = $this->actingAs(User::factory()->create())->get($url);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toStartWith('inline')
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff');
});

test('a docx resume is shown as sandboxed html with its formatting', function () {
    $url = previewUrlFor('resume.docx', docxBytes(function ($section): void {
        $section->addText('Jane Doe', ['bold' => true, 'size' => 18]);
        $section->addText('Senior PHP Developer');
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Skills');
        $table->addCell(2000)->addText('Laravel');
    }));

    $response = $this->actingAs(User::factory()->create())->get($url);

    $response->assertOk()
        ->assertSee('font-weight:bold', false)
        ->assertSee('Senior PHP Developer')
        ->assertSee('<table class="t">', false);
    expect($response->headers->get('content-type'))->toContain('text/html')
        ->and($response->headers->get('content-security-policy'))->toStartWith('sandbox ')->toContain("default-src 'none'")
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff');
});

test('markup inside a docx is shown as text and never as html', function () {
    $url = previewUrlFor('resume.docx', docxBytes(function ($section): void {
        $section->addText('<script>alert(1)</script> <img src=x onerror=alert(2)>');
    }));

    $content = $this->actingAs(User::factory()->create())->get($url)->assertOk()->getContent();

    expect($content)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>')
        ->not->toContain('<img src=x');
});

test('a legacy doc resume falls back to a text preview', function () {
    $url = previewUrlFor('resume.doc', file_get_contents(base_path('tests/Fixtures/resume-plain.doc')));

    $response = $this->actingAs(User::factory()->create())->get($url);

    $response->assertOk()
        ->assertSee('Rahul Verma')
        ->assertSee('rahul.verma.test@example.com')
        ->assertSee('Text-only preview');
    expect($response->headers->get('content-security-policy'))->toContain('sandbox');
});

test('a docx that cannot be converted falls back to its text or a message', function () {
    $url = previewUrlFor('resume.docx', "PK\x03\x04 this is not really a zip archive");

    $this->actingAs(User::factory()->create())->get($url)
        ->assertOk()
        ->assertSee('cannot be previewed');
});

test('a file that is not a document gets a friendly message instead of an error', function () {
    $url = previewUrlFor('resume.pdf', 'plain text pretending to be a pdf');

    $this->actingAs(User::factory()->create())->get($url)
        ->assertOk()
        ->assertSee('cannot be previewed');
});

test('previewing is not found when there is no resume or the file is gone', function () {
    Storage::fake('local');
    $admin = User::factory()->create();

    $noResume = JobApplication::factory()->create(['resume_path' => null]);
    $missingFile = JobApplication::factory()->create(['resume_path' => 'resumes/1/missing.pdf']);

    $this->actingAs($admin)->get("/admin/candidates/{$noResume->id}/resume/preview")->assertNotFound();
    $this->actingAs($admin)->get("/admin/candidates/{$missingFile->id}/resume/preview")->assertNotFound();
    $this->actingAs($admin)->get('/admin/candidates/99999/resume/preview')->assertNotFound();
});
