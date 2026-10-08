<?php

namespace App\Models;

use App\Enums\ResumeUploadItemStatus;
use Database\Factories\ResumeUploadItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'resume_upload_id',
    'job_application_id',
    'filename',
    'file_path',
    'status',
    'name',
    'email',
    'reason',
])]
class ResumeUploadItem extends Model
{
    /** @use HasFactory<ResumeUploadItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResumeUploadItemStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ResumeUpload, $this>
     */
    public function resumeUpload(): BelongsTo
    {
        return $this->belongsTo(ResumeUpload::class);
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
