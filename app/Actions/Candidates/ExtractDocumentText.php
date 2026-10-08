<?php

namespace App\Actions\Candidates;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

class ExtractDocumentText
{
    public function __construct(private readonly ExtractLegacyDocText $extractLegacyDocText) {}

    /**
     * Extract plain text from a .doc/.docx resume stored on the local disk,
     * since document attachments on AI providers are PDF-native. The format
     * is detected from the file's contents, not its extension, because .doc
     * files are often really DOCX.
     */
    public function handle(string $path): string
    {
        $absolutePath = Storage::disk('local')->path($path);
        $handle = is_file($absolutePath) ? fopen($absolutePath, 'rb') : false;

        if ($handle === false) {
            throw new RuntimeException('The resume file could not be found.');
        }

        $header = (string) fread($handle, 8);
        fclose($handle);

        if (str_starts_with($header, "PK\x03\x04")) {
            return $this->docxText($absolutePath);
        }

        if (str_starts_with($header, ReadCompoundFileStreams::SIGNATURE)) {
            return $this->extractLegacyDocText->handle((string) file_get_contents($absolutePath));
        }

        throw new RuntimeException('The file is not a valid Word document.');
    }

    private function docxText(string $absolutePath): string
    {
        $text = '';

        foreach (IOFactory::load($absolutePath)->getSections() as $section) {
            $text .= $this->extractContainerText($section);
        }

        return trim($text);
    }

    private function extractContainerText(AbstractContainer $container): string
    {
        $text = '';

        foreach ($container->getElements() as $element) {
            if ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $text .= $this->extractContainerText($cell).' ';
                    }
                }
            } elseif (method_exists($element, 'getText')) {
                $value = $element->getText();
                $text .= (is_string($value) ? $value : '').PHP_EOL;
            }
        }

        return $text;
    }
}
