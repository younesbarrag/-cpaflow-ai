<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 */
final class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'offer' => $this->whenLoaded(
                'offer',
                fn (): array => [
                    'id' => $this->offer->id,
                    'name' => $this->offer->name,
                ],
            ),

            'name' => $this->name,
            'traffic_source' => $this->traffic_source,
            'budget' => $this->budget,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
