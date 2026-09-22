<?php

use App\SourceImport\Readers\XlsWorkbookSource;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

function syntheticLegacyWorkbook(): string
{
    $path = tempnam(sys_get_temp_dir(), 'wald-xls-');
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A1', 'CustomerNo');
    $sheet->setCellValue('B1', 'Call No.');
    $sheet->setCellValue('C1', 'Plot Ref');
    $sheet->setCellValue('D1', 'Call Type');
    $sheet->setCellValue('E1', 'VS');
    $sheet->setCellValue('A2', 'CODE-1');
    $sheet->setCellValueExplicit('B2', '00123', DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('C2', '001', DataType::TYPE_STRING);
    $sheet->setCellValue('D2', 'PC1');
    $sheet->setCellValue('E2', 2);
    $sheet->setCellValue('F2', '=1+1');
    $sheet->setCellValue('G2', 45500);
    $sheet->getStyle('G2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
    $sheet->mergeCells('H1:I1');
    $sheet->setCellValue('H1', 'Merged heading');
    $sheet->getRowDimension(3)->setVisible(false);
    (new Xls($book))->save($path);
    $book->disconnectWorksheets();

    return $path;
}

it('reads legacy XLS as neutral physical observations without evaluating formulas', function (): void {
    $path = syntheticLegacyWorkbook();
    try {
        $source = new XlsWorkbookSource($path, new AnalysisBudget);
        try {
            $sheets = iterator_to_array($source->sheets());
            expect($source->metadata()['adapter_version'])->toBe('1')
                ->and($source->metadata()['date_system'])->toBe('1900')
                ->and($sheets)->toHaveCount(1)
                ->and($sheets[0]->cells[2][2]->rawValue)->toBe('00123')
                ->and($sheets[0]->cells[2][3]->rawValue)->toBe('001')
                ->and($sheets[0]->cells[2][5]->rawValue)->toBe('2')
                ->and($sheets[0]->cells[2][6]->formula)->toBe('1+1')
                ->and($sheets[0]->cells[2][7]->style['number_format'])->toBe('dd/mm/yyyy')
                ->and($sheets[0]->merges)->toHaveCount(1)
                ->and($sheets[0]->hiddenRows)->toContain(3);
        } finally {
            $source->close();
        }
    } finally {
        unlink($path);
    }
});

it('rejects disguised files and over-budget XLS', function (): void {
    $fake = tempnam(sys_get_temp_dir(), 'wald-fake-xls-');
    $path = syntheticLegacyWorkbook();
    try {
        file_put_contents($fake, "CustomerNo,Call No.\nCODE-1,123\n");
        expect(fn () => new XlsWorkbookSource($fake, new AnalysisBudget))
            ->toThrow(AnalysisProblem::class);
        expect(function () use ($path): void {
            $source = new XlsWorkbookSource($path, new AnalysisBudget(['cells' => 4]));
            try {
                iterator_to_array($source->sheets());
            } finally {
                $source->close();
            }
        })
            ->toThrow(AnalysisProblem::class);
    } finally {
        unlink($fake);
        unlink($path);
    }
});
