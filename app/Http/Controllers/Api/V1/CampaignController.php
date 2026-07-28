<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Campaign\ActivateCampaignAction;
use App\Actions\Campaign\CreateCampaignAction;
use App\Actions\Campaign\SuspendCampaignAction;
use App\Actions\Campaign\UpdateCampaignAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Campaign\StoreCampaignRequest;
use App\Http\Requests\Api\V1\Campaign\UpdateCampaignRequest;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::query()
            ->whereHas(
                'offer',
                function (Builder $query) use ($request): void {
                    $query->where(
                        'user_id',
                        $request->user()->id,
                    );
                },
            )
            ->with('offer:id,name')
            ->orderByDesc('id')
            ->paginate(15);

        return CampaignResource::collection($campaigns)->response();
    }

    public function store(
        StoreCampaignRequest $request,
        CreateCampaignAction $action,
    ): JsonResponse {
        $offer = $request->offer();

        Gate::authorize('createCampaign', $offer);

        $campaign = $action->execute(
            $offer,
            $request->safe()->only([
                'name',
                'traffic_source',
                'budget',
            ]),
        );

        $campaign->load('offer:id,name');

        return response()->json([
            'data' => [
                'campaign' => new CampaignResource($campaign),
            ],
        ], 201);
    }

    public function show(
        Campaign $campaign,
    ): JsonResponse {
        Gate::authorize('view', $campaign);

        $campaign->load('offer:id,name');

        return response()->json([
            'data' => [
                'campaign' => new CampaignResource($campaign),
            ],
        ]);
    }

    public function update(
        UpdateCampaignRequest $request,
        Campaign $campaign,
        UpdateCampaignAction $action,
    ): JsonResponse {
        Gate::authorize('update', $campaign);

        $updatedCampaign = $action->execute(
            $campaign,
            $request->safe()->only([
                'name',
                'traffic_source',
                'budget',
            ]),
        );

        $updatedCampaign->load('offer:id,name');

        return response()->json([
            'data' => [
                'campaign' => new CampaignResource($updatedCampaign),
            ],
        ]);
    }

    public function activate(
        Campaign $campaign,
        ActivateCampaignAction $action,
    ): JsonResponse {
        Gate::authorize('activate', $campaign);

        $activatedCampaign = $action->execute($campaign);

        $activatedCampaign->load('offer:id,name');

        return response()->json([
            'data' => [
                'campaign' => new CampaignResource($activatedCampaign),
            ],
        ]);
    }

    public function suspend(
        Campaign $campaign,
        SuspendCampaignAction $action,
    ): JsonResponse {
        Gate::authorize('suspend', $campaign);

        $suspendedCampaign = $action->execute($campaign);

        $suspendedCampaign->load('offer:id,name');

        return response()->json([
            'data' => [
                'campaign' => new CampaignResource($suspendedCampaign),
            ],
        ]);
    }
}
