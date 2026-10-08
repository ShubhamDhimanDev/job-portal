<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        html { background: #fff; color: #111; }
        body { max-width: 56rem; margin: 0 auto; padding: 1.5rem; font: 14px/1.5 system-ui, sans-serif; }
        .notice { margin: 0 0 1rem; padding: .5rem .75rem; border-radius: .375rem; background: #f3f4f6; color: #4b5563; font-size: 12px; }
        .text { white-space: pre-wrap; word-break: break-word; }
        .message { margin: 4rem 0; text-align: center; color: #6b7280; }

        .docx { font: 11pt/1.3 'Segoe UI', Calibri, Arial, sans-serif; overflow-wrap: anywhere; }
        .docx .p { margin: 0; white-space: pre-wrap; }
        .docx .gap { height: .6em; }
        .docx .rule { margin: 2px 0 4px; }
        .docx .mk { display: inline-block; text-indent: 0; }
        .docx .tab { display: inline-block; width: 2.2em; }
        .docx .pagebreak { display: block; margin: 1rem 0; border-top: 1px dashed #d1d5db; }
        .docx .tb { margin: .25rem 0; padding: .4rem .6rem; }
        .docx .docx-header { margin-bottom: .75rem; padding-bottom: .5rem; border-bottom: 1px solid #e5e7eb; }
        .docx table.t { border-collapse: collapse; width: 100%; margin: .25rem 0; }
        .docx table.t td { vertical-align: top; padding: 3px 6px; }
        .docx table.t.bordered td { border: 1px solid #bbb; }
        .docx a { color: #1d4ed8; }
        .docx img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    @if ($notice)
        <p class="notice">{{ $notice }}</p>
    @endif

    @if ($html !== null)
        {!! $html !!}
    @elseif ($text !== null)
        <div class="text">{{ $text }}</div>
    @else
        <p class="message">{{ $message }}</p>
    @endif
</body>
</html>
