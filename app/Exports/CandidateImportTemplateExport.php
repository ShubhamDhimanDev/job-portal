<?php

namespace App\Exports;

use App\Exports\CandidateImportTemplate\CandidatesSheet;
use App\Exports\CandidateImportTemplate\JobsSheet;
use App\Models\JobPosting;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CandidateImportTemplateExport implements Export, WithMultipleSheets
{
    use Exportable;

    /**
     * @return array<int, CandidatesSheet|JobsSheet>
     */
    public function sheets(): array
    {
        $jobs = JobPosting::query()->orderBy('title')->get(['id', 'title', 'slug']);

        return [
            new CandidatesSheet($jobs->count()),
            new JobsSheet($jobs),
        ];
    }
}
