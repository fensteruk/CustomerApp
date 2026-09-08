<?php

namespace App\SourceImport\Semantics\Contracts;

use App\SourceImport\Semantics\Data\DictionaryIdentity;
use App\SourceImport\Semantics\Data\ProductRollup;
use App\SourceImport\Semantics\Data\SemanticResult;
use App\SourceImport\Semantics\Enums\ExportScope;

interface SourceBusinessDictionary
{
    public function identity(): DictionaryIdentity;

    public function callType(string $raw): SemanticResult;

    public function completion(string|int|float|bool|null $raw): SemanticResult;

    public function product(string $code, string|int|float|bool|null $quantity): SemanticResult;

    public function field(string $raw): SemanticResult;

    public function scope(?ExportScope $assertion = null): SemanticResult;

    public function rollup(array $products): ProductRollup;
}
