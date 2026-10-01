<?php

namespace App\Jobs;

use App\Actions\Candidates\CreateCandidateApplication;
use App\Enums\CandidateImportStatus;
use App\Imports\CandidatesImport;
use App\Models\CandidateImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;
use ZipArchive;

class ProcessCandidateImport implements ShouldQueue
{
    use Queueable;

    private const MAX_ARCHIVE_FILES = 1000;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public CandidateImport $candidateImport) {}

    public function handle(CreateCandidateApplication $createCandidateApplication): void
    {
        $this->candidateImport->update(['status' => CandidateImportStatus::Processing]);

        $extractDirectory = Storage::disk('local')->path("imports/{$this->candidateImport->id}-extracted");

        try {
            $import = new CandidatesImport(
                $createCandidateApplication,
                $this->extractResumes($extractDirectory),
                $this->candidateImport->rate_with_ai,
            );

            Excel::import($import, $this->candidateImport->spreadsheet_path, 'local');

            $this->candidateImport->update([
                'status' => CandidateImportStatus::Completed,
                'total' => $import->total,
                'created_count' => $import->created,
                'skipped_count' => $import->skipped,
                'failed_count' => $import->failed,
                'issues' => $import->issues,
            ]);
        } catch (Throwable $e) {
            Log::error('Candidate import failed', [
                'candidate_import_id' => $this->candidateImport->id,
                'exception' => $e,
            ]);

            $this->candidateImport->update([
                'status' => CandidateImportStatus::Failed,
                'error' => $e->getMessage(),
            ]);
        } finally {
            File::deleteDirectory($extractDirectory);
            Storage::disk('local')->delete(array_filter([
                $this->candidateImport->spreadsheet_path,
                $this->candidateImport->archive_path,
            ]));
        }
    }

    /**
     * Extract resume files from the ZIP using only each entry's basename so
     * crafted paths can't escape the extraction directory.
     *
     * @return array<string, string> Lowercased filename => absolute path.
     */
    private function extractResumes(string $directory): array
    {
        if ($this->candidateImport->archive_path === null) {
            return [];
        }

        $zip = new ZipArchive;
        $opened = $zip->open(Storage::disk('local')->path($this->candidateImport->archive_path));

        if ($opened !== true) {
            throw new RuntimeException('The resumes ZIP could not be opened.');
        }

        File::ensureDirectoryExists($directory);

        $files = [];

        for ($i = 0; $i < min($zip->numFiles, self::MAX_ARCHIVE_FILES); $i++) {
            $entry = (string) $zip->getNameIndex($i);
            $basename = basename($entry);

            if (str_ends_with($entry, '/') || str_starts_with($basename, '.') || str_contains($entry, '__MACOSX')) {
                continue;
            }

            $stream = $zip->getStream($entry);

            if ($stream === false) {
                continue;
            }

            $target = $directory.DIRECTORY_SEPARATOR.$i.'-'.$basename;
            file_put_contents($target, stream_get_contents($stream));
            fclose($stream);

            $files[strtolower($basename)] = $target;
        }

        $zip->close();

        return $files;
    }
}
