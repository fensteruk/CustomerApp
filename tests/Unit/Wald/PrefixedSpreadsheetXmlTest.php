<?php

use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures;

afterEach(fn () => WaldFixtures::cleanup());

function prefixedSyntheticWorkbook(string $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'): string
{
    $path = WaldFixtures::xlsx([['rows' => WaldFixtures::table()]]);
    $zip = new ZipArchive;
    $zip->open($path);
    foreach (['xl/worksheets/sheet1.xml', 'xl/styles.xml'] as $part) {
        $xml = $zip->getFromName($part);
        if ($xml === false) {
            continue;
        }
        $xml = str_replace('xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"', 'xmlns:x="'.$namespace.'"', $xml);
        $xml = preg_replace('~<(/?)([A-Za-z][A-Za-z0-9]*)(?=[\s/>])~', '<$1x:$2', $xml);
        $zip->addFromString($part, $xml);
    }
    $zip->close();

    return $path;
}

it('reads namespace-prefixed inline strings numeric values and styles without losing physical cells', function (): void {
    $source = (new WorkbookSourceFactory)->open(prefixedSyntheticWorkbook(), 'xlsx', new AnalysisBudget);
    try {
        $sheets = iterator_to_array($source->sheets());
        expect($sheets[0]->cells)->toHaveCount(3)
            ->and($sheets[0]->cells[1][1]->rawValue)->toBe('Reference')
            ->and($sheets[0]->cells[2][1]->rawValue)->toBe('001')
            ->and($sheets[0]->cells[2][3]->rawValue)->toBe('10')
            ->and($sheets[0]->cells[2][1]->source['cell'])->toBe('A2');
    } finally {
        $source->close();
    }
});

it('rejects a foreign namespace pretending to be spreadsheet cell ancestry', function (): void {
    expect(function (): void {
        $source = (new WorkbookSourceFactory)->open(prefixedSyntheticWorkbook('urn:untrusted'), 'xlsx', new AnalysisBudget);
        try {
            iterator_to_array($source->sheets());
        } finally {
            $source->close();
        }
    })->toThrow(AnalysisProblem::class);
});

it('preserves prefixed shared rich text formula metadata boolean and date styles without executing formulas', function (): void {
    $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $path = prefixedSyntheticWorkbook();
    $zip = new ZipArchive;
    $zip->open($path);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<x:worksheet xmlns:x="'.$namespace.'"><x:sheetData><x:row r="1">'
        .'<x:c r="A1" t="s"><x:v>0</x:v></x:c>'
        .'<x:c r="B1"><x:f t="shared" si="7" ref="B1:B2">1+2</x:f><x:v>3</x:v></x:c>'
        .'<x:c r="C1" t="b"><x:v>1</x:v></x:c>'
        .'<x:c r="D1" s="0"><x:v>46000</x:v></x:c>'
        .'</x:row></x:sheetData></x:worksheet>');
    $zip->addFromString('xl/styles.xml', '<x:styleSheet xmlns:x="'.$namespace.'"><x:fonts><x:font><x:b/></x:font></x:fonts>'
        .'<x:cellXfs><x:xf numFmtId="14" fontId="0" fillId="0" borderId="0"/></x:cellXfs></x:styleSheet>');
    // Inline this string table through a controlled workbook relationship.
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $zip->addFromString('xl/_rels/workbook.xml.rels', str_replace('</Relationships>', '<Relationship Id="rStrings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>', $rels));
    $zip->addFromString('xl/sharedStrings.xml', '<x:sst xmlns:x="'.$namespace.'"><x:si><x:r><x:t>00</x:t></x:r><x:r><x:t>1</x:t></x:r><x:rPh><x:t>not source text</x:t></x:rPh></x:si></x:sst>');
    $zip->close();
    $source = (new WorkbookSourceFactory)->open($path, 'xlsx', new AnalysisBudget);
    try {
        $cells = iterator_to_array($source->sheets())[0]->cells[1];
        expect($cells[1]->rawValue)->toBe('001')
            ->and($cells[2]->formula)->toBe('1+2')
            ->and($cells[2]->rawValue)->toBe('3')
            ->and($cells[2]->formulaMetadata)->toMatchArray(['kind' => 'shared', 'shared_index' => 7, 'range' => 'B1:B2', 'cached_value_available' => true])
            ->and($cells[3]->type)->toBe('boolean')
            ->and($cells[3]->rawValue)->toBe('1')
            ->and($cells[4]->rawValue)->toBe('46000')
            ->and($cells[4]->style)->toMatchArray(['number_format_id' => 14, 'bold' => true]);
    } finally {
        $source->close();
    }
});
