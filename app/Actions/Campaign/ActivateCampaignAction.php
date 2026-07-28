<?php

namespace App\Actions\Campaign;

use App\Enums\CampaignStatus;
use App\Exceptions\InvalidCampaignTransition;
use App\Models\Campaign;

final class ActivateCampaignAction
{
    public function execute(Campaign $campaign): Campaign
    {
        if (! in_array(
            $campaign->status,
            [
                CampaignStatus::Draft,
                CampaignStatus::Suspended,
            ],
            true,
        )) {
            throw new InvalidCampaignTransition(
                from: $campaign->status,
                to: CampaignStatus::Active,
            );
        }

        $campaign->status = CampaignStatus::Active;
        $campaign->save();

        return $campaign->refresh();
    }
}
