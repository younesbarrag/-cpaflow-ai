<?php

namespace App\Http\Controllers;

use App\Actions\Offer\ArchiveOfferAction;
use App\Actions\Offer\CreateOfferAction;
use App\Actions\Offer\RestoreOfferAction;
use App\Actions\Offer\UpdateOfferAction;
use App\Enums\OfferStatus;
use App\Http\Requests\Api\V1\Offer\StoreOfferRequest;
use App\Http\Requests\Api\V1\Offer\UpdateOfferRequest;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $hasActiveFilters = $request->filled('search') || ($request->filled('status') && $request->input('status') !== 'all');

        $query = Offer::where('user_id', $request->user()->id)
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->status(OfferStatus::from($request->input('status')));
        }

        $offers = $query->paginate(15)->withQueryString();

        $hasOffers = Offer::where('user_id', $request->user()->id)->exists();

        return view('offers.index', [
            'offers' => $offers,
            'hasOffers' => $hasOffers,
            'hasActiveFilters' => $hasActiveFilters,
        ]);
    }

    public function create(): View
    {
        return view('offers.create');
    }

    public function store(StoreOfferRequest $request, CreateOfferAction $action): RedirectResponse
    {
        $action->execute(
            $request->user(),
            $request->validated('name'),
            $request->validated('destination_url'),
            $request->validated('payout'),
            OfferStatus::from($request->validated('status')),
            $request->validated('description'),
        );

        return redirect()->route('offers.index')
            ->with('success', 'Offer created successfully.');
    }

    public function edit(Offer $offer): View
    {
        abort_unless($offer->user_id === auth()->id(), 403);

        return view('offers.edit', [
            'offer' => $offer,
        ]);
    }

    public function update(UpdateOfferRequest $request, Offer $offer, UpdateOfferAction $action): RedirectResponse
    {
        $action->execute(
            $offer,
            $request->validated(),
        );

        return redirect()->route('offers.index')
            ->with('success', 'Offer updated successfully.');
    }

    public function archive(Offer $offer, ArchiveOfferAction $action): RedirectResponse
    {
        abort_unless($offer->user_id === auth()->id(), 403);

        $action->execute($offer);

        return redirect()->route('offers.index')
            ->with('success', 'Offer archived successfully.');
    }

    public function restore(Offer $offer, RestoreOfferAction $action): RedirectResponse
    {
        abort_unless($offer->user_id === auth()->id(), 403);

        $action->execute($offer);

        return redirect()->route('offers.index')
            ->with('success', 'Offer restored successfully.');
    }
}
