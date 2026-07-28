<?php

namespace App\Actions\Campaign;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Offer;

final class CreateCampaignAction
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function execute(Offer $offer, array $fields): Campaign
    {
        $editableFields = array_intersect_key(
            $fields,
            array_flip([
                'name',
                'traffic_source',
                'budget',
            ]),
        );

        return $offer->campaigns()->create([
            ...$editableFields,
            'status' => CampaignStatus::Draft,
        ]);
    }
}
