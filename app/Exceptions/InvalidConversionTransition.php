<?php

namespace App\Exceptions;

use App\Enums\ConversionStatus;
use DomainException;

final class InvalidConversionTransition extends DomainException
{
    public function __construct(
        public readonly ConversionStatus $from,
        public readonly ConversionStatus $to,
    ) {
        parent::__construct(
            "Conversion cannot transition from {$from->value} to {$to->value}.",
        );
    }
}
