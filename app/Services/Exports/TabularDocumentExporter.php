<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularDocumentExporter
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, string>  $meta  Lines shown above the table
     */
    public function downloadXlsx(
        string $filename,
        string $sheetTitle,
        array $headers,
        array $rows,
        array $meta = [],
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        $lastCol = Coordinate::stringFromColumnIndex(max(1, count($headers)));
        $rowIndex = 1;

        foreach ($meta as $line) {
            $sheet->setCellValue("A{$rowIndex}", $line);
            $sheet->mergeCells("A{$rowIndex}:{$lastCol}{$rowIndex}");
            $rowIndex++;
        }

        if ($meta !== []) {
            $rowIndex++;
        }

        foreach ($headers as $col => $header) {
            $cell = Coordinate::stringFromColumnIndex($col + 1).$rowIndex;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        $rowIndex++;
        foreach ($rows as $row) {
            foreach ($row as $col => $value) {
                $cell = Coordinate::stringFromColumnIndex($col + 1).$rowIndex;
                $sheet->setCellValue($cell, $value);
            }
            $rowIndex++;
        }

        foreach (range(1, max(1, count($headers))) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, string>  $meta
     */
    public function downloadDocx(
        string $filename,
        string $title,
        array $headers,
        array $rows,
        array $meta = [],
    ): StreamedResponse {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection([
            'marginLeft' => 800,
            'marginRight' => 800,
            'marginTop' => 800,
            'marginBottom' => 800,
        ]);

        $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        foreach ($meta as $line) {
            $section->addText($line, ['size' => 10]);
        }

        if ($meta !== []) {
            $section->addTextBreak(1);
        }

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'CBD5E1',
            'cellMargin' => 80,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        $table->addRow();
        foreach ($headers as $header) {
            $table->addCell(1500, ['bgColor' => 'F1F5F9'])->addText((string) $header, ['bold' => true, 'size' => 9]);
        }

        foreach ($rows as $row) {
            $table->addRow();
            foreach ($row as $value) {
                $table->addCell(1500)->addText((string) ($value ?? ''), ['size' => 9]);
            }
        }

        return response()->streamDownload(function () use ($phpWord): void {
            $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
