<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;

class CreateOfferAction
{
    public function execute(
        User $user,
        string $name,
        string $destinationUrl,
        string $payout,
        OfferStatus $status,
        ?string $description,
    ): Offer {
        return $user->offers()->create([
            'name' => $name,
            'destination_url' => $destinationUrl,
            'payout' => $payout,
            'status' => $status,
            'description' => $description,
        ]);
    }
}
