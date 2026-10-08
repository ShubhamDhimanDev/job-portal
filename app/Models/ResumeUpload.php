<?php

namespace App\Models;

use App\Enums\ResumeUploadItemStatus;
use App\Enums\ResumeUploadStatus;
use Database\Factories\ResumeUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'job_posting_id',
    'original_filename',
    'upload_path',
    'rate_with_ai',
    'status',
    'total',
    'error',
])]
class ResumeUpload extends Model
{
    /** @use HasFactory<ResumeUploadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResumeUploadStatus::class,
            'rate_with_ai' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * @return HasMany<ResumeUploadItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ResumeUploadItem::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [ResumeUploadStatus::Pending, ResumeUploadStatus::Processing], true);
    }

    /**
     * Mark the upload completed once every file has an outcome, and remove
     * the temporary files it was processed from.
     */
    public function completeWhenFinished(): void
    {
        if ($this->status !== ResumeUploadStatus::Processing) {
            return;
        }

        if ($this->items()->where('status', ResumeUploadItemStatus::Pending)->exists()) {
            return;
        }

        $this->update(['status' => ResumeUploadStatus::Completed, 'upload_path' => null]);

        Storage::disk('local')->deleteDirectory("resume-uploads/{$this->id}");
    }
}
