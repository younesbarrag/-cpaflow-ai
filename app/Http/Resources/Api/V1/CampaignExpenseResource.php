<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CampaignExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CampaignExpense
 */
final class CampaignExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'spent_at' => $this->spent_at->toDateString(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
