<?php

namespace App\Models;

use App\Enums\CandidateImportStatus;
use Database\Factories\CandidateImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'status',
    'rate_with_ai',
    'spreadsheet_path',
    'archive_path',
    'total',
    'created_count',
    'skipped_count',
    'failed_count',
    'issues',
    'error',
])]
class CandidateImport extends Model
{
    /** @use HasFactory<CandidateImportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CandidateImportStatus::class,
            'rate_with_ai' => 'boolean',
            'issues' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
