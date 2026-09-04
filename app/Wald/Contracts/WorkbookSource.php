<?php

namespace App\Wald\Contracts;

interface WorkbookSource
{
    public function metadata(): array;

    /** @return iterable<SheetObservation> One sparse sheet at a time. */
    public function sheets(): iterable;

    public function close(): void;
}
