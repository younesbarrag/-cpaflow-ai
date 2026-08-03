<?php

namespace Database\Factories;

use App\Enums\AiProcessStatus;
use App\Models\AiAnalysis;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAnalysis>
 */
class AiAnalysisFactory extends Factory
{
    protected $model = AiAnalysis::class;

    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'status' => AiProcessStatus::Pending,
            'score' => null,
            'summary' => null,
            'strengths' => null,
            'weaknesses' => null,
            'recommendations' => null,
            'input_hash' => null,
            'provider' => null,
            'model' => null,
            'error_message' => null,
            'completed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => AiProcessStatus::Pending,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => AiProcessStatus::Processing,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => AiProcessStatus::Completed,
            'score' => fake()->numberBetween(0, 100),
            'summary' => fake()->paragraph(),
            'strengths' => [fake()->sentence(), fake()->sentence()],
            'weaknesses' => [fake()->sentence()],
            'recommendations' => [fake()->sentence(), fake()->sentence(), fake()->sentence()],
            'input_hash' => hash('sha256', fake()->uuid()),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => AiProcessStatus::Failed,
            'error_message' => "L'analyse IA n'a pas pu être terminée. Veuillez réessayer.",
        ]);
    }

    public function forOffer(Offer $offer): static
    {
        return $this->state([
            'offer_id' => $offer->id,
        ]);
    }
}
