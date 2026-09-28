<?php

namespace App\Models;

use App\Enums\AiRatingStatus;
use App\Enums\ApplicationStatus;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_posting_id',
    'name',
    'email',
    'phone',
    'resume_path',
    'cover_note',
    'status',
    'admin_notes',
    'ai_status',
    'ai_score',
    'ai_reasoning',
    'ai_strengths',
    'ai_gaps',
    'ai_profile',
    'ai_rated_at',
    'ai_error',
])]
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'ai_status' => AiRatingStatus::class,
            'ai_strengths' => 'array',
            'ai_gaps' => 'array',
            'ai_profile' => 'array',
            'ai_rated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
