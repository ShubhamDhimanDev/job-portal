<?php

namespace App\Exports\CandidateImportTemplate;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class CandidatesSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    private const LAST_ROW = 1000;

    public function __construct(private readonly int $jobCount) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Candidates';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['name', 'email', 'phone', 'job', 'resume_filename'];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
                    $sheet->getColumnDimension($column)->setWidth(28);
                }

                if ($this->jobCount === 0) {
                    return;
                }

                $lastJobRow = $this->jobCount + 1;

                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Unknown job');
                $validation->setError('Pick a job slug from the list.');
                $validation->setFormula1("Jobs!\$C\$2:\$C\${$lastJobRow}");

                $sheet->setDataValidation('D2:D'.self::LAST_ROW, $validation);
            },
        ];
    }
}
