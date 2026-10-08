<?php

use App\Actions\Candidates\ConvertDocxToHtml;

const DOCX_NAMESPACES = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
    .'xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" '
    .'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
    .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
    .'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" '
    .'xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape" '
    .'xmlns:v="urn:schemas-microsoft-com:vml"';

const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

function wordXml(string $root, string $inner): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:'.$root.' '.DOCX_NAMESPACES.'>'.$inner.'</w:'.$root.'>';
}

function relationships(string ...$relationships): string
{
    return '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('', $relationships).'</Relationships>';
}

/**
 * Build a .docx from a body (and any extra package parts) and convert it.
 *
 * @param  array<string, string>  $parts
 */
function convertBody(string $body, array $parts = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach (['word/document.xml' => wordXml('document', '<w:body>'.$body.'</w:body>'), ...$parts] as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    try {
        return app(ConvertDocxToHtml::class)->handle($path);
    } finally {
        unlink($path);
    }
}

function paragraphOf(string $text, string $properties = ''): string
{
    return '<w:p>'.($properties === '' ? '' : '<w:pPr>'.$properties.'</w:pPr>').'<w:r><w:t>'.$text.'</w:t></w:r></w:p>';
}

test('runs split by spell-check markers and links stay in one paragraph', function () {
    $html = convertBody(
        '<w:p><w:r><w:t xml:space="preserve">Mail: </w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:t>hari</w:t></w:r>'
        .'<w:proofErr w:type="spellEnd"/><w:r><w:t>ohari55</w:t></w:r><w:hyperlink r:id="rId6"><w:r><w:t>@gmail.com</w:t></w:r></w:hyperlink></w:p>'
        .'<w:p><w:r><w:t>Overall, 1</w:t></w:r><w:r><w:t>4</w:t></w:r><w:r><w:t xml:space="preserve"> years</w:t></w:r></w:p>',
        ['word/_rels/document.xml.rels' => relationships('<Relationship Id="rId6" Type="x/hyperlink" Target="mailto:hari@gmail.com" TargetMode="External"/>')],
    );

    expect(substr_count($html, '<div class="p"'))->toBe(2)
        ->and(strip_tags($html))->toBe('Mail: hariohari55@gmail.comOverall, 14 years')
        ->and($html)->toContain('<a href="mailto:hari@gmail.com" target="_blank" rel="noopener noreferrer">');
});

test('direct formatting becomes inline styles and paragraph alignment', function () {
    $html = convertBody(
        '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:i/><w:u w:val="single"/><w:strike/><w:color w:val="C00000"/><w:sz w:val="28"/></w:rPr><w:t>Styled</w:t></w:r></w:p>'
        .'<w:p><w:pPr><w:jc w:val="both"/></w:pPr><w:r><w:rPr><w:b w:val="0"/><w:caps/><w:vertAlign w:val="superscript"/></w:rPr><w:t>justified</w:t></w:r></w:p>',
    );

    expect($html)
        ->toContain('text-align:center')
        ->toContain('font-weight:bold;font-style:italic;text-decoration:underline line-through;font-size:14pt;color:#C00000')
        ->toContain('text-align:justify')
        ->toContain('text-transform:uppercase')
        ->toContain('vertical-align:super');
    expect(substr_count($html, 'font-weight:bold'))->toBe(1);
});

test('styles are inherited and the document default font becomes the base font', function () {
    $styles = wordXml('styles',
        '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="22"/></w:rPr></w:rPrDefault></w:docDefaults>'
        .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:cs="Arial"/></w:rPr></w:style>'
        .'<w:style w:type="paragraph" w:styleId="Heading1"><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:before="240" w:after="60"/>'
        .'<w:pBdr><w:bottom w:val="single" w:sz="8" w:color="1F4E79"/></w:pBdr></w:pPr><w:rPr><w:b/><w:color w:val="1F4E79"/><w:sz w:val="32"/></w:rPr></w:style>'
        .'<w:style w:type="character" w:styleId="Emphasis"><w:rPr><w:i/></w:rPr></w:style>');

    $html = convertBody(
        paragraphOf('Experience', '<w:pStyle w:val="Heading1"/>')
        .'<w:p><w:r><w:rPr><w:rStyle w:val="Emphasis"/></w:rPr><w:t>slanted</w:t></w:r><w:r><w:rPr><w:rFonts w:ascii="Arial"/></w:rPr><w:t>arial</w:t></w:r></w:p>',
        ['word/styles.xml' => $styles],
    );

    expect($html)
        ->toStartWith('<div class="docx" style="font-size:11pt;font-family:\'Times New Roman\',serif">')
        ->toContain('margin-top:16px;margin-bottom:4px')
        ->toContain('border-bottom:1px solid #1F4E79')
        ->toContain('font-weight:bold;font-size:16pt;color:#1F4E79')
        ->toContain('font-style:italic')
        ->toContain("font-family:'Arial',sans-serif");
});

test('bullets and numbered lists get their markers and restart nested levels', function () {
    $numbering = wordXml('numbering',
        '<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="'."\u{F0D8}".'"/><w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr></w:lvl></w:abstractNum>'
        .'<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>'
        .'<w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%2)"/></w:lvl></w:abstractNum>'
        .'<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>');

    $item = fn (string $text, int $id, int $level = 0): string => paragraphOf($text, '<w:numPr><w:ilvl w:val="'.$level.'"/><w:numId w:val="'.$id.'"/></w:numPr>');

    $html = convertBody(
        $item('Bullet one', 1).$item('First', 2).$item('Sub a', 2, 1).$item('Sub b', 2, 1).$item('Second', 2).$item('Sub again', 2, 1),
        ['word/numbering.xml' => $numbering],
    );

    $markers = [];
    preg_match_all('/<span class="mk"[^>]*>([^<]*)<\/span>([^<]*)/', $html, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $markers[] = $match[1].' '.$match[2];
    }

    expect($markers)->toBe(['➢ Bullet one', '1. First', 'a) Sub a', 'b) Sub b', '2. Second', 'a) Sub again'])
        ->and($html)->toContain('margin-left:min(48px,40%);text-indent:-24px');
});

test('tables keep merged cells, widths and shading, and readable text colours', function () {
    $white = '<w:rPr><w:color w:val="FFFFFF"/></w:rPr>';

    $html = convertBody(
        '<w:tbl><w:tblPr><w:tblBorders><w:top w:val="single"/></w:tblBorders></w:tblPr>'
        .'<w:tr><w:tc><w:tcPr><w:tcW w:w="3000" w:type="dxa"/><w:vMerge w:val="restart"/><w:shd w:val="clear" w:fill="1F2937"/></w:tcPr><w:p><w:r>'.$white.'<w:t>Sidebar</w:t></w:r></w:p></w:tc>'
        .'<w:tc><w:tcPr><w:tcW w:w="7000" w:type="dxa"/></w:tcPr><w:p><w:r><w:t>Top right</w:t></w:r></w:p></w:tc></w:tr>'
        .'<w:tr><w:tc><w:tcPr><w:vMerge/></w:tcPr><w:p/></w:tc><w:tc><w:tcPr><w:tcW w:w="7000" w:type="dxa"/></w:tcPr><w:p><w:r><w:t>Bottom right</w:t></w:r></w:p></w:tc></w:tr>'
        .'<w:tr><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r>'.$white.'<w:t>Spanning white</w:t></w:r></w:p></w:tc></w:tr></w:tbl>',
    );

    expect($html)
        ->toContain('<table class="t bordered">')
        ->toContain('rowspan="2"')
        ->toContain('colspan="2"')
        ->toContain('background:#1F2937;width:30%')
        ->toContain('width:70%')
        ->toContain('color:#FFFFFF">Sidebar')
        ->not->toContain('color:#FFFFFF">Spanning white')
        ->and(substr_count($html, '<td'))->toBe(4);
});

test('a text box is shown once, not again from its vml fallback', function () {
    $box = '<w:txbxContent><w:p><w:r><w:t>Boxed Name</w:t></w:r></w:p></w:txbxContent>';

    $html = convertBody(
        '<w:p><w:r><mc:AlternateContent><mc:Choice Requires="wps"><w:drawing><wp:inline><wp:extent cx="3000000" cy="1000000"/>'
        .'<a:graphic><a:graphicData><wps:wsp><wps:spPr><a:solidFill><a:srgbClr val="F3F3F3"/></a:solidFill></wps:spPr><wps:txbx>'.$box.'</wps:txbx></wps:wsp></a:graphicData></a:graphic>'
        .'</wp:inline></w:drawing></mc:Choice><mc:Fallback><w:pict><v:shape><v:textbox>'.$box.'</v:textbox></v:shape></w:pict></mc:Fallback></mc:AlternateContent></w:r></w:p>',
    );

    expect(substr_count($html, 'Boxed Name'))->toBe(1)
        ->and($html)->toContain('<div class="tb" style="background:#F3F3F3">');
});

test('a wide thin shape becomes a rule under its paragraph', function () {
    $html = convertBody(
        '<w:p><w:r><w:drawing><wp:anchor><wp:extent cx="6501765" cy="43180"/><a:graphic><a:graphicData><wps:wsp><wps:spPr><a:solidFill><a:srgbClr val="333333"/></a:solidFill></wps:spPr></wps:wsp></a:graphicData></a:graphic></wp:anchor></w:drawing></w:r>'
        .'<w:r><w:t>Career Summary</w:t></w:r></w:p>'
        .'<w:p><w:r><w:drawing><wp:inline><wp:extent cx="900000" cy="900000"/><a:graphic><a:graphicData><wps:wsp><wps:spPr><a:solidFill><a:srgbClr val="333333"/></a:solidFill></wps:spPr></wps:wsp></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>',
    );

    expect($html)->toContain('<div class="rule" style="border-top:4px solid #333333"></div>')
        ->and(strpos($html, 'Career Summary'))->toBeLessThan(strpos($html, 'class="rule"'))
        ->and(substr_count($html, 'class="rule"'))->toBe(1);
});

test('pictures are embedded as data uris and unsupported ones are skipped', function () {
    $picture = fn (string $id): string => '<w:p><w:r><w:drawing><wp:inline><wp:extent cx="952500" cy="952500"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="'.$id.'"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';

    $html = convertBody($picture('rId1').$picture('rId2'), [
        'word/_rels/document.xml.rels' => relationships(
            '<Relationship Id="rId1" Type="x/image" Target="media/photo.png"/>',
            '<Relationship Id="rId2" Type="x/image" Target="media/vector.emf"/>',
        ),
        'word/media/photo.png' => base64_decode(TINY_PNG),
        'word/media/vector.emf' => 'not-supported',
    ]);

    expect(substr_count($html, '<img'))->toBe(1)
        ->and($html)->toContain('src="data:image/png;base64,'.TINY_PNG.'"')
        ->toContain('width:100px');
});

test('the page header is included but the footer is not', function () {
    $html = convertBody(
        paragraphOf('Body text').'<w:sectPr><w:headerReference w:type="default" r:id="rId1"/><w:footerReference w:type="default" r:id="rId2"/></w:sectPr>',
        [
            'word/_rels/document.xml.rels' => relationships(
                '<Relationship Id="rId1" Type="x/header" Target="header1.xml"/>',
                '<Relationship Id="rId2" Type="x/footer" Target="footer1.xml"/>',
            ),
            'word/header1.xml' => wordXml('hdr', paragraphOf('Header Name 98765')),
            'word/footer1.xml' => wordXml('ftr', paragraphOf('Page 1 of 3')),
        ],
    );

    expect($html)->toContain('<div class="docx-header">')
        ->and(strpos($html, 'Header Name 98765'))->toBeLessThan(strpos($html, 'Body text'))
        ->and($html)->not->toContain('Page 1 of 3');
});

test('hidden and deleted text is left out and inserted text is kept', function () {
    $html = convertBody(
        '<w:p><w:r><w:t>Shown </w:t></w:r><w:r><w:rPr><w:vanish/></w:rPr><w:t>Hidden</w:t></w:r>'
        .'<w:del><w:r><w:delText>Deleted</w:delText></w:r></w:del><w:ins><w:r><w:t>Inserted</w:t></w:r></w:ins></w:p>',
    );

    expect(strip_tags($html))->toBe('Shown Inserted');
});

test('consecutive empty paragraphs collapse into one gap and tabs and breaks are kept', function () {
    $html = convertBody(
        paragraphOf('One').'<w:p/><w:p/><w:p><w:pPr/></w:p>'.paragraphOf('Two')
        .'<w:p><w:r><w:t>A</w:t><w:tab/><w:t>B</w:t><w:br/><w:t>C</w:t><w:br w:type="page"/></w:r></w:p>',
    );

    expect(substr_count($html, 'class="p gap"'))->toBe(1)
        ->and($html)->toContain('A<span class="tab"></span>B<br>C<span class="pagebreak"></span>');
});

test('nothing in the file can inject markup or active content', function () {
    $html = convertBody(
        paragraphOf('&lt;script&gt;alert(1)&lt;/script&gt; &lt;img src=x onerror=alert(2)&gt;')
        .'<w:p><w:hyperlink r:id="rId1"><w:r><w:t>evil link</w:t></w:r></w:hyperlink><w:hyperlink r:id="rId2"><w:r><w:t>good link</w:t></w:r></w:hyperlink></w:p>'
        .'<w:p><w:r><w:rPr><w:color w:val="red;background:url(x)"/><w:rFonts w:ascii="Arial\'};x{y"/></w:rPr><w:t>sneaky</w:t></w:r></w:p>',
        ['word/_rels/document.xml.rels' => relationships(
            '<Relationship Id="rId1" Type="x/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>',
            '<Relationship Id="rId2" Type="x/hyperlink" Target="https://example.com/?a=1&amp;b=&quot;2" TargetMode="External"/>',
        )],
    );

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script')
        ->not->toContain('<img src=x')
        ->not->toContain('javascript:')
        ->toContain('<a href="https://example.com/?a=1&amp;b=&quot;2"')
        ->not->toContain('background:url')
        ->not->toContain('};x{y')
        ->and(strip_tags($html))->toContain('evil link');
});

test('a document that declares a doctype or entities is refused', function () {
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]>'.wordXml('document', '<w:body>'.paragraphOf('&x;').'</w:body>'));
    $zip->close();

    try {
        expect(fn () => app(ConvertDocxToHtml::class)->handle($path))->toThrow(RuntimeException::class);
    } finally {
        unlink($path);
    }
});

test('a file that is not a docx is refused', function () {
    $path = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($path, 'not a zip');

    try {
        expect(fn () => app(ConvertDocxToHtml::class)->handle($path))->toThrow(RuntimeException::class);
    } finally {
        unlink($path);
    }
});
