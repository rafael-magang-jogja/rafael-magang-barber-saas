<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExcelExportService
{
    /**
     * @param array{
     *     title: string,
     *     meta: array<string, string>,
     *     kpi_summary?: array<string, int|string>,
     *     headers: array<string>,
     *     alignments?: array<string>,
     *     formats?: array<string>,
     *     rows: array<array<mixed>>,
     *     totals?: array<mixed>,
     *     theme_color?: string
     * } $data
     */
    public function downloadXlsx(string $filename, array $data): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($data['title'], 0, 31));

        // Set default font
        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);

        $currentRow = 1;
        $totalColumns = count($data['headers']);
        $lastColumnLetter = $this->getColumnLetter($totalColumns);

        // 1. JUDUL LAPORAN UTAMA
        $sheet->setCellValue("A{$currentRow}", strtoupper($data['title']));
        $sheet->getStyle("A{$currentRow}")->getFont()->setSize(14)->setBold(true);
        $currentRow++;

        // 2. METADATA LAPORAN
        if (! empty($data['meta'])) {
            foreach ($data['meta'] as $label => $val) {
                $sheet->setCellValue("A{$currentRow}", $label);
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB('71717A');
                $sheet->setCellValue("B{$currentRow}", $val);
                $sheet->getStyle("B{$currentRow}")->getFont()->getColor()->setRGB('18181B');
                $currentRow++;
            }
        }

        $currentRow++; // Baris jeda

        // 3. RINGKASAN EKSEKUTIF (KPI SUMMARY BOX)
        if (! empty($data['kpi_summary'])) {
            $sheet->setCellValue("A{$currentRow}", 'RINGKASAN EKSEKUTIF');
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('18181B');
            $currentRow++;

            $kpiStartRow = $currentRow;
            foreach ($data['kpi_summary'] as $kpiLabel => $kpiValue) {
                $sheet->setCellValue("A{$currentRow}", $kpiLabel);
                $sheet->getStyle("A{$currentRow}")->getFont()->getColor()->setRGB('52525B');

                $sheet->setCellValue("B{$currentRow}", $kpiValue);
                $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB('09090B');
                if (is_numeric($kpiValue)) {
                    $sheet->getStyle("B{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
                }
                $currentRow++;
            }

            // Style box KPI ringkasan
            $kpiEndRow = $currentRow - 1;
            $sheet->getStyle("A{$kpiStartRow}:B{$kpiEndRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E4E7');
            $sheet->getStyle("A{$kpiStartRow}:B{$kpiEndRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FAFAFA');

            $currentRow++; // Baris jeda
        }

        // 4. HEADER TABEL DATA
        $headerRow = $currentRow;
        $themeColor = $data['theme_color'] ?? '18181B'; // Default dark zinc

        foreach ($data['headers'] as $colIdx => $headerText) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $headerText);
        }

        // Styling Header Tabel
        $headerRange = "A{$headerRow}:{$lastColumnLetter}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $themeColor],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => false,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => $themeColor],
                ],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $currentRow++;

        // 5. ISI BARIS DATA
        $dataStartRow = $currentRow;
        foreach ($data['rows'] as $rowIdx => $rowValues) {
            $isEven = ($rowIdx % 2 === 0);
            $rowBgColor = $isEven ? 'FFFFFF' : 'FBFBFC';

            foreach ($rowValues as $colIdx => $val) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $cellCoordinate = "{$colLetter}{$currentRow}";

                $sheet->setCellValue($cellCoordinate, $val);

                // Format angka jika tipe currency/number
                $formatType = $data['formats'][$colIdx] ?? 'text';
                if ($formatType === 'currency') {
                    $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0');
                } elseif ($formatType === 'integer') {
                    $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0');
                } elseif ($formatType === 'percentage') {
                    $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('0.0%');
                }

                // Alignment
                $alignType = $data['alignments'][$colIdx] ?? 'left';
                $sheet->getStyle($cellCoordinate)->getAlignment()->setHorizontal(
                    match ($alignType) {
                        'center' => Alignment::HORIZONTAL_CENTER,
                        'right' => Alignment::HORIZONTAL_RIGHT,
                        default => Alignment::HORIZONTAL_LEFT,
                    }
                )->setVertical(Alignment::VERTICAL_CENTER);
            }

            // Border dan background baris data
            $rowRange = "A{$currentRow}:{$lastColumnLetter}{$currentRow}";
            $sheet->getStyle($rowRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rowBgColor);
            $sheet->getStyle($rowRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E4E7');
            $sheet->getRowDimension($currentRow)->setRowHeight(22);

            $currentRow++;
        }
        $dataEndRow = $currentRow - 1;

        // 6. BARIS TOTAL AKHIR (JIKA ADA)
        if (! empty($data['totals'])) {
            $totalRow = $currentRow;
            foreach ($data['totals'] as $colIdx => $totVal) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $cellCoord = "{$colLetter}{$totalRow}";

                $sheet->setCellValue($cellCoord, $totVal);

                $formatType = $data['formats'][$colIdx] ?? 'text';
                if ($formatType === 'currency' || $formatType === 'integer') {
                    if (is_numeric($totVal)) {
                        $sheet->getStyle($cellCoord)->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                $alignType = $data['alignments'][$colIdx] ?? 'left';
                $sheet->getStyle($cellCoord)->getAlignment()->setHorizontal(
                    match ($alignType) {
                        'center' => Alignment::HORIZONTAL_CENTER,
                        'right' => Alignment::HORIZONTAL_RIGHT,
                        default => Alignment::HORIZONTAL_LEFT,
                    }
                )->setVertical(Alignment::VERTICAL_CENTER);
            }

            // Styling baris TOTAL akuntansi (Bold, Background Highlight, Top Thin Border, Bottom Double Border)
            $totalRange = "A{$totalRow}:{$lastColumnLetter}{$totalRow}";
            $sheet->getStyle($totalRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '09090B'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F4F4F5'],
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'A1A1AA'],
                    ],
                    'bottom' => [
                        'borderStyle' => Border::BORDER_DOUBLE,
                        'color' => ['rgb' => '18181B'],
                    ],
                ],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(26);
            $currentRow++;
        }

        // 7. AUTO FIT LEBAR KOLOM
        for ($col = 1; $col <= $totalColumns; $col++) {
            $colLetter = $this->getColumnLetter($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Return Streamed Response XLSX
        return new StreamedResponse(
            function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    private function getColumnLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex > 0) {
            $modulo = ($colIndex - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $colIndex = (int) (($colIndex - $modulo) / 26);
        }

        return $letter;
    }
}
