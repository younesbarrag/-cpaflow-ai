<?php

namespace App\Services;

use App\DTOs\OfferContentGenerationSnapshot;

final class GenerationInputHasher
{
    public function compute(OfferContentGenerationSnapshot $snapshot): string
    {
        $canonical = json_encode($snapshot->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonical);
    }
}
