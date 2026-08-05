<?php

namespace Database\Factories;

use App\Enums\ConversionStatus;
use App\Models\Campaign;
use App\Models\Conversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversion>
 */
final class ConversionFactory extends Factory
{
    protected $model = Conversion::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory()->active(),
            'external_id' => fake()->uuid(),
            'source' => fake()->optional(0.5)->word(),
            'revenue' => '25.00',
            'status' => ConversionStatus::Pending,
            'converted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => ConversionStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => ConversionStatus::Rejected]);
    }

    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn () => [
            'campaign_id' => $campaign->id,
            'revenue' => $campaign->offer->payout,
        ]);
    }
}
