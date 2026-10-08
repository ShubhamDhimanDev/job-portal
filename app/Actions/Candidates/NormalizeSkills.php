<?php

namespace App\Actions\Candidates;

class NormalizeSkills
{
    public const MAX_SKILLS = 50;

    public const MAX_LENGTH = 50;

    /**
     * Tidy a list of skills: trim them, collapse inner whitespace, drop blanks
     * and case-insensitive duplicates (the first spelling wins), and keep the
     * list and each skill within their limits.
     *
     * @param  array<int|string, mixed>  $skills
     * @return array<int, string>
     */
    public function handle(array $skills): array
    {
        $unique = [];

        foreach ($skills as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $skill = trim(preg_replace('/\s+/u', ' ', $skill) ?? '');

            if ($skill !== '') {
                $unique[mb_strtolower($skill)] ??= mb_substr($skill, 0, self::MAX_LENGTH);
            }
        }

        return array_slice(array_values($unique), 0, self::MAX_SKILLS);
    }

    /**
     * The form of a skill used to compare skills: lower case, with the
     * character that separates skills in the search column kept out of it.
     */
    public function matchKey(string $skill): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace('|', ' ', $skill)) ?? ''));
    }

    /**
     * Every skill, lower-cased and wrapped in separators ("|php|laravel|"), so
     * a candidate's skills can be matched exactly with a plain LIKE on any
     * database.
     *
     * @param  array<int, mixed>|null  $skills
     */
    public function searchColumn(?array $skills): ?string
    {
        $keys = array_values(array_unique(array_filter(array_map(
            fn (mixed $skill): string => is_string($skill) ? $this->matchKey($skill) : '',
            $skills ?? [],
        ))));

        return $keys === [] ? null : '|'.implode('|', $keys).'|';
    }
}
