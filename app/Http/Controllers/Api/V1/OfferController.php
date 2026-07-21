<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Offer\CreateOfferAction;
use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Offer\StoreOfferRequest;
use App\Http\Resources\Api\V1\OfferResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function index(Request $request): JsonResponse
    {
        $offers = $request->user()
            ->offers()
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
}
