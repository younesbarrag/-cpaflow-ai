<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Conversion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversion
 */
final class ConversionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'external_id' => $this->external_id,
            'source' => $this->source,
            'revenue' => number_format((float) $this->revenue, 2, '.', ''),
            'status' => $this->status->value,
            'converted_at' => $this->converted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
