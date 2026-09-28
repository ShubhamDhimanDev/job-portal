<?php

namespace App\Enums;

enum WorkMode: string
{
    case OnSite = 'on_site';
    case Remote = 'remote';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::OnSite => 'On-site',
            self::Remote => 'Remote',
            self::Hybrid => 'Hybrid',
        };
    }
}
