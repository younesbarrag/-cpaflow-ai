<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

final class OfferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function update(User $user, Offer $offer): bool
    {
        return $this->ownsOffer($user, $offer);
    }

    public function archive(User $user, Offer $offer): bool
    {
        return $this->ownsOffer($user, $offer);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function ownsOffer(User $user, Offer $offer): bool
    {
        return $user->id === $offer->user_id;
    }
}
