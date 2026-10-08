<?php

namespace App\Actions\Candidates;

use RuntimeException;

class ReadCompoundFileStreams
{
    public const SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    private const END_OF_CHAIN = 0xFFFFFFFE;

    private const FREE_SECTOR = 0xFFFFFFFF;

    private const DIRECTORY_ENTRY_SIZE = 128;

    private const STREAM_ENTRY = 2;

    private const ROOT_ENTRY = 5;

    /**
     * Read named streams out of an OLE2 compound file - the container used
     * by legacy Microsoft Office formats such as .doc. Streams that aren't
     * in the file are left out of the result.
     *
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    public function handle(string $bytes, array $names): array
    {
        if (strlen($bytes) < 512 || ! str_starts_with($bytes, self::SIGNATURE)) {
            throw new RuntimeException('The file is not a valid Office document.');
        }

        $sectorSize = 1 << $this->uint16($bytes, 0x1E);
        $miniSectorSize = 1 << $this->uint16($bytes, 0x20);
        $miniStreamCutoff = $this->uint32($bytes, 0x38);

        if ($sectorSize < 512 || $sectorSize > 4096 || $miniSectorSize !== 64) {
            throw new RuntimeException('The Office document is corrupt.');
        }

        $fat = $this->readFat($bytes, $sectorSize);

        $directory = $this->readChain($bytes, $this->uint32($bytes, 0x30), $fat, $sectorSize);
        $entries = $this->parseDirectory($directory);

        $root = collect($entries)->firstWhere('type', self::ROOT_ENTRY);

        $miniFat = $this->uint32($bytes, 0x40) > 0
            ? $this->unpackSectors($this->readChain($bytes, $this->uint32($bytes, 0x3C), $fat, $sectorSize))
            : [];
        $miniStream = null;

        $streams = [];

        foreach ($entries as $entry) {
            if ($entry['type'] !== self::STREAM_ENTRY || ! in_array($entry['name'], $names, true)) {
                continue;
            }

            if ($entry['size'] < $miniStreamCutoff) {
                $miniStream ??= $root === null
                    ? ''
                    : $this->readChain($bytes, $root['start'], $fat, $sectorSize);

                $data = $this->readMiniChain($miniStream, $entry['start'], $miniFat, $miniSectorSize);
            } else {
                $data = $this->readChain($bytes, $entry['start'], $fat, $sectorSize);
            }

            $streams[$entry['name']] = substr($data, 0, $entry['size']);
        }

        return $streams;
    }

    /**
     * @return array<int, int>
     */
    private function readFat(string $bytes, int $sectorSize): array
    {
        $fatSectors = array_values(array_filter(
            $this->unpackSectors(substr($bytes, 0x4C, 109 * 4)),
            fn (int $sector): bool => $sector !== self::FREE_SECTOR,
        ));

        $difatSector = $this->uint32($bytes, 0x44);

        for ($i = 0, $count = $this->uint32($bytes, 0x48); $i < $count && $difatSector < self::END_OF_CHAIN; $i++) {
            $entries = $this->unpackSectors($this->readSector($bytes, $difatSector, $sectorSize));
            $difatSector = array_pop($entries);

            array_push($fatSectors, ...array_filter($entries, fn (int $sector): bool => $sector !== self::FREE_SECTOR));
        }

        $fat = [];

        foreach ($fatSectors as $sector) {
            array_push($fat, ...$this->unpackSectors($this->readSector($bytes, $sector, $sectorSize)));
        }

        return $fat;
    }

    /**
     * @param  array<int, int>  $fat
     */
    private function readChain(string $bytes, int $start, array $fat, int $sectorSize): string
    {
        $data = '';
        $sector = $start;

        for ($i = 0; $sector < self::END_OF_CHAIN; $i++) {
            if ($i > count($fat) || ! isset($fat[$sector])) {
                throw new RuntimeException('The Office document is corrupt.');
            }

            $data .= $this->readSector($bytes, $sector, $sectorSize);
            $sector = $fat[$sector];
        }

        return $data;
    }

    /**
     * @param  array<int, int>  $miniFat
     */
    private function readMiniChain(string $miniStream, int $start, array $miniFat, int $miniSectorSize): string
    {
        $data = '';
        $sector = $start;

        for ($i = 0; $sector < self::END_OF_CHAIN; $i++) {
            if ($i > count($miniFat) || ! isset($miniFat[$sector])) {
                throw new RuntimeException('The Office document is corrupt.');
            }

            $data .= substr($miniStream, $sector * $miniSectorSize, $miniSectorSize);
            $sector = $miniFat[$sector];
        }

        return $data;
    }

    private function readSector(string $bytes, int $sector, int $sectorSize): string
    {
        $offset = ($sector + 1) * $sectorSize;

        if ($offset >= strlen($bytes)) {
            throw new RuntimeException('The Office document is truncated.');
        }

        return substr($bytes, $offset, $sectorSize);
    }

    /**
     * @return array<int, array{name: string, type: int, start: int, size: int}>
     */
    private function parseDirectory(string $directory): array
    {
        $entries = [];

        for ($offset = 0; $offset + self::DIRECTORY_ENTRY_SIZE <= strlen($directory); $offset += self::DIRECTORY_ENTRY_SIZE) {
            $entry = substr($directory, $offset, self::DIRECTORY_ENTRY_SIZE);
            $nameLength = min($this->uint16($entry, 64), 64);

            $entries[] = [
                'name' => mb_convert_encoding(substr($entry, 0, max(0, $nameLength - 2)), 'UTF-8', 'UTF-16LE'),
                'type' => ord($entry[66]),
                'start' => $this->uint32($entry, 116),
                'size' => $this->uint32($entry, 120),
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, int>
     */
    private function unpackSectors(string $data): array
    {
        $data = substr($data, 0, intdiv(strlen($data), 4) * 4);

        return $data === '' ? [] : array_values(unpack('V*', $data));
    }

    private function uint16(string $bytes, int $offset): int
    {
        return unpack('v', $bytes, $offset)[1];
    }

    private function uint32(string $bytes, int $offset): int
    {
        return unpack('V', $bytes, $offset)[1];
    }
}
