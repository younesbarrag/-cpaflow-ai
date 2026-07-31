<?php

namespace Database\Factories;

use App\Models\TrackingClick;
use App\Models\TrackingLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingClick>
 */
class TrackingClickFactory extends Factory
{
    protected $model = TrackingClick::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracking_link_id' => TrackingLink::factory(),
            'ip_hash' => $this->generateIpHash(),
            'user_agent' => fake()->userAgent(),
            'referer' => fake()->optional(0.6)->url(),
            'utm_source' => fake()->optional(0.5)->word(),
            'utm_medium' => fake()->optional(0.5)->word(),
            'utm_campaign' => fake()->optional(0.5)->word(),
            'utm_term' => fake()->optional(0.3)->word(),
            'utm_content' => fake()->optional(0.3)->word(),
        ];
    }

    private function generateIpHash(): ?string
    {
        if (fake()->boolean(30)) {
            return null;
        }

        return hash('sha256', fake()->ipv4());
    }
}
