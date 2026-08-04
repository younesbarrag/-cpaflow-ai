<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AiGeneration\GetGenerationAction;
use App\Actions\AiGeneration\GetOfferGenerationsAction;
use App\Actions\AiGeneration\RequestContentGenerationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AiGeneration\AiGenerationRequest;
use App\Http\Resources\Api\V1\AiGenerationResource;
use App\Jobs\GenerateContentJob;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiGenerationController extends Controller
{
    public function store(
        AiGenerationRequest $request,
        Offer $offer,
        RequestContentGenerationAction $action,
    ): JsonResponse {
        $result = $action->execute($offer);

        $generation = $result['generation'];
        $shouldDispatch = $result['dispatch'];

        if ($shouldDispatch) {
            GenerateContentJob::dispatch($generation->id)->afterCommit();
        }

        $httpStatus = $shouldDispatch ? 202 : 200;

        return response()->json([
            'data' => [
                'id' => $generation->id,
                'offer_id' => $generation->offer_id,
                'status' => $generation->status->value,
                'created_at' => $generation->created_at->toISOString(),
            ],
        ], $httpStatus);
    }

    public function index(
        Offer $offer,
        GetOfferGenerationsAction $action,
    ): AnonymousResourceCollection {
        Gate::authorize('generate', $offer);

        $result = $action->execute($offer);

        return AiGenerationResource::collection($result['generations']);
    }

    public function show(
        Offer $offer,
        int $generation,
        GetGenerationAction $action,
    ): JsonResponse {
        Gate::authorize('generate', $offer);

        $result = $action->execute($offer, $generation);

        $gen = $result['generation'];

        if ($gen === null) {
            return response()->json([
                'message' => 'No generation found for this offer.',
            ], 404);
        }

        $gen->is_stale = $result['is_stale'];

        return response()->json([
            'data' => new AiGenerationResource($gen),
        ]);
    }
}
