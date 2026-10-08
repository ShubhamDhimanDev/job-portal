<?php

namespace App\Actions\Candidates;

use DOMDocument;
use DOMElement;
use DOMNode;

class DocxStyleSheet
{
    public const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @var array<string, array{based_on: ?string, paragraph: array<string, mixed>, run: array<string, mixed>, bordered: bool}>
     */
    private array $styles = [];

    /**
     * @var array<string, mixed>
     */
    private array $defaultParagraph = [];

    /**
     * @var array<string, mixed>
     */
    private array $defaultRun = [];

    private ?string $defaultParagraphStyle = null;

    /**
     * @var array<int, int>
     */
    private array $numberToAbstract = [];

    /**
     * @var array<int, array<int, array{format: string, text: string, start: int, left: ?int, hanging: ?int}>>
     */
    private array $levels = [];

    public function __construct(?string $stylesXml, ?string $numberingXml)
    {
        $styles = self::loadXml($stylesXml);

        if ($styles !== null) {
            $this->readStyles($styles);
        }

        $numbering = self::loadXml($numberingXml);

        if ($numbering !== null) {
            $this->readNumbering($numbering);
        }
    }

    /**
     * Parse XML from an untrusted file: no network access, and no DTDs or
     * entities at all, which Word never writes.
     */
    public static function loadXml(?string $xml): ?DOMDocument
    {
        if ($xml === null || $xml === '' || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadXml($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * @return array<int, DOMElement>
     */
    public static function children(?DOMNode $parent): array
    {
        $elements = [];

        foreach ($parent?->childNodes ?? [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    public static function child(?DOMNode $parent, string $name): ?DOMElement
    {
        foreach (self::children($parent) as $element) {
            if ($element->namespaceURI === self::NS && $element->localName === $name) {
                return $element;
            }
        }

        return null;
    }

    public static function hasBorders(?DOMElement $borders): bool
    {
        foreach (self::children($borders) as $side) {
            if (! in_array($side->getAttributeNS(self::NS, 'val'), ['', 'nil', 'none'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function readRunProperties(DOMElement $rPr): array
    {
        $props = [];

        foreach (self::children($rPr) as $element) {
            if ($element->namespaceURI !== self::NS) {
                continue;
            }

            $value = $element->getAttributeNS(self::NS, 'val');

            if ($element->localName === 'rFonts' && ($font = $this->fontName($element)) !== null) {
                $props['font'] = $font;
            }

            match ($element->localName) {
                'b' => $props['bold'] = $this->isOn($element),
                'i' => $props['italic'] = $this->isOn($element),
                'strike', 'dstrike' => $props['strike'] = $this->isOn($element),
                'caps' => $props['caps'] = $this->isOn($element),
                'smallCaps' => $props['small_caps'] = $this->isOn($element),
                'vanish' => $props['hidden'] = $this->isOn($element),
                'u' => $props['underline'] = $value !== 'none',
                'sz' => $props['size'] = is_numeric($value) ? (float) $value / 2 : null,
                'color' => $props['color'] = preg_match('/^[0-9A-Fa-f]{6}$/', $value) === 1 ? strtoupper($value) : null,
                'vertAlign' => $props['vertical'] = in_array($value, ['superscript', 'subscript'], true) ? $value : null,
                'rStyle' => $props['style'] = $value,
                default => null,
            };
        }

        return $props;
    }

    /**
     * @return array<string, mixed>
     */
    public function readParagraphProperties(DOMElement $pPr): array
    {
        $props = [];

        foreach (self::children($pPr) as $element) {
            if ($element->namespaceURI !== self::NS) {
                continue;
            }

            $value = $element->getAttributeNS(self::NS, 'val');

            switch ($element->localName) {
                case 'pStyle':
                    $props['style'] = $value;
                    break;
                case 'jc':
                    $props['align'] = match ($value) {
                        'center' => 'center',
                        'right', 'end' => 'right',
                        'both', 'distribute' => 'justify',
                        default => 'left',
                    };
                    break;
                case 'ind':
                    $this->readIndent($element, $props);
                    break;
                case 'spacing':
                    $this->readSpacing($element, $props);
                    break;
                case 'numPr':
                    $props['num_id'] = (int) self::child($element, 'numId')?->getAttributeNS(self::NS, 'val');
                    $props['level'] = (int) self::child($element, 'ilvl')?->getAttributeNS(self::NS, 'val');
                    break;
                case 'pBdr':
                    foreach (['top', 'bottom'] as $side) {
                        $border = self::child($element, $side);

                        if ($border !== null) {
                            $color = $border->getAttributeNS(self::NS, 'color');
                            $drawn = ! in_array($border->getAttributeNS(self::NS, 'val'), ['', 'nil', 'none'], true);
                            $props["border_{$side}"] = $drawn ? (preg_match('/^[0-9A-Fa-f]{6}$/', $color) === 1 ? strtoupper($color) : '808080') : null;
                        }
                    }
                    break;
            }
        }

        return $props;
    }

    /**
     * Paragraph properties after applying the document defaults and the
     * paragraph style's inheritance chain beneath the direct formatting.
     *
     * @param  array<string, mixed>  $direct
     * @return array<string, mixed>
     */
    public function paragraphProperties(array $direct): array
    {
        $styleId = ($direct['style'] ?? null) ?: $this->defaultParagraphStyle;
        $props = $this->defaultParagraph;

        foreach ($this->chain($styleId) as $style) {
            $props = array_replace($props, $style['paragraph']);
        }

        return [...array_replace($props, $direct), 'style' => $styleId];
    }

    /**
     * The run formatting a paragraph passes to its runs.
     *
     * @param  array<string, mixed>  $paragraph
     * @return array<string, mixed>
     */
    public function paragraphRunProperties(array $paragraph): array
    {
        $props = $this->defaultRun;

        foreach ($this->chain($paragraph['style'] ?? null) as $style) {
            $props = array_replace($props, $style['run']);
        }

        return $props;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $direct
     * @return array<string, mixed>
     */
    public function runProperties(array $base, array $direct): array
    {
        $props = $base;

        foreach ($this->chain($direct['style'] ?? null) as $style) {
            $props = array_replace($props, $style['run']);
        }

        unset($props['style']);

        return array_replace($props, array_diff_key($direct, ['style' => true]));
    }

    /**
     * The run formatting that applies to text with no formatting of its own.
     *
     * @return array<string, mixed>
     */
    public function baseRunProperties(): array
    {
        return $this->paragraphRunProperties($this->paragraphProperties([]));
    }

    public function defaultFontSize(): float
    {
        return $this->baseRunProperties()['size'] ?? 10.0;
    }

    public function defaultFont(): ?string
    {
        return $this->baseRunProperties()['font'] ?? null;
    }

    public function tableHasBorders(?string $styleId): bool
    {
        foreach ($this->chain($styleId) as $style) {
            if ($style['bordered']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{format: string, text: string, start: int, left: ?int, hanging: ?int}|null
     */
    public function level(int $numberId, int $level): ?array
    {
        return $this->levels[$this->numberToAbstract[$numberId] ?? -1][$level] ?? null;
    }

    private function readStyles(DOMDocument $document): void
    {
        $defaults = $document->getElementsByTagNameNS(self::NS, 'docDefaults')->item(0);
        $runDefaults = self::child(self::child($defaults, 'rPrDefault'), 'rPr');
        $paragraphDefaults = self::child(self::child($defaults, 'pPrDefault'), 'pPr');

        $this->defaultRun = $runDefaults ? $this->readRunProperties($runDefaults) : [];
        $this->defaultParagraph = $paragraphDefaults ? $this->readParagraphProperties($paragraphDefaults) : [];

        foreach ($document->getElementsByTagNameNS(self::NS, 'style') as $style) {
            $id = $style->getAttributeNS(self::NS, 'styleId');

            if ($id === '') {
                continue;
            }

            $pPr = self::child($style, 'pPr');
            $rPr = self::child($style, 'rPr');

            $this->styles[$id] = [
                'based_on' => self::child($style, 'basedOn')?->getAttributeNS(self::NS, 'val') ?: null,
                'paragraph' => $pPr ? $this->readParagraphProperties($pPr) : [],
                'run' => $rPr ? $this->readRunProperties($rPr) : [],
                'bordered' => self::hasBorders(self::child(self::child($style, 'tblPr'), 'tblBorders')),
            ];

            if ($style->getAttributeNS(self::NS, 'type') === 'paragraph' && $style->getAttributeNS(self::NS, 'default') === '1') {
                $this->defaultParagraphStyle = $id;
            }
        }
    }

    private function readNumbering(DOMDocument $document): void
    {
        foreach ($document->getElementsByTagNameNS(self::NS, 'abstractNum') as $abstract) {
            $abstractId = (int) $abstract->getAttributeNS(self::NS, 'abstractNumId');

            foreach (self::children($abstract) as $level) {
                if ($level->localName !== 'lvl') {
                    continue;
                }

                $indent = self::child(self::child($level, 'pPr'), 'ind');
                $left = $indent?->getAttributeNS(self::NS, 'left') ?: $indent?->getAttributeNS(self::NS, 'start');
                $hanging = $indent?->getAttributeNS(self::NS, 'hanging');

                $this->levels[$abstractId][(int) $level->getAttributeNS(self::NS, 'ilvl')] = [
                    'format' => self::child($level, 'numFmt')?->getAttributeNS(self::NS, 'val') ?? 'decimal',
                    'text' => self::child($level, 'lvlText')?->getAttributeNS(self::NS, 'val') ?? '',
                    'start' => (int) (self::child($level, 'start')?->getAttributeNS(self::NS, 'val') ?: 1),
                    'left' => is_numeric($left) ? (int) $left : null,
                    'hanging' => is_numeric($hanging) ? (int) $hanging : null,
                ];
            }
        }

        foreach ($document->getElementsByTagNameNS(self::NS, 'num') as $number) {
            $abstractId = self::child($number, 'abstractNumId')?->getAttributeNS(self::NS, 'val');

            if (is_numeric($abstractId)) {
                $this->numberToAbstract[(int) $number->getAttributeNS(self::NS, 'numId')] = (int) $abstractId;
            }
        }
    }

    /**
     * @return array<int, array{based_on: ?string, paragraph: array<string, mixed>, run: array<string, mixed>, bordered: bool}>
     */
    private function chain(?string $styleId): array
    {
        $chain = [];
        $seen = [];

        while ($styleId !== null && isset($this->styles[$styleId]) && ! isset($seen[$styleId])) {
            $seen[$styleId] = true;
            array_unshift($chain, $this->styles[$styleId]);
            $styleId = $this->styles[$styleId]['based_on'];
        }

        return $chain;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function readIndent(DOMElement $element, array &$props): void
    {
        foreach (['left' => ['left', 'start'], 'right' => ['right', 'end']] as $key => $attributes) {
            foreach ($attributes as $attribute) {
                $value = $element->getAttributeNS(self::NS, $attribute);

                if (is_numeric($value)) {
                    $props[$key] = (int) $value;
                    break;
                }
            }
        }

        $firstLine = $element->getAttributeNS(self::NS, 'firstLine');
        $hanging = $element->getAttributeNS(self::NS, 'hanging');

        if (is_numeric($hanging)) {
            $props['first_line'] = -(int) $hanging;
        } elseif (is_numeric($firstLine)) {
            $props['first_line'] = (int) $firstLine;
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function readSpacing(DOMElement $element, array &$props): void
    {
        foreach (['before', 'after'] as $key) {
            $value = $element->getAttributeNS(self::NS, $key);

            if (is_numeric($value)) {
                $props[$key] = (int) $value;
            }
        }

        $line = $element->getAttributeNS(self::NS, 'line');
        $rule = $element->getAttributeNS(self::NS, 'lineRule');

        if (is_numeric($line) && in_array($rule, ['', 'auto'], true)) {
            $props['line_height'] = (int) $line / 240;
        }
    }

    private function fontName(DOMElement $fonts): ?string
    {
        $name = $fonts->getAttributeNS(self::NS, 'ascii') ?: $fonts->getAttributeNS(self::NS, 'hAnsi');
        $name = trim(preg_replace('/[^A-Za-z0-9 \-]/', '', $name) ?? '');

        return $name === '' ? null : $name;
    }

    private function isOn(DOMElement $element): bool
    {
        return ! in_array(strtolower($element->getAttributeNS(self::NS, 'val')), ['0', 'false', 'off', 'none'], true);
    }
}
