<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Dashboard\GetDashboardStatisticsAction;
use App\DTOs\DashboardStatisticsPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dashboard\DashboardStatisticsRequest;
use App\Http\Resources\Api\V1\DashboardStatisticsResource;
use Illuminate\Http\JsonResponse;

final class DashboardStatisticsController extends Controller
{
    public function show(
        DashboardStatisticsRequest $request,
        GetDashboardStatisticsAction $action,
    ): JsonResponse {
        $period = DashboardStatisticsPeriod::fromRequest($request);
        $statistics = $action->execute($request->user(), $period);

        return response()->json([
            'data' => [
                'statistics' => new DashboardStatisticsResource($statistics),
            ],
        ]);
    }
}
