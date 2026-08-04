<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

final class OfferPolicy
{
    public function update(User $user, Offer $offer): bool
    {
        return $this->ownsOffer($user, $offer);
    }

    public function archive(User $user, Offer $offer): bool
    {
        return $this->ownsOffer($user, $offer);
    }

    public function createCampaign(User $user, Offer $offer): bool
    {
        return $this->ownsOffer($user, $offer);
    }

    public function analyze(User $user, Offer $offer): bool
    {
        return $this->ownsOffer($user, $offer);
    }

    public function generate(User $user, Offer $offer): bool
    {
        return $this->ownsOffer($user, $offer);
    }

    private function ownsOffer(User $user, Offer $offer): bool
    {
        return $user->id === $offer->user_id;
    }
}
