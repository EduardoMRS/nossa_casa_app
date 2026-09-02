<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class EventRegistrationExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function xlsx(array $headers, array $rows): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'event-registrations-');

        if ($temporaryPath === false) {
            throw new RuntimeException(__('admin.event_registrations.errors.create_export_file'));
        }

        $archive = new ZipArchive;

        if ($archive->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('admin.event_registrations.errors.create_spreadsheet_archive'));
        }

        $archive->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $archive->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $archive->addFromString('xl/workbook.xml', $this->workbookXml());
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml([$headers, ...$rows]));
        $archive->close();

        $contents = file_get_contents($temporaryPath);
        unlink($temporaryPath);

        if ($contents === false) {
            throw new RuntimeException(__('admin.event_registrations.errors.read_spreadsheet_export'));
        }

        return $contents;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $branding
     * @param  list<string>  $participantNames
     * @param  list<array{width?: int, break_before?: bool}>  $fieldLayout
     */
    public function pdf(
        string $title,
        array $headers,
        array $rows,
        string $subtitle = '',
        array $branding = [],
        array $participantNames = [],
        array $fieldLayout = [],
    ): string {
        $branding = array_merge([
            'name' => (string) config('app.name'),
            'tagline' => '',
            'primary_color' => '#342f87',
            'accent_color' => '#5eead4',
            'participant_label' => __('admin.event_registrations.pdf.participant'),
            'project_reference' => __('admin.event_registrations.pdf.project_reference'),
        ], $branding);
        $pageWidth = 595.0;
        $pageHeight = 842.0;

        return $this->document(
            $pageWidth,
            $pageHeight,
            $this->registrationPages(
                $pageWidth,
                $pageHeight,
                $title,
                $subtitle,
                $headers,
                $rows,
                $branding,
                $participantNames,
                count($rows) > 1,
                $fieldLayout,
            ),
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $branding
     * @param  list<string>  $participantNames
     * @param  list<array{width?: int, break_before?: bool}>  $fieldLayout
     * @return list<string>
     */
    private function registrationPages(
        float $pageWidth,
        float $pageHeight,
        string $title,
        string $subtitle,
        array $headers,
        array $rows,
        array $branding,
        array $participantNames,
        bool $showParticipantHeading,
        array $fieldLayout,
    ): array {
        $pages = [];
        $content = $this->pageHeader($pageWidth, $pageHeight, $title, $subtitle, $branding);
        $x = 36.0;
        $y = $pageHeight - 124.0;
        $bottom = 44.0;
        $contentWidth = $pageWidth - 72.0;
        $participantHeight = 30.0;
        $columnGap = 7.0;
        $rowGap = 9.0;
        $sectionGap = 12.0;
        $primary = $this->color($branding['primary_color']);
        $accent = $this->color($branding['accent_color']);

        if ($rows === []) {
            return [$content.$this->text('-', $x + 10, $y - 20, 9, false, [0.42, 0.46, 0.54]).$this->pageFooter($pageWidth, $branding)];
        }

        foreach ($rows as $participantIndex => $row) {
            $remainingRows = $this->formRows($headers, $row, $fieldLayout);

            do {
                $headingSpace = $showParticipantHeading ? $participantHeight + $rowGap : 0.0;
                $firstRowHeight = (float) collect($remainingRows[0] ?? [])->max('height');

                if ($y - $bottom < $headingSpace + $firstRowHeight) {
                    $pages[] = $content.$this->pageFooter($pageWidth, $branding);
                    $content = $this->pageHeader($pageWidth, $pageHeight, $title, $subtitle, $branding);
                    $y = $pageHeight - 124.0;
                }

                if ($showParticipantHeading) {
                    $participantLabel = str_replace(':number', (string) ($participantIndex + 1), $branding['participant_label']);
                    $participantName = trim((string) ($participantNames[$participantIndex] ?? ''));

                    if ($participantName !== '') {
                        $participantLabel .= ' - '.$participantName;
                    }

                    $content .= $this->rect($x, $y - $participantHeight, $contentWidth, $participantHeight, [0.94, 0.95, 0.98], [0.82, 0.84, 0.9]);
                    $content .= $this->rect($x, $y - $participantHeight, 4, $participantHeight, $accent);
                    $content .= $this->text($this->fit($participantLabel, 76), $x + 14, $y - 20, 10, true, $primary);
                    $y -= $participantHeight + $rowGap;
                }

                $renderedRow = false;

                while ($remainingRows !== []) {
                    $formRow = $remainingRows[0];
                    $rowHeight = (float) collect($formRow)->max('height');

                    if ($y - $rowHeight < $bottom && $renderedRow) {
                        break;
                    }

                    array_shift($remainingRows);
                    $usedColumns = 0;
                    $unitWidth = ($contentWidth - (11 * $columnGap)) / 12;

                    foreach ($formRow as $field) {
                        $width = $field['width'];
                        $fieldX = $x + ($usedColumns * ($unitWidth + $columnGap));
                        $fieldWidth = ($width * $unitWidth) + (($width - 1) * $columnGap);
                        $content .= $this->rect($fieldX, $y - $rowHeight, $fieldWidth, $rowHeight, [0.975, 0.98, 0.99], [0.86, 0.88, 0.92]);
                        $content .= $this->rect($fieldX, $y - $rowHeight, 3, $rowHeight, $accent);
                        $content .= $this->text(
                            $this->fit($field['label'], max(8, $width * 7)),
                            $fieldX + 10,
                            $y - 15,
                            8,
                            true,
                            $primary,
                        );

                        foreach ($field['lines'] as $lineIndex => $line) {
                            $content .= $this->text(
                                $line,
                                $fieldX + 10,
                                $y - 31 - ($lineIndex * 11),
                                9,
                                false,
                                [0.11, 0.13, 0.18],
                            );
                        }

                        $usedColumns += $width;
                    }

                    $y -= $rowHeight + $rowGap;
                    $renderedRow = true;
                }

                $y -= $sectionGap;

                if ($remainingRows !== []) {
                    $pages[] = $content.$this->pageFooter($pageWidth, $branding);
                    $content = $this->pageHeader($pageWidth, $pageHeight, $title, $subtitle, $branding);
                    $y = $pageHeight - 124.0;
                }
            } while ($remainingRows !== []);
        }

        $pages[] = $content.$this->pageFooter($pageWidth, $branding);

        return $pages;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $row
     * @param  list<array{width?: int, break_before?: bool}>  $fieldLayout
     * @return list<list<array{label: string, lines: list<string>, width: int, height: float}>>
     */
    private function formRows(array $headers, array $row, array $fieldLayout): array
    {
        $rows = [];
        $currentRow = [];
        $usedColumns = 0;

        foreach (array_keys($headers) as $index) {
            $layout = $fieldLayout[$index] ?? [];
            $width = max(1, min(12, (int) ($layout['width'] ?? 12)));
            $breakBefore = (bool) ($layout['break_before'] ?? false);

            if ($currentRow !== [] && ($breakBefore || $usedColumns + $width > 12)) {
                $rows[] = $currentRow;
                $currentRow = [];
                $usedColumns = 0;
            }

            $characterWidth = max(12, ($width * 7) - 2);
            $lines = $this->wrappedLines((string) ($row[$index] ?? ''), $characterWidth, 4);
            $currentRow[] = [
                'label' => (string) ($headers[$index] ?? ''),
                'lines' => $lines,
                'width' => $width,
                'height' => max(46.0, 34.0 + (count($lines) * 11.0)),
            ];
            $usedColumns += $width;

            if ($usedColumns === 12) {
                $rows[] = $currentRow;
                $currentRow = [];
                $usedColumns = 0;
            }
        }

        if ($currentRow !== []) {
            $rows[] = $currentRow;
        }

        return $rows;
    }

    /** @return list<string> */
    private function wrappedLines(string $value, int $lineLength, int $maxLines): array
    {
        $remaining = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if ($remaining === '') {
            return ['-'];
        }

        $lines = [];

        while ($remaining !== '' && count($lines) < $maxLines) {
            if (mb_strlen($remaining) <= $lineLength) {
                $lines[] = $remaining;

                break;
            }

            if (count($lines) === $maxLines - 1) {
                $lines[] = $this->fit($remaining, $lineLength);

                break;
            }

            $candidate = mb_substr($remaining, 0, $lineLength + 1);
            $breakAt = mb_strrpos($candidate, ' ');

            if ($breakAt === false || $breakAt < (int) floor($lineLength / 2)) {
                $breakAt = $lineLength;
            }

            $lines[] = trim(mb_substr($remaining, 0, $breakAt));
            $remaining = ltrim(mb_substr($remaining, $breakAt));
        }

        return $lines;
    }

    /** @param array<string, string> $branding */
    private function pageHeader(
        float $pageWidth,
        float $pageHeight,
        string $title,
        string $subtitle,
        array $branding,
    ): string {
        $primary = $this->color($branding['primary_color']);
        $accent = $this->color($branding['accent_color']);
        $content = $this->rect(0, $pageHeight - 102, $pageWidth, 102, $primary);
        $content .= $this->rect(0, $pageHeight - 106, $pageWidth, 4, $accent);
        $content .= $this->rect(36, $pageHeight - 58, 34, 34, $accent);
        $content .= $this->text($this->initials($branding['name']), 43, $pageHeight - 46, 11, true, $primary);
        $content .= $this->text($this->fit($branding['name'], 42), 80, $pageHeight - 37, 15, true, [1, 1, 1]);

        if ($branding['tagline'] !== '') {
            $content .= $this->text($this->fit($branding['tagline'], 70), 80, $pageHeight - 52, 7.5, false, [0.88, 0.9, 0.96]);
        }

        $content .= $this->text($this->fit($title, 85), 36, $pageHeight - 76, 13, true, [1, 1, 1]);

        if ($subtitle !== '') {
            $content .= $this->text($this->fit($subtitle, 110), 36, $pageHeight - 91, 8, false, [0.88, 0.9, 0.96]);
        }

        return $content;
    }

    /** @param array<string, string> $branding */
    private function pageFooter(float $pageWidth, array $branding): string
    {
        $reference = $this->fit($branding['project_reference'], 90);
        $estimatedWidth = strlen($reference) * 3.3;
        $x = max(36.0, ($pageWidth - $estimatedWidth) / 2);

        return $this->text($reference, $x, 22, 6.5, false, [0.55, 0.58, 0.64]);
    }

    /** @param list<string> $pageStreams */
    private function document(float $pageWidth, float $pageHeight, array $pageStreams): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $pageIds = [];

        foreach ($pageStreams as $index => $stream) {
            $pageId = 5 + ($index * 2);
            $contentId = $pageId + 1;
            $pageIds[] = $pageId;
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $id): string => "{$id} 0 R", $pageIds)).'] /Count '.count($pageIds).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_keys($objects) as $id) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $fill
     * @param  array{0: float, 1: float, 2: float}|null  $stroke
     */
    private function rect(
        float $x,
        float $y,
        float $width,
        float $height,
        array $fill,
        ?array $stroke = null,
    ): string {
        $operator = $stroke === null ? 'f' : 'B';
        $command = "q {$fill[0]} {$fill[1]} {$fill[2]} rg ";

        if ($stroke !== null) {
            $command .= "{$stroke[0]} {$stroke[1]} {$stroke[2]} RG 0.5 w ";
        }

        return $command."{$x} {$y} {$width} {$height} re {$operator} Q\n";
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $color
     */
    private function text(
        string $value,
        float $x,
        float $y,
        float $size,
        bool $bold,
        array $color,
    ): string {
        $font = $bold ? 'F2' : 'F1';

        return "BT /{$font} {$size} Tf {$color[0]} {$color[1]} {$color[2]} rg {$x} {$y} Td (".$this->pdfText($value).") Tj ET\n";
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function color(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '342f87';
        }

        return [
            round(hexdec(substr($hex, 0, 2)) / 255, 3),
            round(hexdec(substr($hex, 2, 2)) / 255, 3),
            round(hexdec(substr($hex, 4, 2)) / 255, 3),
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(collect($parts)->take(2)->map(fn (string $part): string => mb_substr($part, 0, 1))->implode(''));
    }

    private function fit(string $value, int $length): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return mb_strlen($value) > $length
            ? mb_substr($value, 0, max(1, $length - 1)).'...'
            : $value;
    }

    /** @param list<list<string>> $rows */
    private function worksheetXml(array $rows): string
    {
        $sheetRows = collect($rows)->map(function (array $row, int $rowIndex): string {
            $cells = collect($row)->map(function (string $value, int $columnIndex) use ($rowIndex): string {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);

                return '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($value).'</t></is></c>';
            })->implode('');

            return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$sheetRows.'</sheetData></worksheet>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Registrations" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function pdfText(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }
}
