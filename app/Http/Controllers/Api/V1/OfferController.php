<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Offer\ArchiveOfferAction;
use App\Actions\Offer\CreateOfferAction;
use App\Actions\Offer\UpdateOfferAction;
use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Offer\IndexOfferRequest;
use App\Http\Requests\Api\V1\Offer\StoreOfferRequest;
use App\Http\Requests\Api\V1\Offer\UpdateOfferRequest;
use App\Http\Resources\Api\V1\OfferResource;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OfferController extends Controller
{
    public function store(StoreOfferRequest $request, CreateOfferAction $action): JsonResponse
    {
        $offer = $action->execute(
            $request->user(),
            $request->validated('name'),
            $request->validated('destination_url'),
            $request->validated('payout'),
            OfferStatus::from($request->validated('status')),
            $request->validated('description'),
        );

        return response()->json([
            'data' => [
                'offer' => new OfferResource($offer),
            ],
        ], 201);
    }

    public function index(IndexOfferRequest $request): JsonResponse
    {
        $statusValue = $request->validated('status');

        $status = is_string($statusValue)
            ? OfferStatus::from($statusValue)
            : null;

        $search = $request->validated('search');

        $offers = $request->user()
            ->offers()
            ->status($status)
            ->search(is_string($search) ? $search : null)
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => OfferResource::collection($offers),
            'links' => [
                'first' => $offers->url(1),
                'last' => $offers->url($offers->lastPage()),
                'prev' => $offers->previousPageUrl(),
                'next' => $offers->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }

    public function update(
        UpdateOfferRequest $request,
        Offer $offer,
        UpdateOfferAction $action,
    ): JsonResponse {
        Gate::authorize('update', $offer);

        $fields = $request->safe()->only([
            'name',
            'destination_url',
            'payout',
            'status',
            'description',
        ]);

        $updatedOffer = $action->execute($offer, $fields);

        return response()->json([
            'data' => [
                'offer' => new OfferResource($updatedOffer),
            ],
        ]);
    }

    public function archive(
        Offer $offer,
        ArchiveOfferAction $action,
    ): JsonResponse {
        Gate::authorize('archive', $offer);

        $archivedOffer = $action->execute($offer);

        return response()->json([
            'data' => [
                'offer' => new OfferResource($archivedOffer),
            ],
        ]);
    }
}
