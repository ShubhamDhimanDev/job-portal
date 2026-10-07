<?php

namespace App\Actions\Candidates;

class ParseNoticePeriodDays
{
    private const MAX_DAYS = 65535;

    /**
     * Turn the free-text notice period a recruiter typed ("Immediate", "30 days",
     * "2 months", "1-2 months") into a number of days so candidates can be
     * filtered on it. A range uses its upper end; text with no recognisable
     * duration returns null.
     */
    public function handle(?string $noticePeriod): ?int
    {
        $text = strtolower(trim((string) $noticePeriod));

        if ($text === '') {
            return null;
        }

        if (preg_match('/immediate|asap|\bnil\b|no notice/', $text) === 1) {
            return 0;
        }

        $pattern = '/(\d+(?:\.\d+)?)(?:\s*(?:-|to)\s*(\d+(?:\.\d+)?))?\s*(days?|d|weeks?|wks?|w|months?|mths?|mon|mos?|m|years?|yrs?|y)?\b/';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        $daysPerUnit = match (substr($matches[3] ?? '', 0, 1)) {
            'w' => 7,
            'm' => 30,
            'y' => 365,
            default => 1,
        };

        $amount = max((float) $matches[1], (float) ($matches[2] ?? 0));

        return min((int) round($amount * $daysPerUnit), self::MAX_DAYS);
    }
}
