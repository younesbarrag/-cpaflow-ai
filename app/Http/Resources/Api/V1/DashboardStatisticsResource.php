<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
final class DashboardStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'offer_count' => $this->resource['offer_count'],
            'campaign_count' => $this->resource['campaign_count'],
            'active_campaign_count' => $this->resource['active_campaign_count'],
            'click_count' => $this->resource['click_count'],
            'conversion_count' => $this->resource['conversion_count'],
            'revenue' => number_format((float) $this->resource['revenue'], 2, '.', ''),
            'total_expenses' => number_format((float) $this->resource['total_expenses'], 2, '.', ''),
            'profit' => number_format((float) $this->resource['profit'], 2, '.', ''),
        ];
    }
}
