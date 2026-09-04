<?php

namespace App\Wald\Services;

final class AnalysisBudget
{
    private float $started;

    private int $cells = 0;

    public function __construct(private readonly array $limits = [])
    {
        $this->started = microtime(true);
    }

    public function limit(string $key): int
    {
        $limit = $this->limits[$key] ?? match ($key) {
            'file_bytes' => 25 * 1024 * 1024, 'archive_bytes' => 100 * 1024 * 1024,
            'entry_bytes' => 32 * 1024 * 1024, 'entries' => 2048, 'sheets' => 50,
            'cells' => 1_000_000, 'rows' => 10_000, 'columns' => 1024, 'merges' => 2048,
            'styles' => 4096, 'strings' => 100_000, 'string_bytes' => 16 * 1024 * 1024,
            'cell_bytes' => 32768, 'node_bytes' => 1024 * 1024, 'regions' => 256, 'seconds' => 300,
            'memory_bytes' => 384 * 1024 * 1024,
        };
        if ($key === 'memory_bytes') {
            $configured = trim((string) ini_get('memory_limit'));
            if ($configured !== '-1') {
                $factor = match (strtolower(substr($configured, -1))) {
                    'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
                };
                $limit = min($limit, max(1, (int) $configured * $factor - 32 * 1024 * 1024));
            }
        }

        return $limit;
    }

    public function guard(string $key, int $value): void
    {
        if ($value > $this->limit($key)) {
            throw new AnalysisProblem('resource_limit_exceeded', ['limit' => $key, 'maximum' => $this->limit($key)]);
        }
        $this->checkpoint();
    }

    public function cell(): void
    {
        $this->guard('cells', ++$this->cells);
    }

    public function checkpoint(): void
    {
        if (microtime(true) - $this->started > $this->limit('seconds') || memory_get_usage(true) > $this->limit('memory_bytes')) {
            throw new AnalysisProblem('resource_limit_exceeded', ['limit' => 'time_or_memory']);
        }
    }

    public function reserve(int $bytes): void
    {
        if (memory_get_usage(true) + $bytes > $this->limit('memory_bytes')) {
            throw new AnalysisProblem('resource_limit_exceeded', ['limit' => 'memory_bytes']);
        }
        $this->checkpoint();
    }
}
