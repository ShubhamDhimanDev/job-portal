<?php

namespace App\Exports\CandidateImportTemplate;

use App\Models\JobPosting;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JobsSheet implements FromCollection, WithEvents, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  Collection<int, JobPosting>  $jobs
     */
    public function __construct(private readonly Collection $jobs) {}

    public function title(): string
    {
        return 'Jobs';
    }

    /**
     * @return Collection<int, JobPosting>
     */
    public function collection(): Collection
    {
        return $this->jobs;
    }

    /**
     * The sheet only feeds the job dropdown, so it stays hidden from the user.
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $event->sheet->getDelegate()
                ->setSheetState(Worksheet::SHEETSTATE_HIDDEN),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['id', 'title', 'slug'];
    }

    /**
     * @param  JobPosting  $row
     * @return array<int, int|string>
     */
    public function map($row): array
    {
        return [$row->id, $row->title, $row->slug];
    }
}
