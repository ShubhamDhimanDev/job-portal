<?php

namespace App\Jobs;

use App\Enums\ResumeUploadItemStatus;
use App\Enums\ResumeUploadStatus;
use App\Models\ResumeUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class ProcessResumeUpload implements ShouldQueue
{
    use Queueable;

    public const MAX_RESUME_BYTES = 5 * 1024 * 1024;

    public const RESUME_EXTENSIONS = ['pdf', 'doc', 'docx'];

    private const MAX_ARCHIVE_FILES = 1000;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public ResumeUpload $resumeUpload) {}

    /**
     * Split the upload (one resume, or a ZIP of them) into one item per
     * resume, then queue each item to be parsed and added as a candidate.
     */
    public function handle(): void
    {
        $this->resumeUpload->update(['status' => ResumeUploadStatus::Processing]);

        try {
            if (strtolower(pathinfo($this->resumeUpload->upload_path, PATHINFO_EXTENSION)) === 'zip') {
                $this->createItemsFromArchive();
            } else {
                $this->createItemFromSingleFile();
            }

            if (! $this->resumeUpload->items()->exists()) {
                throw new RuntimeException('No resumes were found in the ZIP.');
            }
        } catch (Throwable $e) {
            Log::error('Resume upload failed', [
                'resume_upload_id' => $this->resumeUpload->id,
                'exception' => $e,
            ]);

            $this->resumeUpload->items()->delete();
            $this->resumeUpload->update([
                'status' => ResumeUploadStatus::Failed,
                'upload_path' => null,
                'error' => $e->getMessage(),
            ]);
            Storage::disk('local')->deleteDirectory("resume-uploads/{$this->resumeUpload->id}");

            return;
        }

        $this->resumeUpload->update(['total' => $this->resumeUpload->items()->count()]);

        foreach ($this->resumeUpload->items()->where('status', ResumeUploadItemStatus::Pending)->get() as $item) {
            ImportResumeFile::dispatch($item);
        }

        $this->resumeUpload->completeWhenFinished();
    }

    private function createItemFromSingleFile(): void
    {
        $path = $this->resumeUpload->upload_path;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = $this->resumeUpload->original_filename;

        if (! in_array($extension, self::RESUME_EXTENSIONS, true)) {
            $this->addItem($filename, ResumeUploadItemStatus::Failed, reason: 'Only PDF, DOC and DOCX resumes are supported.');
        } elseif (Storage::disk('local')->size($path) > self::MAX_RESUME_BYTES) {
            $this->addItem($filename, ResumeUploadItemStatus::Failed, reason: 'Resume must be smaller than 5MB.');
        } else {
            $this->addItem($filename, ResumeUploadItemStatus::Pending, $path);
        }
    }

    /**
     * Extract resumes from the ZIP, naming each file by its position so
     * crafted entry paths can't escape the upload directory.
     */
    private function createItemsFromArchive(): void
    {
        $zip = new ZipArchive;

        if ($zip->open(Storage::disk('local')->path($this->resumeUpload->upload_path)) !== true) {
            throw new RuntimeException('The ZIP file could not be opened.');
        }

        $directory = "resume-uploads/{$this->resumeUpload->id}";
        $accepted = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            $basename = basename(str_replace('\\', '/', $entry));

            if (str_ends_with($entry, '/') || str_starts_with($basename, '.') || str_contains($entry, '__MACOSX')) {
                continue;
            }

            if ($accepted === self::MAX_ARCHIVE_FILES) {
                $this->addItem('Remaining files', ResumeUploadItemStatus::Failed, reason: 'Only the first '.self::MAX_ARCHIVE_FILES.' files of a ZIP are processed.');

                break;
            }

            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

            if (! in_array($extension, self::RESUME_EXTENSIONS, true)) {
                $this->addItem($basename, ResumeUploadItemStatus::Failed, reason: 'Only PDF, DOC and DOCX resumes are supported.');

                continue;
            }

            if (($zip->statIndex($i)['size'] ?? 0) > self::MAX_RESUME_BYTES) {
                $this->addItem($basename, ResumeUploadItemStatus::Failed, reason: 'Resume must be smaller than 5MB.');

                continue;
            }

            $stream = $zip->getStream($entry);

            if ($stream === false) {
                $this->addItem($basename, ResumeUploadItemStatus::Failed, reason: 'This file could not be read from the ZIP.');

                continue;
            }

            $path = "{$directory}/{$i}.{$extension}";
            Storage::disk('local')->put($path, $stream);
            fclose($stream);

            $this->addItem($basename, ResumeUploadItemStatus::Pending, $path);
            $accepted++;
        }

        $zip->close();
    }

    private function addItem(string $filename, ResumeUploadItemStatus $status, ?string $filePath = null, ?string $reason = null): void
    {
        $this->resumeUpload->items()->create([
            'filename' => $filename,
            'file_path' => $filePath,
            'status' => $status,
            'reason' => $reason,
        ]);
    }
}
