<?php

namespace App\Services;

use App\DTOs\OfferAiInputSnapshot;

final class OfferInputHasher
{
    public function compute(OfferAiInputSnapshot $snapshot): string
    {
        $canonical = json_encode($snapshot->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonical);
    }
}
