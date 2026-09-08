<?php

namespace App\SourceImport\Semantics\Data;

use App\Wald\Contracts\CellObservation;
use InvalidArgumentException;
use JsonSerializable;

/** Private reader evidence, supplied by the trusted caller; not an authorisation token. */
final readonly class ObservedCell implements JsonSerializable
{
    public function __construct(
        public string $sourceChecksum,
        public string $sheetId,
        public CellObservation $cell,
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/D', $sourceChecksum) || $sheetId === ''
            || $cell->row < 1 || $cell->column < 1) {
            throw new InvalidArgumentException('invalid_observation_identity');
        }
    }

    public function jsonSerialize(): array
    {
        return ['source_checksum' => $this->sourceChecksum, 'sheet_id' => $this->sheetId,
            'row' => $this->cell->row, 'column' => $this->cell->column,
            'raw_value' => $this->cell->rawValue, 'type' => $this->cell->type,
            'formula' => $this->cell->formula, 'formula_metadata' => $this->cell->formulaMetadata,
            'style' => $this->cell->style, 'source' => $this->cell->source];
    }
}
