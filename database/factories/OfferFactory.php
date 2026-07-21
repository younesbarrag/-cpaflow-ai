<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'destination_url' => 'https://'.fake()->domainName().'/'.fake()->uuid(),
            'payout' => number_format(fake()->randomFloat(2, 0, 500), 2, '.', ''),
            'status' => fake()->randomElement(OfferStatus::cases()),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => OfferStatus::Draft]);
    }

    public function active(): static
    {
        return $this->state(['status' => OfferStatus::Active]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
