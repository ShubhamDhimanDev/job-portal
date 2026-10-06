<?php

namespace App\Exports\CandidateImportTemplate;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
        return [
            'name',
            'email',
            'phone',
            'job',
            'resume_filename',
            'gender',
            'date_of_birth',
            'total_experience',
            'relevant_experience',
            'current_company',
            'industry_type',
            'current_designation',
            'current_location',
            'current_ctc',
            'expected_ctc',
            'notice_period',
            'interview_type',
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                foreach (range('A', 'Q') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(28);
                }

                $this->addListValidation($sheet, 'F2:F'.self::LAST_ROW, '"Male,Female,Other"', 'Pick Male, Female or Other.');
                $this->addListValidation($sheet, 'Q2:Q'.self::LAST_ROW, '"Face to Face,Virtual"', 'Pick Face to Face or Virtual.');
                $sheet->getStyle('G2:G'.self::LAST_ROW)->getNumberFormat()->setFormatCode('yyyy-mm-dd');

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

    private function addListValidation(Worksheet $sheet, string $range, string $formula, string $error): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid value');
        $validation->setError($error);
        $validation->setFormula1($formula);

        $sheet->setDataValidation($range, $validation);
    }
}
