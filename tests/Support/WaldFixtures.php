<?php

namespace Tests\Support;

use App\Wald\Contracts\SourceRange;
use ZipArchive;

/** Synthetic corpus and deterministic mutations. Never use customer files here. */
final class WaldFixtures
{
    private static array $paths = [];

    public static function table(): array
    {
        return [1 => [1 => 'Reference', 2 => 'Category', 3 => 'Amount'], 2 => [1 => '001', 2 => 'Alpha', 3 => 10], 3 => [1 => 'A02', 2 => 'Beta', 3 => 20]];
    }

    public static function move(array $rows, int $rowOffset = 0, int $columnOffset = 0): array
    {
        $moved = [];
        foreach ($rows as $row => $cells) {
            foreach ($cells as $column => $value) {
                $moved[$row + $rowOffset][$column + $columnOffset] = $value;
            }
        }

        return $moved;
    }

    public static function insertColumn(array $rows, int $before): array
    {
        $result = [];
        foreach ($rows as $row => $cells) {
            foreach ($cells as $column => $value) {
                $result[$row][$column >= $before ? $column + 1 : $column] = $value;
            }
        }

        return $result;
    }

    public static function reorder(array $rows, array $order): array
    {
        $result = [];
        foreach ($rows as $row => $cells) {
            foreach ($order as $position => $column) {
                $result[$row][$position + 1] = $cells[$column] ?? '';
            }
        }

        return $result;
    }

    public static function xlsx(array $sheets, array $parts = []): string
    {
        $path = self::path('xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $sheetXml = $rels = '';
        foreach ($sheets as $index => $sheet) {
            $id = $index + 1;
            $sheetXml .= '<sheet sheetId="'.$id.'" name="'.self::escape($sheet['name'] ?? 'Data').'" state="'.($sheet['visibility'] ?? 'visible').'" r:id="rId'.$id.'"/>';
            $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
            $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
            if (isset($sheet['dimension'])) {
                $xml .= '<dimension ref="'.$sheet['dimension'].'"/>';
            }
            $xml .= '<cols>';
            foreach ($sheet['hidden_columns'] ?? [] as $column) {
                $xml .= '<col min="'.$column.'" max="'.$column.'" hidden="1"/>';
            }
            $xml .= '</cols><sheetData>';
            foreach ($sheet['rows'] as $row => $cells) {
                $xml .= '<row r="'.$row.'"'.(in_array($row, $sheet['hidden_rows'] ?? []) ? ' hidden="1"' : '').'>';
                foreach ($cells as $column => $value) {
                    $cell = SourceRange::columnLetters($column).$row;
                    $options = is_array($value) ? $value : ['value' => $value];
                    $value = $options['value'] ?? '';
                    $type = $options['type'] ?? (is_int($value) || is_float($value) ? 'n' : 'inlineStr');
                    $xml .= '<c r="'.$cell.'" t="'.$type.'" s="'.($options['style'] ?? 0).'">';
                    if (array_key_exists('formula', $options)) {
                        $xml .= '<f'.(isset($options['formula_type']) ? ' t="'.$options['formula_type'].'"' : '').'>'.self::escape($options['formula']).'</f>';
                    }
                    if ($type === 'inlineStr') {
                        $xml .= '<is><t>'.self::escape((string) $value).'</t></is>';
                    } elseif (! ($options['no_cache'] ?? false)) {
                        $xml .= '<v>'.self::escape((string) $value).'</v>';
                    }
                    $xml .= '</c>';
                }
                $xml .= '</row>';
            }
            $xml .= '</sheetData><mergeCells>';
            foreach ($sheet['merges'] ?? [] as $merge) {
                $xml .= '<mergeCell ref="'.$merge.'"/>';
            }
            $xml .= '</mergeCells></worksheet>';
            $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $xml);
        }
        $rels .= '<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        if (isset($parts['xl/sharedStrings.xml'])) {
            $rels .= '<Relationship Id="strings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        }
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr/><sheets>'.$sheetXml.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        $zip->addFromString('xl/styles.xml', '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font/><font><b/></font></fonts><cellXfs count="4"><xf numFmtId="0" fontId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1"/><xf numFmtId="14"/><xf numFmtId="10"/></cellXfs></styleSheet>');
        foreach ($parts as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    public static function file(string $contents, string $extension = 'csv'): string
    {
        $path = self::path($extension);
        file_put_contents($path, $contents);

        return $path;
    }

    public static function cleanup(): void
    {
        foreach (self::$paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        self::$paths = [];
    }

    private static function path(string $extension): string
    {
        $directory = dirname(__DIR__, 2).'/storage/framework/testing';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $path = $directory.'/wald-'.bin2hex(random_bytes(8)).'.'.$extension;
        self::$paths[] = $path;

        return $path;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
