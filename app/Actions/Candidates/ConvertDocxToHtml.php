<?php

namespace App\Actions\Candidates;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class ConvertDocxToHtml
{
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const MC_NS = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    private const NAMESPACES = [
        'w' => DocxStyleSheet::NS,
        'r' => self::REL_NS,
        'mc' => self::MC_NS,
        'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
        'wp' => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
        'wps' => 'http://schemas.microsoft.com/office/word/2010/wordprocessingShape',
        'v' => 'urn:schemas-microsoft-com:vml',
    ];

    private const IMAGE_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    private const MAX_PART_BYTES = 20 * 1024 * 1024;

    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    private const MAX_IMAGE_TOTAL_BYTES = 6 * 1024 * 1024;

    private const GAP = '<div class="p gap"></div>';

    private ZipArchive $zip;

    private DocxStyleSheet $styles;

    /**
     * @var array<string, array{target: string, external: bool}>
     */
    private array $relationships = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $counters = [];

    private int $imageBytes = 0;

    /**
     * Horizontal lines drawn as shapes inside the paragraph being read.
     *
     * @var array<int, string>
     */
    private array $rules = [];

    /**
     * Convert a .docx into an HTML fragment that keeps the document's text
     * flow and formatting: paragraphs, styles, lists, tables, text boxes,
     * links and images. Every piece of text and every attribute value is
     * escaped or whitelisted, because the file is untrusted.
     */
    public function handle(string $absolutePath): string
    {
        $this->counters = [];
        $this->imageBytes = 0;
        $this->rules = [];
        $this->zip = new ZipArchive;

        if ($this->zip->open($absolutePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The DOCX file could not be opened.');
        }

        try {
            $document = DocxStyleSheet::loadXml($this->part('word/document.xml'));
            $body = DocxStyleSheet::child($document?->documentElement, 'body');

            if ($body === null) {
                throw new RuntimeException('The DOCX has no readable content.');
            }

            $this->styles = new DocxStyleSheet($this->part('word/styles.xml'), $this->part('word/numbering.xml'));
            $this->relationships = $this->readRelationships('word/_rels/document.xml.rels');

            $html = $this->headerHtml($body).$this->blocks($body, false);
            $wrapper = $this->wrapperStyle();
        } finally {
            $this->zip->close();
        }

        return trim($html) === '' ? '' : '<div class="docx"'.$wrapper.'>'.$html.'</div>';
    }

    private function wrapperStyle(): string
    {
        $css = ['font-size:'.round($this->styles->defaultFontSize(), 1).'pt'];
        $font = $this->fontFamily($this->styles->defaultFont());

        if ($font !== null) {
            $css[] = 'font-family:'.$font;
        }

        return ' style="'.implode(';', $css).'"';
    }

    private function fontFamily(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $generic = match (true) {
            preg_match('/courier|consolas|mono|lucida console|menlo/i', $name) === 1 => 'monospace',
            preg_match('/times|georgia|garamond|cambria|palatino|book antiqua|century|constantia|baskerville|minion/i', $name) === 1 => 'serif',
            default => 'sans-serif',
        };

        return "'{$name}',{$generic}";
    }

    private function part(string $name): ?string
    {
        $stat = $this->zip->statName($name);

        if ($stat === false || $stat['size'] > self::MAX_PART_BYTES) {
            return null;
        }

        $data = $this->zip->getFromName($name);

        return $data === false ? null : $data;
    }

    /**
     * @return array<string, array{target: string, external: bool}>
     */
    private function readRelationships(string $name): array
    {
        $document = DocxStyleSheet::loadXml($this->part($name));
        $relationships = [];

        foreach ($document?->getElementsByTagNameNS(self::PACKAGE_REL_NS, 'Relationship') ?? [] as $relationship) {
            $relationships[$relationship->getAttribute('Id')] = [
                'target' => $relationship->getAttribute('Target'),
                'external' => $relationship->getAttribute('TargetMode') === 'External',
            ];
        }

        return $relationships;
    }

    /**
     * The page header, where many resumes keep the candidate's name and contact details.
     */
    private function headerHtml(DOMElement $body): string
    {
        $sectionProperties = DocxStyleSheet::child($body, 'sectPr');
        $target = null;

        foreach (DocxStyleSheet::children($sectionProperties) as $reference) {
            if ($reference->namespaceURI === DocxStyleSheet::NS && $reference->localName === 'headerReference') {
                $type = $reference->getAttributeNS(DocxStyleSheet::NS, 'type');
                $candidate = $this->relationships[$reference->getAttributeNS(self::REL_NS, 'id')]['target'] ?? null;

                if ($candidate !== null && ($type === 'default' || $target === null)) {
                    $target = $candidate;
                }
            }
        }

        if ($target === null) {
            return '';
        }

        $header = DocxStyleSheet::loadXml($this->part($this->resolvePath($target)));
        $html = $header === null ? '' : $this->blocks($header->documentElement, false);

        return trim(strip_tags($html)) === '' && ! str_contains($html, '<img') ? '' : '<div class="docx-header">'.$html.'</div>';
    }

    private function blocks(DOMElement $container, bool $onDark): string
    {
        $html = '';
        $previousWasGap = false;

        foreach (DocxStyleSheet::children($container) as $element) {
            if ($element->namespaceURI !== DocxStyleSheet::NS) {
                continue;
            }

            $block = match ($element->localName) {
                'p' => $this->paragraph($element, $onDark),
                'tbl' => $this->table($element, $onDark),
                'sdt' => $this->blocks(DocxStyleSheet::child($element, 'sdtContent') ?? $element, $onDark),
                default => '',
            };

            if ($block === '') {
                continue;
            }

            if ($block === self::GAP) {
                if ($previousWasGap) {
                    continue;
                }

                $previousWasGap = true;
            } else {
                $previousWasGap = false;
            }

            $html .= $block;
        }

        return $html;
    }

    private function paragraph(DOMElement $paragraph, bool $onDark): string
    {
        $pPr = DocxStyleSheet::child($paragraph, 'pPr');
        $direct = $pPr ? $this->styles->readParagraphProperties($pPr) : [];
        $props = $this->styles->paragraphProperties($direct);

        $outerRules = $this->rules;
        $this->rules = [];

        $inner = $this->inline($paragraph, $this->styles->paragraphRunProperties($props), $onDark);
        $marker = $this->listMarker($props, $direct);

        $rules = implode('', $this->rules);
        $this->rules = $outerRules;

        if ($marker === null && trim(strip_tags($inner)) === '' && ! str_contains($inner, '<img')) {
            return $rules === '' ? self::GAP : $rules;
        }

        return '<div class="p"'.$this->paragraphStyle($props, $direct, $marker).'>'.($marker['html'] ?? '').$inner.'</div>'.$rules;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $direct
     * @return array{html: string, left: int, hanging: int}|null
     */
    private function listMarker(array $props, array $direct): ?array
    {
        $numberId = (int) ($props['num_id'] ?? 0);

        if ($numberId === 0) {
            return null;
        }

        $level = (int) ($props['level'] ?? 0);
        $definition = $this->styles->level($numberId, $level) ?? ['format' => 'bullet', 'text' => '', 'start' => 1, 'left' => null, 'hanging' => null];

        $this->counters[$numberId][$level] = ($this->counters[$numberId][$level] ?? $definition['start'] - 1) + 1;

        foreach (array_keys($this->counters[$numberId]) as $deeper) {
            if ($deeper > $level) {
                unset($this->counters[$numberId][$deeper]);
            }
        }

        $left = $direct['left'] ?? $definition['left'] ?? 360 * ($level + 1);
        $hanging = isset($direct['first_line']) && $direct['first_line'] < 0 ? -$direct['first_line'] : ($definition['hanging'] ?? 360);
        $width = max(10, min(40, $this->pixels($hanging)));

        return [
            'html' => '<span class="mk" style="min-width:'.$width.'px">'.$this->e($this->markerText($numberId, $level, $definition)).'</span>',
            'left' => $left,
            'hanging' => $hanging,
        ];
    }

    /**
     * @param  array{format: string, text: string, start: int, left: ?int, hanging: ?int}  $definition
     */
    private function markerText(int $numberId, int $level, array $definition): string
    {
        if ($definition['format'] === 'bullet') {
            return $this->bulletCharacter($definition['text'], $level);
        }

        $text = $definition['text'] !== '' ? $definition['text'] : '%'.($level + 1).'.';

        $text = preg_replace_callback('/%([1-9])/', function (array $match) use ($numberId): string {
            $index = (int) $match[1] - 1;
            $definition = $this->styles->level($numberId, $index);

            return $this->formatNumber($this->counters[$numberId][$index] ?? ($definition['start'] ?? 1), $definition['format'] ?? 'decimal');
        }, $text) ?? $text;

        return preg_replace('/[\x{E000}-\x{F8FF}]/u', '', $text) ?? $text;
    }

    /**
     * Word often draws bullets with a symbol font, which stores them in the
     * private-use area; map the usual ones to real characters.
     */
    private function bulletCharacter(string $text, int $level): string
    {
        $character = mb_substr($text, 0, 1);

        $mapped = [
            "\u{F0B7}" => '•',
            "\u{F0A7}" => '▪',
            "\u{F0D8}" => '➢',
            "\u{F0FC}" => '✓',
            "\u{F076}" => '❖',
            "\u{F0E8}" => '➔',
            'o' => '◦',
        ][$character] ?? null;

        if ($mapped !== null) {
            return $mapped;
        }

        if ($character === '' || preg_match('/[\x{E000}-\x{F8FF}]/u', $character) === 1) {
            return ['•', '◦', '▪'][min($level, 2)];
        }

        return $character;
    }

    private function formatNumber(int $number, string $format): string
    {
        return match ($format) {
            'decimalZero' => sprintf('%02d', $number),
            'lowerLetter' => $this->letters($number),
            'upperLetter' => strtoupper($this->letters($number)),
            'lowerRoman' => strtolower($this->roman($number)),
            'upperRoman' => $this->roman($number),
            default => (string) $number,
        };
    }

    private function letters(int $number): string
    {
        $letters = '';

        for ($n = max(1, $number); $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(97 + ($n - 1) % 26).$letters;
        }

        return $letters;
    }

    private function roman(int $number): string
    {
        $roman = '';

        foreach (['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $symbol => $value) {
            while ($number >= $value) {
                $roman .= $symbol;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $direct
     * @param  array{html: string, left: int, hanging: int}|null  $marker
     */
    private function paragraphStyle(array $props, array $direct, ?array $marker): string
    {
        $css = [];

        if (($props['align'] ?? 'left') !== 'left') {
            $css[] = 'text-align:'.$props['align'];
        }

        if ($marker !== null) {
            $css[] = 'margin-left:min('.$this->pixels($marker['left']).'px,40%)';
            $css[] = 'text-indent:-'.min(40, $this->pixels($marker['hanging'])).'px';
        } else {
            if (($props['left'] ?? 0) > 0) {
                $css[] = 'margin-left:min('.$this->pixels($props['left']).'px,30%)';
            }

            if (($props['right'] ?? 0) > 0) {
                $css[] = 'margin-right:min('.$this->pixels($props['right']).'px,30%)';
            }

            if (($props['first_line'] ?? 0) !== 0) {
                $css[] = 'text-indent:'.max(-40, min(80, $this->pixels($props['first_line']))).'px';
            }
        }

        foreach (['before' => 'margin-top', 'after' => 'margin-bottom'] as $key => $property) {
            if (isset($props[$key])) {
                $css[] = $property.':'.min(30, $this->pixels($props[$key])).'px';
            }
        }

        if (isset($props['line_height']) && $props['line_height'] >= 1 && $props['line_height'] <= 2.5) {
            $css[] = 'line-height:'.round($props['line_height'], 2);
        }

        foreach (['border_top' => 'border-top', 'border_bottom' => 'border-bottom'] as $key => $property) {
            if (! empty($props[$key])) {
                $css[] = $property.':1px solid #'.$props[$key];
            }
        }

        return $css === [] ? '' : ' style="'.implode(';', $css).'"';
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function inline(DOMElement $container, array $base, bool $onDark): string
    {
        $html = '';

        foreach (DocxStyleSheet::children($container) as $element) {
            if ($element->namespaceURI !== DocxStyleSheet::NS) {
                continue;
            }

            $html .= match ($element->localName) {
                'r' => $this->run($element, $base, $onDark),
                'hyperlink' => $this->hyperlink($element, $base, $onDark),
                'ins', 'smartTag', 'customXml', 'fldSimple', 'moveTo' => $this->inline($element, $base, $onDark),
                'sdt' => $this->inline(DocxStyleSheet::child($element, 'sdtContent') ?? $element, $base, $onDark),
                default => '',
            };
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function hyperlink(DOMElement $link, array $base, bool $onDark): string
    {
        $inner = $this->inline($link, $base, $onDark);
        $relationship = $this->relationships[$link->getAttributeNS(self::REL_NS, 'id')] ?? null;
        $url = $relationship !== null && $relationship['external'] ? $relationship['target'] : '';

        if ($inner === '' || preg_match('/^(https?:|mailto:|tel:)/i', $url) !== 1) {
            return $inner;
        }

        return '<a href="'.$this->e($url).'" target="_blank" rel="noopener noreferrer">'.$inner.'</a>';
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function run(DOMElement $run, array $base, bool $onDark): string
    {
        $rPr = DocxStyleSheet::child($run, 'rPr');
        $props = $this->styles->runProperties($base, $rPr ? $this->styles->readRunProperties($rPr) : []);

        if ($props['hidden'] ?? false) {
            return '';
        }

        $text = '';
        $embedded = '';

        $this->readRunContent($run, $text, $embedded, $onDark);

        $css = $text === '' ? '' : $this->runStyle($props, $onDark);

        return ($text === '' ? '' : ($css === '' ? $text : '<span style="'.$css.'">'.$text.'</span>')).$embedded;
    }

    private function readRunContent(DOMElement $parent, string &$text, string &$embedded, bool $onDark): void
    {
        foreach (DocxStyleSheet::children($parent) as $element) {
            if ($element->namespaceURI === self::MC_NS && $element->localName === 'AlternateContent') {
                $choice = collect(DocxStyleSheet::children($element))->first(fn (DOMElement $child): bool => $child->localName === 'Choice')
                    ?? collect(DocxStyleSheet::children($element))->first();

                if ($choice !== null) {
                    $this->readRunContent($choice, $text, $embedded, $onDark);
                }

                continue;
            }

            if ($element->namespaceURI !== DocxStyleSheet::NS) {
                continue;
            }

            switch ($element->localName) {
                case 't':
                    $text .= $this->e($element->textContent);
                    break;
                case 'tab':
                    $text .= '<span class="tab"></span>';
                    break;
                case 'br':
                case 'cr':
                    $type = $element->getAttributeNS(DocxStyleSheet::NS, 'type');
                    $text .= in_array($type, ['page', 'column'], true) ? '<span class="pagebreak"></span>' : '<br>';
                    break;
                case 'noBreakHyphen':
                    $text .= '-';
                    break;
                case 'drawing':
                case 'pict':
                    $embedded .= $this->embedded($element, $onDark);
                    break;
            }
        }
    }

    /**
     * Text boxes and pictures. Text boxes are listed twice in the XML (as
     * DrawingML and as a VML fallback), but only the first form is read.
     */
    private function embedded(DOMElement $drawing, bool $onDark): string
    {
        $html = '';
        $textBoxes = array_filter(
            $this->query($drawing, './/w:txbxContent'),
            fn (DOMNode $box): bool => ! $this->isNestedTextBox($box, $drawing),
        );

        if ($textBoxes !== []) {
            $fill = $this->fillColor($drawing);
            $dark = $fill !== null ? $this->luminance($fill) < 0.5 : $onDark;

            foreach ($textBoxes as $box) {
                $html .= '<div class="tb"'.($fill !== null ? ' style="background:#'.$fill.'"' : '').'>'.$this->blocks($box, $dark).'</div>';
            }

            return $html;
        }

        $rule = $this->ruleFor($drawing);

        if ($rule !== null) {
            $this->rules[] = $rule;

            return '';
        }

        foreach ($this->query($drawing, './/a:blip | .//v:imagedata') as $image) {
            $id = $image->getAttributeNS(self::REL_NS, 'embed') ?: $image->getAttributeNS(self::REL_NS, 'id');
            $extent = $this->query($drawing, './/wp:extent')[0] ?? null;
            $width = $extent instanceof DOMElement ? (int) ((int) $extent->getAttribute('cx') / 9525) : 0;

            $html .= $this->image($id, $width);
        }

        return $html;
    }

    /**
     * A very wide, very thin shape is how Word draws the horizontal line
     * under a heading; show it as a rule.
     */
    private function ruleFor(DOMElement $drawing): ?string
    {
        $extent = $this->query($drawing, './/wp:extent')[0] ?? null;

        if (! $extent instanceof DOMElement || $this->query($drawing, './/a:blip | .//v:imagedata') !== []) {
            return null;
        }

        $width = (int) $extent->getAttribute('cx');
        $height = (int) $extent->getAttribute('cy');
        $lineWidth = $this->query($drawing, './/a:ln')[0] ?? null;
        $thickness = max($height, $lineWidth instanceof DOMElement ? (int) $lineWidth->getAttribute('w') : 0);

        if ($width < 600000 || $height > 120000 || $width < max($height, 1) * 8) {
            return null;
        }

        $colorNode = $this->query($drawing, './/wps:spPr//a:srgbClr')[0] ?? null;
        $color = $colorNode instanceof DOMElement && preg_match('/^[0-9A-Fa-f]{6}$/', $colorNode->getAttribute('val')) === 1
            ? strtoupper($colorNode->getAttribute('val'))
            : '000000';

        return '<div class="rule" style="border-top:'.max(1, min(4, (int) round($thickness / 9525))).'px solid #'.$color.'"></div>';
    }

    private function image(string $relationshipId, int $width): string
    {
        $target = $this->relationships[$relationshipId]['target'] ?? null;
        $type = $target === null ? null : (self::IMAGE_TYPES[strtolower(pathinfo($target, PATHINFO_EXTENSION))] ?? null);
        $path = $target === null ? '' : $this->resolvePath($target);
        $stat = $type === null ? false : $this->zip->statName($path);

        if ($stat === false || $stat['size'] > self::MAX_IMAGE_BYTES || $this->imageBytes + $stat['size'] > self::MAX_IMAGE_TOTAL_BYTES) {
            return '';
        }

        $data = $this->zip->getFromName($path);

        if ($data === false) {
            return '';
        }

        $this->imageBytes += strlen($data);
        $style = $width > 0 ? 'width:'.min($width, 700).'px;max-width:100%;height:auto' : 'max-width:100%;height:auto';

        return '<img alt="" style="'.$style.'" src="data:'.$type.';base64,'.base64_encode($data).'">';
    }

    private function isNestedTextBox(DOMNode $box, DOMElement $root): bool
    {
        for ($ancestor = $box->parentNode; $ancestor !== null && ! $ancestor->isSameNode($root); $ancestor = $ancestor->parentNode) {
            if ($ancestor instanceof DOMElement && $ancestor->namespaceURI === DocxStyleSheet::NS && $ancestor->localName === 'txbxContent') {
                return true;
            }
        }

        return false;
    }

    private function fillColor(DOMElement $drawing): ?string
    {
        $drawingFill = $this->query($drawing, './/wps:spPr/a:solidFill/a:srgbClr')[0] ?? null;
        $fill = $drawingFill instanceof DOMElement ? $drawingFill->getAttribute('val') : '';

        if ($fill === '') {
            $shape = $this->query($drawing, './/*[@fillcolor]')[0] ?? null;
            $fill = $shape instanceof DOMElement ? ltrim($shape->getAttribute('fillcolor'), '#') : '';
        }

        return preg_match('/^[0-9A-Fa-f]{6}$/', $fill) === 1 ? strtoupper($fill) : null;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function runStyle(array $props, bool $onDark): string
    {
        $css = [];

        if ($props['bold'] ?? false) {
            $css[] = 'font-weight:bold';
        }

        if ($props['italic'] ?? false) {
            $css[] = 'font-style:italic';
        }

        $decorations = array_filter([
            ($props['underline'] ?? false) ? 'underline' : null,
            ($props['strike'] ?? false) ? 'line-through' : null,
        ]);

        if ($decorations !== []) {
            $css[] = 'text-decoration:'.implode(' ', $decorations);
        }

        $font = $props['font'] ?? null;

        if ($font !== null && $font !== $this->styles->defaultFont()) {
            $css[] = 'font-family:'.$this->fontFamily($font);
        }

        $size = $props['size'] ?? null;

        if ($size !== null && $size !== $this->styles->defaultFontSize()) {
            $css[] = 'font-size:'.max(6, min(40, round($size, 1))).'pt';
        }

        $color = $props['color'] ?? null;

        if ($color !== null && $this->isReadable($color, $onDark)) {
            $css[] = 'color:#'.$color;
        }

        if ($props['caps'] ?? false) {
            $css[] = 'text-transform:uppercase';
        } elseif ($props['small_caps'] ?? false) {
            $css[] = 'font-variant:small-caps';
        }

        if (($props['vertical'] ?? null) !== null) {
            $css[] = 'vertical-align:'.($props['vertical'] === 'superscript' ? 'super' : 'sub').';font-size:smaller';
        }

        return implode(';', $css);
    }

    /**
     * Light text only survives on a dark background, and dark text only on
     * a light one, so a resume never ends up with invisible words.
     */
    private function isReadable(string $color, bool $onDark): bool
    {
        $luminance = $this->luminance($color);

        return $onDark ? $luminance > 0.3 : $luminance < 0.85;
    }

    private function luminance(string $hex): float
    {
        return (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2))) / 255;
    }

    private function table(DOMElement $table, bool $onDark): string
    {
        $tblPr = DocxStyleSheet::child($table, 'tblPr');
        $styleId = DocxStyleSheet::child($tblPr, 'tblStyle')?->getAttributeNS(DocxStyleSheet::NS, 'val');
        $bordered = $this->styles->tableHasBorders($styleId) || DocxStyleSheet::hasBorders(DocxStyleSheet::child($tblPr, 'tblBorders'));

        $rows = [];

        foreach (DocxStyleSheet::children($table) as $rowElement) {
            if ($rowElement->namespaceURI !== DocxStyleSheet::NS || $rowElement->localName !== 'tr') {
                continue;
            }

            $cells = [];
            $column = 0;

            foreach (DocxStyleSheet::children($rowElement) as $cellElement) {
                if ($cellElement->namespaceURI !== DocxStyleSheet::NS || $cellElement->localName !== 'tc') {
                    continue;
                }

                $tcPr = DocxStyleSheet::child($cellElement, 'tcPr');
                $merge = DocxStyleSheet::child($tcPr, 'vMerge');
                $fill = DocxStyleSheet::child($tcPr, 'shd')?->getAttributeNS(DocxStyleSheet::NS, 'fill') ?? '';
                $width = DocxStyleSheet::child($tcPr, 'tcW');
                $span = max(1, (int) DocxStyleSheet::child($tcPr, 'gridSpan')?->getAttributeNS(DocxStyleSheet::NS, 'val'));

                $cells[] = [
                    'element' => $cellElement,
                    'column' => $column,
                    'span' => $span,
                    'rowspan' => 1,
                    'merge' => $merge === null ? null : ($merge->getAttributeNS(DocxStyleSheet::NS, 'val') === 'restart' ? 'restart' : 'continue'),
                    'fill' => preg_match('/^[0-9A-Fa-f]{6}$/', $fill) === 1 ? strtoupper($fill) : null,
                    'width' => $width !== null && $width->getAttributeNS(DocxStyleSheet::NS, 'type') === 'dxa' ? (int) $width->getAttributeNS(DocxStyleSheet::NS, 'w') : 0,
                    'bordered' => DocxStyleSheet::hasBorders(DocxStyleSheet::child($tcPr, 'tcBorders')),
                    'middle' => DocxStyleSheet::child($tcPr, 'vAlign')?->getAttributeNS(DocxStyleSheet::NS, 'val') === 'center',
                    'skip' => false,
                ];

                $column += $span;
            }

            $rows[] = $cells;
        }

        $this->mergeVertically($rows);

        $html = '';

        foreach ($rows as $cells) {
            $total = array_sum(array_column($cells, 'width'));
            $html .= '<tr>';

            foreach ($cells as $cell) {
                if ($cell['skip']) {
                    continue;
                }

                $style = array_filter([
                    $cell['fill'] !== null ? 'background:#'.$cell['fill'] : null,
                    $cell['width'] > 0 && $total > 0 ? 'width:'.round($cell['width'] / $total * 100).'%' : null,
                    $cell['bordered'] && ! $bordered ? 'border:1px solid #bbb' : null,
                    $cell['middle'] ? 'vertical-align:middle' : null,
                ]);

                $dark = $cell['fill'] !== null ? $this->luminance($cell['fill']) < 0.5 : $onDark;
                $content = $this->blocks($cell['element'], $dark);

                $html .= '<td'
                    .($cell['span'] > 1 ? ' colspan="'.$cell['span'].'"' : '')
                    .($cell['rowspan'] > 1 ? ' rowspan="'.$cell['rowspan'].'"' : '')
                    .($style === [] ? '' : ' style="'.implode(';', $style).'"')
                    .'>'.($content === '' ? '&nbsp;' : $content).'</td>';
            }

            $html .= '</tr>';
        }

        return '<table class="t'.($bordered ? ' bordered' : '').'">'.$html.'</table>';
    }

    /**
     * Turn vertically merged cells into a single cell with a rowspan.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $rows
     */
    private function mergeVertically(array &$rows): void
    {
        foreach ($rows as $rowIndex => $cells) {
            foreach ($cells as $cellIndex => $cell) {
                if ($cell['merge'] !== 'restart') {
                    continue;
                }

                for ($next = $rowIndex + 1; $next < count($rows); $next++) {
                    $continued = collect($rows[$next])->search(
                        fn (array $candidate): bool => $candidate['column'] === $cell['column'] && $candidate['merge'] === 'continue',
                    );

                    if ($continued === false) {
                        break;
                    }

                    $rows[$next][$continued]['skip'] = true;
                    $rows[$rowIndex][$cellIndex]['rowspan']++;
                }
            }
        }
    }

    /**
     * @return array<int, DOMNode>
     */
    private function query(DOMNode $context, string $expression): array
    {
        $xpath = new DOMXPath($context instanceof DOMDocument ? $context : $context->ownerDocument);

        foreach (self::NAMESPACES as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        $result = $xpath->query($expression, $context);

        return $result === false ? [] : iterator_to_array($result, false);
    }

    private function resolvePath(string $target): string
    {
        $parts = [];

        foreach (explode('/', ltrim($target, '/')) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '' && $segment !== '.') {
                $parts[] = $segment;
            }
        }

        $path = implode('/', $parts);

        return str_starts_with($target, '/') || str_starts_with($path, 'word/') ? $path : 'word/'.$path;
    }

    private function pixels(int $twips): int
    {
        return (int) round($twips / 15);
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
