<?php

namespace App\Exceptions;

use App\Enums\CampaignStatus;
use DomainException;

final class InvalidCampaignTransition extends DomainException
{
    public function __construct(
        public readonly CampaignStatus $from,
        public readonly CampaignStatus $to,
    ) {
        parent::__construct(
            "Campaign cannot transition from {$from->value} to {$to->value}.",
        );
    }
}
