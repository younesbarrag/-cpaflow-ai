<?php

namespace App\Actions\Campaign;

use App\Enums\CampaignStatus;
use App\Exceptions\InvalidCampaignTransition;
use App\Models\Campaign;

final class SuspendCampaignAction
{
    public function execute(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::Active) {
            throw new InvalidCampaignTransition(
                from: $campaign->status,
                to: CampaignStatus::Suspended,
            );
        }

        $campaign->status = CampaignStatus::Suspended;
        $campaign->save();

        return $campaign->refresh();
    }
}
