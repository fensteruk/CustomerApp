<?php

namespace App\SourceImport\Integration;

final readonly class ExportOrder
{
    public const PROVENANCE = 'STAFF_DECLARED';

    public const CONFIRMATION = 'I confirm this is the latest RedZebra export available for this slot.';

    public function __construct(public string $date, public string $slot)
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (! $parsed || $parsed->format('Y-m-d') !== $date || ! in_array($slot, ['MORNING', 'AFTERNOON'], true)) {
            throw new \InvalidArgumentException('invalid_export_order');
        }
    }

    public function key(): string
    {
        return $this->date.($this->slot === 'MORNING' ? ':0' : ':1');
    }

    public function compare(self $other): int
    {
        return $this->key() <=> $other->key();
    }
}
