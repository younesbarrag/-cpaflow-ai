<?php

namespace App\DTOs;

use App\Models\AiAnalysis;
use App\Models\Offer;

final readonly class OfferContentGenerationSnapshot
{
    private function __construct(
        public string $name,
        public ?string $description,
        public string $payout,
        public string $destinationUrl,
        public int $analysisScore,
        public string $analysisSummary,
        public ?array $analysisStrengths,
        public ?array $analysisWeaknesses,
        public ?array $analysisRecommendations,
    ) {}

    public static function fromOfferAndAnalysis(Offer $offer, AiAnalysis $analysis): self
    {
        return new self(
            name: (string) $offer->name,
            description: $offer->description,
            payout: number_format((float) $offer->payout, 2, '.', ''),
            destinationUrl: (string) $offer->destination_url,
            analysisScore: (int) $analysis->score,
            analysisSummary: (string) $analysis->summary,
            analysisStrengths: $analysis->strengths,
            analysisWeaknesses: $analysis->weaknesses,
            analysisRecommendations: $analysis->recommendations,
        );
    }

    /**
     * @return array{name: string, description: ?string, payout: string, destination_url: string, analysis_score: int, analysis_summary: string, analysis_strengths: ?array, analysis_weaknesses: ?array, analysis_recommendations: ?array}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'payout' => $this->payout,
            'destination_url' => $this->destinationUrl,
            'analysis_score' => $this->analysisScore,
            'analysis_summary' => $this->analysisSummary,
            'analysis_strengths' => $this->analysisStrengths,
            'analysis_weaknesses' => $this->analysisWeaknesses,
            'analysis_recommendations' => $this->analysisRecommendations,
        ];
    }
}
