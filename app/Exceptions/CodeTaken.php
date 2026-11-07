<?php

namespace App\Exceptions;

use RuntimeException;

/** A single-use code was taken by another order between the check and the save (#85); the order is rolled back. */
class CodeTaken extends RuntimeException
{
    public function __construct(public readonly int $participant)
    {
        parent::__construct("The code of participant {$participant} is taken.");
    }
}
