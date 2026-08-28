<?php

namespace App\Data;

readonly class XlsxSourceReadResult
{
    /**
     * @param  list<XlsxSourceRow>  $rows
     * @param  list<string>  $worksheetNames
     * @param  list<string>  $headers
     */
    public function __construct(
        public string $worksheet,
        public array $worksheetNames,
        public array $headers,
        public array $rows,
        public int $blankRowCount,
    ) {}
}
