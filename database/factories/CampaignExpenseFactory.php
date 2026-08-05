<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignExpense>
 */
final class CampaignExpenseFactory extends Factory
{
    protected $model = CampaignExpense::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory()->active(),
            'amount' => $this->generateAmount(),
            'spent_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn () => [
            'campaign_id' => $campaign->id,
        ]);
    }

    private function generateAmount(): string
    {
        $amountInCents = fake()->numberBetween(1, 999_999_999_999);

        $wholePart = intdiv($amountInCents, 100);
        $decimalPart = $amountInCents % 100;

        return sprintf('%d.%02d', $wholePart, $decimalPart);
    }
}
