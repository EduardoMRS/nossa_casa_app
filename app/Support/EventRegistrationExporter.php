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
            throw new RuntimeException('Unable to create an export file.');
        }

        $archive = new ZipArchive;

        if ($archive->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the spreadsheet archive.');
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
            throw new RuntimeException('Unable to read the spreadsheet export.');
        }

        return $contents;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function pdf(string $title, array $headers, array $rows, string $subtitle = ''): string
    {
        $lines = [strtoupper($title), $subtitle, str_repeat('=', 110), ''];

        if (count($rows) === 1) {
            foreach ($headers as $index => $header) {
                $lines[] = $header;
                $lines[] = '  '.($rows[0][$index] ?? '');
                $lines[] = str_repeat('-', 110);
            }
        } else {
            foreach ($rows as $rowIndex => $row) {
                $lines[] = '#'.($rowIndex + 1);

                foreach ($headers as $index => $header) {
                    $lines[] = $header.': '.($row[$index] ?? '');
                }

                $lines[] = str_repeat('-', 110);
            }
        }

        $wrappedLines = collect($lines)
            ->flatMap(fn (string $line): array => $line === '' ? [''] : explode("\n", wordwrap($line, 110)))
            ->all();
        $pages = array_chunk($wrappedLines, 52);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageIds = [];

        foreach ($pages as $index => $pageLines) {
            $pageId = 4 + ($index * 2);
            $contentId = $pageId + 1;
            $pageIds[] = $pageId;
            $content = "BT /F1 9 Tf 40 800 Td\n";

            foreach ($pageLines as $line) {
                $content .= '('.$this->pdfText($line).") Tj 0 -14 Td\n";
            }

            $content .= 'ET';
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($content).">>\nstream\n{$content}\nendstream";
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
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_keys($objects) as $id) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1).' /Root 1 0 R >>'."\nstartxref\n{$xrefOffset}\n%%EOF";
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
