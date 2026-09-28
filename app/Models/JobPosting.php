<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\WorkMode;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'company_id',
    'posted_by_id',
    'title',
    'slug',
    'description',
    'responsibilities',
    'requirements',
    'location',
    'work_mode',
    'employment_type',
    'experience_level',
    'min_salary',
    'max_salary',
    'salary_negotiable',
    'department',
    'vacancies',
    'status',
    'application_deadline',
])]
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_mode' => WorkMode::class,
            'employment_type' => EmploymentType::class,
            'status' => JobStatus::class,
            'salary_negotiable' => 'boolean',
            'application_deadline' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    protected static function booted(): void
    {
        static::creating(function (JobPosting $jobPosting): void {
            if (blank($jobPosting->slug)) {
                $jobPosting->slug = static::uniqueSlugFor($jobPosting->title);
            }
        });
    }

    protected static function uniqueSlugFor(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
