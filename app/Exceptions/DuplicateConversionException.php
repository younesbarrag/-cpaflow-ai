<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DuplicateConversionException extends RuntimeException
{
    public function __construct(
        private readonly string $externalId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "A conversion with external ID \"{$externalId}\" already exists.",
            0,
            $previous,
        );
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }
}
