<?php

namespace App\SourceImport\Integration;

use App\Wald\Contracts\WorkbookSource;
use LogicException;

/** Retain the exact guarded observations already consumed by the profiler, without rereading XML. */
final class CapturedWorkbookSource implements WorkbookSource
{
    private array $observations = [];

    private bool $consumed = false;

    public function __construct(private readonly WorkbookSource $source) {}

    public function metadata(): array
    {
        return $this->source->metadata();
    }

    public function sheets(): iterable
    {
        if ($this->consumed) {
            throw new LogicException('captured_workbook_already_consumed');
        }
        $this->consumed = true;
        foreach ($this->source->sheets() as $sheet) {
            $this->observations[] = $sheet;
            yield $sheet;
        }
    }

    public function observations(): array
    {
        return $this->observations;
    }

    public function close(): void
    {
        $this->source->close();
    }
}
