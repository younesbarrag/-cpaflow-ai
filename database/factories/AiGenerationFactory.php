<?php

namespace Database\Factories;

use App\Enums\AiProcessStatus;
use App\Models\AiGeneration;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    protected $model = AiGeneration::class;

    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'status' => AiProcessStatus::Pending,
            'hooks' => null,
            'captions' => null,
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
            'hooks' => [
                fake()->sentence(),
                fake()->sentence(),
                fake()->sentence(),
            ],
            'captions' => [
                fake()->paragraph(),
                fake()->paragraph(),
                fake()->paragraph(),
            ],
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
            'error_message' => "La génération de contenu n'a pas pu être terminée. Veuillez réessayer.",
        ]);
    }

    public function forOffer(Offer $offer): static
    {
        return $this->state([
            'offer_id' => $offer->id,
        ]);
    }
}
