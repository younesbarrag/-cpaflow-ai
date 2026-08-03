<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AiAnalysis\GetOfferAnalysisAction;
use App\Actions\AiAnalysis\RequestOfferAnalysisAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AiAnalysis\AiAnalysisRequest;
use App\Http\Resources\Api\V1\AiAnalysisResource;
use App\Jobs\AnalyzeOfferJob;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiAnalysisController extends Controller
{
    public function analyze(
        AiAnalysisRequest $request,
        Offer $offer,
        RequestOfferAnalysisAction $action,
    ): JsonResponse {
        $result = $action->execute($offer);

        $analysis = $result['analysis'];
        $shouldDispatch = $result['dispatch'];

        if ($shouldDispatch) {
            AnalyzeOfferJob::dispatch($analysis->id)->afterCommit();
        }

        $httpStatus = $shouldDispatch ? 202 : 200;

        return response()->json([
            'data' => [
                'id' => $analysis->id,
                'offer_id' => $analysis->offer_id,
                'status' => $analysis->status->value,
                'created_at' => $analysis->created_at->toISOString(),
            ],
        ], $httpStatus);
    }

    public function show(
        Offer $offer,
        GetOfferAnalysisAction $action,
    ): JsonResponse {
        Gate::authorize('analyze', $offer);

        $result = $action->execute($offer);

        $analysis = $result['analysis'];

        if ($analysis === null) {
            return response()->json([
                'message' => 'No analysis found for this offer.',
            ], 404);
        }

        $analysis->is_stale = $result['is_stale'];

        return response()->json([
            'data' => new AiAnalysisResource($analysis),
        ]);
    }
}
