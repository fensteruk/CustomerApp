<?php

namespace App\Wald\Services;

use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;
use App\Wald\Contracts\WorkbookSource;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

final class XlsxWorkbookSource implements WorkbookSource
{
    private ZipArchive $zip;

    private array $sheetDefinitions = [];

    private array $strings = [];

    private array $styles = [];

    private array $warnings = [];

    private string $dateSystem = '1900';

    private bool $closed = false;

    public function __construct(string $path, private readonly AnalysisBudget $budget)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new AnalysisProblem('corrupt_archive');
        }
        try {
            $this->guardArchive();
            $relationships = [];
            foreach ($this->nodes('xl/_rels/workbook.xml.rels', ['Relationship'], root: 'Relationships') as $node) {
                if ((string) $node['TargetMode'] === 'External') {
                    continue;
                }
                $target = (string) $node['Target'];
                $relationships[(string) $node['Id']] = [
                    'type' => basename((string) $node['Type']),
                    'path' => str_starts_with($target, '/') ? substr($target, 1) : 'xl/'.$target,
                ];
            }
            foreach ($this->nodes('xl/workbook.xml', ['workbookPr', 'sheet'], root: 'workbook') as $node) {
                if ($node->getName() === 'workbookPr') {
                    $this->dateSystem = in_array((string) $node['date1904'], ['1', 'true'], true) ? '1904' : '1900';

                    continue;
                }
                $id = (string) $node['sheetId'];
                $rid = '';
                foreach ($node->getDocNamespaces(true) as $namespace) {
                    if (str_ends_with($namespace, '/relationships')) {
                        $rid = (string) $node->attributes($namespace)['id'];
                    }
                }
                $relationship = $relationships[$rid] ?? null;
                if ($id === '' || $relationship === null || $relationship['type'] !== 'worksheet') {
                    throw new AnalysisProblem('unsupported_sheet_type');
                }
                if (isset($this->sheetDefinitions[$id])) {
                    throw new AnalysisProblem('invalid_workbook');
                }
                $this->sheetDefinitions[$id] = ['id' => 'sheet-'.$id, 'name' => (string) $node['name'], 'visibility' => (string) ($node['state'] ?? 'visible'), 'path' => $relationship['path']];
                $budget->guard('sheets', count($this->sheetDefinitions));
            }
            if ($this->sheetDefinitions === []) {
                throw new AnalysisProblem('invalid_workbook');
            }
            foreach ($relationships as $relationship) {
                if ($relationship['type'] === 'sharedStrings') {
                    $bytes = 0;
                    foreach ($this->nodes($relationship['path'], ['si'], root: 'sst') as $node) {
                        $value = $this->text($node);
                        $bytes += strlen($value);
                        $budget->guard('strings', count($this->strings) + 1);
                        $budget->guard('string_bytes', $bytes);
                        $budget->guard('cell_bytes', strlen($value));
                        $this->strings[] = $value;
                    }
                } elseif ($relationship['type'] === 'styles') {
                    $this->readStyles($relationship['path']);
                }
            }
        } catch (\Throwable $exception) {
            $this->close();
            throw $exception;
        }
    }

    public function metadata(): array
    {
        return ['adapter' => 'wald_sparse_ooxml', 'adapter_version' => '2', 'format' => 'xlsx', 'date_system' => $this->dateSystem,
            'capabilities' => ['physical_cells' => true, 'formulas' => true, 'cached_values' => true, 'merges' => true, 'visibility' => true, 'basic_styles' => true, 'comments' => false, 'display_rendering' => false],
            'warnings' => array_values(array_unique($this->warnings)),
        ];
    }

    public function sheets(): iterable
    {
        $position = 0;
        foreach ($this->sheetDefinitions as $definition) {
            $cells = $merges = $hiddenRows = $hiddenColumns = $warnings = [];
            $declared = null;
            $rowNumber = 0;
            $columnNumber = 0;
            $seenRows = [];
            foreach ($this->nodes($definition['path'], ['dimension', 'row', 'c', 'mergeCell', 'col'], ['row'], 'worksheet') as $node) {
                switch ($node->getName()) {
                    case 'dimension':
                        $declared = SourceRange::parse((string) $node['ref'])->address();
                        break;
                    case 'row':
                        $rowNumber = isset($node['r']) ? (int) $node['r'] : $rowNumber + 1;
                        SourceRange::parse('A'.$rowNumber);
                        if (isset($seenRows[$rowNumber])) {
                            throw new AnalysisProblem('duplicate_cell_reference');
                        }
                        $seenRows[$rowNumber] = true;
                        $columnNumber = 0;
                        if (in_array((string) $node['hidden'], ['1', 'true'], true)) {
                            $hiddenRows[] = $rowNumber;
                        }
                        // Count physical row elements too, including formatting-only rows.
                        $this->budget->guard('rows', count($seenRows));
                        break;
                    case 'col':
                        $min = (int) $node['min'];
                        $max = (int) $node['max'];
                        if ($min < 1 || $max < $min || $max > 16384) {
                            throw new AnalysisProblem('invalid_cell_reference');
                        }
                        if (in_array((string) $node['hidden'], ['1', 'true'], true)) {
                            $hiddenColumns[] = ['start' => $min, 'end' => $max];
                        }
                        $this->budget->guard('styles', count($hiddenColumns));
                        break;
                    case 'mergeCell':
                        $merges[] = SourceRange::parse((string) $node['ref']);
                        $this->budget->guard('merges', count($merges));
                        break;
                    case 'c':
                        if ($rowNumber < 1) {
                            throw new AnalysisProblem('invalid_cell_reference');
                        }
                        $ref = isset($node['r']) ? SourceRange::parse((string) $node['r']) : new SourceRange($rowNumber, $rowNumber, $columnNumber + 1, $columnNumber + 1);
                        if ($ref->startRow !== $rowNumber || $ref->startRow !== $ref->endRow || $ref->startColumn !== $ref->endColumn || $ref->startColumn <= $columnNumber) {
                            throw new AnalysisProblem('invalid_cell_reference');
                        }
                        $columnNumber = $ref->startColumn;
                        $this->budget->cell();
                        $cell = $this->cell($node, $rowNumber, $columnNumber);
                        if ($cell->hasContent()) {
                            $this->budget->guard('columns', $columnNumber);
                            $cells[$rowNumber][$columnNumber] = $cell;
                        }
                        if ($cell->formula !== null && $cell->formulaMetadata['kind'] !== 'normal') {
                            $warnings['formula_context_required'] = 'formula_context_required';
                        }
                        break;
                }
            }
            ksort($cells);
            sort($hiddenRows);
            usort($merges, fn ($a, $b) => [$a->startRow, $a->startColumn, $a->endRow, $a->endColumn] <=> [$b->startRow, $b->startColumn, $b->endRow, $b->endColumn]);
            yield new SheetObservation($definition['id'], $definition['name'], ++$position, $definition['visibility'], $declared, $cells, $merges, $hiddenRows, $hiddenColumns, array_values($warnings), ['date_system' => $this->dateSystem, 'comments_available' => false]);
            unset($cells);
        }
    }

    public function close(): void
    {
        if (! $this->closed) {
            $this->zip->close();
            $this->closed = true;
        }
    }

    private function cell(SimpleXMLElement $node, int $row, int $column): CellObservation
    {
        $type = (string) ($node['t'] ?? 'n');
        $raw = (string) ($node->v ?? '');
        if ($type === 's') {
            if (! ctype_digit($raw) || ! array_key_exists((int) $raw, $this->strings)) {
                throw new AnalysisProblem('invalid_shared_string');
            }
            $raw = $this->strings[(int) $raw];
        } elseif ($type === 'inlineStr') {
            $raw = $this->text($node->is ?? new SimpleXMLElement('<is/>'));
        }
        $formula = isset($node->f) ? (string) $node->f : null;
        $this->budget->guard('cell_bytes', strlen($raw) + strlen($formula ?? ''));
        $styleId = (int) ($node['s'] ?? 0);

        return new CellObservation($row, $column, $raw, match ($type) {
            'n' => 'number', 'b' => 'boolean', 'e' => 'error', 'd' => 'date', default => 'text',
        }, $formula, $this->styles[$styleId] ?? [], ['cell' => SourceRange::columnLetters($column).$row], $formula === null ? [] : [
            'kind' => (string) ($node->f['t'] ?? 'normal'), 'shared_index' => isset($node->f['si']) ? (int) $node->f['si'] : null,
            'range' => isset($node->f['ref']) ? SourceRange::parse((string) $node->f['ref'])->address() : null,
            'cached_value_available' => isset($node->v), 'cached_value_freshness' => 'unknown',
        ]);
    }

    private function guardArchive(): void
    {
        $this->budget->guard('entries', $this->zip->numFiles);
        $size = 0;
        $names = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            if ($stat === false) {
                throw new AnalysisProblem('corrupt_archive');
            }
            $name = strtolower($stat['name']);
            if (isset($names[$name]) || preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|:|\x00)~', $name)
                || str_contains($name, 'vbaproject') || str_ends_with($name, '.bin') || str_contains($name, 'macrosheet') || str_contains($name, 'activex') || str_contains($name, 'embeddings/') || ($stat['encryption_method'] ?? 0) !== 0) {
                throw new AnalysisProblem('unsafe_archive');
            }
            $names[$name] = true;
            $size += (int) $stat['size'];
            $this->budget->guard('entry_bytes', (int) $stat['size']);
            $this->budget->guard('archive_bytes', $size);
            if (str_contains($name, 'comments')) {
                $this->warnings[] = 'comments_present_not_read';
            }
            if (str_contains($name, 'externallinks/') || str_ends_with($name, 'connections.xml')) {
                $this->warnings[] = 'external_data_not_fetched';
            }
        }
        foreach ($this->nodes('[Content_Types].xml', ['Override', 'Default'], root: 'Types') as $node) {
            if (preg_match('/macroenabled|macrosheet|vba|activex/i', (string) $node['ContentType'])) {
                throw new AnalysisProblem('unsafe_archive');
            }
        }
        foreach (array_keys($names) as $name) {
            if (str_ends_with($name, '.rels')) {
                // Locate original case without guessing external targets or opening them.
                $index = $this->zip->locateName($name, ZipArchive::FL_NOCASE);
                $relationshipIds = [];
                foreach ($this->nodes($this->zip->getNameIndex($index), ['Relationship'], root: 'Relationships') as $node) {
                    $id = (string) $node['Id'];
                    if ($id === '' || isset($relationshipIds[$id])) {
                        throw new AnalysisProblem('invalid_workbook');
                    }
                    $relationshipIds[$id] = true;
                    if ((string) $node['TargetMode'] === 'External') {
                        $this->warnings[] = 'external_data_not_fetched';
                    }
                }
            }
        }
    }

    /** @return iterable<SimpleXMLElement> XML nodes never escape this adapter. */
    private function nodes(string $part, array $wanted, array $attributesOnly = [], ?string $root = null): iterable
    {
        if (preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|:|\x00)~', $part)) {
            throw new AnalysisProblem('unsafe_archive');
        }
        $stat = $this->zip->statName($part);
        if ($stat === false) {
            throw new AnalysisProblem('missing_workbook_part');
        }
        $this->budget->reserve((int) $stat['size'] * 3);
        $xml = $this->zip->getFromName($part);
        if ($xml === false) {
            throw new AnalysisProblem('missing_workbook_part');
        }
        if ($xml === '') {
            throw new AnalysisProblem('invalid_xml');
        }
        $this->budget->guard('entry_bytes', strlen($xml));
        if (str_contains($xml, "\0") || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
            throw new AnalysisProblem('unsafe_xml');
        }
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new XMLReader;
        $ancestors = [];
        try {
            if (! $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new AnalysisProblem('invalid_xml');
            }
            $reader->setParserProperty(XMLReader::LOADDTD, false);
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
            while ($reader->read()) {
                $this->budget->checkpoint();
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === 0 && $root !== null && $reader->localName !== $root) {
                    throw new AnalysisProblem('invalid_xml');
                }
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    $ancestors = array_slice($ancestors, 0, $reader->depth);
                    $ancestors[] = $reader->localName;
                }
                if ($reader->nodeType !== XMLReader::ELEMENT || ! in_array($reader->localName, $wanted, true)) {
                    continue;
                }
                // Local tag names alone cannot establish physical workbook lineage.
                $expectedPath = match ($root) {
                    'worksheet' => match ($reader->localName) {
                        'dimension' => ['worksheet', 'dimension'],
                        'row' => ['worksheet', 'sheetData', 'row'],
                        'c' => ['worksheet', 'sheetData', 'row', 'c'],
                        'mergeCell' => ['worksheet', 'mergeCells', 'mergeCell'],
                        'col' => ['worksheet', 'cols', 'col'],
                    },
                    'Relationships' => ['Relationships', 'Relationship'],
                    'Types' => ['Types', $reader->localName],
                    'workbook' => $reader->localName === 'sheet' ? ['workbook', 'sheets', 'sheet'] : ['workbook', 'workbookPr'],
                    'sst' => ['sst', 'si'],
                    default => null,
                };
                if ($expectedPath !== null && $ancestors !== $expectedPath) {
                    throw new AnalysisProblem('invalid_xml');
                }
                if (in_array($reader->localName, $attributesOnly, true)) {
                    $node = new SimpleXMLElement('<'.$reader->localName.'/>');
                    if ($reader->moveToFirstAttribute()) {
                        do {
                            $node->addAttribute($reader->localName, $reader->value);
                        } while ($reader->moveToNextAttribute());
                        $reader->moveToElement();
                    }
                } else {
                    $fragment = $reader->readOuterXml();
                    $this->budget->guard('node_bytes', strlen($fragment));
                    $this->budget->reserve(strlen($fragment) * 8);
                    $node = simplexml_load_string($fragment, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
                    if ($node === false) {
                        throw new AnalysisProblem('invalid_xml');
                    }
                }
                yield $node;
            }
            if (libxml_get_errors() !== []) {
                throw new AnalysisProblem('invalid_xml');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $node): string
    {
        // Excludes phonetic annotations; concatenates the actual rich-text runs only.
        return implode('', array_map(fn ($text) => (string) $text, $node->xpath('./*[local-name()="t"] | ./*[local-name()="r"]/*[local-name()="t"]') ?: []));
    }

    private function readStyles(string $part): void
    {
        $formats = [0 => 'General', 9 => '0%', 10 => '0.00%', 14 => 'mm-dd-yy', 15 => 'd-mmm-yy', 16 => 'd-mmm', 17 => 'mmm-yy', 22 => 'm/d/yy h:mm'];
        $fonts = [];
        foreach ($this->nodes($part, ['numFmt', 'fonts', 'cellXfs'], root: 'styleSheet') as $node) {
            if ($node->getName() === 'numFmt') {
                $formats[(int) $node['numFmtId']] = (string) $node['formatCode'];
                $this->budget->guard('styles', count($formats));
            } elseif ($node->getName() === 'fonts') {
                foreach ($node->font as $font) {
                    $fonts[] = isset($font->b) && ! in_array((string) $font->b['val'], ['0', 'false'], true);
                    $this->budget->guard('styles', count($fonts));
                }
            } else {
                foreach ($node->xf as $style) {
                    $id = (int) ($style['numFmtId'] ?? 0);
                    $this->styles[] = ['style_id' => count($this->styles), 'number_format_id' => $id, 'number_format' => $formats[$id] ?? null, 'bold' => $fonts[(int) $style['fontId']] ?? false, 'fill_id' => (int) $style['fillId'], 'border_id' => (int) $style['borderId']];
                    $this->budget->guard('styles', count($this->styles));
                }
            }
        }
    }
}
