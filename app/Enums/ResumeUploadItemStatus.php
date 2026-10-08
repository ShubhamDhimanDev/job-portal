<?php

namespace App\Enums;

enum ResumeUploadItemStatus: string
{
    case Pending = 'pending';
    case Created = 'created';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Created => 'Added',
            self::Skipped => 'Skipped',
            self::Failed => 'Failed',
        };
    }
}
