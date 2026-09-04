<?php

namespace App\Wald\Contracts;

use App\Wald\Services\AnalysisProblem;

final readonly class SourceRange
{
    public function __construct(public int $startRow, public int $endRow, public int $startColumn, public int $endColumn) {}

    public static function parse(string $reference): self
    {
        if (! preg_match('/^\$?([A-Z]{1,3})\$?([1-9][0-9]*)(?::\$?([A-Z]{1,3})\$?([1-9][0-9]*))?$/D', $reference, $m)) {
            throw new AnalysisProblem('invalid_cell_reference');
        }
        $range = new self((int) $m[2], (int) ($m[4] ?? $m[2]), self::columnNumber($m[1]), self::columnNumber($m[3] ?? $m[1]));
        if ($range->endRow < $range->startRow || $range->endColumn < $range->startColumn || $range->endRow > 1048576 || $range->endColumn > 16384) {
            throw new AnalysisProblem('invalid_cell_reference');
        }

        return $range;
    }

    public static function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return $number;
    }

    public static function columnLetters(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $letters = chr(65 + ($number - 1) % 26).$letters;
            $number = intdiv($number - 1, 26);
        }

        return $letters;
    }

    public function contains(int $row, int $column): bool
    {
        return $row >= $this->startRow && $row <= $this->endRow && $column >= $this->startColumn && $column <= $this->endColumn;
    }

    public function address(): string
    {
        return self::columnLetters($this->startColumn).$this->startRow.':'.self::columnLetters($this->endColumn).$this->endRow;
    }

    public function toArray(): array
    {
        return ['address' => $this->address(), 'start_row' => $this->startRow, 'end_row' => $this->endRow, 'start_column' => $this->startColumn, 'end_column' => $this->endColumn];
    }
}
