<?php

use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures;

afterEach(fn () => WaldFixtures::cleanup());

function w5ExtensionWorkbook(string $extra, string $core = '<workbookPr date1904="0"/>'): string
{
    return WaldFixtures::xlsx([['rows' => WaldFixtures::table()]], ['xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:x15="http://schemas.microsoft.com/office/spreadsheetml/2010/11/main">'
        .$core.'<sheets><sheet name="Synthetic" sheetId="1" r:id="rId1"/></sheets>'.$extra.'</workbook>']);
}

it('W5-T01 ignores the precisely namespaced Excel extension without changing core properties', function (string $coreDate, string $extensionDate) {
    $extra = '<extLst><ext uri="synthetic"><x15:workbookPr chartTrackingRefBase="1" date1904="'.$extensionDate.'"/></ext></extLst>';
    $source = (new WorkbookSourceFactory)->open(w5ExtensionWorkbook($extra, '<workbookPr date1904="'.$coreDate.'"/>'), 'xlsx', new AnalysisBudget);
    try {
        $sheets = iterator_to_array($source->sheets());
        expect($source->metadata()['date_system'])->toBe($coreDate === '1' ? '1904' : '1900')
            ->and($source->metadata()['adapter_version'])->toBe('3')
            ->and($sheets)->toHaveCount(1)
            ->and($sheets[0]->cells[2][1]->rawValue)->toBe('001');
    } finally {
        $source->close();
    }
})->with([['0', '1'], ['1', '0']]);

it('W5-T01 preserves refusal of misplaced or spoofed workbook metadata', function (string $extra) {
    expect(fn () => (new WorkbookSourceFactory)->open(w5ExtensionWorkbook($extra), 'xlsx', new AnalysisBudget))->toThrow(AnalysisProblem::class);
})->with([
    'core in extension' => '<extLst><ext><workbookPr date1904="1"/></ext></extLst>',
    'wrong extension namespace' => '<extLst><ext><bad:workbookPr xmlns:bad="urn:untrusted"/></ext></extLst>',
    'wrong extension location' => '<extLst><x15:workbookPr/></extLst>',
    'extension at core location' => '<x15:workbookPr date1904="1"/>',
    'unknown namespace at core location' => '<bad:workbookPr xmlns:bad="urn:untrusted" date1904="1"/>',
    'spoofed parents' => '<extLst xmlns="urn:untrusted"><ext><x15:workbookPr/></ext></extLst>',
    'nested sheet injection' => '<extLst><ext><x15:workbookPr><sheet sheetId="2" r:id="rId1"/></x15:workbookPr></ext></extLst>',
    'nested core property injection' => '<extLst><ext><x15:workbookPr><workbookPr date1904="1"/></x15:workbookPr></ext></extLst>',
]);

it('W5-T01 still rejects DTD entities in extension-bearing metadata', function () {
    $path = w5ExtensionWorkbook('<extLst><ext><x15:workbookPr/></ext></extLst>');
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('xl/workbook.xml');
    $zip->addFromString('xl/workbook.xml', '<!DOCTYPE workbook [<!ENTITY injected "unsafe">]>'.$xml);
    $zip->close();
    expect(fn () => (new WorkbookSourceFactory)->open($path, 'xlsx', new AnalysisBudget))->toThrow(AnalysisProblem::class);
});
