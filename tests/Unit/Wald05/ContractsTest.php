<?php

use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ReviewedWorkbookSelection;

it('orders by declared date then morning afternoon only', function () {
    $am = new ExportOrder('2026-09-08', 'MORNING');
    $pm = new ExportOrder('2026-09-08', 'AFTERNOON');
    $next = new ExportOrder('2026-09-09', 'MORNING');
    expect($am->compare($pm))->toBe(-1)->and($pm->compare($next))->toBe(-1)
        ->and($am->compare(new ExportOrder('2026-09-08', 'MORNING')))->toBe(0)
        ->and($next->compare($am))->toBe(1)->and(ExportOrder::PROVENANCE)->toBe('STAFF_DECLARED');
});

it('refuses malformed declared export ordering', function (string $date, string $slot) {
    expect(fn () => new ExportOrder($date, $slot))->toThrow(InvalidArgumentException::class);
})->with([['2026-02-30', 'MORNING'], ['2026-9-08', 'MORNING'], ['08/09/2026', 'MORNING'], ['2026-09-08', 'morning'], ['2026-09-08', 'EVENING'], ['', 'MORNING']]);

it('keeps DEC050 corrections scoped to exact reviewed bytes with original raw values', function () {
    $selection = new ReviewedWorkbookSelection;
    $hash = ReviewedWorkbookSelection::CHECKSUM;
    $corrected = $selection->treatment($hash, 'sheet-1', 2, 'synthetic-visit', 'Synthetic Site', 'CC!');
    expect($corrected['raw_call_type'])->toBe('CC!')->and($corrected['canonical_override'])->toBe('CC1')
        ->and($corrected['approvals'])->toBe(['DEC-050'])
        ->and($selection->treatment(str_repeat('a', 64), 'sheet-1', 2, 'synthetic-visit', 'Synthetic Site', 'CC!')['canonical_override'])->toBeNull();
    expect($selection->treatment($hash, 'sheet-1', 2, 'synthetic-visit', 'Synthetic Site', 'CM2')['excluded'])->toBeTrue()
        ->and($selection->treatment(str_repeat('a', 64), 'sheet-1', 2, 'synthetic-visit', 'Synthetic Site', 'CM2')['excluded'])->toBeFalse();
});

it('does not infer exclusions or conversions for ordinary call types and test-looking names', function (string $type) {
    $result = (new ReviewedWorkbookSelection)->treatment(ReviewedWorkbookSelection::CHECKSUM, 'sheet-1', 2, 'synthetic-visit', 'Synthetic TEST', $type);
    expect($result['excluded'])->toBeFalse()->and($result['canonical_override'])->toBeNull()->and($result['raw_call_type'])->toBe($type);
})->with(['PC1', 'CC1', 'CM1', 'CML', 'UNKNOWN']);

it('DEC051 excludes only the complete explicitly approved artifact and row identity tuple', function () {
    $selection = new ReviewedWorkbookSelection;
    $arguments = [ReviewedWorkbookSelection::CHECKSUM, 'sheet-1', 32, '5181', 'Nick TEST', 'PC1'];
    $result = $selection->treatment(...$arguments);
    expect($result['excluded'])->toBeTrue()->and($result['approvals'])->toBe(['DEC-051']);
    foreach ([0 => str_repeat('b', 64), 1 => 'sheet-2', 2 => 33, 3 => 'synthetic-other', 4 => 'Synthetic TEST'] as $index => $different) {
        $changed = $arguments;
        $changed[$index] = $different;
        expect($selection->treatment(...$changed)['excluded'])->toBeFalse();
    }
});
