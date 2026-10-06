<?php

namespace App\Actions\Candidates;

use App\Models\JobApplication;
use Illuminate\Support\Facades\Storage;

class DeleteCandidateApplications
{
    /**
     * Delete the given candidates along with their stored resume files.
     *
     * @param  array<int, int>  $ids
     */
    public function handle(array $ids): int
    {
        $candidates = JobApplication::query()->whereIn('id', $ids)->get(['id', 'resume_path']);

        JobApplication::query()->whereIn('id', $candidates->modelKeys())->delete();

        Storage::disk('local')->delete($candidates->pluck('resume_path')->filter()->all());

        return $candidates->count();
    }
}
