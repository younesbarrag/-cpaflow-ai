<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

final class CampaignPolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function activate(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function suspend(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    private function ownsCampaign(User $user, Campaign $campaign): bool
    {
        return $user->id === $campaign->offer->user_id;
    }
}
