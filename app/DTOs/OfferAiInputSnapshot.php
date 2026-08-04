<?php

namespace App\DTOs;

use App\Models\Offer;

final readonly class OfferAiInputSnapshot
{
    private function __construct(
        public string $name,
        public ?string $description,
        public string $payout,
        public string $destinationUrl,
    ) {}

    public static function fromOffer(Offer $offer): self
    {
        return new self(
            name: (string) $offer->name,
            description: $offer->description !== null ? (string) $offer->description : null,
            payout: number_format((float) $offer->payout, 2, '.', ''),
            destinationUrl: (string) $offer->destination_url,
        );
    }

    /**
     * @return array{name: string, description: ?string, payout: string, destination_url: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'payout' => $this->payout,
            'destination_url' => $this->destinationUrl,
        ];
    }
}
