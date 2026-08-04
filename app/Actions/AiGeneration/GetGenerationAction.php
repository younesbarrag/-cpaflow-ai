<?php

namespace App\Actions\AiGeneration;

use App\DTOs\OfferAiInputSnapshot;
use App\DTOs\OfferContentGenerationSnapshot;
use App\Enums\AiProcessStatus;
use App\Models\AiGeneration;
use App\Models\Offer;
use App\Services\GenerationInputHasher;
use App\Services\OfferInputHasher;

/**
 * @phpstan-type GenerationResult array{generation: AiGeneration|null, is_stale: bool}
 */
final class GetGenerationAction
{
    public function __construct(
        private readonly OfferInputHasher $offerInputHasher,
    ) {}

    /**
     * @return GenerationResult
     */
    public function execute(Offer $offer, int $generationId): array
    {
        $generation = $offer->generations()->find($generationId);

        if ($generation === null) {
            return [
                'generation' => null,
                'is_stale' => false,
            ];
        }

        if ($generation->status !== AiProcessStatus::Completed) {
            return [
                'generation' => $generation,
                'is_stale' => false,
            ];
        }

        if ($generation->input_hash === null) {
            return [
                'generation' => $generation,
                'is_stale' => true,
            ];
        }

        $analysis = $offer->analysis;

        if ($analysis === null || $analysis->status !== AiProcessStatus::Completed) {
            return [
                'generation' => $generation,
                'is_stale' => true,
            ];
        }

        $currentOfferHash = $this->offerInputHasher->compute(
            OfferAiInputSnapshot::fromOffer($offer),
        );

        if ($currentOfferHash !== $analysis->input_hash) {
            return [
                'generation' => $generation,
                'is_stale' => true,
            ];
        }

        $currentSnapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $analysis);
        $currentHash = app(GenerationInputHasher::class)->compute($currentSnapshot);

        return [
            'generation' => $generation,
            'is_stale' => $currentHash !== $generation->input_hash,
        ];
    }
}
