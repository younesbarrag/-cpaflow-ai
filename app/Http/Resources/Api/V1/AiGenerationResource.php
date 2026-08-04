<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AiGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiGeneration
 */
final class AiGenerationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'status' => $this->status->value,
            'hooks' => $this->hooks,
            'captions' => $this->captions,
            'is_stale' => $this->resource->is_stale ?? false,
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
