<?php

namespace App\Actions\AiGeneration;

use App\DTOs\OfferAiInputSnapshot;
use App\DTOs\OfferContentGenerationSnapshot;
use App\Enums\AiProcessStatus;
use App\Models\AiGeneration;
use App\Models\Offer;
use App\Services\GenerationInputHasher;
use App\Services\OfferInputHasher;

final class GetOfferGenerationsAction
{
    public function __construct(
        private readonly OfferInputHasher $offerInputHasher,
    ) {}

    /**
     * @return array{generations: AiGeneration[]}
     */
    public function execute(Offer $offer): array
    {
        $generations = $offer->generations()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->all();

        foreach ($generations as $generation) {
            $generation->is_stale = $this->computeStaleness($offer, $generation);
        }

        return [
            'generations' => $generations,
        ];
    }

    private function computeStaleness(Offer $offer, AiGeneration $generation): bool
    {
        if ($generation->status !== AiProcessStatus::Completed) {
            return false;
        }

        if ($generation->input_hash === null) {
            return true;
        }

        $analysis = $offer->analysis;

        if ($analysis === null || $analysis->status !== AiProcessStatus::Completed) {
            return true;
        }

        $currentOfferHash = $this->offerInputHasher->compute(
            OfferAiInputSnapshot::fromOffer($offer),
        );

        if ($currentOfferHash !== $analysis->input_hash) {
            return true;
        }

        $currentSnapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $analysis);
        $currentHash = app(GenerationInputHasher::class)->compute($currentSnapshot);

        return $currentHash !== $generation->input_hash;
    }
}
