<?php

use App\Actions\Candidates\ExtractDocumentText;
use App\Actions\Candidates\ExtractLegacyDocText;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Store a file on the fake local disk and extract its text.
 */
function extractStoredText(string $path, string $contents): string
{
    Storage::fake('local');
    Storage::disk('local')->put($path, $contents);

    return app(ExtractDocumentText::class)->handle($path);
}

function docFixture(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/{$name}"));
}

test('a word 97-2003 document is read with its table, header and visible link text', function () {
    $text = extractStoredText('resumes/plain.doc', docFixture('resume-plain.doc'));

    expect($text)
        ->toContain('Rahul Verma')
        ->toContain('Email: rahul.verma.test@example.com | Mobile: 9876501234')
        ->toContain('Senior Java Developer at Globex Technologies, 2018 - Present')
        ->toContain("Skills\tJava, Spring, AWS\nNotice period\t30 days")
        ->toContain('Header Marker Rahul')
        ->not->toContain('hidden.target@example.org')
        ->not->toContain('HYPERLINK')
        ->not->toMatch('/[\x00-\x08\x0B-\x1F]/');
});

test('a word document with non-latin text is decoded from its utf-16 pieces', function () {
    $text = extractStoredText('resumes/unicode.doc', docFixture('resume-unicode.doc'));

    expect($text)
        ->toContain('राहुल Sharma')
        ->toContain('José Müller - priya.test@example.com - +91 98765 43210');
});

test('the format is detected from the contents rather than the extension', function () {
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Jane Doe - Senior PHP Developer');
    $path = tempnam(sys_get_temp_dir(), 'resume');
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    $docx = file_get_contents($path);
    unlink($path);

    expect(extractStoredText('resumes/really-a-docx.doc', $docx))->toContain('Jane Doe - Senior PHP Developer')
        ->and(extractStoredText('resumes/really-a-doc.docx', docFixture('resume-plain.doc')))->toContain('Rahul Verma');
});

test('files that are not word documents are refused with a clear reason', function (string $contents, string $message) {
    expect(fn () => extractStoredText('resumes/bad.doc', $contents))
        ->toThrow(RuntimeException::class, $message);
})->with([
    'plain text' => ['just some text', 'not a valid Word document'],
    'empty file' => ['', 'not a valid Word document'],
    'ole header only' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 600), 'corrupt'],
]);

test('a truncated word document fails cleanly instead of reading past the end', function () {
    expect(fn () => app(ExtractLegacyDocText::class)->handle(substr(docFixture('resume-plain.doc'), 0, 3000)))
        ->toThrow(RuntimeException::class);
});

test('a missing resume file is reported', function () {
    Storage::fake('local');

    expect(fn () => app(ExtractDocumentText::class)->handle('resumes/missing.doc'))
        ->toThrow(RuntimeException::class, 'could not be found');
});
