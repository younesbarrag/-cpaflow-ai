<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
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
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Active,
        ]);

        return [
            'campaign_id' => $campaign->id,
            'external_id' => fake()->uuid(),
            'source' => fake()->optional(0.5)->word(),
            'revenue' => $campaign->offer->payout,
            'status' => ConversionStatus::Pending,
            'converted_at' => now(),
        ];
    }

    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn () => [
            'campaign_id' => $campaign->id,
            'revenue' => $campaign->offer->payout,
        ]);
    }
}
