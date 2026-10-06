<?php

namespace App\Actions\Candidates;

use App\Models\JobApplication;
use Illuminate\Support\Collection;

class FindDuplicateCandidates
{
    /**
     * Group candidates that share an email address or phone number, across
     * all jobs. Candidates linked through either field end up in one group.
     * Each group lists its ids oldest first, so the first id is the one to keep.
     *
     * @return Collection<int, array<int, int>>
     */
    public function handle(): Collection
    {
        $parents = [];
        $owners = [];

        $find = function (int $id) use (&$parents): int {
            while ($parents[$id] !== $id) {
                $parents[$id] = $parents[$parents[$id]];
                $id = $parents[$id];
            }

            return $id;
        };

        foreach (JobApplication::query()->orderBy('id')->get(['id', 'email', 'phone']) as $candidate) {
            $parents[$candidate->id] = $candidate->id;

            foreach ($this->identityKeys($candidate) as $key) {
                if (isset($owners[$key])) {
                    $parents[$find($candidate->id)] = $find($owners[$key]);
                } else {
                    $owners[$key] = $candidate->id;
                }
            }
        }

        return collect(array_keys($parents))
            ->groupBy(fn (int $id): int => $find($id))
            ->filter(fn (Collection $ids): bool => $ids->count() > 1)
            ->map(fn (Collection $ids): array => $ids->sort()->values()->all())
            ->values();
    }

    /**
     * The ids of every candidate that is part of a duplicate group.
     *
     * @return array<int, int>
     */
    public function duplicateIds(): array
    {
        return $this->handle()->flatten()->all();
    }

    /**
     * The ids that can be removed while keeping the oldest of each group.
     *
     * @return array<int, int>
     */
    public function redundantIds(): array
    {
        return $this->handle()
            ->flatMap(fn (array $ids): array => array_slice($ids, 1))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function identityKeys(JobApplication $candidate): array
    {
        $keys = [];

        $email = mb_strtolower(trim($candidate->email));

        if ($email !== '') {
            $keys[] = "email:{$email}";
        }

        $digits = preg_replace('/\D+/', '', $candidate->phone) ?? '';

        if ($digits !== '') {
            $keys[] = 'phone:'.substr($digits, -10);
        }

        return $keys;
    }
}
