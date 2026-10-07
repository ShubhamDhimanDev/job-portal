<?php

namespace App\Enums;

enum NoticePeriodFilter: string
{
    case Immediate = 'immediate';
    case FifteenDays = '15_days';
    case OneMonth = '1_month';
    case TwoMonths = '2_months';
    case ThreeMonths = '3_months';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediate joiner',
            self::FifteenDays => 'Within 15 days',
            self::OneMonth => 'Within 1 month',
            self::TwoMonths => 'Within 2 months',
            self::ThreeMonths => 'Within 3 months',
        };
    }

    /**
     * The longest notice period, in days, that this option still matches.
     */
    public function maxDays(): int
    {
        return match ($this) {
            self::Immediate => 0,
            self::FifteenDays => 15,
            self::OneMonth => 30,
            self::TwoMonths => 60,
            self::ThreeMonths => 90,
        };
    }
}
