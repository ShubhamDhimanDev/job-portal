<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case New = 'new';
    case Shortlisted = 'shortlisted';
    case OnHold = 'on_hold';
    case Rejected = 'rejected';
    case Hired = 'hired';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Shortlisted => 'Shortlisted',
            self::OnHold => 'On Hold',
            self::Rejected => 'Rejected',
            self::Hired => 'Hired',
        };
    }
}
