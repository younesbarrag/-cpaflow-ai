<?php

namespace App\Actions\Campaign;

use App\Models\Campaign;

final class UpdateCampaignAction
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function execute(Campaign $campaign, array $fields): Campaign
    {
        $editableFields = array_intersect_key(
            $fields,
            array_flip([
                'name',
                'traffic_source',
                'budget',
            ]),
        );

        $campaign->fill($editableFields);
        $campaign->save();

        return $campaign->refresh();
    }
}
