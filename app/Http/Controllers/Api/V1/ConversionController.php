<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversion\RecordConversionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Conversion\StoreConversionRequest;
use App\Http\Resources\Api\V1\ConversionResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

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
}
