<?php

namespace App\Actions\Candidates;

use Illuminate\Support\Facades\Storage;
use Throwable;

class RenderResumePreview
{
    public function __construct(
        private readonly ExtractDocumentText $extractDocumentText,
        private readonly ConvertDocxToHtml $convertDocxToHtml,
    ) {}

    /**
     * Whether the stored resume is a PDF, which the browser can display itself.
     */
    public function isPdf(string $path): bool
    {
        return str_starts_with($this->fileHeader($path), '%PDF-');
    }

    /**
     * A self-contained HTML page showing the resume: DOCX files are converted
     * with their formatting, anything else (legacy .doc, or a DOCX that can't
     * be converted) is shown as plain text.
     */
    public function html(string $path, string $title): string
    {
        if (str_starts_with($this->fileHeader($path), "PK\x03\x04")) {
            $document = $this->convertDocx($path);

            if ($document !== null) {
                return view('resume-preview', ['title' => $title, 'html' => $document, 'text' => null, 'notice' => null, 'message' => null])->render();
            }
        }

        try {
            $text = $this->extractDocumentText->handle($path);
        } catch (Throwable) {
            $text = '';
        }

        return view('resume-preview', [
            'title' => $title,
            'html' => null,
            'text' => $text === '' ? null : $text,
            'notice' => $text === '' ? null : 'Text-only preview - download the original to see the full formatting.',
            'message' => 'This resume cannot be previewed. Download it to open it.',
        ])->render();
    }

    private function convertDocx(string $path): ?string
    {
        try {
            $html = $this->convertDocxToHtml->handle(Storage::disk('local')->path($path));
        } catch (Throwable) {
            return null;
        }

        return trim(strip_tags($html)) === '' && ! str_contains($html, '<img') ? null : $html;
    }

    private function fileHeader(string $path): string
    {
        $absolutePath = Storage::disk('local')->path($path);
        $handle = is_file($absolutePath) ? fopen($absolutePath, 'rb') : false;

        if ($handle === false) {
            return '';
        }

        $header = (string) fread($handle, 8);
        fclose($handle);

        return $header;
    }
}
