<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
final class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'name' => fake()->words(3, true),
            'traffic_source' => fake()->randomElement([
                'Facebook',
                'Google Ads',
                'TikTok',
                'Instagram',
                'Newsletter',
                'Organic Search',
            ]),
            'budget' => $this->generateBudget(),
            'status' => CampaignStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Active,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Suspended,
        ]);
    }

    private function generateBudget(): string
    {
        $amountInCents = fake()->numberBetween(0, 999_999_999_999);

        $wholePart = intdiv($amountInCents, 100);
        $decimalPart = $amountInCents % 100;

        return sprintf('%d.%02d', $wholePart, $decimalPart);
    }
}
