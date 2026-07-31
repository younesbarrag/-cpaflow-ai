<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class CannotGenerateTrackingLink extends RuntimeException
{
    public function __construct(
        int $attempts,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "The tracking link code could not be generated after {$attempts} attempts.",
            0,
            $previous,
        );
    }
}
