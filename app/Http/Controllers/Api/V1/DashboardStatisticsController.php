<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Dashboard\GetDashboardStatisticsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DashboardStatisticsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardStatisticsController extends Controller
{
    public function show(
        Request $request,
        GetDashboardStatisticsAction $action,
    ): JsonResponse {
        $statistics = $action->execute($request->user());

        return response()->json([
            'data' => [
                'statistics' => new DashboardStatisticsResource($statistics),
            ],
        ]);
    }
}
