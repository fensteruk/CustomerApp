<?php

namespace App\Wald\Services;

use App\Wald\Contracts\CellObservation;

final class ValueProfiler
{
    public function type(CellObservation $cell): string
    {
        if ($cell->formula !== null) {
            return 'formula';
        }
        $value = trim($cell->rawValue);
        if ($value === '') {
            return 'blank';
        }
        if ($cell->type === 'error') {
            return 'error';
        }
        if ($cell->type === 'boolean' || preg_match('/^(true|false|yes|no)$/i', $value)) {
            return 'boolean_like';
        }
        $format = $cell->style['number_format'] ?? '';
        if (preg_match('/^[+-]?[0-9]+(?:\.[0-9]+)?\s*%$/D', $value) || (is_numeric($value) && str_contains($format, '%'))) {
            return 'percentage_like';
        }
        if ($cell->type === 'date' || $this->dateShape($value) !== null || (is_numeric($value) && $this->dateFormat($format))) {
            return 'date_like';
        }
        // Leading zero strings are never converted to numbers.
        if (preg_match('/^0[0-9]+$/D', $value) && $cell->type === 'text') {
            return 'identifier_shaped';
        }
        if (preg_match('/^[+-]?[0-9]+$/D', $value)) {
            return 'integer';
        }
        if (is_numeric($value)) {
            return 'decimal';
        }
        if ($this->identifier($value)) {
            return 'identifier_shaped';
        }

        return 'text';
    }

    /** Bounded summaries, never a copy of a full column. */
    public function profile(iterable $cells, int $population): array
    {
        $types = array_fill_keys(['blank', 'text', 'integer', 'decimal', 'date_like', 'percentage_like', 'boolean_like', 'formula', 'identifier_shaped', 'error'], 0);
        $seen = $frequencies = $samples = $unusual = $shapes = $dates = $scales = [];
        $count = $length = $textCount = $abbreviations = $numeric = $zero = $negative = $identifiers = 0;
        $min = $max = null;
        $cardinalityCapped = false;
        foreach ($cells as $cell) {
            $value = trim($cell->rawValue);
            $type = $this->type($cell);
            $types[$type]++;
            $count++;
            $sample = ['value' => mb_substr($cell->rawValue, 0, 160), 'row' => $cell->row, 'column' => $cell->column, 'source' => $cell->source, 'type' => $type];
            if (count($samples) < 5) {
                $samples[] = $sample;
            }
            if (! isset($unusual[$type])) {
                $unusual[$type] = $sample;
            }
            $hash = hash('sha256', $value);
            if (count($seen) < 2048 || isset($seen[$hash])) {
                $seen[$hash] = true;
            } else {
                $cardinalityCapped = true;
            }
            if (isset($frequencies[$hash])) {
                $frequencies[$hash]['count']++;
            } elseif (count($frequencies) < 64) {
                $frequencies[$hash] = ['value' => mb_substr($value, 0, 80), 'count' => 1];
            }
            if ($type !== 'formula' && is_numeric($value) && $type !== 'identifier_shaped') {
                $number = (float) $value;
                if (is_finite($number)) {
                    $numeric++;
                    $min = $min === null ? $number : min($min, $number);
                    $max = $max === null ? $number : max($max, $number);
                    $zero += (int) ($number === 0.0);
                    $negative += (int) ($number < 0);
                    if ($number >= 0 && $number <= 100) {
                        $scales['unconfirmed_0_to_100'] = true;
                    }
                    if ($number >= 0 && $number <= 1) {
                        $scales['unconfirmed_0_to_1'] = true;
                    }
                }
            }
            if ($type === 'percentage_like') {
                $scales[str_contains($value, '%') ? 'explicit_percent_text' : 'formatted_fraction'] = true;
            }
            if ($type === 'date_like') {
                $shape = $this->dateShape($value) ?? (is_numeric($value) ? 'spreadsheet_serial_format_evidence' : 'reader_date');
                $dates[$shape] = ($dates[$shape] ?? 0) + 1;
            }
            if ($this->identifier($value)) {
                $identifiers++;
                $shape = preg_replace(['/\pL+/u', '/\d+/'], ['A', '9'], $value);
                if (isset($shapes[$shape]) || count($shapes) < 16) {
                    $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
                }
            }
            if ($cell->type === 'text') {
                $textCount++;
                $length += mb_strlen($value);
                $abbreviations += (int) (bool) preg_match('/^[A-Z]{2,6}$/D', $value);
            }
        }
        $types['blank'] += max(0, $population - $count);
        usort($frequencies, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['value'], $b['value']));
        ksort($shapes);
        ksort($dates);
        ksort($scales);

        return ['population' => $population, 'observed' => $count, 'types' => $types,
            'distinct_count' => count($seen), 'distinct_count_is_lower_bound' => $cardinalityCapped,
            'uniqueness' => $cardinalityCapped ? null : round(count($seen) / max(1, $count), 4),
            'numeric' => ['count' => $numeric, 'min' => $min, 'max' => $max, 'zero_count' => $zero, 'negative_count' => $negative, 'integer_count' => $types['integer'], 'decimal_count' => $types['decimal']],
            'identifier' => ['count' => $identifiers, 'patterns' => $shapes, 'numeric_ratio' => round(($types['integer'] + $types['decimal']) / max(1, $count), 4)],
            'dates' => ['shapes' => $dates, 'ambiguous' => isset($dates['ambiguous_day_month']) || isset($dates['week_shaped']), 'normalised' => false],
            'percentage' => ['possible_scales' => array_keys($scales), 'normalised' => false],
            'text' => ['average_length' => round($length / max(1, $textCount), 2), 'abbreviation_count' => $abbreviations, 'free_text_like' => $textCount > 0 && $length / $textCount > 40],
            'samples' => $samples, 'unusual_type_samples' => array_values($unusual), 'common_values' => array_slice($frequencies, 0, 5),
            'limitations' => ['frequency_candidates_limited_to_first_64_distinct_values', 'samples_max_160_characters', 'shapes_do_not_establish_business_meaning'],
        ];
    }

    private function identifier(string $value): bool
    {
        return strlen($value) <= 40 && (bool) preg_match('/^(?:[A-Za-z]{0,12}[-_]?)?[0-9]+[A-Za-z]{0,4}$/D', $value);
    }

    private function dateFormat(string $format): bool
    {
        $format = preg_replace('/"[^"]*"|\\\\./', '', $format);

        return (bool) preg_match('/[dy]/i', $format);
    }

    private function dateShape(string $value): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:T.*)?$/D', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return 'iso_date';
        }
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{2}|\d{4})$/D', $value, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            if ($a <= 12 && $b <= 12 && $a > 0 && $b > 0) {
                return 'ambiguous_day_month';
            }
            if (checkdate($b, $a, $year)) {
                return 'uk_date';
            }
        }
        if (preg_match('/^(?:\d{4}-(?:0[1-9]|1[0-2])|(?:0?[1-9]|1[0-2])\/\d{4}|[A-Za-z]{3,9} \d{4})$/D', $value)) {
            return 'month_year_shaped';
        }
        if (preg_match('/^(?:\d{4}-W\d{1,2}|W(?:eek)?\s*\d{1,2})$/iD', $value)) {
            return 'week_shaped';
        }

        return null;
    }
}
