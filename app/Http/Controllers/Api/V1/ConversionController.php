<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversion\RecordConversionAction;
use App\Actions\Conversion\ReviewConversionAction;
use App\Enums\ConversionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Conversion\StoreConversionRequest;
use App\Http\Resources\Api\V1\ConversionResource;
use App\Models\Campaign;
use App\Models\Conversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ConversionController extends Controller
{
    public function store(
        StoreConversionRequest $request,
        Campaign $campaign,
        RecordConversionAction $action,
    ): JsonResponse {
        $conversion = $action->execute(
            $campaign,
            $request->validated('external_id'),
            $request->validated('source'),
        );

        return response()->json([
            'data' => [
                'conversion' => new ConversionResource($conversion),
            ],
        ], 201);
    }

    public function approve(
        Campaign $campaign,
        Conversion $conversion,
        ReviewConversionAction $action,
    ): JsonResponse {
        Gate::authorize('approveConversion', $campaign);

        $conversion = $action->execute(
            $campaign,
            $conversion->id,
            ConversionStatus::Approved,
        );

        return response()->json([
            'data' => [
                'conversion' => new ConversionResource($conversion),
            ],
        ]);
    }

    public function reject(
        Campaign $campaign,
        Conversion $conversion,
        ReviewConversionAction $action,
    ): JsonResponse {
        Gate::authorize('rejectConversion', $campaign);

        $conversion = $action->execute(
            $campaign,
            $conversion->id,
            ConversionStatus::Rejected,
        );

        return response()->json([
            'data' => [
                'conversion' => new ConversionResource($conversion),
            ],
        ]);
    }
}
