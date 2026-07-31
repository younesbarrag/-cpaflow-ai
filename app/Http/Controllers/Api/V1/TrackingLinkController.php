<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TrackingLink\GenerateTrackingLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TrackingLink\GenerateTrackingLinkRequest;
use App\Http\Resources\Api\V1\TrackingLinkResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

class TrackingLinkController extends Controller
{
    public function store(
        GenerateTrackingLinkRequest $request,
        Campaign $campaign,
        GenerateTrackingLinkAction $action,
    ): JsonResponse {
        $trackingLink = $action->execute($campaign);

        return response()->json([
            'data' => [
                'tracking_link' => new TrackingLinkResource($trackingLink),
            ],
        ], 201);
    }
}
