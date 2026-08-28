<?php

namespace App\Exceptions;

use DomainException;

class InvalidSourceWorkbook extends DomainException
{
    /** @param list<array{code: string, message: string, blocking: bool}> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The uploaded workbook does not satisfy the approved source contract.');
    }
}
