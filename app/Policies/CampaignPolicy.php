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

    public function generateTrackingLink(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function recordConversion(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function viewExpenses(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function recordExpense(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function updateExpense(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function deleteExpense(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function approveConversion(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }

    public function rejectConversion(User $user, Campaign $campaign): bool
    {
        return $this->ownsCampaign($user, $campaign);
    }
}
