<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExcelExportService
{
    /**
     * Palette warna profesional untuk styling spreadsheet.
     */
    private const PRIMARY_NAVY = '0F172A';      // Slate 900 (Header utama)
    private const ACCENT_GOLD = 'F59E0B';       // Amber 500 (Garis aksen elegan)
    private const KPI_BG = 'F8FAFC';            // Slate 50 (Background kartu ringkasan)
    private const KPI_BORDER = 'CBD5E1';        // Slate 300 (Border kartu ringkasan)
    private const KPI_HEADER_BG = 'E2E8F0';     // Slate 200
    private const ROW_EVEN = 'FFFFFF';          // Putih bersih
    private const ROW_ODD = 'F8FAFC';           // Slate 50 selang-seling halus
    private const BORDER_COLOR = 'E2E8F0';      // Slate 200
    private const TOTAL_BG = 'F1F5F9';          // Slate 100
    private const TOTAL_TEXT = '0F172A';        // Slate 900

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
        $sheet->setTitle(substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $data['title']) ?: 'Laporan', 0, 31));

        // 1. TAMPILAN HALAMAN (Gridlines & Freeze Panes)
        $sheet->setShowGridLines(true);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        // Font default Segoe UI yang modern, tajam, dan sangat mudah dibaca (ukuran 12)
        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(12);

        $currentRow = 1;
        $totalColumns = count($data['headers']);
        $lastColumnLetter = $this->getColumnLetter($totalColumns);
        $headerColor = ! empty($data['theme_color']) ? ltrim($data['theme_color'], '#') : self::PRIMARY_NAVY;

        // 2. HEADER BANNER JUDUL LAPORAN (Hero Banner)
        $sheet->mergeCells("A{$currentRow}:{$lastColumnLetter}{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", '  ' . strtoupper($data['title']));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => [
                'name' => 'Segoe UI',
                'size' => 16,
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $headerColor],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);
        $sheet->getRowDimension($currentRow)->setRowHeight(44);
        $currentRow++;

        // Garis aksen emas di bawah banner judul
        $sheet->mergeCells("A{$currentRow}:{$lastColumnLetter}{$currentRow}");
        $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::ACCENT_GOLD],
            ],
        ]);
        $sheet->getRowDimension($currentRow)->setRowHeight(4);
        $currentRow += 2; // Beri ruang bernapas

        // 3. KARTU METADATA LAPORAN (Informasi Dokumen)
        if (! empty($data['meta'])) {
            $metaStartRow = $currentRow;
            foreach ($data['meta'] as $label => $val) {
                $sheet->setCellValue("A{$currentRow}", $label);
                $sheet->setCellValue("B{$currentRow}", ': ' . $val);

                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '64748B'], 'size' => 11],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("B{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 12],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(24);
                $currentRow++;
            }
            $currentRow++; // Jeda setelah metadata
        }

        // 4. KARTU KPI RINGKASAN EKSEKUTIF (Executive Summary Box)
        if (! empty($data['kpi_summary'])) {
            $kpiHeaderRow = $currentRow;
            $sheet->mergeCells("A{$kpiHeaderRow}:B{$kpiHeaderRow}");
            $sheet->setCellValue("A{$kpiHeaderRow}", 'RINGKASAN EKSEKUTIF');
            $sheet->getStyle("A{$kpiHeaderRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '0F172A']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::KPI_HEADER_BG],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'indent' => 1,
                ],
            ]);
            $sheet->getRowDimension($kpiHeaderRow)->setRowHeight(28);
            $currentRow++;

            $kpiStartRow = $currentRow;
            foreach ($data['kpi_summary'] as $kpiLabel => $kpiValue) {
                $sheet->setCellValue("A{$currentRow}", '  ' . $kpiLabel);
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => ['color' => ['rgb' => '334155'], 'size' => 11.5],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->setCellValue("B{$currentRow}", $kpiValue);
                $sheet->getStyle("B{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 12],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => is_numeric($kpiValue) ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT,
                    ],
                ]);

                if (is_numeric($kpiValue)) {
                    $sheet->getStyle("B{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
                }
                $sheet->getRowDimension($currentRow)->setRowHeight(25);
                $currentRow++;
            }

            $kpiEndRow = $currentRow - 1;
            $sheet->getStyle("A{$kpiStartRow}:B{$kpiEndRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::KPI_BG],
                ],
            ]);

            // Border seluruh kartu KPI
            $sheet->getStyle("A{$kpiHeaderRow}:B{$kpiEndRow}")->applyFromArray([
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => self::KPI_BORDER],
                    ],
                    'inside' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => self::BORDER_COLOR],
                    ],
                ],
            ]);

            $currentRow++; // Baris jeda sebelum tabel utama
        }

        // 5. HEADER TABEL DATA
        $headerRow = $currentRow;
        foreach ($data['headers'] as $colIdx => $headerText) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $headerText);
        }

        $headerRange = "A{$headerRow}:{$lastColumnLetter}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $headerColor],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => false,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '334155'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => self::ACCENT_GOLD],
                ],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(34);

        // Freeze pane: kunci agar baris header tetap terlihat saat scroll ke bawah
        $sheet->freezePane('A' . ($headerRow + 1));
        $currentRow++;

        // 6. ISI BARIS DATA
        $dataStartRow = $currentRow;
        foreach ($data['rows'] as $rowIdx => $rowValues) {
            $isEven = ($rowIdx % 2 === 0);
            $rowBgColor = $isEven ? self::ROW_EVEN : self::ROW_ODD;

            foreach ($rowValues as $colIdx => $val) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $cellCoordinate = "{$colLetter}{$currentRow}";

                // Format penulisan angka & teks
                $formatType = $data['formats'][$colIdx] ?? 'text';
                if ($formatType === 'currency' || $formatType === 'integer') {
                    if (is_numeric($val)) {
                        $sheet->setCellValueExplicit($cellCoordinate, (float) $val, DataType::TYPE_NUMERIC);
                        $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0');
                    } else {
                        $sheet->setCellValue($cellCoordinate, $val);
                    }
                } elseif ($formatType === 'percentage') {
                    if (is_numeric($val)) {
                        $sheet->setCellValueExplicit($cellCoordinate, (float) $val, DataType::TYPE_NUMERIC);
                        $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('0.0%');
                    } else {
                        $sheet->setCellValue($cellCoordinate, $val);
                    }
                } else {
                    $sheet->setCellValue($cellCoordinate, $val);
                }

                // Alignment & Font Size 12
                $alignType = $data['alignments'][$colIdx] ?? 'left';
                $horizontalAlign = match ($alignType) {
                    'center' => Alignment::HORIZONTAL_CENTER,
                    'right' => Alignment::HORIZONTAL_RIGHT,
                    default => Alignment::HORIZONTAL_LEFT,
                };

                $sheet->getStyle($cellCoordinate)->getFont()->setSize(12);
                $sheet->getStyle($cellCoordinate)->getAlignment()
                    ->setHorizontal($horizontalAlign)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            // Styling zebra striping & border halus
            $rowRange = "A{$currentRow}:{$lastColumnLetter}{$currentRow}";
            $sheet->getStyle($rowRange)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $rowBgColor],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => self::BORDER_COLOR],
                    ],
                ],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(26);

            $currentRow++;
        }
        $dataEndRow = $currentRow - 1;

        // 7. BARIS TOTAL AKHIR (Grand Total)
        if (! empty($data['totals'])) {
            $totalRow = $currentRow;
            foreach ($data['totals'] as $colIdx => $totVal) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $cellCoord = "{$colLetter}{$totalRow}";

                $formatType = $data['formats'][$colIdx] ?? 'text';
                if ($formatType === 'currency' || $formatType === 'integer') {
                    if (is_numeric($totVal)) {
                        $sheet->setCellValueExplicit($cellCoord, (float) $totVal, DataType::TYPE_NUMERIC);
                        $sheet->getStyle($cellCoord)->getNumberFormat()->setFormatCode('#,##0');
                    } else {
                        $sheet->setCellValue($cellCoord, $totVal);
                    }
                } else {
                    $sheet->setCellValue($cellCoord, $totVal);
                }

                $alignType = $data['alignments'][$colIdx] ?? 'left';
                $horizontalAlign = match ($alignType) {
                    'center' => Alignment::HORIZONTAL_CENTER,
                    'right' => Alignment::HORIZONTAL_RIGHT,
                    default => Alignment::HORIZONTAL_LEFT,
                };

                $sheet->getStyle($cellCoord)->getAlignment()
                    ->setHorizontal($horizontalAlign)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            // Styling total akuntansi standar tinggi (font size 12)
            $totalRange = "A{$totalRow}:{$lastColumnLetter}{$totalRow}";
            $sheet->getStyle($totalRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => self::TOTAL_TEXT],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::TOTAL_BG],
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '94A3B8'],
                    ],
                    'bottom' => [
                        'borderStyle' => Border::BORDER_DOUBLE,
                        'color' => ['rgb' => '0F172A'],
                    ],
                    'inside' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => self::BORDER_COLOR],
                    ],
                ],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(32);
            $currentRow++;
        }

        // 8. AUTO-FIT LEBAR KOLOM DENGAN PADDING AMAN
        for ($col = 1; $col <= $totalColumns; $col++) {
            $colLetter = $this->getColumnLetter($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
        $sheet->calculateColumnWidths();

        // Tambahkan padding minimal (+3 karakter) agar angka/teks tidak mepet ke tepi border
        for ($col = 1; $col <= $totalColumns; $col++) {
            $colLetter = $this->getColumnLetter($col);
            $calculatedWidth = $sheet->getColumnDimension($colLetter)->getWidth();
            $sheet->getColumnDimension($colLetter)->setAutoSize(false);
            $sheet->getColumnDimension($colLetter)->setWidth(max(10, $calculatedWidth + 3.5));
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
        return Coordinate::stringFromColumnIndex($colIndex);
    }
}
