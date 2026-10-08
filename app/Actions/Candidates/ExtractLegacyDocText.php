<?php

namespace App\Actions\Candidates;

use RuntimeException;

class ExtractLegacyDocText
{
    private const WORD_MAGIC = 0xA5EC;

    private const FIRST_SUPPORTED_VERSION = 0x00C1;

    private const FIB_FLAGS_OFFSET = 0x0A;

    private const FIB_CLX_OFFSET = 0x1A2;

    private const FLAG_ENCRYPTED = 0x0100;

    private const FLAG_TABLE_STREAM_1 = 0x0200;

    public function __construct(private readonly ReadCompoundFileStreams $readCompoundFileStreams) {}

    /**
     * Extract the plain text of a Word 97-2003 (.doc) file by reading its
     * text pieces directly, since PhpWord's .doc reader returns garbled text.
     */
    public function handle(string $bytes): string
    {
        $streams = $this->readCompoundFileStreams->handle($bytes, ['WordDocument', '0Table', '1Table']);
        $document = $streams['WordDocument'] ?? '';

        if (strlen($document) <= self::FIB_CLX_OFFSET + 8 || $this->uint16($document, 0) !== self::WORD_MAGIC) {
            throw new RuntimeException('The file is not a Word document.');
        }

        if ($this->uint16($document, 2) < self::FIRST_SUPPORTED_VERSION) {
            throw new RuntimeException('Word 95 and older documents are not supported - save it as DOCX or PDF.');
        }

        $flags = $this->uint16($document, self::FIB_FLAGS_OFFSET);

        if (($flags & self::FLAG_ENCRYPTED) !== 0) {
            throw new RuntimeException('The document is password protected.');
        }

        $table = $streams[($flags & self::FLAG_TABLE_STREAM_1) !== 0 ? '1Table' : '0Table'] ?? '';

        $clx = substr(
            $table,
            $this->uint32($document, self::FIB_CLX_OFFSET),
            $this->uint32($document, self::FIB_CLX_OFFSET + 4),
        );

        return $this->tidy($this->stripFieldCodes($this->readPieces($document, $this->piecePlan($clx))));
    }

    /**
     * Locate the piece table in the CLX structure and list each piece's
     * character count, file offset and encoding.
     *
     * @return array<int, array{length: int, offset: int, compressed: bool}>
     */
    private function piecePlan(string $clx): array
    {
        $position = 0;

        while (($clx[$position] ?? '') === "\x01") {
            $position += 3 + $this->uint16($clx, $position + 1);
        }

        if (($clx[$position] ?? '') !== "\x02") {
            throw new RuntimeException('The Word document has no readable text.');
        }

        $plcPcd = substr($clx, $position + 5, $this->uint32($clx, $position + 1));
        $count = intdiv(strlen($plcPcd) - 4, 12);

        $pieces = [];

        for ($i = 0; $i < $count; $i++) {
            $start = $this->uint32($plcPcd, $i * 4);
            $end = $this->uint32($plcPcd, ($i + 1) * 4);
            $fc = $this->uint32($plcPcd, ($count + 1) * 4 + $i * 8 + 2);

            $compressed = ($fc & 0x40000000) !== 0;
            $fc &= 0x3FFFFFFF;

            $pieces[] = [
                'length' => max(0, $end - $start),
                'offset' => $compressed ? intdiv($fc, 2) : $fc,
                'compressed' => $compressed,
            ];
        }

        return $pieces;
    }

    /**
     * @param  array<int, array{length: int, offset: int, compressed: bool}>  $pieces
     */
    private function readPieces(string $document, array $pieces): string
    {
        $text = '';

        foreach ($pieces as $piece) {
            $text .= $piece['compressed']
                ? mb_convert_encoding(substr($document, $piece['offset'], $piece['length']), 'UTF-8', 'Windows-1252')
                : mb_convert_encoding(substr($document, $piece['offset'], $piece['length'] * 2), 'UTF-8', 'UTF-16LE');
        }

        return $text;
    }

    /**
     * Drop the instruction half of Word fields (e.g. the target of a
     * hyperlink) and keep the text the reader actually sees.
     */
    private function stripFieldCodes(string $text): string
    {
        if (preg_match('/[\x13\x15]/', $text) !== 1) {
            return $text;
        }

        $kept = '';
        $inInstruction = [];
        $hidden = 0;

        foreach (preg_split('/([\x13\x14\x15])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $token) {
            if ($token === "\x13") {
                $inInstruction[] = true;
                $hidden++;
            } elseif ($token === "\x14") {
                if ($inInstruction !== [] && end($inInstruction) === true) {
                    $inInstruction[array_key_last($inInstruction)] = false;
                    $hidden--;
                }
            } elseif ($token === "\x15") {
                if (array_pop($inInstruction) === true) {
                    $hidden--;
                }
            } elseif ($hidden === 0) {
                $kept .= $token;
            }
        }

        return $kept;
    }

    /**
     * Turn Word's control characters into plain whitespace.
     */
    private function tidy(string $text): string
    {
        $text = str_replace(["\x07\x07", "\r", "\x0B", "\x0C", "\x07", "\x1E", "\u{00A0}"], ["\n", "\n", "\n", "\n", "\t", '-', ' '], $text);
        $text = preg_replace('/[\x00-\x08\x0E-\x1F]/', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    private function uint16(string $bytes, int $offset): int
    {
        return strlen($bytes) >= $offset + 2 ? unpack('v', $bytes, $offset)[1] : 0;
    }

    private function uint32(string $bytes, int $offset): int
    {
        return strlen($bytes) >= $offset + 4 ? unpack('V', $bytes, $offset)[1] : 0;
    }
}
