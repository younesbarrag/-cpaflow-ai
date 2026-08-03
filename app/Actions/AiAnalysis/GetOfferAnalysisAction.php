<?php

namespace App\Actions\AiAnalysis;

use App\DTOs\OfferAiInputSnapshot;
use App\Enums\AiProcessStatus;
use App\Models\AiAnalysis;
use App\Models\Offer;
use App\Services\OfferInputHasher;

/**
 * @phpstan-type AnalysisResult array{analysis: AiAnalysis|null, is_stale: bool}
 */
final class GetOfferAnalysisAction
{
    public function __construct(
        private readonly OfferInputHasher $hasher,
    ) {}

    /**
     * @return AnalysisResult
     */
    public function execute(Offer $offer): array
    {
        $analysis = $offer->analysis;

        if ($analysis === null) {
            return [
                'analysis' => null,
                'is_stale' => false,
            ];
        }

        if ($analysis->status !== AiProcessStatus::Completed) {
            return [
                'analysis' => $analysis,
                'is_stale' => false,
            ];
        }

        $currentHash = $this->hasher->compute(
            OfferAiInputSnapshot::fromOffer($offer),
        );

        return [
            'analysis' => $analysis,
            'is_stale' => $currentHash !== $analysis->input_hash,
        ];
    }
}
