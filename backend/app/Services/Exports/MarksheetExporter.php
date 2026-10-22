<?php

namespace App\Services\Exports;

use App\Models\AcademicSession;
use App\Models\CourseUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Renders the marksheet — one session + course unit, the whole class,
 * never paginated — as a downloadable csv, xlsx or pdf. All three share
 * one column layout, so the printed sheet matches the spreadsheet.
 */
class MarksheetExporter
{
    /**
     * The flat header row every format shares: serial, admission number,
     * name, then the three theory papers with their average and the
     * three practical papers with theirs.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'S/N', 'ADM NO.', 'Name',
        'CAM 1', 'CAM 2', 'CAM 3', 'Theory Average',
        'PRACT 1', 'PRACT 2', 'PRACT 3', 'Practical Average',
    ];

    /**
     * Column widths in points for the pdf table (758pt inside A4
     * landscape margins).
     *
     * @var list<int>
     */
    private const PDF_WIDTHS = [32, 88, 160, 58, 58, 58, 64, 58, 58, 58, 66];

    /**
     * Build the attachment for one format. The rows are streamed, so a
     * class of any size never lands in memory as a finished file.
     *
     * @param  Collection<int, array{student_id: int, admission_no: ?string, name: ?string, theory: array<int, float|null>, theory_average: ?float, practical: array<int, float|null>, practical_average: ?float}>  $rows
     */
    public function download(string $format, AcademicSession $session, CourseUnit $courseUnit, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($format, $session, $courseUnit, $rows): void {
            match ($format) {
                'csv' => $this->csv($session, $courseUnit, $rows),
                'xlsx' => $this->xlsx($session, $courseUnit, $rows),
                'pdf' => $this->pdf($session, $courseUnit, $rows),
            };
        }, $this->filename($format, $session, $courseUnit), [
            'Content-Type' => match ($format) {
                'csv' => 'text/csv; charset=UTF-8',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pdf' => 'application/pdf',
            },
        ]);
    }

    /**
     * A utf-8 csv (with BOM so Excel decodes it) of the flat table.
     *
     * @param  Collection<int, array{...}>  $rows
     */
    private function csv(AcademicSession $session, CourseUnit $courseUnit, Collection $rows): void
    {
        $out = fopen('php://output', 'w');

        // BOM: without it Excel reads the names as mojibake.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::COLUMNS, ',', '"', '\\');

        foreach ($this->flatRows($rows) as $row) {
            fputcsv($out, $row, ',', '"', '\\');
        }

        fclose($out);
    }

    /**
     * A real .xlsx (inline strings, one styled sheet with a frozen
     * header) written through the zip extension and echoed out.
     *
     * @param  Collection<int, array{...}>  $rows
     */
    private function xlsx(AcademicSession $session, CourseUnit $courseUnit, Collection $rows): void
    {
        // ZipArchive writes in place, so reuse the tempnam file itself
        // (a `.xlsx`-suffixed sibling would leak the original behind).
        $path = tempnam(sys_get_temp_dir(), 'marksheet-');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::XLSX_CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::XLSX_ROOT_RELS);
        $zip->addFromString('xl/workbook.xml', self::XLSX_WORKBOOK);
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::XLSX_WORKBOOK_RELS);
        $zip->addFromString('xl/styles.xml', self::XLSX_STYLES);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet($session, $courseUnit, $rows));
        $zip->close();

        readfile($path);
        unlink($path);
    }

    /**
     * The sheet XML: a two-line title block, a bold header row and the
     * data with the average columns in red.
     *
     * @param  Collection<int, array{...}>  $rows
     */
    private function xlsxSheet(AcademicSession $session, CourseUnit $courseUnit, Collection $rows): string
    {
        $title = sprintf('Marksheet — %s %s', $courseUnit->unit?->code, $courseUnit->unit?->name);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<cols>'
            .'<col min="1" max="1" width="6" customWidth="1"/>'
            .'<col min="2" max="2" width="14" customWidth="1"/>'
            .'<col min="3" max="3" width="26" customWidth="1"/>'
            .'<col min="4" max="11" width="11" customWidth="1"/>'
            .'</cols>'
            .'<sheetData>'
            .$this->xlsxRow(1, [[['t' => 's', 'v' => $title, 'style' => 1]]])
            .$this->xlsxRow(2, [[['t' => 's', 'v' => $session->name, 'style' => 0]]])
            .$this->xlsxRow(4, array_map(
                fn (string $column): array => [['t' => 's', 'v' => $column, 'style' => 2]],
                self::COLUMNS,
            ));

        $rowNumber = 5;

        foreach ($this->flatRows($rows) as $flat) {
            $cells = [];

            foreach ($flat as $index => $value) {
                if ($value === null || $value === '') {
                    $cells[] = ['skip' => true];

                    continue;
                }

                $isAverage = $index === 6 || $index === 10;
                $cells[] = is_numeric($value) && ! is_string($value)
                    ? ['t' => 'n', 'v' => $value, 'style' => $isAverage ? 3 : 0]
                    : ['t' => 's', 'v' => (string) $value, 'style' => $isAverage ? 3 : 0];
            }

            $xml .= $this->xlsxRow($rowNumber, [$cells]);
            $rowNumber++;
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * One `<row>` of inline-string / numeric cells, column by column.
     *
     * @param  list<list<array{t?: string, v?: int|float|string, style?: int, skip?: bool}>>  $cellGroups
     */
    private function xlsxRow(int $rowNumber, array $cellGroups): string
    {
        $xml = "<row r=\"{$rowNumber}\">";
        $columnIndex = 0;

        foreach ($cellGroups as $cells) {
            foreach ($cells as $cell) {
                $reference = self::xlsxColumnLetter($columnIndex).$rowNumber;
                $style = $cell['style'] ?? 0;

                if ($cell['skip'] ?? false) {
                    $columnIndex++;

                    continue;
                }

                if (($cell['t'] ?? 's') === 'n') {
                    $xml .= "<c r=\"{$reference}\" s=\"{$style}\"><v>{$cell['v']}</v></c>";
                } else {
                    $value = htmlspecialchars((string) $cell['v'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xml .= "<c r=\"{$reference}\" s=\"{$style}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$value}</t></is></c>";
                }

                $columnIndex++;
            }
        }

        return $xml.'</row>';
    }

    /**
     * A landscape A4 pdf of the same table: grouped theory / practical
     * headers, red averages, rules between rows and a page footer.
     *
     * @param  Collection<int, array{...}>  $rows
     */
    private function pdf(AcademicSession $session, CourseUnit $courseUnit, Collection $rows): void
    {
        $margin = 36;
        $rowHeight = 16;
        $perChunk = 24;

        $chunks = $rows->chunk($perChunk)->values();
        $pageCount = max(1, $chunks->count());

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids ['.implode(' ', $this->pdfPageReferences($pageCount)).'] /Count '.$pageCount.' >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $x = $margin;
        $columnX = [$margin];

        foreach (self::PDF_WIDTHS as $width) {
            $x += $width;
            $columnX[] = $x;
        }

        foreach (range(0, $pageCount - 1) as $pageIndex) {
            $body = $this->pdfPageBody(
                $session,
                $courseUnit,
                $chunks->get($pageIndex, collect()),
                $pageIndex,
                $pageCount,
                $columnX,
                $rowHeight,
                $pageIndex * $perChunk,
            );

            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($pageIndex * 2 + 6).' 0 R >>';
            $objects[] = '<< /Length '.strlen($body)." >>\nstream\n".$body.'endstream';
        }

        echo $this->pdfSerialize($objects);
    }

    /**
     * One page's drawing commands: title block, both header rows, the
     * student rows with red averages, and the footer.
     *
     * @param  Collection<int, array{...}>  $chunk
     * @param  list<int>  $columnX  Left edge of every column, plus the table's right edge.
     */
    private function pdfPageBody(
        AcademicSession $session,
        CourseUnit $courseUnit,
        Collection $chunk,
        int $pageIndex,
        int $pageCount,
        array $columnX,
        int $rowHeight,
        int $rowOffset,
    ): string {
        $margin = 36;
        $top = 595 - $margin;
        $body = '';

        // Title block (compact running header from page 2 on).
        if ($pageIndex === 0) {
            $body .= $this->pdfText('MARKSHEET', $margin, $top - 14, 14, 'F2');
            $body .= $this->pdfText(
                sprintf('%s  %s', $courseUnit->unit?->code, $courseUnit->unit?->name),
                $margin,
                $top - 30,
                11,
                'F2',
            );
            $body .= $this->pdfText(
                sprintf('%s  ·  %s  ·  %s', $session->name, $courseUnit->course?->name, $courseUnit->curriculum?->name),
                $margin,
                $top - 44,
                10,
                'F1',
            );
        } else {
            $body .= $this->pdfText(
                sprintf('MARKSHEET — %s %s  ·  %s', $courseUnit->unit?->code, $courseUnit->unit?->name, $session->name),
                $margin,
                $top - 14,
                10,
                'F2',
            );
        }

        $headerTop = $pageIndex === 0 ? $top - 56 : $top - 26;
        $firstRowY = $headerTop - (2 * 18);

        // Gray band behind both header rows.
        $body .= sprintf(
            "0.92 g %.2f %.2f %.2f 36 re f 0 g\n",
            $margin,
            $firstRowY,
            $columnX[count($columnX) - 1] - $margin,
        );

        // Group headers: theory over its four columns, practical over its four.
        $body .= $this->pdfCentered('Theory Marks (100%)', $columnX[3], $columnX[7], $headerTop - 13, 9, 'F2');
        $body .= $this->pdfCentered('Practical Marks (100%)', $columnX[7], $columnX[11], $headerTop - 13, 9, 'F2');

        // Column headers; the first three sit across both rows.
        $labels = self::COLUMNS;

        foreach ($labels as $index => $label) {
            $y = $index < 3 ? $headerTop - 25 : $headerTop - 31;
            $body .= $this->pdfCentered($label, $columnX[$index], $columnX[$index + 1], $y, 8, 'F2');
        }

        // Student rows.
        $y = $firstRowY - $rowHeight;
        $rowIndex = 0;

        foreach ($chunk as $row) {
            $flat = $this->flatRow($row, $rowOffset + $rowIndex + 1);

            foreach ($flat as $index => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $isAverage = $index === 6 || $index === 10;
                $color = $isAverage ? '0.86 0.15 0.15 rg' : '0 0 0 rg';
                $body .= $color.' ';
                $body .= $this->pdfCentered(
                    (string) $value,
                    $columnX[$index],
                    $columnX[$index + 1],
                    $y,
                    9,
                    $isAverage ? 'F2' : 'F1',
                );
            }

            $body .= sprintf("0.85 G %.2f %.2f m %.2f %.2f l S\n", $margin, $y - 4, $columnX[count($columnX) - 1], $y - 4);

            $y -= $rowHeight;
            $rowIndex++;
        }

        // Footer: source line left, page number right.
        $body .= '0 0 0 rg '.$this->pdfText(
            'Printed from the SchoolMS marksheet',
            $margin,
            22,
            8,
            'F1',
        );
        $body .= $this->pdfRight(
            sprintf('Page %d of %d', $pageIndex + 1, $pageCount),
            $columnX[count($columnX) - 1],
            22,
            8,
            'F1',
        );

        return $body;
    }

    /**
     * Draw one run of text; `$x` is the left edge.
     */
    private function pdfText(string $text, float $x, float $y, float $size, string $font): string
    {
        return sprintf(
            "BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
            $font,
            $size,
            $x,
            $y,
            $this->pdfEscape($text),
        );
    }

    /**
     * Draw one run of text centered between two column edges.
     */
    private function pdfCentered(string $text, float $left, float $right, float $y, float $size, string $font): string
    {
        $width = $this->pdfWidth($text, $size, $font);
        $x = max($left + 2, ($left + $right - $width) / 2);

        return $this->pdfText($text, $x, $y, $size, $font);
    }

    /**
     * Draw one run of text ending at the right edge.
     */
    private function pdfRight(string $text, float $right, float $y, float $size, string $font): string
    {
        return $this->pdfText($text, $right - $this->pdfWidth($text, $size, $font), $y, $size, $font);
    }

    /**
     * Approximate a string's width in points: Helvetica digits are
     * 0.556em, the decimal point 0.278em, everything else 0.5em.
     */
    private function pdfWidth(string $text, float $size, string $font): float
    {
        // Measure the same WinAnsi bytes the PDF will draw, so an
        // accented name counts as one character, not two utf-8 bytes.
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        $width = 0.0;

        foreach (str_split($text) as $char) {
            $width += match (true) {
                $char >= '0' && $char <= '9' => 0.556,
                $char === '.' => 0.278,
                default => 0.5,
            };
        }

        // Bold Helvetica runs slightly wider.
        return $width * $size * ($font === 'F2' ? 1.05 : 1.0);
    }

    /**
     * WinAnsi-encode and escape a string for a pdf literal.
     */
    private function pdfEscape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    /**
     * Page object references for the page tree: page objects are 5, 7,
     * 9 … since each is followed by its content stream object.
     *
     * @return list<string>
     */
    private function pdfPageReferences(int $pageCount): array
    {
        return array_map(
            fn (int $index): string => ($index * 2 + 5).' 0 R',
            range(0, $pageCount - 1),
        );
    }

    /**
     * Assemble the pdf: header, numbered objects, xref table, trailer.
     *
     * @param  array<int, string>  $objects  Object bodies keyed by object number.
     */
    private function pdfSerialize(array $objects): string
    {
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf
            ."trailer\n<< /Size {$size} /Root 1 0 R >>\n"
            ."startxref\n{$xrefOffset}\n%%EOF\n";
    }

    /**
     * The shared flat row shape: serial number, admission number, name,
     * six papers and the two averages — nulls stay null so each format
     * renders its own blank.
     *
     * @param  Collection<int, array{...}>  $rows
     * @return list<array<int, string|int|float|null>>
     */
    private function flatRows(Collection $rows): array
    {
        return $rows
            ->map(fn (array $row, int $index): array => $this->flatRow($row, $index + 1))
            ->all();
    }

    /**
     * One student as a flat row.
     *
     * @param  array{admission_no: ?string, name: ?string, theory: array<int, float|null>, theory_average: ?float, practical: array<int, float|null>, practical_average: ?float}  $row
     * @return array<int, string|int|float|null>
     */
    private function flatRow(array $row, int $serial): array
    {
        return [
            $serial,
            $row['admission_no'],
            $row['name'],
            ...$row['theory'],
            $row['theory_average'],
            ...$row['practical'],
            $row['practical_average'],
        ];
    }

    /**
     * `marksheet-dfdt-1101-term-1-2026-2027.xlsx` and friends. Slashes
     * and commas in the session name ("Term 1, 2026/2027") become dashes
     * rather than vanishing, so the year range stays readable.
     */
    private function filename(string $format, AcademicSession $session, CourseUnit $courseUnit): string
    {
        return sprintf(
            'marksheet-%s-%s.%s',
            Str::slug($courseUnit->unit?->code ?? 'unit'),
            Str::slug(str_replace(['/', ','], ' ', $session->name)),
            $format,
        );
    }

    /**
     * Zero-based column index to the spreadsheet column letter.
     */
    private static function xlsxColumnLetter(int $index): string
    {
        return chr(65 + $index);
    }

    private const XLSX_CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        .'</Types>';

    private const XLSX_ROOT_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';

    private const XLSX_WORKBOOK = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Marksheet" sheetId="1" r:id="rId1"/></sheets>'
        .'</workbook>';

    private const XLSX_WORKBOOK_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .'</Relationships>';

    /**
     * Three fonts (plain, bold, red bold), one gray fill for headers;
     * cell styles 0 = plain, 1 = bold title, 2 = header, 3 = red average.
     */
    private const XLSX_STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="3">'
        .'<font><sz val="11"/><name val="Calibri"/></font>'
        .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
        .'<font><b/><color rgb="FFDC2626"/><sz val="11"/><name val="Calibri"/></font>'
        .'</fonts>'
        .'<fills count="3">'
        .'<fill><patternFill patternType="none"/></fill>'
        .'<fill><patternFill patternType="gray125"/></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFE5E7EB"/><bgColor indexed="64"/></patternFill></fill>'
        .'</fills>'
        .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="4">'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .'</cellXfs>'
        .'</styleSheet>';
}
