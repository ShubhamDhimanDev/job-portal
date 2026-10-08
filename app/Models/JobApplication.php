<?php

namespace App\Models;

use App\Actions\Candidates\NormalizeSkills;
use App\Actions\Candidates\ParseNoticePeriodDays;
use App\Concerns\HasReferenceCode;
use App\Enums\AiRatingStatus;
use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\InterviewType;
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
    'gender',
    'date_of_birth',
    'total_experience',
    'relevant_experience',
    'current_company',
    'industry_type',
    'current_designation',
    'current_location',
    'current_ctc',
    'expected_ctc',
    'notice_period',
    'interview_type',
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
    'skills',
    'ai_rated_at',
    'ai_error',
])]
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory, HasReferenceCode;

    /** Indian mobile: 10 digits starting 6-9, optional +91 / 91 / 0 prefix, spaces or dashes allowed. */
    public const PHONE_PATTERN = '/^(?:(?:\+?91|0)[\s-]*)?[6-9](?:[\s-]*\d){9}$/';

    protected static function referenceCodePrefix(): string
    {
        return 'CAN';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'ai_status' => AiRatingStatus::class,
            'gender' => Gender::class,
            'interview_type' => InterviewType::class,
            'date_of_birth' => 'date',
            'total_experience' => 'float',
            'relevant_experience' => 'float',
            'current_ctc' => 'float',
            'expected_ctc' => 'float',
            'notice_period_days' => 'integer',
            'ai_strengths' => 'array',
            'ai_gaps' => 'array',
            'ai_profile' => 'array',
            'skills' => 'array',
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

    /**
     * Keep the columns used for filtering in step with the free-text notice
     * period and the skills list recruiters edit.
     */
    protected static function booted(): void
    {
        static::saving(function (JobApplication $application): void {
            if ($application->isDirty('notice_period')) {
                $application->notice_period_days = app(ParseNoticePeriodDays::class)->handle($application->notice_period);
            }

            if ($application->isDirty('skills')) {
                $application->skills_search = app(NormalizeSkills::class)->searchColumn($application->skills);
            }
        });
    }
}
